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

require_once __DIR__ . '/pet-insurance-app/config/constants.php';
require_once __DIR__ . '/pet-insurance-app/config/session.php';

require_once __DIR__ . '/pet-insurance-app/includes/sanitize.php';
require_once __DIR__ . '/pet-insurance-app/includes/csrf.php';
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>

<!-- ── Navbar ──────────────────────────────────────────────── -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/index.php">🐾 PawShield</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/index.php">Home</a>
                </li>
                <?php if (!empty($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/../dashboard/my-pets.php">My Pets</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/../dashboard/profile.php">Profile</a>
                </li>
                <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link text-warning" href="/admin/index.php">Admin</a>
                </li>
                <?php endif; ?>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto">
                <?php if (!empty($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <span class="nav-link text-light">
                        👋 <?= htmlspecialchars($_SESSION['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-outline-light btn-sm px-3 ms-2"
                       href="/pet-insurance-app/auth/logout.php">Log Out</a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="/pet-insurance-app/auth/login.php">Log In</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-primary btn-sm px-3 ms-2 text-white"
                       href="/pet-insurance-app/auth/register.php">Register</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

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