<?php
/**
 * Terms of Service — placeholder content; update with your legal text.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/sanitize.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="PawShield Terms of Service — Terms and conditions for using our pet insurance services.">
    <title>Terms of Service — PawShield Pet Insurance</title>
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

    <section class="hero-section hero-section--short" aria-labelledby="terms-hero-heading">
        <div class="hero-bg-shapes" aria-hidden="true">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
        </div>
        <div class="container hero-content">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <span class="hero-badge"><i class="bi bi-file-text me-1" aria-hidden="true"></i>Legal</span>
                    <h1 id="terms-hero-heading" class="hero-heading">Terms of Service</h1>
                    <p class="hero-subtitle mb-0 text-center mx-auto" style="max-width: 32rem;">
                        Terms and conditions for using PawShield pet insurance services.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-6">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 legal-prose">
                    <p class="text-muted small">Last updated: <?= date('F j, Y') ?></p>

                    <h2 class="h5 fw-bold mt-4 mb-2" style="color: var(--ps-navy);">1. Acceptance of terms</h2>
                    <p>By creating an account or using PawShield’s website and services, you agree to these Terms of Service. If you do not agree, please do not use our services.</p>

                    <h2 class="h5 fw-bold mt-4 mb-2" style="color: var(--ps-navy);">2. Description of services</h2>
                    <p>PawShield provides pet insurance and an AI-powered claims process. You may register, add pets, choose a plan, and submit claims by uploading vet receipts. Quotes and coverage are subject to your plan and our verification process.</p>

                    <h2 class="h5 fw-bold mt-4 mb-2" style="color: var(--ps-navy);">3. Your account and responsibilities</h2>
                    <p>You are responsible for keeping your account credentials secure and for the accuracy of the information you provide. You must not misuse the service, upload false documents, or attempt to gain unauthorized access to our systems or other users’ data.</p>

                    <h2 class="h5 fw-bold mt-4 mb-2" style="color: var(--ps-navy);">4. Cancellation</h2>
                    <p>You may cancel your plan or account in accordance with the options provided in your account settings. Refunds or partial refunds are subject to our cancellation policy as stated at the time of purchase.</p>

                    <h2 class="h5 fw-bold mt-4 mb-2" style="color: var(--ps-navy);">5. Changes to terms</h2>
                    <p>We may update these terms from time to time. We will notify you of material changes by posting the updated terms on this page and updating the “Last updated” date. Continued use of our services after changes constitutes acceptance of the updated terms.</p>

                    <div class="text-center mt-5 pt-3">
                        <a href="<?= base_path() ?>/pages/contact.php" class="btn btn-primary legal-cta-btn">
                            Contact us <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php require_once __DIR__ . '/../includes/back-to-top.php'; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
