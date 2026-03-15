<?php
/**
 * Stripe webhook receiver.
 *
 * Handles checkout.session.completed to mark subscriptions active.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/stripe.php';

header('Content-Type: application/json; charset=utf-8');

$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!STRIPE_WEBHOOK_SECRET) {
    http_response_code(400);
    echo json_encode(['error' => 'Webhook not configured.']);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sigHeader,
        STRIPE_WEBHOOK_SECRET
    );
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

if ($event->type === 'checkout.session.completed') {
    $session = $event->data->object;
    $metadata = $session->metadata ?? null;

    if ($metadata && isset($metadata->subscription_id)) {
        $subId = (int) $metadata->subscription_id;
        $stripeSubId = $session->subscription ?? null; // sub_xxx when mode=subscription
        try {
            if ($stripeSubId) {
                $stmt = $db->prepare('
                    UPDATE subscriptions
                    SET status = :status, stripe_subscription_id = :stripe_sub_id
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':status'          => 'active',
                    ':stripe_sub_id'   => $stripeSubId,
                    ':id'              => $subId,
                ]);
            } else {
                $stmt = $db->prepare('
                    UPDATE subscriptions SET status = :status WHERE id = :id
                ');
                $stmt->execute([':status' => 'active', ':id' => $subId]);
            }
        } catch (Throwable $e) {
            error_log('stripe-webhook: failed updating subscription: ' . $e->getMessage());
        }
    }
}

http_response_code(200);
echo json_encode(['received' => true]);

