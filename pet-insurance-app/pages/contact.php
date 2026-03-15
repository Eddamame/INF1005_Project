<?php
/**
 * Contact — contact form with DB-backed submission.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../services/ContactSubmit.php';

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $result = ContactSubmit::handle($db);
    $errors = $result['errors'] ?? [];
    if ($result['success']) {
        $successMessage = 'Thank you, your message has been sent.';
        // Clear POST-backed fields so the form appears reset
        $_POST = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contact PawShield — Get in touch with our team. Questions about pet insurance or claims? We're here to help.">
    <title>Contact Us — PawShield Pet Insurance</title>
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
    <section class="hero-section hero-section--short" aria-labelledby="contact-hero-heading">
        <div class="hero-bg-shapes" aria-hidden="true">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
        </div>
        <div class="container hero-content">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <span class="hero-badge">
                        <i class="bi bi-envelope me-1" aria-hidden="true"></i>Get in Touch
                    </span>
                    <h1 id="contact-hero-heading" class="hero-heading">
                        Contact Us
                    </h1>
                    <p class="hero-subtitle mb-0 text-center mx-auto" style="max-width: 32rem;">
                        Have a question about our plans or claims? Send us a message and we’ll get back to you.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact form -->
    <section class="py-6 contact-section" aria-labelledby="contact-form-heading">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-xl-7 contact-form-wrap">
                    <div class="contact-form-card p-4 p-lg-5 rounded-3">
                        <div class="contact-form-card-header mb-4">
                            <span class="contact-form-icon rounded-circle d-inline-flex align-items-center justify-content-center mb-3" aria-hidden="true">
                                <i class="bi bi-chat-dots-fill"></i>
                            </span>
                            <h2 id="contact-form-heading" class="h3 mb-0" style="color: var(--ps-navy);">Send a message</h2>
                            <p class="text-muted small mt-1 mb-0">We’ll get back to you within 1–2 business days.</p>
                        </div>

                        <?php if (!empty($successMessage)): ?>
                            <div class="alert alert-success" role="status">
                                <?= esc($successMessage) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors['general'])): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= esc($errors['general']) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="contact-form" class="contact-form">
                            <?= csrfField() ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="contact-name" class="form-label">Name</label>
                                    <input
                                        type="text"
                                        id="contact-name"
                                        name="name"
                                        class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                        placeholder="Your name"
                                        value="<?= esc($_POST['name'] ?? '') ?>"
                                        autofocus
                                    >
                                    <?php if (isset($errors['name'])): ?>
                                        <div class="invalid-feedback"><?= esc($errors['name']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="contact-email" class="form-label">Email</label>
                                    <input
                                        type="email"
                                        id="contact-email"
                                        name="email"
                                        class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                        placeholder="you@example.com"
                                        value="<?= esc($_POST['email'] ?? '') ?>"
                                        data-inline-validate="email"
                                    >
                                    <div class="invalid-feedback" data-inline-feedback><?= isset($errors['email']) ? esc($errors['email']) : '' ?></div>
                                </div>
                                <div class="col-12">
                                    <label for="contact-phone" class="form-label">Phone <span class="text-muted">(optional)</span></label>
                                    <input
                                        type="tel"
                                        id="contact-phone"
                                        name="phone"
                                        class="form-control"
                                        placeholder="Your phone number"
                                        value="<?= esc($_POST['phone'] ?? '') ?>"
                                    >
                                </div>
                                <div class="col-12">
                                    <label for="contact-subject" class="form-label">Subject</label>
                                    <select
                                        id="contact-subject"
                                        name="subject"
                                        class="form-select <?= isset($errors['subject']) ? 'is-invalid' : '' ?>"
                                    >
                                        <?php $selected = $_POST['subject'] ?? ''; ?>
                                        <option value="">Choose a topic</option>
                                        <option value="general" <?= $selected === 'general' ? 'selected' : '' ?>>General enquiry</option>
                                        <option value="claims"  <?= $selected === 'claims'  ? 'selected' : '' ?>>Claims &amp; reimbursement</option>
                                        <option value="plans"   <?= $selected === 'plans'   ? 'selected' : '' ?>>Plans &amp; pricing</option>
                                        <option value="account" <?= $selected === 'account' ? 'selected' : '' ?>>Account &amp; login</option>
                                        <option value="other"   <?= $selected === 'other'   ? 'selected' : '' ?>>Other</option>
                                    </select>
                                    <?php if (isset($errors['subject'])): ?>
                                        <div class="invalid-feedback"><?= esc($errors['subject']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label for="contact-message" class="form-label">Message</label>
                                    <textarea
                                        id="contact-message"
                                        name="message"
                                        class="form-control <?= isset($errors['message']) ? 'is-invalid' : '' ?>"
                                        rows="5"
                                        placeholder="How can we help?"
                                    ><?= esc($_POST['message'] ?? '') ?></textarea>
                                    <?php if (isset($errors['message'])): ?>
                                        <div class="invalid-feedback"><?= esc($errors['message']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 pt-2 text-center">
                                    <button type="submit" class="btn btn-primary contact-form-submit">
                                        Send message <i class="bi bi-send ms-2" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </form>

                        <p class="small text-muted mt-4 mb-0 text-center">
                            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                            Secure form. We never share your details.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<script src="<?= base_path() ?>/assets/js/inline-validation.js"></script>
<script>
(function() {
    var el = document.querySelector('.contact-form-wrap');
    if (!el) return;
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('in-view');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -60px 0px', threshold: 0.15 });
    observer.observe(el);
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
