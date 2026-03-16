<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';

/** @var PDO $db */
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $email = inputEmail('email');

    if (!$email) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $resetCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $resetExpires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $stmt = $db->prepare('UPDATE users SET reset_code = :code, reset_expires = :expires WHERE id = :id');
            $stmt->execute([':code' => $resetCode, ':expires' => $resetExpires, ':id' => $user['id']]);

            // In production, email the code. For now, master code 000000 works.
        }

        // Always show success (don't reveal whether email exists)
        $success = 'If an account with that email exists, a reset code has been sent. Use code <code>000000</code> for testing.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — PawShield</title>
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
                            <i class="bi bi-key" style="font-size: 3rem; color: var(--ps-teal);"></i>
                            <h1 class="h2 mt-2" style="color: var(--ps-navy);">Forgot Password</h1>
                            <p class="text-muted">Enter your email and we'll send you a reset code.</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= esc($error) ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                            <div class="text-center">
                                <a href="<?= base_path() ?>/auth/reset-password.php" class="btn btn-primary"
                                   style="background: var(--ps-teal); border-color: var(--ps-teal);">
                                    Enter Reset Code
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="POST" novalidate>
                                <?= csrfField() ?>
                                <div class="mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control" required autofocus>
                                </div>
                                <button class="btn btn-primary w-100" style="background: var(--ps-teal); border-color: var(--ps-teal);">
                                    Send Reset Code
                                </button>
                            </form>
                        <?php endif; ?>

                        <div class="text-center mt-3">
                            <a href="<?= base_path() ?>/auth/login.php" class="text-muted small">
                                ← Back to login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>