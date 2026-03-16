<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';

/** @var PDO $db */
$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $email    = inputEmail('email');
    $code     = inputString('reset_code') ?? '';
    $password = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!$email) $errors['email'] = 'Email is required.';
    if (!$code)  $errors['code']  = 'Reset code is required.';

    // Password validation
    $passwordErrors = [];
    if (strlen($password) < 8) $passwordErrors[] = 'at least 8 characters';
    if (!preg_match('/[A-Z]/', $password)) $passwordErrors[] = 'at least one uppercase letter';
    if (!preg_match('/[0-9]/', $password)) $passwordErrors[] = 'at least one number';
    if ($passwordErrors) {
        $errors['password'] = 'Password must contain: ' . implode(', ', $passwordErrors) . '.';
    }

    if ($password !== $confirm) {
        $errors['confirm'] = 'Passwords do not match.';
    }

    if (!$errors) {
        $stmt = $db->prepare('SELECT id, reset_code, reset_expires FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            $errors['email'] = 'No account found with that email.';
        } elseif ($code !== VERIFICATION_MASTER_CODE && $code !== $user['reset_code']) {
            $errors['code'] = 'Invalid reset code.';
        } elseif ($code !== VERIFICATION_MASTER_CODE && strtotime($user['reset_expires']) < time()) {
            $errors['code'] = 'Reset code has expired. Please request a new one.';
        } else {
            // Update password
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare('UPDATE users SET password_hash = :hash, reset_code = NULL, reset_expires = NULL WHERE id = :id');
            $stmt->execute([':hash' => $hash, ':id' => $user['id']]);

            $success = 'Password has been reset. You can now log in with your new password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — PawShield</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body>

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content">
    <section class="auth-hero-section">
        <div class="hero-bg-shapes" aria-hidden="true">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
        </div>
        <div class="container hero-content position-relative">
            <div class="row justify-content-center py-5">
                <div class="col-md-6 col-lg-5">
                    <div class="p-4 p-lg-5 rounded-3 shadow-lg auth-form-card">
                        <div class="text-center mb-4">
                            <i class="bi bi-shield-lock" style="font-size: 3rem; color: var(--ps-teal);"></i>
                            <h1 class="h2 mt-2" style="color: var(--ps-navy);">Reset Password</h1>
                            <p class="text-muted">Enter your email, the reset code, and your new password.</p>
                            <p class="small text-muted"><em>For testing: use code <code>000000</code></em></p>
                        </div>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= esc($success) ?></div>
                            <div class="text-center">
                                <a href="<?= base_path() ?>/auth/login.php" class="btn btn-primary"
                                   style="background: var(--ps-teal); border-color: var(--ps-teal);">
                                    Go to Login
                                </a>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($errors as $e): ?>
                                        <div><?= esc($e) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" novalidate>
                                <?= csrfField() ?>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control"
                                           value="<?= esc($_POST['email'] ?? '') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Reset Code</label>
                                    <input type="text" name="reset_code" class="form-control text-center"
                                           maxlength="6" placeholder="000000"
                                           style="letter-spacing: 0.3em; font-weight: 600;" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                    <div class="form-text">Min 8 chars, one uppercase letter, one number.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                <button class="btn btn-primary w-100" style="background: var(--ps-teal); border-color: var(--ps-teal);">
                                    Reset Password
                                </button>
                            </form>
                        <?php endif; ?>

                        <div class="text-center mt-3">
                            <a href="<?= base_path() ?>/auth/forgot-password.php" class="text-muted small">
                                ← Request a new code
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>