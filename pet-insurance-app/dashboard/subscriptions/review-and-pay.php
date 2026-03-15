<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../auth/middleware.php';

requireLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$errors = [];

$selectedPlanId = inputInt('plan_id', 'GET');
$selectedPetId  = inputInt('pet_id', 'GET');

// Load user's pets
$pets = [];
if ($db instanceof PDO) {
    $stmt = $db->prepare('SELECT * FROM pets WHERE user_id = :uid ORDER BY created_at DESC');
    $stmt->execute([':uid' => $userId]);
    $pets = $stmt->fetchAll();
}

// Load active plans
$plans = [];
if ($db instanceof PDO) {
    $stmt = $db->query('SELECT * FROM insurance_plans WHERE is_active = 1 ORDER BY monthly_premium ASC');
    $plans = $stmt->fetchAll();
}

// Helper lookups
function findById(array $list, int $id): ?array {
    foreach ($list as $item) {
        if ((int) $item['id'] === $id) {
            return $item;
        }
    }
    return null;
}

$selectedPlan = $selectedPlanId ? findById($plans, $selectedPlanId) : null;
$selectedPet  = $selectedPetId  ? findById($pets, $selectedPetId)   : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review &amp; Pay — Pet Insurance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(generateCsrfToken()); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-9">
            <h1 class="mb-4">Review &amp; Pay</h1>

            <p class="text-muted">
                Choose which pet to cover and confirm the plan details before we redirect you to a secure Stripe Checkout page.
            </p>

            <form id="review-pay-form" class="card mt-4" data-checkout-api="<?= esc(base_path() . '/api/create-checkout-session.php') ?>">
                <div class="card-body">
                    <?= csrfField() ?>

                    <div class="mb-4">
                        <h5 class="mb-2">1. Choose pet</h5>
                        <?php if (empty($pets)): ?>
                            <p class="text-muted">
                                You don't have any pets yet. <a href="<?= base_path() ?>/dashboard/my-pets.php">Add a pet first</a>.
                            </p>
                        <?php else: ?>
                            <select name="pet_id" id="pet_id" class="form-select" required>
                                <option value="">— Select a pet —</option>
                                <?php foreach ($pets as $pet): ?>
                                    <option value="<?= (int)$pet['id'] ?>"
                                        <?= $selectedPet && (int)$selectedPet['id'] === (int)$pet['id'] ? 'selected' : '' ?>>
                                        <?= esc($pet['name']) ?> (<?= esc(ucfirst($pet['species'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-2">2. Choose plan</h5>
                        <?php if (empty($plans)): ?>
                            <p class="text-muted">No active plans configured.</p>
                        <?php else: ?>
                            <?php foreach ($plans as $plan): ?>
                                <?php $isSelected = $selectedPlan && (int)$selectedPlan['id'] === (int)$plan['id']; ?>
                                <div class="form-check mb-2">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        name="plan_id"
                                        id="plan_<?= (int)$plan['id'] ?>"
                                        value="<?= (int)$plan['id'] ?>"
                                        <?= $isSelected ? 'checked' : '' ?>
                                        required
                                    >
                                    <label class="form-check-label" for="plan_<?= (int)$plan['id'] ?>">
                                        <strong><?= esc($plan['name']) ?></strong>
                                        — $<?= number_format((float)$plan['monthly_premium'], 2) ?>/mo,
                                        up to $<?= number_format((float)$plan['annual_limit']) ?>
                                        with <?= number_format((float)$plan['coverage_pct']) ?>% coverage
                                        and $<?= number_format((float)$plan['deductible']) ?> deductible
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <h5 class="mb-2">3. Summary</h5>
                        <p class="small text-muted mb-1">You can still change your plan on this page before paying. Once you proceed to checkout, Stripe will handle your card securely.</p>
                        <div id="review-summary" class="small"></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="<?= base_path() ?>/dashboard/my-pets.php" class="btn btn-link text-muted">
                            &larr; Back to My Pets
                        </a>
                        <button type="submit" class="btn btn-primary" id="confirm-pay-btn">
                            Confirm and Pay
                        </button>
                    </div>

                    <div class="mt-3 small text-danger" id="review-error" style="display:none;"></div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_path() ?>/assets/js/review-pay.js"></script>
</body>
</html>

