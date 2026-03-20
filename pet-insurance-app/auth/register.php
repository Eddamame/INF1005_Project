<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/middleware.php';
isLoggedIn();

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $firstName = inputString('first_name');
    $lastName  = inputString('last_name');
    $email     = inputEmail('email');
    $phone     = inputString('phone');
    $password  = $_POST['password']  ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    $old = [
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'email'      => $email,
        'phone'      => $phone,
    ];

    // Validation
    if (!$firstName) $errors['first_name'] = 'First name is required.';
    if (!$lastName)  $errors['last_name']  = 'Last name is required.';
    if (!$email)     $errors['email']      = 'A valid email address is required.';

    $passwordErrors = [];
    if (strlen($password) < 8) {
        $passwordErrors[] = 'at least 8 characters';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $passwordErrors[] = 'at least one uppercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $passwordErrors[] = 'at least one number';
    }
    if ($passwordErrors) {
        $errors['password'] = 'Password must contain: ' . implode(', ', $passwordErrors) . '.';
    }

    if ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // CAPTCHA verification (Google reCAPTCHA in configured environments,
    // simple local checkbox fallback when keys are not configured)
    if (RECAPTCHA_SITE_KEY && RECAPTCHA_SECRET_KEY) {
        $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
        if (!$recaptchaResponse) {
            $errors['captcha'] = 'Please complete the CAPTCHA.';
        } else {
            $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
            $response = file_get_contents($verifyUrl . '?' . http_build_query([
                'secret'   => RECAPTCHA_SECRET_KEY,
                'response' => $recaptchaResponse,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]));
            $result = json_decode($response, true);
            if (empty($result['success'])) {
                $errors['captcha'] = 'CAPTCHA verification failed. Please try again.';
            }
        }
    } else {
        if (empty($_POST['local_captcha'])) {
            $errors['captcha'] = 'Please confirm you are not a bot.';
        }
    }

    // Duplicate email check
    if (!$errors && $email) {
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_BCRYPT);

        // Generate 6-digit verification code
        $verificationCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $verificationExpires = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_CODE_EXPIRY . ' minutes'));

        $stmt = $db->prepare('
            INSERT INTO users (email, password_hash, first_name, last_name, phone, email_verified, verification_code, verification_expires)
            VALUES (:email, :hash, :first_name, :last_name, :phone, 0, :code, :expires)
        ');
        $stmt->execute([
            ':email'      => $email,
            ':hash'       => $hash,
            ':first_name' => $firstName,
            ':last_name'  => $lastName,
            ':phone'      => $phone,
            ':code'       => $verificationCode,
            ':expires'    => $verificationExpires,
        ]);

        $userId = $db->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user_id']    = $userId;
        $_SESSION['user_role']  = 'customer';
        $_SESSION['user_name']  = $firstName;
        $_SESSION['email_verified'] = false;

        // In production, email the code. For now, it's in the DB.
        // Master code 000000 always works.

        header('Location: ' . BASE_PATH . '/auth/verify-email.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — PawShield Pet Insurance</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
    <?php if (RECAPTCHA_SITE_KEY && RECAPTCHA_SECRET_KEY): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
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
                        <h1 class="h2 mb-4" style="color: var(--ps-navy);">Create Account</h1>
                        <form method="POST" novalidate>
                            <?= csrfField() ?>

                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label" for="reg-first-name">First Name</label>
                                    <input type="text" id="reg-first-name" name="first_name" class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>"
                                           value="<?= esc($old['first_name'] ?? '') ?>" required autofocus autocomplete="given-name">
                                    <div class="invalid-feedback"><?= esc($errors['first_name'] ?? '') ?></div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="reg-last-name">Last Name</label>
                                    <input type="text" id="reg-last-name" name="last_name" class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>"
                                           value="<?= esc($old['last_name'] ?? '') ?>" required autocomplete="family-name">
                                    <div class="invalid-feedback"><?= esc($errors['last_name'] ?? '') ?></div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="reg-email">Email</label>
                                    <input type="email" id="reg-email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                           value="<?= esc($old['email'] ?? '') ?>" required autocomplete="email">
                                    <div class="invalid-feedback"><?= esc($errors['email'] ?? '') ?></div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="reg-phone">Phone <span class="text-muted">(optional)</span></label>
                                    <input type="tel" id="reg-phone" name="phone" class="form-control"
                                           value="<?= esc($old['phone'] ?? '') ?>" autocomplete="tel">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="register-password">Password</label>
                                    <div class="input-group">
                                        <input type="password" id="register-password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" required autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary password-toggle" data-target="register-password" aria-label="Show password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Min 8 chars, one uppercase letter, one number.</div>
                                    <div class="invalid-feedback"><?= esc($errors['password'] ?? '') ?></div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="register-confirm-password">Confirm Password</label>
                                    <div class="input-group">
                                        <input type="password" id="register-confirm-password" name="confirm_password"
                                           class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" required autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary password-toggle" data-target="register-confirm-password" aria-label="Show confirm password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback"><?= esc($errors['confirm_password'] ?? '') ?></div>
                                </div>
                                <div class="col-12">
                                    <?php if (RECAPTCHA_SITE_KEY && RECAPTCHA_SECRET_KEY): ?>
                                    <div class="g-recaptcha" data-sitekey="<?= esc(RECAPTCHA_SITE_KEY) ?>"></div>
                                    <?php else: ?>
                                    <div class="form-check p-3 rounded-3 border" style="background: var(--ps-off-white); border-color: var(--ps-gray-200) !important;">
                                        <input class="form-check-input <?= isset($errors['captcha']) ? 'is-invalid' : '' ?>" type="checkbox" value="1" id="local-captcha" name="local_captcha" <?= !empty($_POST['local_captcha']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="local-captcha">
                                            I’m not a robot
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (isset($errors['captcha'])): ?>
                                        <div class="text-danger small mt-1"><?= esc($errors['captcha']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <button class="btn btn-primary w-100 mt-4" style="background: var(--ps-teal); border-color: var(--ps-teal);">Create Account</button>
                            <p class="text-center mt-3 mb-0">Already have an account? <a href="<?= base_path() ?>/auth/login.php">Log in</a></p>
                        </form>
                    </div>
                </div>
                <div class="col-lg-7 d-none d-lg-flex justify-content-center align-items-center" aria-hidden="true">
                    <div class="hero-card-float auth-float-cards">
                        <div class="float-card fc-1">
                            <i class="bi bi-heart-pulse"></i>
                            <span>Add your pet</span>
                            <strong>We've got them covered</strong>
                        </div>
                        <div class="float-card fc-2">
                            <i class="bi bi-cpu"></i>
                            <span>AI Scanning…</span>
                            <div class="scan-bar"><div class="scan-fill"></div></div>
                        </div>
                        <div class="float-card fc-3">
                            <i class="bi bi-file-earmark-check"></i>
                            <span>Claim Approved</span>
                            <strong>$420.00</strong>
                        </div>
                        <div class="hero-paw-large">🐾</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
document.querySelectorAll('.password-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var input = document.getElementById(btn.getAttribute('data-target'));
        if (!input) return;
        var isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });
});
</script>