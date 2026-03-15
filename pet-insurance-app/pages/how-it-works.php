<?php
/**
 * How It Works — three-step claims process + why it's better.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/csrf.php';

$steps = [
    ['icon' => 'bi-upload',     'num' => '01', 'title' => 'Upload Your Receipt', 'desc' => 'Take a photo or scan your vet invoice and upload it through our secure portal. From your phone or computer — no faxing, no mailing.'],
    ['icon' => 'bi-cpu-fill',   'num' => '02', 'title' => 'AI Scans & Verifies', 'desc' => 'Our AI reads the receipt, identifies treatments and costs, and cross-checks with our verified vet clinic network. You get accuracy without the wait.'],
    ['icon' => 'bi-cash-coin',  'num' => '03', 'title' => 'Get Your Quote',      'desc' => 'Receive a detailed reimbursement breakdown in seconds. Review it, approve if it looks right, and funds are on their way.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="How PawShield works — Upload your vet receipt, our AI scans and verifies it, and you get an instant claim quote. No paperwork, no waiting.">
    <title>How It Works — PawShield Pet Insurance</title>
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
    <section class="hero-section hero-section--short" aria-labelledby="how-hero-heading">
        <div class="hero-bg-shapes" aria-hidden="true">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
        </div>
        <div class="container hero-content">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <span class="hero-badge">
                        <i class="bi bi-stars me-1" aria-hidden="true"></i>Simple Process
                    </span>
                    <h1 id="how-hero-heading" class="hero-heading">
                        How PawShield Works
                    </h1>
                    <p class="hero-subtitle mb-0 text-center mx-auto" style="max-width: 32rem;">
                        From vet visit to reimbursement in three simple steps.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Intro -->
    <section class="py-5" aria-labelledby="intro-heading">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="how-intro-block">
                        <div class="how-intro-icon" aria-hidden="true">
                            <i class="bi bi-info-circle-fill"></i>
                        </div>
                        <div class="how-intro-content">
                            <h2 id="intro-heading" class="visually-hidden">Introduction</h2>
                            <p class="how-intro-text mb-0">
                                Submitting a claim with PawShield is designed to be simple: you upload your vet receipt, our AI reads and verifies it, and you get an instant reimbursement quote. No lengthy forms, no waiting for someone to process paperwork. Here’s exactly how it works and what you’ll need to get started.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- What you'll need -->
    <section class="py-6" style="background: var(--ps-off-white);" aria-labelledby="need-heading">
        <div class="container">
            <div class="section-header text-center mb-4">
                <span class="section-label">Before You Start</span>
                <h2 id="need-heading" class="section-title">What you’ll need</h2>
            </div>
            <div class="row g-4 justify-content-center">
                <div class="col-md-5 col-lg-4">
                    <div class="d-flex gap-3 p-4 rounded-3 h-100" style="background: var(--ps-white); border: 1px solid var(--ps-gray-200);">
                        <div class="flex-shrink-0 rounded-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: var(--ps-teal-light);">
                            <i class="bi bi-file-earmark-text" style="color: var(--ps-teal); font-size: 1.25rem;"></i>
                        </div>
                        <div>
                            <h3 class="h6 fw-bold mb-1" style="color: var(--ps-navy);">Your vet receipt or invoice</h3>
                            <p class="small text-muted mb-0">A clear photo or scan of the invoice from your vet visit. Make sure the total, date, and treatment details are visible.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-5 col-lg-4">
                    <div class="d-flex gap-3 p-4 rounded-3 h-100" style="background: var(--ps-white); border: 1px solid var(--ps-gray-200);">
                        <div class="flex-shrink-0 rounded-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: var(--ps-teal-light);">
                            <i class="bi bi-person-badge" style="color: var(--ps-teal); font-size: 1.25rem;"></i>
                        </div>
                        <div>
                            <h3 class="h6 fw-bold mb-1" style="color: var(--ps-navy);">A PawShield account</h3>
                            <p class="small text-muted mb-0">Sign up for free, add your pet and plan, then you’re ready to submit claims from your dashboard.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Three steps -->
    <section class="py-6" id="steps" aria-labelledby="steps-heading">
        <div class="container">
            <div class="section-header text-center mb-5">
                <span class="section-label">The Process</span>
                <h2 id="steps-heading" class="section-title">Three simple steps</h2>
            </div>
            <div class="row g-4 justify-content-center">
                <?php foreach ($steps as $step): ?>
                <div class="col-md-4">
                    <div class="step-card" role="article">
                        <div class="step-num" aria-hidden="true"><?= $step['num'] ?></div>
                        <div class="step-icon" aria-hidden="true">
                            <i class="bi <?= $step['icon'] ?>"></i>
                        </div>
                        <h3 class="step-title"><?= htmlspecialchars($step['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="step-desc"><?= htmlspecialchars($step['desc'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Why it's better -->
    <section class="py-6" style="background: var(--ps-off-white);" aria-labelledby="why-heading">
        <div class="container">
            <div class="section-header text-center mb-5">
                <span class="section-label">Why It’s Better</span>
                <h2 id="why-heading" class="section-title">No paperwork. No waiting.</h2>
                <p class="section-subtitle">
                    We built PawShield so you spend less time on forms and more time with your pet.
                </p>
            </div>
            <div class="row g-4 justify-content-center">
                <div class="col-md-4">
                    <div class="text-center p-4 rounded-3 h-100" style="background: var(--ps-white); border: 1px solid var(--ps-gray-200);">
                        <i class="bi bi-lightning-charge-fill d-block mb-2" style="font-size: 2rem; color: var(--ps-teal);" aria-hidden="true"></i>
                        <h3 class="h6 fw-bold mb-2" style="color: var(--ps-navy);">Instant quotes</h3>
                        <p class="small text-muted mb-0">Get a reimbursement estimate in seconds, not days or weeks.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-4 rounded-3 h-100" style="background: var(--ps-white); border: 1px solid var(--ps-gray-200);">
                        <i class="bi bi-file-earmark-x d-block mb-2" style="font-size: 2rem; color: var(--ps-teal);" aria-hidden="true"></i>
                        <h3 class="h6 fw-bold mb-2" style="color: var(--ps-navy);">No claim forms</h3>
                        <p class="small text-muted mb-0">Just upload your receipt. Our AI does the rest — no lengthy paperwork.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-4 rounded-3 h-100" style="background: var(--ps-white); border: 1px solid var(--ps-gray-200);">
                        <i class="bi bi-shield-check d-block mb-2" style="font-size: 2rem; color: var(--ps-teal);" aria-hidden="true"></i>
                        <h3 class="h6 fw-bold mb-2" style="color: var(--ps-navy);">Verified & secure</h3>
                        <p class="small text-muted mb-0">We cross-check with verified vet clinics and keep your data secure.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-6" style="background: var(--ps-off-white);" aria-labelledby="faq-heading">
        <div class="container">
            <div class="section-header text-center mb-5">
                <span class="section-label">Common Questions</span>
                <h2 id="faq-heading" class="section-title">Frequently asked questions</h2>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion how-it-works-faq" id="howItWorksFaq">
                        <div class="accordion-item border-0 rounded-3 mb-2 shadow-sm" style="overflow: hidden;">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold" style="background: var(--ps-white); color: var(--ps-navy);" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="false" aria-controls="faq1">
                                    How long does it take to get a quote?
                                </button>
                            </h3>
                            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#howItWorksFaq">
                                <div class="accordion-body text-muted small">
                                    Usually within seconds. Our AI scans your receipt and returns a detailed reimbursement breakdown almost instantly. If we need to verify something with your vet clinic, we’ll let you know and it may take a bit longer.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 rounded-3 mb-2 shadow-sm" style="overflow: hidden;">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold" style="background: var(--ps-white); color: var(--ps-navy);" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false" aria-controls="faq2">
                                    What file formats can I upload?
                                </button>
                            </h3>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#howItWorksFaq">
                                <div class="accordion-body text-muted small">
                                    We accept photos (JPEG, PNG, WebP) and PDFs. The most important thing is that the receipt or invoice is clear and readable — avoid blurry or dark images so our AI can read the amounts and details correctly.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 rounded-3 mb-2 shadow-sm" style="overflow: hidden;">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold" style="background: var(--ps-white); color: var(--ps-navy);" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false" aria-controls="faq3">
                                    Do I need to fill out claim forms?
                                </button>
                            </h3>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#howItWorksFaq">
                                <div class="accordion-body text-muted small">
                                    No. You just upload your receipt and we do the rest. Our AI extracts the relevant information and checks it against our verified vet network. You only need to review the quote and approve it if everything looks correct.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 rounded-3 mb-2 shadow-sm" style="overflow: hidden;">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold" style="background: var(--ps-white); color: var(--ps-navy);" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false" aria-controls="faq4">
                                    What if my vet isn’t in your network?
                                </button>
                            </h3>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#howItWorksFaq">
                                <div class="accordion-body text-muted small">
                                    We work with a growing network of verified clinics. If your vet isn’t listed yet, we may still be able to process your claim — upload the receipt and we’ll let you know. We’re always adding new clinics.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta-section py-6" aria-labelledby="how-cta-heading">
        <div class="container text-center">
            <h2 id="how-cta-heading" class="cta-title">Ready to try it?</h2>
            <p class="cta-subtitle">Create an account, add your pet, and submit your first claim in minutes.</p>
            <a href="<?= base_path() ?>/auth/register.php" class="btn btn-cta btn-lg">
                Get Started For Free <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
            </a>
        </div>
    </section>

    <?php require_once __DIR__ . '/../includes/back-to-top.php'; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
