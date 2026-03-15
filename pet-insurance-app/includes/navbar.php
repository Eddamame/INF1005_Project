<?php
/**
 * includes/navbar.php
 * Bootstrap 5 responsive navbar.
 * Detects current page for active link highlighting.
 */
$nav_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$nav_dir   = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$nav_active = '';
if ($nav_script === 'index.php' && strpos($nav_dir, 'dashboard') === false && strpos($nav_dir, 'admin') === false && strpos($nav_dir, 'auth') === false) {
    $nav_active = 'home';
} elseif (strpos($nav_dir, 'pages') !== false) {
    if ($nav_script === 'about.php') $nav_active = 'about';
    elseif ($nav_script === 'services.php') $nav_active = 'services';
    elseif ($nav_script === 'pricing.php') $nav_active = 'pricing';
    elseif ($nav_script === 'how-it-works.php') $nav_active = 'how-it-works';
    elseif ($nav_script === 'contact.php') $nav_active = 'contact';
}
?>
<nav class="navbar navbar-expand-lg pawshield-nav" aria-label="Main navigation">
    <div class="container">
        <a class="navbar-brand" href="<?= base_path() ?>/index.php" aria-label="PawShield Home">
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
                    <a class="nav-link<?= $nav_active === 'home' ? ' active' : '' ?>" href="<?= base_path() ?>/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $nav_active === 'services' ? ' active' : '' ?>" href="<?= base_path() ?>/pages/services.php">Services</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $nav_active === 'pricing' ? ' active' : '' ?>" href="<?= base_path() ?>/pages/pricing.php">Pricing</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $nav_active === 'how-it-works' ? ' active' : '' ?>" href="<?= base_path() ?>/pages/how-it-works.php">How It Works</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $nav_active === 'about' ? ' active' : '' ?>" href="<?= base_path() ?>/pages/about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $nav_active === 'contact' ? ' active' : '' ?>" href="<?= base_path() ?>/pages/contact.php">Contact</a>
                </li>

                <?php if (!empty($_SESSION['user_id'])): ?>
                <!-- Logged-in only -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_path() ?>/dashboard/my-pets.php">
                        <i class="bi bi-heart-pulse me-1" aria-hidden="true"></i>My Pets
                    </a>
                </li>
                <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link text-warning" href="<?= base_path() ?>/admin/index.php">
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
                    <a class="nav-link" href="<?= base_path() ?>/dashboard/profile.php">
                        <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Account', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-outline-light btn-sm px-3"
                       href="<?= base_path() ?>/auth/logout.php">Log Out</a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_path() ?>/auth/login.php">Log In</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-primary btn-sm px-3 text-white nav-cta"
                       href="<?= base_path() ?>/auth/register.php">Get Started</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>