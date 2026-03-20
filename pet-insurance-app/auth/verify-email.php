<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';

// Must be logged in but not yet verified
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . '/auth/login.php');
    exit;
}

/** @var PDO $db */
$userId = (int) $_SESSION['user_id'];

// Check if already verified
$stmt = $db->prepare('SELECT email_verified, email FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if ($user && (int)$user['email_verified'] === 1) {
    header('Location: ' . BASE_PATH . '/auth/add-payment.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $code = inputString('verification_code') ?? '';

    if (!$code) {
        $error = 'Please enter the verification code.';
    } elseif ($code === VERIFICATION_MASTER_CODE) {
        // Master code bypass
        $stmt = $db->prepare('UPDATE users SET email_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $_SESSION['email_verified'] = true;

        header('Location: ' . BASE_PATH . '/auth/add-payment.php');
        exit;
    } else {
        // Check actual code
        $stmt = $db->prepare('SELECT verification_code, verification_expires FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();

        if (!$row || $row['verification_code'] !== $code) {
            $error = 'Invalid verification code.';
        } elseif (strtotime($row['verification_expires']) < time()) {
            $error = 'Verification code has expired. Please request a new one.';
        } else {
            $stmt = $db->prepare('UPDATE users SET email_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = :id');
            $stmt->execute([':id' => $userId]);
            $_SESSION['email_verified'] = true;

            header('Location: ' . BASE_PATH . '/auth/add-payment.php');
            exit;
        }
    }
}

// Handle resend
if (isset($_GET['resend'])) {
    $newCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $newExpires = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_CODE_EXPIRY . ' minutes'));
    $stmt = $db->prepare('UPDATE users SET verification_code = :code, verification_expires = :expires WHERE id = :id');
    $stmt->execute([':code' => $newCode, ':expires' => $newExpires, ':id' => $userId]);
    $success = 'A new verification code has been sent.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — PawShield</title>
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
                            <i class="bi bi-envelope-check" style="font-size: 3rem; color: var(--ps-teal);"></i>
                            <h1 class="h2 mt-2" style="color: var(--ps-navy);">Verify Your Email</h1>
                            <p class="text-muted">
                                We've sent a 6-digit code to <strong><?= esc($user['email'] ?? '') ?></strong>.
                                Enter it below to continue.
                            </p>
                            <p class="small text-muted">
                                <em>For testing: use master code <code>000000</code></em>
                            </p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= esc($error) ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= esc($success) ?></div>
                        <?php endif; ?>

                        <form method="POST" novalidate>
                            <?= csrfField() ?>
                            <div class="mb-3">
                                <label class="form-label" for="verify-code">Verification Code</label>
                                <input type="text" id="verify-code" name="verification_code" class="form-control form-control-lg text-center"
                                       maxlength="6" placeholder="000000"
                                       style="letter-spacing: 0.5em; font-size: 1.5rem; font-weight: 700;"
                                       autofocus required autocomplete="one-time-code" inputmode="numeric">
                            </div>
                            <button class="btn btn-primary w-100" style="background: var(--ps-teal); border-color: var(--ps-teal);">
                                Verify
                            </button>
                        </form>

                        <div class="text-center mt-3">
                            <a href="<?= base_path() ?>/auth/verify-email.php?resend=1" class="text-muted small">
                                Didn't receive a code? Resend
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>