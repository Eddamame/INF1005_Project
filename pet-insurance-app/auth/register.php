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

    // Hi Jk just added this to aggregate all the errors so the user doesn't have to submit multiple times to see them all
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
        $stmt = $db->prepare('
            INSERT INTO users (email, password_hash, first_name, last_name, phone)
            VALUES (:email, :hash, :first_name, :last_name, :phone)
        ');
        $stmt->execute([
            ':email'      => $email,
            ':hash'       => $hash,
            ':first_name' => $firstName,
            ':last_name'  => $lastName,
            ':phone'      => $phone,
        ]);

        $userId = $db->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user_id']    = $userId;
        $_SESSION['user_role']  = 'customer';
        $_SESSION['user_name']  = $firstName;

        header('Location: /dashboard/my-pets.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register — Pet Insurance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
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
<div class="container py-5" style="max-width:520px">
    <h2 class="mb-4">Create Account</h2>
    <form method="POST" novalidate>
        <?= csrfField() ?>

        <div class="row g-3">
            <div class="col-6">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>"
                       value="<?= esc($old['first_name'] ?? '') ?>" required>
                <div class="invalid-feedback"><?= esc($errors['first_name'] ?? '') ?></div>
            </div>
            <div class="col-6">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>"
                       value="<?= esc($old['last_name'] ?? '') ?>" required>
                <div class="invalid-feedback"><?= esc($errors['last_name'] ?? '') ?></div>
            </div>
            <div class="col-12">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                       value="<?= esc($old['email'] ?? '') ?>" required>
                <div class="invalid-feedback"><?= esc($errors['email'] ?? '') ?></div>
            </div>
            <div class="col-12">
                <label class="form-label">Phone <span class="text-muted">(optional)</span></label>
                <input type="tel" name="phone" class="form-control"
                       value="<?= esc($old['phone'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" required>
                <div class="form-text">Min 8 chars, one uppercase letter, one number.</div>
                <div class="invalid-feedback"><?= esc($errors['password'] ?? '') ?></div>
            </div>
            <div class="col-12">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password"
                       class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" required>
                <div class="invalid-feedback"><?= esc($errors['confirm_password'] ?? '') ?></div>
            </div>
        </div>

        <button class="btn btn-primary w-100 mt-4">Create Account</button>
        <p class="text-center mt-3">Already have an account? <a href="login.php">Log in</a></p>
    </form>
</div>
</body>
</html>
