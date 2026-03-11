<?php
/**
 * includes/navbar.php
 * Bootstrap 5 responsive navbar.
 * All paths are root-relative from pet-insurance-app/.
 */
?>
<nav class="navbar navbar-expand-lg pawshield-nav" aria-label="Main navigation">
    <div class="container">
        <a class="navbar-brand" href="/index.php" aria-label="PawShield Home">
            <span class="brand-paw">🐾</span>
            <span class="brand-text">PawShield</span>
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarMain"
                aria-controls="navbarMain"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <!-- Left: public links -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/pages/services.php">Services</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/pages/pricing.php">Pricing</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/pages/how-it-works.php">How It Works</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/pages/about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/pages/contact.php">Contact</a>
                </li>

                <?php if (!empty($_SESSION['user_id'])): ?>
                <!-- Logged-in only -->
                <li class="nav-item">
                    <a class="nav-link" href="/dashboard/my-pets.php">
                        <i class="bi bi-heart-pulse me-1" aria-hidden="true"></i>My Pets
                    </a>
                </li>
                <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link text-warning" href="/admin/index.php">
                        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Admin
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>
            </ul>

            <!-- Right: auth -->
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center gap-lg-2">
                <?php if (!empty($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/dashboard/profile.php">
                        <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Account', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-outline-light btn-sm px-3"
                       href="/auth/logout.php">Log Out</a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="/auth/login.php">Log In</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-primary btn-sm px-3 text-white nav-cta"
                       href="/auth/register.php">Get Started</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>