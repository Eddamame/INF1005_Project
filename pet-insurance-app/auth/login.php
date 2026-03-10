<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';

// Redirect already-logged-in users
if (!empty($_SESSION['user_id'])) {
    header('Location: ../dashboard/my-pets.php');
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

            header('Location: /pet-insurance-app/dashboard/my-pets.php');
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
    <title>Login — Pet Insurance</title>
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

<div class="container py-5" style="max-width:440px">
    <h2 class="mb-4">Log In</h2>

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
        <p class="text-center mt-3">No account? <a href="register.php">Register</a></p>
    </form>
</div>
</body>
</html>
