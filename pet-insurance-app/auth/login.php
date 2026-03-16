<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';

// Redirect already-logged-in users
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . '/dashboard/my-pets.php');
    exit;
}

$error = '';
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $email    = inputEmail('email');
    $password = $_POST['password'] ?? '';
    $oldEmail = $email ?? '';

    // Rate limiting: track failed attempts in session
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0);
    $_SESSION['login_last_attempt'] = ($_SESSION['login_last_attempt'] ?? 0);

    $lockoutSeconds = 300; // 5 minutes
    $maxAttempts    = 5;

    // Hi JK added this to reset lockout if the timer has expired
    if ($_SESSION['login_attempts'] >= $maxAttempts &&
        (time() - $_SESSION['login_last_attempt']) >= $lockoutSeconds) {
        $_SESSION['login_attempts'] = 0;
    }

    if ($_SESSION['login_attempts'] >= $maxAttempts &&
        (time() - $_SESSION['login_last_attempt']) < $lockoutSeconds) {
        $wait = $lockoutSeconds - (time() - $_SESSION['login_last_attempt']);
        $error = "Too many failed attempts. Please wait {$wait} seconds.";
    } elseif (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $db->prepare('SELECT id, password_hash, first_name, role FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Reset rate limit on success
            $_SESSION['login_attempts'] = 0;

            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['first_name'];

            header('Location: ' . BASE_PATH . '/dashboard/my-pets.php');
            exit;
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['login_last_attempt'] = time();
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In — PawShield Pet Insurance</title>
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
    <section class="auth-hero-section">
        <div class="hero-bg-shapes" aria-hidden="true">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
        </div>
        <div class="container hero-content position-relative">
            <div class="row align-items-center min-vh-75 py-5">
                <div class="col-lg-5">
                    <div class="p-4 p-lg-5 rounded-3 shadow-lg auth-form-card">
                        <h1 class="h2 mb-4" style="color: var(--ps-navy);">Log In</h1>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= esc($error) ?></div>
                        <?php endif; ?>

                        <form method="POST" novalidate>
                            <?= csrfField() ?>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control"
                                       value="<?= esc($oldEmail) ?>" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button class="btn btn-primary w-100">Log In</button>
                            <p class="text-center mt-3 mb-0">No account? 
                                <p class="text-center mb-0">
                                    <a href="<?= base_path() ?>/auth/forgot-password.php" class="text-muted small">Forgot your password?</a>
                                </p>
                                <a href="<?= base_path() ?>/auth/register.php">Register</a>
                            </p>
                        </form>
                    </div>
                </div>
                <div class="col-lg-7 d-none d-lg-flex justify-content-center align-items-center" aria-hidden="true">
                    <div class="hero-card-float auth-float-cards">
                        <div class="float-card fc-1">
                            <i class="bi bi-file-earmark-check"></i>
                            <span>Claim Approved</span>
                            <strong>$420.00</strong>
                        </div>
                        <div class="float-card fc-2">
                            <i class="bi bi-cpu"></i>
                            <span>AI Scanning…</span>
                            <div class="scan-bar"><div class="scan-fill"></div></div>
                        </div>
                        <div class="float-card fc-3">
                            <i class="bi bi-shield-check"></i>
                            <span>Secure login</span>
                            <strong>You're in control</strong>
                        </div>
                        <div class="hero-paw-large">🐾</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
