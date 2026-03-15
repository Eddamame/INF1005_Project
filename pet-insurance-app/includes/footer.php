<?php
/**
 * includes/footer.php
 * Sitewide footer + Bootstrap JS + app.js.
 * All paths root-relative from pet-insurance-app/.
 */
?>
<footer class="pawshield-footer">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <a class="footer-brand" href="<?= base_path() ?>/index.php" aria-label="PawShield Home">
                    <span>🐾</span> PawShield
                </a>
                <p class="footer-tagline mt-2">
                    AI-powered pet insurance claims.<br>
                    Upload a receipt. Get an instant quote.
                </p>
                <div class="footer-socials mt-3" aria-label="Social media links">
                    <a href="#" aria-label="Facebook"><i class="bi bi-facebook" aria-hidden="true"></i></a>
                    <a href="#" aria-label="Instagram"><i class="bi bi-instagram" aria-hidden="true"></i></a>
                    <a href="#" aria-label="Twitter / X"><i class="bi bi-twitter-x" aria-hidden="true"></i></a>
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h3 class="footer-heading">Company</h3>
                <ul class="footer-links">
                    <li><a href="<?= base_path() ?>/pages/about.php">About</a></li>
                    <li><a href="<?= base_path() ?>/pages/contact.php">Contact</a></li>
                    <li><a href="<?= base_path() ?>/pages/how-it-works.php#faq">FAQ</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h3 class="footer-heading">Insurance</h3>
                <ul class="footer-links">
                    <li><a href="<?= base_path() ?>/pages/services.php">Services</a></li>
                    <li><a href="<?= base_path() ?>/pages/pricing.php">Pricing</a></li>
                    <li><a href="<?= base_path() ?>/pages/how-it-works.php">How It Works</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h3 class="footer-heading">Account</h3>
                <ul class="footer-links">
                    <li><a href="<?= base_path() ?>/auth/login.php">Log In</a></li>
                    <li><a href="<?= base_path() ?>/auth/register.php">Register</a></li>
                    <?php if (!empty($_SESSION['user_id'])): ?>
                    <li><a href="<?= base_path() ?>/dashboard/index.php">Dashboard</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h3 class="footer-heading">Legal</h3>
                <ul class="footer-links">
                    <li><a href="<?= base_path() ?>/pages/privacy.php">Privacy Policy</a></li>
                    <li><a href="<?= base_path() ?>/pages/terms.php">Terms of Service</a></li>
                </ul>
            </div>
        </div>

        <hr class="footer-divider" aria-hidden="true">

        <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> PawShield. All rights reserved.</p>
            <p class="mb-0 footer-note">Built with ❤️ for pets everywhere.</p>
        </div>
    </div>
</footer>

<!-- Bootstrap JS (required for accordion, collapse, etc.) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<?php if (file_exists(__DIR__ . '/../assets/js/app.js')): ?>
<script src="<?= base_path() ?>/assets/js/app.js"></script>
<?php endif; ?>
</body>
</html>