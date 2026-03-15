<?php
/**
 * Pricing — Compare insurance plans (Bootstrap comparison table, data from DB).
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/csrf.php';

$plans = [];
if ($db instanceof PDO) {
    $stmt = $db->query("SELECT * FROM insurance_plans WHERE is_active = 1 ORDER BY monthly_premium ASC");
    $plans = $stmt->fetchAll();
}
if (empty($plans)) {
    $plans = [
        ['name' => 'Basic',    'monthly_premium' => '29.99', 'annual_limit' => '5000.00',  'deductible' => '100.00', 'coverage_pct' => '70.00', 'description' => 'Essential coverage for routine vet visits and minor treatments.'],
        ['name' => 'Premium',  'monthly_premium' => '49.99', 'annual_limit' => '15000.00', 'deductible' => '50.00',  'coverage_pct' => '80.00', 'description' => 'Comprehensive coverage including surgeries, diagnostics, and specialist referrals.'],
        ['name' => 'Ultimate', 'monthly_premium' => '79.99', 'annual_limit' => '50000.00', 'deductible' => '0.00',   'coverage_pct' => '90.00', 'description' => 'Full coverage with zero deductible, from routine checkups to emergency surgeries.'],
    ];
}

$planFeaturedIndex = min(1, count($plans) - 1); // highlight middle plan (e.g. Premium)
$planIcons = ['bi-shield', 'bi-shield-fill-check', 'bi-shield-fill-plus']; // same as home page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Compare PawShield pet insurance plans — pricing, limits, deductibles, and coverage. Choose the right plan for your pet.">
    <title>Pricing — PawShield Pet Insurance</title>
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

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content">

    <!-- Page hero -->
    <section class="hero-section hero-section--short" aria-labelledby="pricing-hero-heading">
        <div class="hero-bg-shapes" aria-hidden="true">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
        </div>
        <div class="container hero-content">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <span class="hero-badge">
                        <i class="bi bi-tag me-1" aria-hidden="true"></i>Plans & Pricing
                    </span>
                    <h1 id="pricing-hero-heading" class="hero-heading">
                        Compare Plans
                    </h1>
                    <p class="hero-subtitle mb-0 text-center mx-auto" style="max-width: 32rem;">
                        See how our plans stack up. All include instant AI claim quotes and our verified vet network.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Comparison table -->
    <section class="py-6" aria-labelledby="comparison-heading">
        <div class="container">
            <h2 id="comparison-heading" class="visually-hidden">Plan comparison</h2>
            <div class="pricing-table-wrapper">
                <div class="table-responsive">
                    <table class="table align-middle pricing-table">
                        <caption class="visually-hidden">Plan comparison: features, monthly premium, annual limit, deductible, and coverage for each plan.</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="pricing-table-feature">Feature</th>
                                <?php foreach ($plans as $i => $plan):
                                    $iconClass = $planIcons[$i] ?? 'bi-shield';
                                ?>
                                <th scope="col" class="text-center pricing-table-plan-header <?= $i === $planFeaturedIndex ? 'pricing-table-popular' : '' ?>">
                                    <div class="pricing-table-plan-icon" aria-hidden="true">
                                        <i class="bi <?= $iconClass ?>"></i>
                                    </div>
                                    <span class="d-block fw-bold pricing-table-plan-name"><?= htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($i === $planFeaturedIndex): ?>
                                    <span class="badge rounded-pill mt-2 pricing-table-badge">Most Popular</span>
                                    <?php endif; ?>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="pricing-table-row-premium">
                                <th scope="row" class="pricing-table-feature">Monthly premium</th>
                                <?php foreach ($plans as $i => $plan): ?>
                                <td class="text-center <?= $i === $planFeaturedIndex ? 'pricing-table-cell-popular' : '' ?>">
                                    <span class="pricing-table-price">$<?= number_format((float)$plan['monthly_premium'], 2) ?></span>
                                    <span class="text-muted small">/mo</span>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                        <tr>
                            <th scope="row" class="pricing-table-feature">Annual limit</th>
                            <?php foreach ($plans as $i => $plan): ?>
                            <td class="text-center <?= $i === $planFeaturedIndex ? 'pricing-table-cell-popular' : '' ?>">$<?= number_format((float)$plan['annual_limit']) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th scope="row" class="pricing-table-feature">Deductible</th>
                            <?php foreach ($plans as $i => $plan): ?>
                            <td class="text-center <?= $i === $planFeaturedIndex ? 'pricing-table-cell-popular' : '' ?>">$<?= number_format((float)$plan['deductible']) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th scope="row" class="pricing-table-feature">Coverage</th>
                            <?php foreach ($plans as $i => $plan): ?>
                            <td class="text-center <?= $i === $planFeaturedIndex ? 'pricing-table-cell-popular' : '' ?>"><?= number_format((float)$plan['coverage_pct']) ?>%</td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th scope="row" class="pricing-table-feature">Summary</th>
                            <?php foreach ($plans as $i => $plan): ?>
                            <td class="text-muted small <?= $i === $planFeaturedIndex ? 'pricing-table-cell-popular' : '' ?>"><?= htmlspecialchars($plan['description'], ENT_QUOTES, 'UTF-8') ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th scope="row" class="pricing-table-feature">Includes</th>
                            <?php foreach ($plans as $i => $plan): ?>
                            <td class="<?= $i === $planFeaturedIndex ? 'pricing-table-cell-popular' : '' ?>">
                                <ul class="list-unstyled small mb-0 pricing-table-includes">
                                    <li><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>Instant AI claim quotes</li>
                                    <li><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>Verified vet network</li>
                                    <li><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>Cancel anytime</li>
                                </ul>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="pricing-table-actions">
                            <th scope="row" class="pricing-table-feature"></th>
                            <?php foreach ($plans as $i => $plan): ?>
                            <td class="text-center <?= $i === $planFeaturedIndex ? 'pricing-table-cell-popular' : '' ?>">
                                <?php if (empty($_SESSION['user_id'])): ?>
                                    <a href="<?= base_path() ?>/auth/register.php" class="btn <?= $i === $planFeaturedIndex ? 'btn-primary' : 'btn-outline-primary' ?> pricing-table-cta">
                                        Choose <?= htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= base_path() ?>/dashboard/subscriptions/review-and-pay.php?plan_id=<?= (int)$plan['id'] ?>" class="btn <?= $i === $planFeaturedIndex ? 'btn-primary' : 'btn-outline-primary' ?> pricing-table-cta">
                                        Choose <?= htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta-section py-6" aria-labelledby="pricing-cta-heading">
        <div class="container text-center">
            <h2 id="pricing-cta-heading" class="cta-title">Ready to get started?</h2>
            <p class="cta-subtitle">Create an account and pick the plan that fits your pet.</p>
            <a href="<?= base_path() ?>/auth/register.php" class="btn btn-cta btn-lg">
                Get Started For Free <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
            </a>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
