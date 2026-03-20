<?php
/**
 * Custom 429 — Too many requests.
 */
http_response_code(429);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Too many requests — PawShield</title>
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
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>
<main id="main-content">
    <section class="py-6">
        <div class="container text-center py-5">
            <p class="display-1 mb-2" style="color: var(--ps-teal); font-weight: 800;">429</p>
            <h1 class="h2 mb-3" style="color: var(--ps-navy);">Too many requests</h1>
            <p class="text-muted mb-4">You’ve made too many requests. Please wait a moment and try again.</p>
            <a href="<?= base_path() ?>/index.php" class="btn btn-primary">Back to home</a>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
