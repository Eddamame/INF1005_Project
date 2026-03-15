<?php
/**
 * Stripe configuration.
 *
 * Keys are loaded from environment variables in production and may fall back
 * to placeholder defaults in local dev. Do NOT commit real keys.
 */

require_once __DIR__ . '/constants.php';

define('STRIPE_SECRET_KEY', getenv('STRIPE_SECRET_KEY') ?: 'sk_test_PLACEHOLDER');
define('STRIPE_PUBLISHABLE_KEY', getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_PLACEHOLDER');
define('STRIPE_WEBHOOK_SECRET', getenv('STRIPE_WEBHOOK_SECRET') ?: '');

/**
 * Get a configured Stripe client instance.
 *
 * Requires stripe/stripe-php via Composer.
 *
 * @return \Stripe\StripeClient
 */
function stripeClient(): \Stripe\StripeClient
{
    static $client = null;
    if ($client === null) {
        require_once __DIR__ . '/../vendor/autoload.php';
        $client = new \Stripe\StripeClient(STRIPE_SECRET_KEY);
    }
    return $client;
}

