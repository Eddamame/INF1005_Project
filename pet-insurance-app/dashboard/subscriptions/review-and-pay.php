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
    $stmt = $db->prepare('
        SELECT p.* FROM pets p
        WHERE p.user_id = :uid
        AND p.id NOT IN (
            SELECT s.pet_id FROM subscriptions s
            WHERE s.user_id = :uid2 AND s.status = :status
        )
        ORDER BY p.created_at DESC
    ');
    $stmt->execute([':uid' => $userId, ':uid2' => $userId, ':status' => 'active']);
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

$breadcrumb_items = [
    ['My Pets', base_path() . '/dashboard/my-pets.php'],
    ['Review & Pay', ''],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review &amp; Pay — PawShield Pet Insurance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(generateCsrfToken()); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body>

<a class="skip-link" href="#main-content">Skip to main content</a>

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<main id="main-content">
    <!-- Hero -->
    <section class="hero-section hero-section--short" aria-labelledby="review-hero-heading">
        <div class="hero-bg-shapes" aria-hidden="true">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
        </div>
        <div class="container hero-content">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <span class="hero-badge">
                        <i class="bi bi-credit-card me-1" aria-hidden="true"></i>Checkout
                    </span>
                    <h1 id="review-hero-heading" class="hero-heading">Review &amp; Pay</h1>
                    <p class="hero-subtitle mb-0 text-center mx-auto" style="max-width: 32rem;">
                        Choose your pet and plan, then we’ll send you to secure Stripe checkout.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-6">
        <div class="container">
            <?php require_once __DIR__ . '/../../includes/breadcrumb.php'; ?>
            <div class="row justify-content-center mt-3">
                <div class="col-xl-8 col-lg-9">
                    <form id="review-pay-form" class="card border-0 shadow-sm review-pay-card" data-checkout-api="<?= esc(base_path() . '/api/create-checkout-session.php') ?>">
                        <div class="card-body p-4 p-lg-5">
                            <?= csrfField() ?>

                            <div class="review-pay-step mb-4">
                                <span class="review-pay-step-num" aria-hidden="true">1</span>
                                <div>
                                    <h2 class="h5 mb-2" style="color: var(--ps-navy);">Choose pet</h2>
                                    <?php if (empty($pets)): ?>
                                        <p class="text-muted mb-0">
                                            You don't have any pets yet. <a href="<?= base_path() ?>/dashboard/my-pets.php">Add a pet first</a>.
                                        </p>
                                    <?php else: ?>
                                        <select name="pet_id" id="pet_id" class="form-select form-select-lg" required>
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
                            </div>

                            <div class="review-pay-step mb-4">
                                <span class="review-pay-step-num" aria-hidden="true">2</span>
                                <div>
                                    <h2 class="h5 mb-3" style="color: var(--ps-navy);">Choose plan</h2>
                                    <?php if (empty($plans)): ?>
                                        <p class="text-muted mb-0">No active plans configured.</p>
                                    <?php else: ?>
                                        <div class="review-pay-plans">
                                            <?php foreach ($plans as $plan): ?>
                                                <?php $isSelected = $selectedPlan && (int)$selectedPlan['id'] === (int)$plan['id']; ?>
                                                <label class="review-pay-plan-option <?= $isSelected ? 'review-pay-plan-option--selected' : '' ?>">
                                                    <input type="radio" name="plan_id" value="<?= (int)$plan['id'] ?>"
                                                        id="plan_<?= (int)$plan['id'] ?>"
                                                        class="form-check-input" <?= $isSelected ? 'checked' : '' ?> required>
                                                    <span class="review-pay-plan-text">
                                                        <strong><?= esc($plan['name']) ?></strong>
                                                        — $<?= number_format((float)$plan['monthly_premium'], 2) ?>/mo,
                                                        up to $<?= number_format((float)$plan['annual_limit']) ?>
                                                        with <?= number_format((float)$plan['coverage_pct']) ?>% coverage
                                                        and $<?= number_format((float)$plan['deductible']) ?> deductible
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="review-pay-step mb-4">
                                <span class="review-pay-step-num" aria-hidden="true">3</span>
                                <div>
                                    <h2 class="h5 mb-2" style="color: var(--ps-navy);">Summary</h2>
                                    <p class="small text-muted mb-2">You can change your selection above before paying. Stripe will handle your card securely.</p>
                                    <div id="review-summary" class="small p-3 rounded" style="background: var(--ps-off-white); color: var(--ps-navy);"></div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-3">
                                <a href="<?= base_path() ?>/dashboard/my-pets.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Back to My Pets
                                </a>
                                <button type="submit" class="btn btn-primary px-4" id="confirm-pay-btn">
                                    Confirm and Pay <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
                                </button>
                            </div>

                            <div class="mt-3 small text-danger" id="review-error" style="display:none;"></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_path() ?>/assets/js/review-pay.js"></script>
</body>
</html>

