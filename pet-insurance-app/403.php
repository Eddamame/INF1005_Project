<?php
/**
 * Custom 403 — Forbidden.
 */
http_response_code(403);
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access denied — PawShield</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<main id="main-content">
    <section class="py-6">
        <div class="container text-center py-5">
            <p class="display-1 mb-2" style="color: var(--ps-teal); font-weight: 800;">403</p>
            <h1 class="h2 mb-3" style="color: var(--ps-navy);">Access denied</h1>
            <p class="text-muted mb-4">You don’t have permission to view this page.</p>
            <a href="<?= base_path() ?>/index.php" class="btn btn-primary" style="background: var(--ps-teal); border-color: var(--ps-teal);">Back to home</a>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
