<?php
/**
 * Landing page after successful Stripe Checkout.
 * Creates the subscription record AFTER payment is confirmed.
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../auth/middleware.php';

requireLogin();

/** @var PDO $db */

$userId    = (int) ($_SESSION['user_id'] ?? 0);
$sessionId = isset($_GET['session_id']) ? trim($_GET['session_id']) : '';

$subscription = null;
$error = '';

if ($sessionId && $db instanceof PDO) {
    try {
        // Load Stripe and retrieve the session to get metadata
        require_once __DIR__ . '/../../vendor/autoload.php';
        require_once __DIR__ . '/../../config/stripe.php';

        $stripe  = stripeClient();
        $session = $stripe->checkout->sessions->retrieve($sessionId);

        // Verify payment was successful
        if ($session->payment_status !== 'paid') {
            $error = 'Payment has not been completed.';
        } else {
            $meta     = $session->metadata;
            $metaUser = (int) ($meta->user_id ?? 0);
            $petId    = (int) ($meta->pet_id ?? 0);
            $planId   = (int) ($meta->plan_id ?? 0);
            $duration = (int) ($meta->duration_months ?? 12);

            // Security: verify the session belongs to this user
            if ($metaUser !== $userId) {
                $error = 'Session does not match your account.';
            } elseif (!$petId || !$planId) {
                $error = 'Missing subscription details.';
            } else {
                // Check if subscription already created (prevent double-creation on refresh)
                $check = $db->prepare('
                    SELECT id FROM subscriptions
                    WHERE user_id = :uid AND pet_id = :pid AND stripe_subscription_id = :sid
                    LIMIT 1
                ');
                $check->execute([':uid' => $userId, ':pid' => $petId, ':sid' => $sessionId]);
                $existing = $check->fetch();

                if ($existing) {
                    // Already created — just load it
                    $subId = (int) $existing['id'];
                } else {
                    // Check pet doesn't already have a non-expiring active subscription
                    $activeCheck = $db->prepare('
                        SELECT id, end_date FROM subscriptions
                        WHERE pet_id = :pid AND user_id = :uid AND status = :status
                        LIMIT 1
                    ');
                    $activeCheck->execute([':pid' => $petId, ':uid' => $userId, ':status' => 'active']);
                    $activeSub = $activeCheck->fetch();

                    if ($activeSub) {
                        $endDate = $activeSub['end_date'] ?? null;
                        $expiringWithin30Days = $endDate && strtotime($endDate) <= strtotime('+30 days');
                        if ($expiringWithin30Days) {
                            // Cancel the old subscription since they're renewing
                            $db->prepare('UPDATE subscriptions SET status = :status WHERE id = :id')
                               ->execute([':status' => 'expired', ':id' => $activeSub['id']]);
                        } else {
                            $error = 'This pet already has an active subscription.';
                        }
                    }

                    if (!$error) {
                        // Create the subscription now that payment is confirmed
                        $startDate = date('Y-m-d');
                        $endDate   = date('Y-m-d', strtotime("+{$duration} months"));

                        $stmt = $db->prepare('
                            INSERT INTO subscriptions (user_id, pet_id, plan_id, status, start_date, end_date, stripe_subscription_id)
                            VALUES (:uid, :pid, :plan_id, :status, :start, :end, :sid)
                        ');
                        $stmt->execute([
                            ':uid'     => $userId,
                            ':pid'     => $petId,
                            ':plan_id' => $planId,
                            ':status'  => 'active',
                            ':start'   => $startDate,
                            ':end'     => $endDate,
                            ':sid'     => $sessionId,
                        ]);
                        $subId = (int) $db->lastInsertId();
                    }
                }

                // Load subscription for display
                if (!$error && isset($subId)) {
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
                }
            }
        }
    } catch (Throwable $e) {
        error_log('checkout-success: ' . $e->getMessage());
        $error = 'Could not verify payment. Please contact support if you were charged.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Successful — PawShield</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body>

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 text-center">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-5">
                    <?php if ($error): ?>
                        <div class="text-warning mb-3">
                            <i class="bi bi-exclamation-triangle-fill" style="font-size: 4rem;"></i>
                        </div>
                        <h1 class="h3 mb-2">Something went wrong</h1>
                        <p class="text-muted mb-4"><?= esc($error) ?></p>
                        <a href="<?= base_path() ?>/dashboard/my-pets.php" class="btn btn-primary">
                            Go to My Pets
                        </a>
                    <?php else: ?>
                        <div class="text-success mb-3">
                            <i class="bi bi-check-circle-fill" style="font-size: 4rem;"></i>
                        </div>
                        <h1 class="h3 mb-2">Payment successful</h1>
                        <p class="text-muted mb-4">
                            Your payment has been processed and your coverage is now active.
                        </p>
                        <?php if ($subscription): ?>
                            <p class="mb-1">
                                <strong><?= esc($subscription['pet_name']) ?></strong> (<?= esc(ucfirst($subscription['species'])) ?>)
                                is now covered under the <strong><?= esc($subscription['plan_name']) ?></strong> plan.
                            </p>
                            <p class="small text-muted mb-1">
                                $<?= number_format((float) $subscription['monthly_premium'], 2) ?>/month
                            </p>
                            <?php if (!empty($subscription['start_date']) && !empty($subscription['end_date'])): ?>
                            <p class="small text-muted mb-4">
                                Coverage: <?= esc(date('d M Y', strtotime($subscription['start_date']))) ?>
                                — <?= esc(date('d M Y', strtotime($subscription['end_date']))) ?>
                            </p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="<?= base_path() ?>/dashboard/my-pets.php" class="btn btn-primary me-2">
                            My Pets
                        </a>
                        <a href="<?= base_path() ?>/dashboard/subscriptions/purchase-coverage.php" class="btn btn-outline-secondary">
                            Add another plan
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>