<?php
// inc/footer.php

// Ensure config is loaded if this file is included standalone (optional safety check)
if (!defined('OFFICE_PHONE')) {
    // Fallbacks just in case config isn't loaded
    define('OFFICE_PHONE', '+254 700 000 000');
    define('OFFICE_EMAIL', 'info@umojadriverssacco.co.ke');
    define('OFFICE_LOCATION', 'Umoja House, Nairobi');
}
?>

<footer class="sacco-footer mt-auto pt-5 pb-4" id="contact" style="background: var(--bg-surface); border-top: 1px solid var(--border-color);">
    <div class="container">
        <div class="row gy-5">

            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="<?= ASSET_BASE ?>/images/people_logo.png"
                         alt="Sacco Logo"
                         class="rounded-circle bg-white p-1"
                         style="width: 55px; height: 55px; border: 2px solid var(--sacco-gold);">
                    <h5 class="section-title mb-0">
                        About <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Sacco' ?>
                    </h5>
                </div>

                <p class="small opacity-75 mb-4" style="line-height: 1.7;">
                    Empowering members through unity, reliable savings, transparent lending, and sustainable investment.
                    We exist to uplift all stakeholders in the transport sector.
                </p>

                <h6 class="fw-bold small mb-3">Our Core Pillars</h6>
                <div class="d-flex flex-wrap gap-2">
                    <div class="footer-pill"><i class="bi bi-wallet-fill me-1"></i> Savings</div>
                    <div class="footer-pill"><i class="bi bi-cash-stack me-1"></i> Loans</div>
                    <div class="footer-pill"><i class="bi bi-graph-up-arrow me-1"></i> Investment</div>
                    <div class="footer-pill"><i class="bi bi-people-fill me-1"></i> Welfare</div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <h5 class="section-title">Quick Links</h5>
                <ul class="list-unstyled footer-link-list">
                    <li><a href="<?= BASE_URL ?>/public/index.php" class="footer-link-text"><i class="bi bi-chevron-right me-2"></i> Home</a></li>
                    <li><a href="<?= BASE_URL ?>/public/index.php#about" class="footer-link-text"><i class="bi bi-chevron-right me-2"></i> About Us</a></li>
                    <li><a href="<?= BASE_URL ?>/public/index.php#services" class="footer-link-text"><i class="bi bi-chevron-right me-2"></i> Services</a></li>
                    <li><a href="<?= BASE_URL ?>/public/index.php#portfolio" class="footer-link-text"><i class="bi bi-chevron-right me-2"></i> Assets & Projects</a></li>
                    <li><a href="<?= BASE_URL ?>/public/login.php" class="footer-link-text"><i class="bi bi-chevron-right me-2"></i> Member Login</a></li>
                    <li><a href="<?= BASE_URL ?>/public/register.php" class="footer-link-text"><i class="bi bi-chevron-right me-2"></i> Become a Member</a></li>
                </ul>
            </div>

            <div class="col-lg-4 col-md-12">
                <h5 class="section-title">Connect With Us</h5>

                <ul class="list-unstyled mb-4 opacity-75 small footer-contact-list">
                    <li class="mb-3 d-flex align-items-start">
                        <i class="bi bi-geo-alt-fill me-3 text-warning mt-1"></i>
                        <span><?= htmlspecialchars(OFFICE_LOCATION) ?></span>
                    </li>

                    <li class="mb-3 d-flex align-items-center">
                        <i class="bi bi-telephone-fill me-3 text-warning"></i>
                        <a href="tel:<?= htmlspecialchars(OFFICE_PHONE) ?>"
                           class="text-reset text-decoration-none fw-bold">
                           <?= htmlspecialchars(OFFICE_PHONE) ?>
                        </a>
                    </li>

                    <li class="mb-3 d-flex align-items-center">
                        <i class="bi bi-envelope-fill me-3 text-warning"></i>
                        <a href="mailto:<?= htmlspecialchars(OFFICE_EMAIL) ?>"
                           class="text-reset text-decoration-none">
                           <?= htmlspecialchars(OFFICE_EMAIL) ?>
                        </a>
                    </li>

                    <li class="mb-3 d-flex align-items-center">
                        <i class="bi bi-credit-card-2-front-fill me-3 text-success fs-5"></i>
                        <span class="fw-bold text-success">Paybill: 247247</span>
                    </li>
                </ul>

                <h6 class="fw-bold small mb-3">Follow Us</h6>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', COMPANY_PHONE) ?>" class="social-btn" target="_blank" title="WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                    <?php if (defined('SOCIAL_FACEBOOK') && SOCIAL_FACEBOOK): ?>
                    <a href="<?= SOCIAL_FACEBOOK ?>" class="social-btn" target="_blank" title="Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_TWITTER') && SOCIAL_TWITTER): ?>
                    <a href="<?= SOCIAL_TWITTER ?>" class="social-btn" target="_blank" title="Twitter/X">
                        <i class="bi bi-twitter"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_INSTAGRAM') && SOCIAL_INSTAGRAM): ?>
                    <a href="<?= SOCIAL_INSTAGRAM ?>" class="social-btn" target="_blank" title="Instagram">
                        <i class="bi bi-instagram"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_YOUTUBE') && SOCIAL_YOUTUBE): ?>
                    <a href="<?= SOCIAL_YOUTUBE ?>" class="social-btn" target="_blank" title="YouTube">
                        <i class="bi bi-youtube"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_TIKTOK') && SOCIAL_TIKTOK): ?>
                    <a href="<?= SOCIAL_TIKTOK ?>" class="social-btn" target="_blank" title="TikTok">
                        <i class="bi bi-tiktok"></i>
                    </a>
                    <?php endif; ?>
                </div>

            </div>

        </div>

        <hr class="mt-5 mb-3 border-secondary opacity-10">

        <div class="row align-items-center small opacity-50">
            <div class="col-md-6 text-center text-md-start">
                &copy; <?= date('Y') ?> <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Sacco' ?>.
                All rights reserved.
            </div>
            <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                Powered by <span class="text-warning fw-bold">Bezalel Technologies LTD</span>
            </div>
        </div>

    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= ASSET_BASE ?>/js/main.js"></script>

</body>
</html>