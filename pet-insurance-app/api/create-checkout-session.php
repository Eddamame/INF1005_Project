<?php
/**
 * Create Stripe Checkout Session for subscriptions.
 *
 * Expects POST with:
 *   - pet_id
 *   - plan_id
 *   - csrf_token (body or X-CSRF-Token header)
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

    // Debugging check
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

    // Create subscription row (status active until webhook / real flow)
    $subStmt = $db->prepare('
        INSERT INTO subscriptions (user_id, pet_id, plan_id, status, start_date)
        VALUES (:uid, :pid, :plan_id, :status, :start_date)
    ');
    $subStmt->execute([
        ':uid'        => $userId,
        ':pid'        => $petId,
        ':plan_id'    => $planId,
        ':status'     => 'active',
        ':start_date' => date('Y-m-d'),
    ]);
    $subscriptionId = (int) $db->lastInsertId();

    $amountCents = (int) round(((float) $plan['monthly_premium']) * 100);
    if ($amountCents <= 0) {
        json_error('Invalid plan amount.', 400);
    }

    $baseUrl = (defined('BASE_PATH') ? BASE_PATH : '') . '/dashboard/subscriptions/';
    $successUrl = $baseUrl . 'checkout-success.php?sub_id=' . $subscriptionId;
    $cancelUrl  = $baseUrl . 'checkout-cancelled.php?sub_id=' . $subscriptionId;

    // Stripe expects full URLs; if BASE_PATH is a path like /pet-insurance-app, build full URL
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $successUrlFull = $proto . '://' . $host . $successUrl . '&session_id={CHECKOUT_SESSION_ID}';
    $cancelUrlFull  = $proto . '://' . $host . $cancelUrl;

    // Use subscription mode so Stripe creates a recurring subscription and we get stripe_subscription_id
    $session = $stripe->checkout->sessions->create([
        'mode' => 'subscription',
        'customer' => $stripeCustomerId,
        'line_items' => [[
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => $plan['name'] . ' plan for ' . $pet['name'],
                ],
                'unit_amount' => $amountCents,
                'recurring' => ['interval' => 'month'],
            ],
            'quantity' => 1,
        ]],
        'success_url' => $successUrlFull,
        'cancel_url'  => $cancelUrlFull,
        'metadata' => [
            'user_id'         => (string) $userId,
            'pet_id'          => (string) $petId,
            'plan_id'         => (string) $planId,
            'subscription_id' => (string) $subscriptionId,
        ],
    ]);

    json_success([
        'checkout_url' => $session->url,
    ]);
} catch (Throwable $e) {
    error_log('create-checkout-session: ' . $e->getMessage());
    json_error('Unable to start checkout. Please try again or contact support.', 502);
}

