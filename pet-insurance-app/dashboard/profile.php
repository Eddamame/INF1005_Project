<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../auth/middleware.php';
requireLogin();

/** @var PDO $db */

$userId  = $_SESSION['user_id'];
$success = '';
$errors  = [];

// Flash message
if (!empty($_SESSION['flash_message'])) {
    $success = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Fetch current user
$stmt = $db->prepare('SELECT first_name, last_name, phone, email FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

// Fetch payment methods
$stmt = $db->prepare('SELECT * FROM payment_methods WHERE user_id = :uid ORDER BY is_default DESC, created_at DESC');
$stmt->execute([':uid' => $userId]);
$paymentMethods = $stmt->fetchAll();

// ── Handle Profile Update ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    requireValidCsrf();

    $firstName = inputString('first_name');
    $lastName  = inputString('last_name');
    $phone     = inputString('phone');

    if (!$firstName) $errors['first_name'] = 'First name is required.';
    if (!$lastName)  $errors['last_name']  = 'Last name is required.';

    if (!$errors) {
        $stmt = $db->prepare('UPDATE users SET first_name = :fn, last_name = :ln, phone = :ph WHERE id = :id');
        $stmt->execute([':fn' => $firstName, ':ln' => $lastName, ':ph' => $phone, ':id' => $userId]);
        $_SESSION['user_name'] = $firstName;
        $user['first_name']    = $firstName;
        $user['last_name']     = $lastName;
        $user['phone']         = $phone;
        $success = 'Profile updated successfully.';
    }
}

// ── Handle Password Change ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    requireValidCsrf();

    $oldPass = $_POST['old_password']     ?? '';
    $newPass = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

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

// ── Handle Add Card ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_card') {
    requireValidCsrf();

    $authPass    = $_POST['auth_password'] ?? '';
    $cardHolder  = inputString('card_holder');
    $cardNumber  = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
    $expiryMonth = inputInt('expiry_month');
    $expiryYear  = inputInt('expiry_year');
    $cvv         = inputString('cvv');

    // Verify password first
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();

    if (!password_verify($authPass, $row['password_hash'])) {
        $errors['card_auth'] = 'Incorrect password.';
    } else {
        if (!$cardHolder) $errors['card_holder'] = 'Cardholder name is required.';
        if (!$cardNumber || strlen($cardNumber) < 13 || strlen($cardNumber) > 19 || !ctype_digit($cardNumber)) {
            $errors['card_number'] = 'Please enter a valid card number.';
        }
        if (!$expiryMonth || $expiryMonth < 1 || $expiryMonth > 12) {
            $errors['card_expiry'] = 'Invalid expiry month.';
        }
        if (!$expiryYear || $expiryYear < (int)date('Y') || $expiryYear > (int)date('Y') + 10) {
            $errors['card_expiry'] = 'Invalid expiry year.';
        }
        if (!$cvv || strlen($cvv) < 3 || strlen($cvv) > 4) {
            $errors['card_cvv'] = 'Please enter a valid CVV.';
        }
    }

    if (!$errors) {
        $brand = 'Unknown';
        if (str_starts_with($cardNumber, '4')) $brand = 'Visa';
        elseif (str_starts_with($cardNumber, '5') || str_starts_with($cardNumber, '2')) $brand = 'Mastercard';
        elseif (str_starts_with($cardNumber, '3')) $brand = 'Amex';

        $lastFour = substr($cardNumber, -4);

        // If this is the first card, make it default
        $isDefault = empty($paymentMethods) ? 1 : 0;

        $stmt = $db->prepare('
            INSERT INTO payment_methods (user_id, card_holder, card_last_four, card_brand, expiry_month, expiry_year, is_default)
            VALUES (:uid, :holder, :last4, :brand, :month, :year, :default)
        ');
        $stmt->execute([
            ':uid'     => $userId,
            ':holder'  => $cardHolder,
            ':last4'   => $lastFour,
            ':brand'   => $brand,
            ':month'   => $expiryMonth,
            ':year'    => $expiryYear,
            ':default' => $isDefault,
        ]);

        $success = 'Card added successfully.';

        // Refresh payment methods
        $stmt = $db->prepare('SELECT * FROM payment_methods WHERE user_id = :uid ORDER BY is_default DESC, created_at DESC');
        $stmt->execute([':uid' => $userId]);
        $paymentMethods = $stmt->fetchAll();
    }
}

// ── Handle Remove Card ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_card') {
    requireValidCsrf();

    $authPass = $_POST['auth_password'] ?? '';
    $cardId   = inputInt('card_id');

    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();

    if (!password_verify($authPass, $row['password_hash'])) {
        $errors['remove_auth'] = 'Incorrect password.';
    } elseif ($cardId) {
        $stmt = $db->prepare('DELETE FROM payment_methods WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $cardId, ':uid' => $userId]);
        $success = 'Card removed.';

        // Refresh
        $stmt = $db->prepare('SELECT * FROM payment_methods WHERE user_id = :uid ORDER BY is_default DESC, created_at DESC');
        $stmt->execute([':uid' => $userId]);
        $paymentMethods = $stmt->fetchAll();
    }
}

// ── Handle Set Default Card ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_default_card') {
    requireValidCsrf();

    $authPass = $_POST['auth_password'] ?? '';
    $cardId   = inputInt('card_id');

    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();

    if (!password_verify($authPass, $row['password_hash'])) {
        $errors['default_auth'] = 'Incorrect password.';
    } elseif ($cardId) {
        // Unset all defaults
        $db->prepare('UPDATE payment_methods SET is_default = 0 WHERE user_id = :uid')->execute([':uid' => $userId]);
        // Set new default
        $db->prepare('UPDATE payment_methods SET is_default = 1 WHERE id = :id AND user_id = :uid')
           ->execute([':id' => $cardId, ':uid' => $userId]);
        $success = 'Default card updated.';

        // Refresh
        $stmt = $db->prepare('SELECT * FROM payment_methods WHERE user_id = :uid ORDER BY is_default DESC, created_at DESC');
        $stmt->execute([':uid' => $userId]);
        $paymentMethods = $stmt->fetchAll();
    }
}

