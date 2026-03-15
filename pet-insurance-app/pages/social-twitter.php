<?php
/**
 * Placeholder — PawShield on X (Twitter).
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PawShield on X — PawShield Pet Insurance</title>
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
    <section class="hero-section hero-section--short" aria-labelledby="social-hero-heading">
        <div class="hero-bg-shapes" aria-hidden="true">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
        </div>
        <div class="container hero-content">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <span class="hero-badge"><i class="bi bi-twitter-x me-1" aria-hidden="true"></i>X (Twitter)</span>
                    <h1 id="social-hero-heading" class="hero-heading">PawShield on X</h1>
                    <p class="hero-subtitle mb-0 text-center mx-auto" style="max-width: 32rem;">News, tips, and updates. Coming soon.</p>
                </div>
            </div>
        </div>
    </section>
    <section class="py-6">
        <div class="container text-center">
            <p class="text-muted mb-4">Our X (Twitter) profile isn’t live yet. Check back later or <a href="<?= base_path() ?>/pages/contact.php">contact us</a> for updates.</p>
            <a href="<?= base_path() ?>/index.php" class="btn btn-primary">Back to home</a>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
