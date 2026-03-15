<?php
/**
 * Privacy Policy — placeholder content; update with your legal text.
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
    <meta name="description" content="PawShield Privacy Policy — How we collect, use, and protect your information.">
    <title>Privacy Policy — PawShield Pet Insurance</title>
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

    <section class="hero-section hero-section--short" aria-labelledby="privacy-hero-heading">
        <div class="hero-bg-shapes" aria-hidden="true">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
        </div>
        <div class="container hero-content">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <span class="hero-badge"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Legal</span>
                    <h1 id="privacy-hero-heading" class="hero-heading">Privacy Policy</h1>
                    <p class="hero-subtitle mb-0 text-center mx-auto" style="max-width: 32rem;">
                        How we collect, use, and protect your information.
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

                    <h2 class="h5 fw-bold mt-4 mb-2" style="color: var(--ps-navy);">1. Information we collect</h2>
                    <p>We collect information you provide when you register, add a pet, submit a claim, or contact us. This may include your name, email address, phone number, pet details, and documents you upload (such as vet receipts) for claim processing.</p>

                    <h2 class="h5 fw-bold mt-4 mb-2" style="color: var(--ps-navy);">2. How we use your information</h2>
                    <p>We use your information to provide and improve our services, process claims, verify vet clinics, communicate with you, and comply with legal obligations. We do not sell your personal information to third parties.</p>

                    <h2 class="h5 fw-bold mt-4 mb-2" style="color: var(--ps-navy);">3. Data security</h2>
                    <p>We use industry-standard measures to protect your data. Uploaded documents and personal information are stored securely and accessed only as needed to provide our services.</p>

                    <h2 class="h5 fw-bold mt-4 mb-2" style="color: var(--ps-navy);">4. Your rights</h2>
                    <p>You may request access to, correction of, or deletion of your personal data. You can also opt out of marketing communications at any time. Contact us using the form on our Contact page for any privacy-related requests.</p>

                    <h2 class="h5 fw-bold mt-4 mb-2" style="color: var(--ps-navy);">5. Changes to this policy</h2>
                    <p>We may update this privacy policy from time to time. We will post the updated version on this page and update the “Last updated” date. Continued use of our services after changes constitutes acceptance of the updated policy.</p>

                    <div class="text-center mt-5 pt-3">
                        <a href="<?= base_path() ?>/pages/contact.php" class="btn btn-primary legal-cta-btn">
                            Contact us <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
