<?php
/**
 * Landing page.
 */
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

require_once __DIR__ . '/includes/sanitize.php';
require_once __DIR__ . '/includes/csrf.php';

$plans = [];
if ($db instanceof PDO) {
    $stmt  = $db->query("SELECT * FROM insurance_plans WHERE is_active = 1 ORDER BY monthly_premium ASC LIMIT 3");
    $plans = $stmt->fetchAll();
}
if (empty($plans)) {
    $plans = [
        ['name' => 'Basic',    'monthly_premium' => '29.99', 'annual_limit' => '5000.00',  'deductible' => '100.00', 'coverage_pct' => '70.00', 'description' => 'Essential coverage for routine vet visits and minor treatments.'],
        ['name' => 'Premium',  'monthly_premium' => '49.99', 'annual_limit' => '15000.00', 'deductible' => '50.00',  'coverage_pct' => '80.00', 'description' => 'Comprehensive coverage including surgeries, diagnostics, and specialist referrals.'],
        ['name' => 'Ultimate', 'monthly_premium' => '79.99', 'annual_limit' => '50000.00', 'deductible' => '0.00',   'coverage_pct' => '90.00', 'description' => 'Full coverage with zero deductible, from routine checkups to emergency surgeries.'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo esc(generateCsrfToken()); ?>">
    <title>PawShield — Pet Insurance Made Simple</title>
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

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main id="main-content">

    <section class="hero-section" aria-labelledby="hero-heading">
        <div class="hero-bg-shapes" aria-hidden="true">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
        </div>
        <div class="container hero-content">
            <div class="row align-items-center min-vh-75">
                <div class="col-lg-6 hero-text">
                    <span class="hero-badge">
                        <i class="bi bi-stars me-1" aria-hidden="true"></i>AI-Powered Claims
                    </span>
                    <h1 id="hero-heading" class="hero-heading">
                        Pet Insurance<br><em>Made Simple.</em>
                    </h1>
                    <p class="hero-subtitle">
                        Upload your vet receipt, let our AI scan it, and receive an instant
                        claim quote — no paperwork, no waiting, no stress.
                    </p>
                    <div class="hero-actions">
                        <a href="<?= base_path() ?>/auth/register.php" class="btn btn-hero-primary">
                            Protect My Pet
                            <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
                        </a>
                        <a href="<?= base_path() ?>/pages/how-it-works.php" class="btn btn-hero-secondary">
                            See How It Works
                        </a>
                    </div>
                    <div class="hero-trust mt-4">
                        <span><i class="bi bi-shield-check text-success me-1"></i>No hidden fees</span>
                        <span><i class="bi bi-clock text-success me-1"></i>Instant quotes</span>
                        <span><i class="bi bi-heart text-success me-1"></i>Cancel anytime</span>
                    </div>
                </div>
                <div class="col-lg-6 hero-visual d-none d-lg-flex" aria-hidden="true">
                    <div class="hero-card-float">
                        <div class="float-card fc-1">
                            <i class="bi bi-file-earmark-check"></i>
                            <span>Claim Approved</span>
                            <strong>$420.00</strong>
                        </div>
                        <div class="float-card fc-2">
                            <i class="bi bi-cpu"></i>
                            <span>AI Scanning…</span>
                            <div class="scan-bar"><div class="scan-fill"></div></div>
                        </div>
                        <div class="float-card fc-3">
                            <i class="bi bi-heart-pulse"></i>
                            <span>Max, Golden Retriever</span>
                            <strong>Premium Plan</strong>
                        </div>
                        <div class="hero-paw-large">🐾</div>
                    </div>
                </div>
            </div>
            <!-- Mobile / tablet: compact pet-themed strip (desktop keeps floating cards column) -->
            <div class="row d-lg-none justify-content-center pt-2 pb-1">
                <div class="col-12 text-center">
                    <p class="hero-mobile-strip-label text-white-50 small text-uppercase mb-2">Built for dogs, cats &amp; you</p>
                    <div class="hero-mobile-strip d-flex justify-content-center align-items-center flex-wrap gap-3 gap-md-4" role="presentation">
                        <span class="hero-mobile-strip__item"><i class="bi bi-heart-pulse" aria-hidden="true"></i><span>Healthy pets</span></span>
                        <span class="hero-mobile-strip__item"><i class="bi bi-shield-check" aria-hidden="true"></i><span>Clear coverage</span></span>
                        <span class="hero-mobile-strip__item"><i class="bi bi-cpu" aria-hidden="true"></i><span>AI claims</span></span>
                        <span class="hero-mobile-strip__item"><i class="bi bi-phone" aria-hidden="true"></i><span>Mobile-first</span></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="how-section py-6" id="how-it-works" aria-labelledby="how-heading">
        <div class="container">
            <div class="section-header text-center mb-5">
                <span class="section-label">Simple Process</span>
                <h2 id="how-heading" class="section-title">How PawShield Works</h2>
                <p class="section-subtitle">From vet visit to reimbursement in three easy steps.</p>
            </div>
            <div class="row g-4 justify-content-center">
                <?php
                $steps = [
                    ['icon' => 'bi-upload',    'num' => '01', 'title' => 'Upload Your Receipt', 'desc' => 'Take a photo or scan your vet invoice and upload it through our secure portal.'],
                    ['icon' => 'bi-cpu-fill',  'num' => '02', 'title' => 'AI Scans & Verifies', 'desc' => 'Our AI reads the receipt, identifies treatments, and cross-checks with verified vet clinics.'],
                    ['icon' => 'bi-cash-coin', 'num' => '03', 'title' => 'Get Your Quote',       'desc' => 'Receive a detailed reimbursement breakdown in seconds. Approve and funds are on their way.'],
                ];
                foreach ($steps as $step): ?>
                <div class="col-md-4">
                    <div class="step-card" role="article">
                        <div class="step-num" aria-hidden="true"><?php echo $step['num']; ?></div>
                        <div class="step-icon" aria-hidden="true">
                            <i class="bi <?php echo $step['icon']; ?>"></i>
                        </div>
                        <h3 class="step-title"><?php echo htmlspecialchars($step['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="step-desc"><?php echo htmlspecialchars($step['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="plans-section py-6" id="plans" aria-labelledby="plans-heading">
        <div class="container">
            <div class="section-header text-center mb-5">
                <span class="section-label">Coverage Options</span>
                <h2 id="plans-heading" class="section-title">Plans for Every Pet</h2>
                <p class="section-subtitle">Simple pricing. No surprises. Cancel anytime.</p>
            </div>
            <div class="row g-4 justify-content-center">
                <?php
                $planIcons    = ['bi-shield', 'bi-shield-fill-check', 'bi-shield-fill-plus'];
                $planFeatured = [false, true, false];
                foreach ($plans as $i => $plan):
                    $featured = $planFeatured[$i] ?? false;
                ?>
                <div class="col-md-4">
                    <div class="plan-card <?php echo $featured ? 'plan-card--featured' : ''; ?>" role="article">
                        <?php if ($featured): ?><div class="plan-badge">Most Popular</div><?php endif; ?>
                        <div class="plan-icon" aria-hidden="true">
                            <i class="bi <?php echo $planIcons[$i]; ?>"></i>
                        </div>
                        <h3 class="plan-name"><?php echo htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="plan-price">
                            <span class="price-dollar">$</span>
                            <span class="price-amount"><?php echo number_format((float)$plan['monthly_premium'], 2); ?></span>
                            <span class="price-period">/mo</span>
                        </div>
                        <p class="plan-desc"><?php echo htmlspecialchars($plan['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <ul class="plan-features">
                            <li><i class="bi bi-check-circle-fill text-success"></i> Up to $<?php echo number_format((float)$plan['annual_limit']); ?> annual limit</li>
                            <li><i class="bi bi-check-circle-fill text-success"></i> <?php echo number_format((float)$plan['coverage_pct']); ?>% coverage</li>
                            <li><i class="bi bi-check-circle-fill text-success"></i> $<?php echo number_format((float)$plan['deductible']); ?> deductible</li>
                            <li><i class="bi bi-check-circle-fill text-success"></i> Instant AI claim quotes</li>
                        </ul>
                        <a href="<?= base_path() ?>/auth/register.php"
                           class="btn <?php echo $featured ? 'btn-primary' : 'btn-outline-primary'; ?> w-100 mt-3">
                            Choose <?php echo htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8'); ?>
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

    <section class="testimonials-section py-6" aria-labelledby="testimonials-heading">
        <div class="container">
            <div class="section-header text-center mb-5">
                <span class="section-label">Happy Pet Parents</span>
                <h2 id="testimonials-heading" class="section-title">What Our Members Say</h2>
            </div>
            <div class="row g-4">
                <?php
                $testimonials = [
                    ['name' => 'Sarah L.', 'pet' => 'Owner of Mochi the Shih Tzu',  'avatar' => 'SL', 'pet_image' => 'mochi-shih-tzu', 'pet_alt' => 'Mochi, a Shih Tzu', 'rating' => 5, 'text' => 'I uploaded the receipt on my phone and had a quote in under 2 minutes. Genuinely impressed!'],
                    ['name' => 'James T.', 'pet' => 'Owner of Duke the Labrador',    'avatar' => 'JT', 'pet_image' => 'duke-labrador',   'pet_alt' => 'Duke, a Labrador retriever', 'rating' => 5, 'text' => 'Duke had emergency surgery last year. PawShield covered 80% of the bill. The AI scanning made it painless.'],
                    ['name' => 'Priya K.', 'pet' => 'Owner of Luna the Persian Cat', 'avatar' => 'PK', 'pet_image' => 'luna-persian',    'pet_alt' => 'Luna, a Persian cat', 'rating' => 5, 'text' => 'Finally a pet insurance that doesn\'t make you fill out endless forms. Highly recommend the Premium plan.'],
                ];
                $testimonialPetDir = __DIR__ . '/assets/images/testimonials/';
                $testimonialPetExts = ['png', 'jpg', 'jpeg', 'webp'];
                foreach ($testimonials as $t):
                    $petBase = $t['pet_image'] ?? '';
                    $petFile = '';
                    if ($petBase !== '') {
                        foreach ($testimonialPetExts as $ext) {
                            $try = $petBase . '.' . $ext;
                            if (is_file($testimonialPetDir . $try)) {
                                $petFile = $try;
                                break;
                            }
                        }
                    }
                    $hasPetImg = $petFile !== '';
                    $petImgSrc = $hasPetImg ? (base_path() . '/assets/images/testimonials/' . rawurlencode($petFile)) : '';
                ?>
                <div class="col-md-4">
                    <div class="testimonial-card" role="article">
                        <div class="testimonial-stars" role="img" aria-label="<?php echo (int) $t['rating']; ?> out of 5 stars">
                            <?php for ($s = 0; $s < $t['rating']; $s++): ?>
                            <i class="bi bi-star-fill" aria-hidden="true"></i>
                            <?php endfor; ?>
                        </div>
                        <blockquote class="testimonial-text">
                            "<?php echo htmlspecialchars($t['text'], ENT_QUOTES, 'UTF-8'); ?>"
                        </blockquote>
                        <div class="testimonial-author">
                            <div class="author-avatar<?php echo $hasPetImg ? ' author-avatar--pet' : ''; ?>"<?php echo $hasPetImg ? '' : ' aria-hidden="true"'; ?>>
                                <?php if ($hasPetImg): ?>
                                    <img src="<?= esc($petImgSrc) ?>" alt="<?= esc($t['pet_alt'] ?? 'Pet photo') ?>" width="52" height="52" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span class="author-avatar-initials"><?php echo esc($t['avatar']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span class="author-pet"><?php echo htmlspecialchars($t['pet'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="cta-section py-6" aria-labelledby="cta-heading">
        <div class="container text-center">
            <h2 id="cta-heading" class="cta-title">Ready to protect your pet?</h2>
            <p class="cta-subtitle">Join thousands of pet parents who trust PawShield.</p>
            <a href="<?= base_path() ?>/auth/register.php" class="btn btn-cta btn-lg">
                Get Started For Free <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
            </a>
        </div>
    </section>

    <?php if (defined('APP_ENV') && APP_ENV === 'local'): ?>
    <div class="container py-3 text-center"
         style="font-size:0.8rem; color:#6b7280; border-top:1px solid #e5e7eb;">
        <strong>Environment:</strong> <?php echo esc(APP_ENV); ?> &nbsp;|&nbsp;
        <strong>Scanner mode:</strong> <?php echo esc(AI_SCANNER_MODE); ?> &nbsp;|&nbsp;
        <strong>Database:</strong> <?php echo esc(DB_HOST); ?>/<?php echo esc(DB_NAME); ?> &nbsp;|&nbsp;
        <span class="text-success">✅ Development environment</span>
    </div>
    <?php endif; ?>

</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
