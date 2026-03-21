<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . '/auth/login.php');
    exit;
}

/** @var PDO $db */
$userId = (int) $_SESSION['user_id'];
$errors = [];

// Check if user already has a payment method (skip if so)
$stmt = $db->prepare('SELECT id FROM payment_methods WHERE user_id = :uid LIMIT 1');
$stmt->execute([':uid' => $userId]);
if ($stmt->fetch()) {
    header('Location: ' . BASE_PATH . '/dashboard/my-pets.php');
    exit;
}

// Allow skip
if (isset($_GET['skip'])) {
    header('Location: ' . BASE_PATH . '/dashboard/my-pets.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $cardHolder  = inputString('card_holder');
    $cardNumber  = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
    $expiryMonth = inputInt('expiry_month');
    $expiryYear  = inputInt('expiry_year');
    $cvv         = inputString('cvv');

    if (!$cardHolder) $errors['card_holder'] = 'Cardholder name is required.';

    if (!$cardNumber || strlen($cardNumber) < 13 || strlen($cardNumber) > 19 || !ctype_digit($cardNumber)) {
        $errors['card_number'] = 'Please enter a valid card number.';
    }

    if (!$expiryMonth || $expiryMonth < 1 || $expiryMonth > 12) {
        $errors['expiry'] = 'Invalid expiry month.';
    }

    if (!$expiryYear || $expiryYear < (int)date('Y') || $expiryYear > (int)date('Y') + 10) {
        $errors['expiry'] = 'Invalid expiry year.';
    }

    if (!$cvv || strlen($cvv) < 3 || strlen($cvv) > 4) {
        $errors['cvv'] = 'Please enter a valid CVV.';
    }

    if (!$errors) {
        // Detect card brand from first digits
        $brand = 'Unknown';
        if (str_starts_with($cardNumber, '4')) {
            $brand = 'Visa';
        } elseif (str_starts_with($cardNumber, '5') || str_starts_with($cardNumber, '2')) {
            $brand = 'Mastercard';
        } elseif (str_starts_with($cardNumber, '3')) {
            $brand = 'Amex';
        }

        $lastFour = substr($cardNumber, -4);

        $stmt = $db->prepare('
            INSERT INTO payment_methods (user_id, card_holder, card_last_four, card_brand, expiry_month, expiry_year, is_default)
            VALUES (:uid, :holder, :last4, :brand, :month, :year, 1)
        ');
        $stmt->execute([
            ':uid'    => $userId,
            ':holder' => $cardHolder,
            ':last4'  => $lastFour,
            ':brand'  => $brand,
            ':month'  => $expiryMonth,
            ':year'   => $expiryYear,
        ]);

        $_SESSION['flash_message'] = 'Account setup complete! Welcome to PawShield.';
        header('Location: ' . BASE_PATH . '/dashboard/my-pets.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Payment — PawShield</title>
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
                <div class="col-md-7 col-lg-5">
                    <div class="p-4 p-lg-5 rounded-3 shadow-lg auth-form-card">
                        <div class="text-center mb-4">
                            <i class="bi bi-credit-card" style="font-size: 3rem; color: var(--ps-teal);"></i>
                            <h1 class="h2 mt-2" style="color: var(--ps-navy);">Add Payment Method</h1>
                            <p class="text-muted">Add a card to your account for future purchases.</p>
                            <p class="small text-muted">
                                <em>For testing, use Visa: <code>4242 4242 4242 4242</code></em>
                            </p>
                        </div>

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
                                <label class="form-label" for="pay-card-holder">Cardholder Name</label>
                                <input type="text" id="pay-card-holder" name="card_holder" class="form-control"
                                       value="<?= esc($_POST['card_holder'] ?? '') ?>"
                                       placeholder="John Doe" required autofocus autocomplete="cc-name">
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="pay-card-number">Card Number</label>
                                <input type="text" id="pay-card-number" name="card_number" class="form-control"
                                       value="<?= esc($_POST['card_number'] ?? '') ?>"
                                       placeholder="4242 4242 4242 4242" maxlength="19" required autocomplete="cc-number" inputmode="numeric">
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-4">
                                    <label class="form-label" for="pay-exp-month">Month</label>
                                    <select id="pay-exp-month" name="expiry_month" class="form-select" required autocomplete="cc-exp-month">
                                        <option value="">MM</option>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?= $m ?>" <?= (int)($_POST['expiry_month'] ?? 0) === $m ? 'selected' : '' ?>>
                                                <?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Year</label>
                                    <select name="expiry_year" class="form-select" required>
                                        <option value="">YY</option>
                                        <?php for ($y = (int)date('Y'); $y <= (int)date('Y') + 10; $y++): ?>
                                            <option value="<?= $y ?>" <?= (int)($_POST['expiry_year'] ?? 0) === $y ? 'selected' : '' ?>>
                                                <?= $y ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <label class="form-label" for="pay-cvv">CVV</label>
                                    <input type="text" id="pay-cvv" name="cvv" class="form-control"
                                           placeholder="123" maxlength="4" required autocomplete="cc-csc" inputmode="numeric">
                                </div>
                            </div>

                            <button class="btn btn-primary w-100" style="background: var(--ps-teal); border-color: var(--ps-teal);">
                                <i class="bi bi-lock me-2"></i>Save Card
                            </button>
                        </form>

                        <div class="text-center mt-3">
                            <a href="<?= base_path() ?>/auth/add-payment.php?skip=1" class="text-muted small">
                                Skip for now →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>