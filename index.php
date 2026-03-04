<?php
/**
 * Pet Insurance App — Landing Page
 *
 * TODO (Role 2 Phase 1):
 *   - Hero section with CTA
 *   - "How it works" 3-step section
 *   - Plan highlight cards
 *   - Testimonials section
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/session.php';
session_start();

require_once __DIR__ . '/includes/sanitize.php';
require_once __DIR__ . '/includes/csrf.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo esc(generateCsrfToken()); ?>">
    <title>PawShield — Pet Insurance Made Simple</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- TODO: include navbar.php -->

    <main class="container py-5">
        <div class="text-center">
            <h1>🐾 PawShield</h1>
            <p class="lead">AI-powered pet insurance claims — upload your receipt, get an instant quote.</p>
            <hr>
            <p><strong>Environment:</strong> <?php echo esc(APP_ENV); ?></p>
            <p><strong>Scanner mode:</strong> <?php echo esc(AI_SCANNER_MODE); ?></p>
            <p><strong>Database:</strong> <?php echo esc(DB_HOST); ?>/<?php echo esc(DB_NAME); ?></p>
            <p class="text-success">✅ Phase 0 setup verified — ready for development.</p>
        </div>
    </main>

    <!-- TODO: include footer.php -->

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>