<?php
/**
 * Landing page after successful Stripe Checkout.
 * Stripe redirects here with sub_id and session_id.
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../auth/middleware.php';

requireLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$subId  = isset($_GET['sub_id']) ? (int) $_GET['sub_id'] : 0;
$sessionId = isset($_GET['session_id']) ? trim($_GET['session_id']) : '';

$subscription = null;
if ($subId && $db instanceof PDO) {
    $stmt = $db->prepare('
        SELECT s.*, p.name AS pet_name, p.species, ip.name AS plan_name, ip.monthly_premium
        FROM subscriptions s
        JOIN pets p ON p.id = s.pet_id
        JOIN insurance_plans ip ON ip.id = s.plan_id
        WHERE s.id = :id AND s.user_id = :uid
        LIMIT 1
    ');
    $stmt->execute([':id' => $subId, ':uid' => $userId]);
    $subscription = $stmt->fetch();

    // If Stripe sent session_id (subscription mode), save stripe_subscription_id from the session
    if ($subscription && $sessionId !== '' && is_file(__DIR__ . '/../../vendor/autoload.php')) {
        try {
            require_once __DIR__ . '/../../config/stripe.php';
            $stripe = stripeClient();
            $session = $stripe->checkout->sessions->retrieve($sessionId, ['expand' => ['subscription']]);
            $sub = $session->subscription ?? null;
            $stripeSubId = is_object($sub) ? ($sub->id ?? null) : (is_string($sub) ? $sub : null);
            if ($stripeSubId !== null && $stripeSubId !== '') {
                $upd = $db->prepare('UPDATE subscriptions SET stripe_subscription_id = :sid, status = :status WHERE id = :id AND user_id = :uid');
                $upd->execute([':sid' => $stripeSubId, ':status' => 'active', ':id' => $subId, ':uid' => $userId]);
            }
        } catch (Throwable $e) {
            error_log('checkout-success: could not retrieve session: ' . $e->getMessage());
        }
    }
}

$breadcrumb_items = [
    ['My Pets', base_path() . '/dashboard/my-pets.php'],
    ['Review & Pay', base_path() . '/dashboard/subscriptions/review-and-pay.php'],
    ['Success', ''],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Successful — Pet Insurance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="container py-5">
    <?php require_once __DIR__ . '/../../includes/breadcrumb.php'; ?>
    <div class="row justify-content-center mt-3">
        <div class="col-lg-6 text-center">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-5">
                    <div class="text-success mb-3">
                        <i class="bi bi-check-circle-fill" style="font-size: 4rem;" aria-hidden="true"></i>
                    </div>
                    <h1 class="h3 mb-2">Payment successful</h1>
                    <p class="text-muted mb-4">
                        Thank you. Your payment has been processed and your coverage is active.
                    </p>
                    <?php if ($subscription): ?>
                        <p class="mb-1">
                            <strong><?= esc($subscription['pet_name']) ?></strong> (<?= esc(ucfirst($subscription['species'])) ?>)
                            is now covered under <strong><?= esc($subscription['plan_name']) ?></strong>.
                        </p>
                        <p class="small text-muted mb-4">
                            $<?= number_format((float) $subscription['monthly_premium'], 2) ?>/month
                        </p>
                    <?php endif; ?>
                    <a href="<?= base_path() ?>/dashboard/my-pets.php" class="btn btn-primary me-2">
                        My Pets
                    </a>
                    <a href="<?= base_path() ?>/dashboard/subscriptions/review-and-pay.php" class="btn btn-outline-secondary">
                        Add another plan
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