// Card brand icons
function cardIcon(string $brand): string {
    $map = [
        'Visa'       => 'bi-credit-card-2-front',
        'Mastercard' => 'bi-credit-card',
        'Amex'       => 'bi-credit-card-fill',
    ];
    return $map[$brand] ?? 'bi-credit-card';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile — PawShield</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body>

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="container py-5" style="max-width:700px">
    <h2 class="mb-4">My Profile</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= esc($success) ?></div>
    <?php endif; ?>

    <!-- ── Personal Details ───────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title"><i class="bi bi-person me-2"></i>Personal Details</h5>
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

    <!-- ── Payment Methods ────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0"><i class="bi bi-credit-card me-2"></i>Payment Methods</h5>
                <button class="btn btn-sm btn-success" type="button"
                        data-bs-toggle="collapse" data-bs-target="#addCardForm">
                    <i class="bi bi-plus-circle me-1"></i>Add Card
                </button>
            </div>

            <?php if (isset($errors['card_auth']) || isset($errors['remove_auth']) || isset($errors['default_auth'])): ?>
                <div class="alert alert-danger small">
                    <?= esc($errors['card_auth'] ?? $errors['remove_auth'] ?? $errors['default_auth'] ?? '') ?>
                </div>
            <?php endif; ?>

            <!-- Existing cards -->
            <?php if (empty($paymentMethods)): ?>
                <p class="text-muted small">No payment methods saved.</p>
            <?php else: ?>
                <?php foreach ($paymentMethods as $pm): ?>
                <div class="d-flex align-items-center justify-content-between border rounded p-3 mb-2">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi <?= cardIcon($pm['card_brand']) ?> fs-4 text-primary"></i>
                        <div>
                            <strong><?= esc($pm['card_brand']) ?></strong> ending in <code><?= esc($pm['card_last_four']) ?></code>
                            <?php if ((int)$pm['is_default']): ?>
                                <span class="badge bg-success ms-1">Default</span>
                            <?php endif; ?>
                            <br>
                            <small class="text-muted">
                                <?= esc($pm['card_holder']) ?> &middot;
                                Expires <?= str_pad($pm['expiry_month'], 2, '0', STR_PAD_LEFT) ?>/<?= esc($pm['expiry_year']) ?>
                            </small>
                        </div>
                    </div>
                    <div class="d-flex gap-1">
                        <?php if (!(int)$pm['is_default']): ?>
                        <!-- Set Default -->
                        <button class="btn btn-sm btn-outline-primary" type="button"
                                data-bs-toggle="modal" data-bs-target="#defaultCardModal<?= (int)$pm['id'] ?>">
                            Set Default
                        </button>
                        <?php endif; ?>
                        <!-- Remove -->
                        <button class="btn btn-sm btn-outline-danger" type="button"
                                data-bs-toggle="modal" data-bs-target="#removeCardModal<?= (int)$pm['id'] ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>

                <!-- Set Default Modal -->
                <div class="modal fade" id="defaultCardModal<?= (int)$pm['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <form method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="set_default_card">
                                <input type="hidden" name="card_id" value="<?= (int)$pm['id'] ?>">
                                <div class="modal-header">
                                    <h6 class="modal-title">Confirm Password</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="small text-muted">Enter your password to set this card as default.</p>
                                    <input type="password" name="auth_password" class="form-control" placeholder="Password" required>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-primary">Confirm</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Remove Card Modal -->
                <div class="modal fade" id="removeCardModal<?= (int)$pm['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <form method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="remove_card">
                                <input type="hidden" name="card_id" value="<?= (int)$pm['id'] ?>">
                                <div class="modal-header">
                                    <h6 class="modal-title">Confirm Removal</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="small text-muted">
                                        Enter your password to remove
                                        <strong><?= esc($pm['card_brand']) ?> ****<?= esc($pm['card_last_four']) ?></strong>.
                                    </p>
                                    <input type="password" name="auth_password" class="form-control" placeholder="Password" required>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-danger">Remove Card</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Add Card Form (collapsible) -->
            <div class="collapse mt-3 <?= (isset($errors['card_holder']) || isset($errors['card_number']) || isset($errors['card_expiry']) || isset($errors['card_cvv']) || isset($errors['card_auth'])) ? 'show' : '' ?>"
                 id="addCardForm">
                <div class="border rounded p-3">
                    <h6><i class="bi bi-plus-circle me-1"></i>Add New Card</h6>
                    <p class="small text-muted mb-3">
                        <em>For testing, use Visa: <code>4242 4242 4242 4242</code></em>
                    </p>

                    <?php if (isset($errors['card_holder']) || isset($errors['card_number']) || isset($errors['card_expiry']) || isset($errors['card_cvv'])): ?>
                        <div class="alert alert-danger small">
                            <?php foreach (['card_holder', 'card_number', 'card_expiry', 'card_cvv'] as $k): ?>
                                <?php if (isset($errors[$k])): ?><div><?= esc($errors[$k]) ?></div><?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" novalidate>
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add_card">

                        <div class="mb-2">
                            <label class="form-label small">Cardholder Name</label>
                            <input type="text" name="card_holder" class="form-control form-control-sm"
                                   value="<?= esc(($_POST['action'] ?? '') === 'add_card' ? ($_POST['card_holder'] ?? '') : '') ?>"
                                   placeholder="John Doe" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Card Number</label>
                            <input type="text" name="card_number" class="form-control form-control-sm"
                                   placeholder="4242 4242 4242 4242" maxlength="19" required>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-4">
                                <label class="form-label small">Month</label>
                                <select name="expiry_month" class="form-select form-select-sm" required>
                                    <option value="">MM</option>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>"><?= str_pad($m, 2, '0', STR_PAD_LEFT) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label small">Year</label>
                                <select name="expiry_year" class="form-select form-select-sm" required>
                                    <option value="">YY</option>
                                    <?php for ($y = (int)date('Y'); $y <= (int)date('Y') + 10; $y++): ?>
                                        <option value="<?= $y ?>"><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label small">CVV</label>
                                <input type="text" name="cvv" class="form-control form-control-sm"
                                       placeholder="123" maxlength="4" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Confirm Password</label>
                            <input type="password" name="auth_password" class="form-control form-control-sm"
                                   placeholder="Enter your account password" required>
                        </div>
                        <button class="btn btn-sm btn-primary">
                            <i class="bi bi-lock me-1"></i>Save Card
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Change Password ────────────────────────────────── -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title"><i class="bi bi-key me-2"></i>Change Password</h5>
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
                    <div class="form-text">Min 8 chars, one uppercase letter, one number.</div>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>