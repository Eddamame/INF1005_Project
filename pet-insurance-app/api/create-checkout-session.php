<?php
/**
 * Create Stripe Checkout Session.
 *
 * Expects POST with:
 *   - pet_id
 *   - plan_id
 *   - duration (1, 6, or 12)
 *   - csrf_token (body or X-CSRF-Token header)
 *
 * No subscription record is created here.
 * The subscription is only created in checkout-success.php after payment confirms.
 */

header('Content-Type: application/json; charset=utf-8');

function json_error(string $message, int $status = 400): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function json_success(array $data): void {
    http_response_code(200);
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';

// API: return JSON instead of redirect when not logged in
if (empty($_SESSION['user_id'])) {
    json_error('Please log in to continue.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($token)) {
    json_error('Invalid or missing CSRF token. Please refresh the page and try again.', 403);
}

if (!$db instanceof PDO) {
    json_error('Database unavailable', 500);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$petId  = inputInt('pet_id') ?? 0;
$planId = inputInt('plan_id') ?? 0;

if (!$petId || !$planId) {
    json_error('Missing pet or plan selection.');
}

try {
    // Verify pet belongs to user
    $stmt = $db->prepare('SELECT * FROM pets WHERE id = :pid AND user_id = :uid LIMIT 1');
    $stmt->execute([':pid' => $petId, ':uid' => $userId]);
    $pet = $stmt->fetch();
    if (!$pet) {
        json_error('Pet not found.', 404);
    }

    // Check for existing active subscription (allow if expiring within 30 days)
    $subCheck = $db->prepare('
        SELECT id, end_date FROM subscriptions
        WHERE pet_id = :pid AND user_id = :uid AND status = :status
        LIMIT 1
    ');
    $subCheck->execute([':pid' => $petId, ':uid' => $userId, ':status' => 'active']);
    $existingSub = $subCheck->fetch();
    
    if ($existingSub) {
        $endDate = $existingSub['end_date'] ?? null;
        $expiringWithin30Days = $endDate && strtotime($endDate) <= strtotime('+30 days');
        if (!$expiringWithin30Days) {
            json_error('This pet already has an active subscription.', 409);
        }
    }

    // Load plan
    $stmt = $db->prepare('SELECT * FROM insurance_plans WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute([':id' => $planId]);
    $plan = $stmt->fetch();
    if (!$plan) {
        json_error('Plan not found.', 404);
    }

    // Load user (for Stripe customer and email)
    $stmt = $db->prepare('SELECT stripe_customer_id, email, first_name, last_name FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        json_error('User not found.', 404);
    }

    // Find the project root (one level up from /api)
    $rootPath = dirname(__DIR__);
    $autoloadPath = $rootPath . '/vendor/autoload.php';

    if (!is_file($autoloadPath)) {
        json_error('Autoload file missing. System was looking in: ' . $autoloadPath, 503);
    }

    require_once $autoloadPath;
    require_once __DIR__ . '/../config/stripe.php';

    $stripe = stripeClient();
    $stripeCustomerId = $user['stripe_customer_id'] ?? null;

    if (!$stripeCustomerId || $stripeCustomerId === '') {
        $customer = $stripe->customers->create([
            'email' => $user['email'],
            'name'  => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
        ]);
        $stripeCustomerId = $customer->id;
        $upd = $db->prepare('UPDATE users SET stripe_customer_id = :cid WHERE id = :id');
        $upd->execute([':cid' => $stripeCustomerId, ':id' => $userId]);
    }

    // Get duration from form (default 12 months)
    $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 12;
    if (!in_array($duration, [1, 6, 12], true)) {
        $duration = 12;
    }

    $discounts = [1 => 0, 6 => 0.05, 12 => 0.10];
    $discount = $discounts[$duration] ?? 0;
    $discountedMonthly = ((float) $plan['monthly_premium']) * (1 - $discount);
    $totalAmount = $discountedMonthly * $duration;
    $amountCents = (int) round($totalAmount * 100);
    if ($amountCents <= 0) {
        json_error('Invalid plan amount.', 400);
    }

    $baseUrl = (defined('BASE_PATH') ? BASE_PATH : '') . '/dashboard/subscriptions/';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $successUrlFull = $proto . '://' . $host . $baseUrl . 'checkout-success.php?session_id={CHECKOUT_SESSION_ID}';
    $cancelUrlFull  = $proto . '://' . $host . $baseUrl . 'checkout-cancelled.php';

    $durationLabel = $duration . ($duration === 1 ? ' month' : ' months');

    $monthlyCents = (int) round($discountedMonthly * 100);
    $discountText = $discount > 0 ? ' (' . ($discount * 100) . '% off)' : '';

    $session = $stripe->checkout->sessions->create([
        'mode' => 'payment',
        'customer' => $stripeCustomerId,
        'line_items' => [[
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => $plan['name'] . ' plan for ' . $pet['name'] . $discountText,
                    'description' => '$' . number_format($discountedMonthly, 2) . '/mo × ' . $durationLabel . ' = $' . number_format($totalAmount, 2) . ' total',
                ],
                'unit_amount' => $monthlyCents,
            ],
            'quantity' => $duration,
        ]],
        'success_url' => $successUrlFull,
        'cancel_url'  => $cancelUrlFull,
        'metadata' => [
            'user_id'         => (string) $userId,
            'pet_id'          => (string) $petId,
            'plan_id'         => (string) $planId,
            'duration_months' => (string) $duration,
        ],
    ]);

    json_success([
        'checkout_url' => $session->url,
    ]);
} catch (Throwable $e) {
    error_log('create-checkout-session: ' . $e->getMessage());
    json_error('Unable to start checkout. Please try again or contact support.', 502);
}