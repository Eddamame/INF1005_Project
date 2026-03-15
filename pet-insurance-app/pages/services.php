<?php
/**
 * Services — What PawShield offers + insurance plan descriptions.
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

$planIcons = ['bi-shield', 'bi-shield-fill-check', 'bi-shield-fill-plus'];
$planFeatured = [false, true, false];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="PawShield services — AI-powered pet insurance claims and plan options. Compare coverage, limits, and pricing for your pet.">
    <title>Services — PawShield Pet Insurance</title>
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
    <section class="hero-section hero-section--short" aria-labelledby="services-hero-heading">
        <div class="hero-bg-shapes" aria-hidden="true">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
        </div>
        <div class="container hero-content">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <span class="hero-badge">
                        <i class="bi bi-shield-check me-1" aria-hidden="true"></i>What We Offer
                    </span>
                    <h1 id="services-hero-heading" class="hero-heading">
                        Our Services
                    </h1>
                    <p class="hero-subtitle mb-0 text-center mx-auto" style="max-width: 32rem;">
                        Coverage and support for every stage of your pet’s care — from routine checkups to emergencies.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- What we cover -->
    <section class="py-6" style="background: var(--ps-off-white);" aria-labelledby="cover-heading">
        <div class="container">
            <div class="section-header text-center mb-5">
                <span class="section-label">What We Cover</span>
                <h2 id="cover-heading" class="section-title">Care that fits your pet’s needs</h2>
                <p class="section-subtitle">
                    Our plans help with the costs of vet care so you can focus on getting your pet the best treatment.
                </p>
            </div>
            <div class="row g-4 services-cover-cards">
                <div class="col-sm-6 col-lg-3">
                    <div class="cover-card d-flex gap-3 p-3 rounded-3 h-100" style="background: var(--ps-white); border: 1px solid var(--ps-gray-200);">
                        <div class="flex-shrink-0 rounded-2 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:var(--ps-teal-light);">
                            <i class="bi bi-heart-pulse" style="color:var(--ps-teal); font-size:1.25rem;"></i>
                        </div>
                        <div>
                            <h3 class="h6 fw-bold mb-1" style="color:var(--ps-navy);">Routine care</h3>
                            <p class="small text-muted mb-0">Checkups, vaccinations, and preventive care.</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="cover-card cover-card--2 d-flex gap-3 p-3 rounded-3 h-100" style="background: var(--ps-white); border: 1px solid var(--ps-gray-200);">
                        <div class="flex-shrink-0 rounded-2 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:var(--ps-teal-light);">
                            <i class="bi bi-bandaid" style="color:var(--ps-teal); font-size:1.25rem;"></i>
                        </div>
                        <div>
                            <h3 class="h6 fw-bold mb-1" style="color:var(--ps-navy);">Accidents & illness</h3>
                            <p class="small text-muted mb-0">Unexpected injuries, infections, and treatment.</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="cover-card cover-card--3 d-flex gap-3 p-3 rounded-3 h-100" style="background: var(--ps-white); border: 1px solid var(--ps-gray-200);">
                        <div class="flex-shrink-0 rounded-2 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:var(--ps-teal-light);">
                            <i class="bi bi-hospital" style="color:var(--ps-teal); font-size:1.25rem;"></i>
                        </div>
                        <div>
                            <h3 class="h6 fw-bold mb-1" style="color:var(--ps-navy);">Surgeries & procedures</h3>
                            <p class="small text-muted mb-0">From dental to emergency surgery.</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="cover-card cover-card--4 d-flex gap-3 p-3 rounded-3 h-100" style="background: var(--ps-white); border: 1px solid var(--ps-gray-200);">
                        <div class="flex-shrink-0 rounded-2 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:var(--ps-teal-light);">
                            <i class="bi bi-file-earmark-medical" style="color:var(--ps-teal); font-size:1.25rem;"></i>
                        </div>
                        <div>
                            <h3 class="h6 fw-bold mb-1" style="color:var(--ps-navy);">Diagnostics & labs</h3>
                            <p class="small text-muted mb-0">Tests, X-rays, and specialist referrals.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why choose PawShield (benefits, not process) -->
    <section class="py-6" aria-labelledby="why-heading">
        <div class="container">
            <div class="section-header text-center mb-5">
                <span class="section-label">Why PawShield</span>
                <h2 id="why-heading" class="section-title">Same great service on every plan</h2>
            </div>
            <div class="row g-3 justify-content-center" style="max-width: 720px; margin: 0 auto;">
                <div class="col-12 d-flex align-items-center gap-3 p-3 rounded-3" style="background: var(--ps-off-white); border: 1px solid var(--ps-gray-200);">
                    <i class="bi bi-lightning-charge-fill flex-shrink-0" style="font-size:1.5rem; color:var(--ps-teal);"></i>
                    <div>
                        <strong style="color:var(--ps-navy);">Instant claim quotes</strong>
                        <span class="text-muted"> — Get a reimbursement estimate in seconds, not days.</span>
                    </div>
                </div>
                <div class="col-12 d-flex align-items-center gap-3 p-3 rounded-3" style="background: var(--ps-off-white); border: 1px solid var(--ps-gray-200);">
                    <i class="bi bi-shield-check flex-shrink-0" style="font-size:1.5rem; color:var(--ps-teal);"></i>
                    <div>
                        <strong style="color:var(--ps-navy);">No hidden fees</strong>
                        <span class="text-muted"> — Clear pricing and coverage. No surprise exclusions.</span>
                    </div>
                </div>
                <div class="col-12 d-flex align-items-center gap-3 p-3 rounded-3" style="background: var(--ps-off-white); border: 1px solid var(--ps-gray-200);">
                    <i class="bi bi-building flex-shrink-0" style="font-size:1.5rem; color:var(--ps-teal);"></i>
                    <div>
                        <strong style="color:var(--ps-navy);">Verified vet network</strong>
                        <span class="text-muted"> — We cross-check claims with trusted clinics.</span>
                    </div>
                </div>
                <div class="col-12 d-flex align-items-center gap-3 p-3 rounded-3" style="background: var(--ps-off-white); border: 1px solid var(--ps-gray-200);">
                    <i class="bi bi-arrow-repeat flex-shrink-0" style="font-size:1.5rem; color:var(--ps-teal);"></i>
                    <div>
                        <strong style="color:var(--ps-navy);">Cancel anytime</strong>
                        <span class="text-muted"> — No long-term lock-in. You’re in control.</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Insurance plan descriptions -->
    <section class="py-6" aria-labelledby="plans-heading">
        <div class="container">
            <div class="section-header text-center mb-5">
                <span class="section-label">Coverage Options</span>
                <h2 id="plans-heading" class="section-title">Insurance plan descriptions</h2>
                <p class="section-subtitle">
                    Pick the plan that matches your budget and your pet’s needs. Each includes our AI-powered claims process.
                </p>
            </div>
            <div class="row g-4 justify-content-center">
                <?php foreach ($plans as $i => $plan):
                    $featured = $planFeatured[$i] ?? false;
                    $icon = $planIcons[$i] ?? 'bi-shield';
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="plan-card <?= $featured ? 'plan-card--featured' : '' ?>" role="article">
                        <?php if ($featured): ?><div class="plan-badge">Most Popular</div><?php endif; ?>
                        <div class="plan-icon" aria-hidden="true">
                            <i class="bi <?= $icon ?>"></i>
                        </div>
                        <h3 class="plan-name"><?= htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <div class="plan-price">
                            <span class="price-dollar">$</span>
                            <span class="price-amount"><?= number_format((float)$plan['monthly_premium'], 2) ?></span>
                            <span class="price-period">/mo</span>
                        </div>
                        <p class="plan-desc"><?= htmlspecialchars($plan['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        <ul class="plan-features">
                            <li><i class="bi bi-check-circle-fill text-success"></i> Up to $<?= number_format((float)$plan['annual_limit']) ?> annual limit</li>
                            <li><i class="bi bi-check-circle-fill text-success"></i> <?= number_format((float)$plan['coverage_pct']) ?>% coverage</li>
                            <li><i class="bi bi-check-circle-fill text-success"></i> $<?= number_format((float)$plan['deductible']) ?> deductible</li>
                            <li><i class="bi bi-check-circle-fill text-success"></i> Instant AI claim quotes</li>
                        </ul>
                        <a href="<?= base_path() ?>/auth/register.php"
                           class="btn <?= $featured ? 'btn-primary' : 'btn-outline-primary' ?> w-100 mt-3">
                            Choose <?= htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="<?= base_path() ?>/pages/pricing.php" class="link-muted">Compare all features in detail →</a>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta-section py-6" aria-labelledby="services-cta-heading">
        <div class="container text-center">
            <h2 id="services-cta-heading" class="cta-title">Find the right plan for your pet</h2>
            <p class="cta-subtitle">Create an account, add your pet, and submit your first claim in minutes.</p>
            <a href="<?= base_path() ?>/auth/register.php" class="btn btn-cta btn-lg">
                Get Started For Free <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
            </a>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
