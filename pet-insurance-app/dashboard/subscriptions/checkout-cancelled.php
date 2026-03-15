<?php
/**
 * Landing page when the user cancels Stripe Checkout (clicks back or closes).
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../auth/middleware.php';

requireLogin();

$breadcrumb_items = [
    ['My Pets', base_path() . '/dashboard/my-pets.php'],
    ['Review & Pay', base_path() . '/dashboard/subscriptions/review-and-pay.php'],
    ['Cancelled', ''],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment cancelled — Pet Insurance</title>
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
                    <div class="text-warning mb-3">
                        <i class="bi bi-x-circle" style="font-size: 4rem;" aria-hidden="true"></i>
                    </div>
                    <h1 class="h3 mb-2">Payment cancelled</h1>
                    <p class="text-muted mb-4">
                        You left checkout before completing payment. No charge was made. You can try again whenever you’re ready.
                    </p>
                    <a href="<?= base_path() ?>/dashboard/subscriptions/review-and-pay.php" class="btn btn-primary me-2">
                        Try again
                    </a>
                    <a href="<?= base_path() ?>/dashboard/my-pets.php" class="btn btn-outline-secondary">
                        My Pets
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
