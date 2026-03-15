<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../auth/middleware.php';
requireLogin();

$userId  = $_SESSION['user_id'];
$success = '';
$errors  = [];

// Fetch current user
$stmt = $db->prepare('SELECT first_name, last_name, phone, email FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

// ── Handle Profile Update ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    requireValidCsrf();

    $firstName = inputString('first_name');
    $lastName  = inputString('last_name');
    $phone     = inputString('phone');

    if (!$firstName) $errors['first_name'] = 'First name is required.';
    if (!$lastName)  $errors['last_name']  = 'Last name is required.';

    if (!$errors) {
        $stmt = $db->prepare('
            UPDATE users SET first_name = :fn, last_name = :ln, phone = :ph
            WHERE id = :id
        ');
        $stmt->execute([':fn' => $firstName, ':ln' => $lastName, ':ph' => $phone, ':id' => $userId]);
        $_SESSION['user_name'] = $firstName;
        $user['first_name']    = $firstName;
        $user['last_name']     = $lastName;
        $user['phone']         = $phone;
        $success = 'Profile updated successfully.';
    }
}

// ── Handle Password Change ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    requireValidCsrf();

    $oldPass  = $_POST['old_password']     ?? '';
    $newPass  = $_POST['new_password']     ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $stmt2 = $db->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt2->execute([':id' => $userId]);
    $row = $stmt2->fetch();

    if (!password_verify($oldPass, $row['password_hash'])) {
        $errors['old_password'] = 'Current password is incorrect.';
    } elseif (strlen($newPass) < 8) {
        $errors['new_password'] = 'New password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
        $errors['new_password'] = 'Password must contain an uppercase letter and a number.';
    } elseif ($newPass !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (!$errors) {
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $stmt3 = $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt3->execute([':hash' => $hash, ':id' => $userId]);
        $success = 'Password changed successfully.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile — Pet Insurance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body>
    
<!-- ── Navbar (was a TODO comment — now using our include) ── -->
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>
<div class="container py-5" style="max-width:600px">
    <h2 class="mb-4">My Profile</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= esc($success) ?></div>
    <?php endif; ?>

    <!-- Profile Form -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Personal Details</h5>
            <form method="POST" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_profile">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name"
                               class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>"
                               value="<?= esc($user['first_name']) ?>" required>
                        <div class="invalid-feedback"><?= esc($errors['first_name'] ?? '') ?></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name"
                               class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>"
                               value="<?= esc($user['last_name']) ?>" required>
                        <div class="invalid-feedback"><?= esc($errors['last_name'] ?? '') ?></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= esc($user['email']) ?>" disabled>
                        <div class="form-text">Email cannot be changed.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control"
                               value="<?= esc($user['phone'] ?? '') ?>">
                    </div>
                </div>
                <button class="btn btn-primary mt-3">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Password Form -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Change Password</h5>
            <form method="POST" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="change_password">
                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="old_password"
                           class="form-control <?= isset($errors['old_password']) ? 'is-invalid' : '' ?>" required>
                    <div class="invalid-feedback"><?= esc($errors['old_password'] ?? '') ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password"
                           class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>" required>
                    <div class="invalid-feedback"><?= esc($errors['new_password'] ?? '') ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password"
                           class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" required>
                    <div class="invalid-feedback"><?= esc($errors['confirm_password'] ?? '') ?></div>
                </div>
                <button class="btn btn-warning">Change Password</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
