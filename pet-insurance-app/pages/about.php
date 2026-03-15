<?php
/**
 * About PawShield — company story, mission, values.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/csrf.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="About PawShield — Pet insurance made simple with AI-powered claims. Our story, mission, and commitment to pet parents.">
    <title>About Us — PawShield Pet Insurance</title>
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
    <section class="hero-section hero-section--short" aria-labelledby="about-hero-heading">
        <div class="hero-bg-shapes" aria-hidden="true">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
        </div>
        <div class="container hero-content">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <span class="hero-badge">
                        <i class="bi bi-heart me-1" aria-hidden="true"></i>Our Story
                    </span>
                    <h1 id="about-hero-heading" class="hero-heading">
                        About PawShield
                    </h1>
                    <p class="hero-subtitle mb-0 text-center mx-auto" style="max-width: 32rem;">
                        We’re pet parents too. We built PawShield so you can focus on your pet — not on paperwork.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Our story -->
    <section class="py-6" aria-labelledby="story-heading">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="section-label">Who We Are</span>
                    <h2 id="story-heading" class="section-title">Pet insurance, reimagined</h2>
                    <p class="text-body">
                        PawShield started with a simple frustration: vet bills are stressful enough without spending hours on claim forms and waiting weeks for a response. We wanted insurance that works the way you live — fast, transparent, and on your phone.
                    </p>
                    <p class="text-body mb-0">
                        So we built an AI-powered platform that reads your vet receipt, verifies it with trusted clinics, and gives you an instant reimbursement quote. No faxing, no back-and-forth. Just upload, get your quote, and get back to what matters: your pet.
                    </p>
                </div>
                <div class="col-lg-6 d-flex justify-content-center">
                    <div class="about-visual p-4 p-lg-5 rounded-3 text-center" style="background: var(--ps-teal-light); border: 1px solid rgba(13,148,136,.2); max-width: 100%;">
                        <div class="d-flex flex-column flex-sm-row align-items-center justify-content-center gap-3 mb-3">
                            <div class="about-icon-box rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:64px;height:64px;background:var(--ps-teal);color:white;">
                                <i class="bi bi-heart-pulse" style="font-size:1.75rem;"></i>
                            </div>
                            <div>
                                <strong class="d-block" style="color:var(--ps-navy);">For pet parents, by pet parents</strong>
                                <span class="text-muted small">Dogs, cats, and every furry friend in between.</span>
                            </div>
                        </div>
                        <p class="mb-0 small text-muted text-center mx-auto" style="max-width: 28rem;">
                            Our team includes vets, engineers, and animal lovers who believe every pet deserves access to great care — and every owner deserves a claims process that doesn’t get in the way.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission -->
    <section class="py-6" style="background: var(--ps-off-white);" aria-labelledby="mission-heading">
        <div class="container">
            <div class="section-header text-center mb-5">
                <span class="section-label">Our Mission</span>
                <h2 id="mission-heading" class="section-title">Simple, fast, fair</h2>
                <p class="section-subtitle">
                    We’re here to make pet insurance feel less like insurance and more like a safety net that’s there when you need it.
                </p>
            </div>
            <div class="row g-4 justify-content-center">
                <?php
                $missions = [
                    ['icon' => 'bi-lightning-charge-fill', 'title' => 'Instant quotes', 'desc' => 'Upload your receipt and get a detailed reimbursement quote in seconds, not days.'],
                    ['icon' => 'bi-shield-check',        'title' => 'No hidden fees', 'desc' => 'Clear pricing, clear coverage. What you see is what you get.'],
                    ['icon' => 'bi-heart-fill',         'title' => 'Built for pets', 'desc' => 'From routine checkups to emergencies, we’re designed around the way you care for your pet.'],
                ];
                foreach ($missions as $m): ?>
                <div class="col-md-4">
                    <div class="step-card step-card--centered" role="article">
                        <div class="step-icon" aria-hidden="true">
                            <i class="bi <?= $m['icon'] ?>"></i>
                        </div>
                        <h3 class="step-title"><?= htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="step-desc"><?= htmlspecialchars($m['desc'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Why PawShield / Values -->
    <section class="py-6" aria-labelledby="values-heading">
        <div class="container">
            <div class="section-header text-center mb-5">
                <span class="section-label">Why PawShield</span>
                <h2 id="values-heading" class="section-title">What we stand for</h2>
            </div>
            <div class="row g-4 values-cards">
                <div class="col-md-6 col-lg-3">
                    <div class="value-card value-card--1 text-center p-4 rounded-3 h-100" style="background: var(--ps-white); border: 1px solid var(--ps-gray-200);">
                        <i class="bi bi-eye-fill d-block mb-2" style="font-size:2rem; color:var(--ps-teal);" aria-hidden="true"></i>
                        <h3 class="h6 fw-bold mb-2" style="color:var(--ps-navy);">Transparency</h3>
                        <p class="small text-muted mb-0">Clear terms, clear payouts. No fine-print surprises.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="value-card value-card--2 text-center p-4 rounded-3 h-100" style="background: var(--ps-white); border: 1px solid var(--ps-gray-200);">
                        <i class="bi bi-speedometer2 d-block mb-2" style="font-size:2rem; color:var(--ps-teal);" aria-hidden="true"></i>
                        <h3 class="h6 fw-bold mb-2" style="color:var(--ps-navy);">Speed</h3>
                        <p class="small text-muted mb-0">AI does the heavy lifting so you get answers fast.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="value-card value-card--3 text-center p-4 rounded-3 h-100" style="background: var(--ps-white); border: 1px solid var(--ps-gray-200);">
                        <i class="bi bi-person-hearts d-block mb-2" style="font-size:2rem; color:var(--ps-teal);" aria-hidden="true"></i>
                        <h3 class="h6 fw-bold mb-2" style="color:var(--ps-navy);">Care</h3>
                        <p class="small text-muted mb-0">We treat every claim like it’s for our own pet.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="value-card value-card--4 text-center p-4 rounded-3 h-100" style="background: var(--ps-white); border: 1px solid var(--ps-gray-200);">
                        <i class="bi bi-patch-check-fill d-block mb-2" style="font-size:2rem; color:var(--ps-teal);" aria-hidden="true"></i>
                        <h3 class="h6 fw-bold mb-2" style="color:var(--ps-navy);">Trust</h3>
                        <p class="small text-muted mb-0">Verified vet clinics and secure, reliable systems.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta-section py-6" aria-labelledby="about-cta-heading">
        <div class="container text-center">
            <h2 id="about-cta-heading" class="cta-title">Ready to protect your pet?</h2>
            <p class="cta-subtitle">Join thousands of pet parents who trust PawShield for simple, AI-powered claims.</p>
            <div class="d-flex flex-wrap gap-3 justify-content-center">
                <a href="<?= base_path() ?>/auth/register.php" class="btn btn-cta btn-lg">
                    Get Started For Free <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
                </a>
                <a href="<?= base_path() ?>/pages/how-it-works.php" class="btn btn-hero-secondary btn-lg">
                    See How It Works
                </a>
            </div>
        </div>
    </section>

</main>

<script>
(function() {
    var el = document.querySelector('.values-cards');
    if (!el) return;
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('in-view');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -80px 0px', threshold: 0.1 });
    observer.observe(el);
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
