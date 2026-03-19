<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../auth/middleware.php';

requireLogin();

/** @var PDO $db */

$userId = (int) ($_SESSION['user_id'] ?? 0);
$errors = [];

$selectedPlanId = inputInt('plan_id', 'GET');
$selectedPetId  = inputInt('pet_id', 'GET');

// Load user's pets WITHOUT active/pending subscriptions
// Load user's pets WITHOUT active subscriptions (or with subscriptions expiring within 30 days)
// Load user's pets that are eligible for purchase/renewal
$pets = [];
if ($db instanceof PDO) {
    $stmt = $db->prepare('
        SELECT DISTINCT p.* FROM pets p
        LEFT JOIN subscriptions s ON s.pet_id = p.id AND s.user_id = p.user_id AND s.status = ?
        WHERE p.user_id = ?
        AND (
            s.id IS NULL
            OR (s.end_date IS NOT NULL AND s.end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
        )
        ORDER BY p.created_at DESC
    ');
    $stmt->execute(['active', $userId]);
    $pets = $stmt->fetchAll();
}

// Load active plans
$plans = [];
if ($db instanceof PDO) {
    $stmt = $db->query('SELECT * FROM insurance_plans WHERE is_active = 1 ORDER BY monthly_premium ASC');
    $plans = $stmt->fetchAll();
}

function findById(array $list, int $id): ?array {
    foreach ($list as $item) {
        if ((int) $item['id'] === $id) return $item;
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
    <title>Review &amp; Pay — PawShield</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(generateCsrfToken()); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body>

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<main id="main-content">
<div class="container py-5" style="max-width: 960px;">

    <a href="<?= base_path() ?>/dashboard/my-pets.php" class="btn btn-outline-secondary btn-sm mb-4">
        <i class="bi bi-arrow-left me-1"></i> Back to My Pets
    </a>

    <h1 class="mb-2"><i class="bi bi-shield-check me-2"></i>Review &amp; Pay</h1>
    <p class="text-muted mb-4">
        Choose your pet, pick a plan and duration, then proceed to secure Stripe checkout.
    </p>

    <form id="review-pay-form" data-checkout-api="<?= esc(base_path() . '/api/create-checkout-session.php') ?>">
        <?= csrfField() ?>

        <!-- Step 1: Choose Pet -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-heart-pulse me-2"></i>1. Choose Pet</h5>
                <?php if (empty($pets)): ?>
                    <p class="text-muted">
                        All your pets already have coverage, or you haven't added any pets yet.
                        <a href="<?= base_path() ?>/dashboard/my-pets.php">Manage pets</a>.
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
        </div>

        <!-- Step 2: Choose Plan (cards like purchase-coverage) -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-shield me-2"></i>2. Choose Plan</h5>
                <?php if (empty($plans)): ?>
                    <p class="text-muted">No active plans configured.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php
                        $planIcons = ['bi-shield', 'bi-shield-fill-check', 'bi-shield-fill-plus'];
                        foreach ($plans as $i => $plan):
                            $isSelected = $selectedPlan && (int)$selectedPlan['id'] === (int)$plan['id'];
                        ?>
                        <div class="col-md-4">
                            <div class="card h-100 plan-select-card <?= $isSelected ? 'border-primary shadow' : '' ?>"
                                 style="cursor: pointer;"
                                 onclick="selectPlan(<?= (int)$plan['id'] ?>, this)">
                                <div class="card-body text-center">
                                    <i class="bi <?= $planIcons[$i] ?? 'bi-shield' ?> fs-2 text-primary mb-2 d-block"></i>
                                    <h5 class="card-title"><?= esc($plan['name']) ?></h5>
                                    <div class="mb-2">
                                        <span class="fs-3 fw-bold">$<?= number_format((float)$plan['monthly_premium'], 2) ?></span>
                                        <span class="text-muted">/mo</span>
                                    </div>
                                    <ul class="list-unstyled small text-start">
                                        <li><i class="bi bi-check-circle-fill text-success me-1"></i>
                                            Up to $<?= number_format((float)$plan['annual_limit']) ?> annual limit</li>
                                        <li><i class="bi bi-check-circle-fill text-success me-1"></i>
                                            <?= number_format((float)$plan['coverage_pct']) ?>% coverage</li>
                                        <li><i class="bi bi-check-circle-fill text-success me-1"></i>
                                            $<?= number_format((float)$plan['deductible']) ?> deductible</li>
                                    </ul>
                                    <p class="text-muted small mt-2 mb-0"><?= esc($plan['description']) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="plan_id" id="selectedPlanId"
                           value="<?= $selectedPlan ? (int)$selectedPlan['id'] : '' ?>">
                <?php endif; ?>
            </div>
        </div>

       <!-- Step 3: Choose Duration -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-calendar-event me-2"></i>3. Choose Duration</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-check border rounded p-3">
                            <input class="form-check-input" type="radio" name="duration" id="dur1" value="1">
                            <label class="form-check-label w-100" for="dur1">
                                <strong>1 Month</strong>
                                <span class="text-muted d-block small">No discount</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check border rounded p-3">
                            <input class="form-check-input" type="radio" name="duration" id="dur6" value="6">
                            <label class="form-check-label w-100" for="dur6">
                                <strong>6 Months</strong>
                                <span class="text-success d-block small fw-semibold">5% discount</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check border rounded p-3">
                            <input class="form-check-input" type="radio" name="duration" id="dur12" value="12" checked>
                            <label class="form-check-label w-100" for="dur12">
                                <strong>12 Months</strong>
                                <span class="text-success d-block small fw-semibold">10% discount</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: Summary -->
        <div class="card border-primary mb-4" id="summaryCard" style="display:none;">
            <div class="card-body">
                <h5><i class="bi bi-receipt me-2"></i>4. Order Summary</h5>
                <table class="table table-borderless mb-3">
                    <tr><td class="text-muted">Pet</td><td class="fw-semibold" id="summaryPet">—</td></tr>
                    <tr><td class="text-muted">Plan</td><td class="fw-semibold" id="summaryPlan">—</td></tr>
                    <tr><td class="text-muted">Duration</td><td class="fw-semibold" id="summaryDuration">12 months</td></tr>
                    <tr><td class="text-muted">Original monthly price</td><td class="fw-semibold" id="summaryOriginal">—</td></tr>
                    <tr id="discountRow" style="display:none;">
                        <td class="text-muted">Discount</td>
                        <td class="fw-semibold text-success" id="summaryDiscount">—</td>
                    </tr>
                    <tr><td class="text-muted">Discounted monthly price</td><td class="fw-semibold" id="summaryMonthly">—</td></tr>
                    <tr class="border-top">
                        <td class="text-muted fw-bold">Total for full duration</td>
                        <td class="fw-bold fs-5 text-primary" id="summaryTotal">—</td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Charged today (first month)</td>
                        <td class="fw-semibold" id="summaryFirstMonth">—</td>
                    </tr>
                </table>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100" id="confirm-pay-btn" disabled>
            <i class="bi bi-lock me-2"></i>Confirm and Pay
        </button>

        <div class="mt-3 small text-danger" id="review-error" style="display:none;"></div>
    </form>

</div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
    const plans = <?= json_encode(array_map(function($p) {
        return [
            'id' => (int)$p['id'],
            'name' => $p['name'],
            'monthly_premium' => (float)$p['monthly_premium'],
        ];
    }, $plans)) ?>;

    const discounts = { 1: 0, 6: 0.05, 12: 0.10 };

    let selectedPlanId = document.getElementById('selectedPlanId')
        ? (document.getElementById('selectedPlanId').value ? parseInt(document.getElementById('selectedPlanId').value) : null)
        : null;

    function selectPlan(planId, cardEl) {
        selectedPlanId = planId;
        if (document.getElementById('selectedPlanId')) {
            document.getElementById('selectedPlanId').value = planId;
        }
        document.querySelectorAll('.plan-select-card').forEach(c => {
            c.classList.remove('border-primary', 'shadow');
        });
        cardEl.classList.add('border-primary', 'shadow');
        updateSummary();
    }

    function getSelectedDuration() {
        const checked = document.querySelector('input[name="duration"]:checked');
        return checked ? parseInt(checked.value) : 12;
    }

    function getSelectedPetName() {
        const sel = document.getElementById('pet_id');
        if (!sel || !sel.value) return null;
        return sel.options[sel.selectedIndex].textContent.trim();
    }

    function updateSummary() {
        const plan = plans.find(p => p.id === selectedPlanId);
        const duration = getSelectedDuration();
        const petName = getSelectedPetName();
        const card = document.getElementById('summaryCard');
        const btn = document.getElementById('confirm-pay-btn');

        if (!plan || !petName) {
            card.style.display = 'none';
            btn.disabled = true;
            return;
        }

        card.style.display = 'block';
        btn.disabled = false;

        const discount = discounts[duration] || 0;
        const originalMonthly = plan.monthly_premium;
        const discountedMonthly = originalMonthly * (1 - discount);
        const total = discountedMonthly * duration;

        document.getElementById('summaryPet').textContent = petName;
        document.getElementById('summaryPlan').textContent = plan.name;
        document.getElementById('summaryDuration').textContent = duration + (duration === 1 ? ' month' : ' months');
        document.getElementById('summaryOriginal').textContent = '$' + originalMonthly.toFixed(2) + '/mo';

        const discountRow = document.getElementById('discountRow');
        if (discount > 0) {
            discountRow.style.display = '';
            document.getElementById('summaryDiscount').textContent = '-' + (discount * 100) + '% ($' + (originalMonthly * discount).toFixed(2) + '/mo saved)';
        } else {
            discountRow.style.display = 'none';
        }

        document.getElementById('summaryMonthly').textContent = '$' + discountedMonthly.toFixed(2) + '/mo';
        document.getElementById('summaryTotal').textContent = '$' + total.toFixed(2);
        document.getElementById('summaryFirstMonth').textContent = '$' + discountedMonthly.toFixed(2);
    }

    document.querySelectorAll('input[name="duration"]').forEach(r => r.addEventListener('change', updateSummary));
    const petSelect = document.getElementById('pet_id');
    if (petSelect) petSelect.addEventListener('change', updateSummary);
    if (selectedPlanId) updateSummary();
</script>
<script src="<?= base_path() ?>/assets/js/review-pay.js"></script>