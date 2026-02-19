<?php
// member/layouts/footer.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
    <footer class="footer mt-auto pt-5 pb-3 border-top" style="background-color: var(--nav-bg); border-top: 3px solid var(--forest, #0f2e25) !important;">
        <div class="container">
            <div class="row g-4 justify-content-between">
                
                <div class="col-lg-4 col-md-6">
                    <div class="mb-3">
                        <h4 class="fw-bold text-success mb-2">
                            <i class="bi bi-shield-check me-2"></i><?= defined('SITE_NAME') ? SITE_NAME : 'Umoja Sacco' ?>
                        </h4>
                        <p class="text-muted small mb-3">
                            <?= defined('SITE_TAGLINE') ? SITE_TAGLINE : 'Empowering our drivers and partners with financial stability.' ?> 
                            Save, borrow, and grow with a trusted community partner.
                        </p>
                    </div>
                    
                    <h6 class="text-uppercase small fw-bold text-secondary mb-2">Download Mobile App</h6>
                    <div class="d-flex gap-2">
                        <a href="#" class="btn btn-dark btn-sm rounded-3 d-flex align-items-center gap-2 px-3">
                            <i class="bi bi-google-play fs-5"></i>
                            <div class="text-start lh-1">
                                <span style="font-size: 0.6rem; display:block;">GET IT ON</span>
                                <span class="fw-bold" style="font-size: 0.8rem;">Google Play</span>
                            </div>
                        </a>
                        <a href="#" class="btn btn-outline-dark btn-sm rounded-3 d-flex align-items-center gap-2 px-3">
                            <i class="bi bi-apple fs-5"></i>
                            <div class="text-start lh-1">
                                <span style="font-size: 0.6rem; display:block;">Download on</span>
                                <span class="fw-bold" style="font-size: 0.8rem;">App Store</span>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="col-lg-2 col-md-3 col-6">
                    <h6 class="fw-bold mb-3 ">My Account</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2 small">
                        <li><a href="dashboard.php" class="text-decoration-none text-muted hover-success"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                        <li><a href="loans.php" class="text-decoration-none text-muted hover-success"><i class="bi bi-cash-stack me-2"></i>My Loans</a></li>
                        <li><a href="savings.php" class="text-decoration-none text-muted hover-success"><i class="bi bi-piggy-bank me-2"></i>Savings</a></li>
                        <li><a href="transactions.php" class="text-decoration-none text-muted hover-success"><i class="bi bi-file-text me-2"></i>Transactions</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-3 col-6">
                    <h6 class="fw-bold mb-3 ">Support</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2 small">
                        <li><a href="tickets.php" class="text-decoration-none text-muted hover-success">Help Desk</a></li>
                        <li><a href="<?= BASE_URL ?>/member/pages/terms.php" class="text-decoration-none text-muted hover-success">Terms of Service</a></li>
                        <li><a href="<?= BASE_URL ?>/member/pages/privacy.php" class="text-decoration-none text-muted hover-success">Privacy Policy</a></li>
                        <li><a href="<?= BASE_URL ?>/member/pages/faqs.php" class="text-decoration-none text-muted hover-success">FAQs</a></li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-12">
                    <h6 class="fw-bold mb-3 ">Contact Us</h6>
                    <ul class="list-unstyled small text-muted mb-4">
                        <li class="mb-2 d-flex"><i class="bi bi-geo-alt-fill text-success me-2"></i> <?= defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : 'Nairobi, Kenya' ?></li>
                        <li class="mb-2 d-flex"><i class="bi bi-envelope-fill text-success me-2"></i> <a href="mailto:<?= defined('COMPANY_EMAIL') ? COMPANY_EMAIL : '#' ?>" class="text-decoration-none text-muted"><?= defined('COMPANY_EMAIL') ? COMPANY_EMAIL : 'support@example.com' ?></a></li>
                        <li class="mb-2 d-flex"><i class="bi bi-telephone-fill text-success me-2"></i> <a href="tel:<?= defined('COMPANY_PHONE') ? str_replace(' ', '', COMPANY_PHONE) : '#' ?>" class="text-decoration-none text-muted"><?= defined('COMPANY_PHONE') ? COMPANY_PHONE : '+254 700 000 000' ?></a></li>
                    </ul>

                    <h6 class="fw-bold mb-2  small">Follow Us</h6>
                    <div class="d-flex gap-2">
                        <?php if(defined('SOCIAL_FACEBOOK')): ?>
                            <a href="<?= SOCIAL_FACEBOOK ?>" target="_blank" class="btn btn-sm btn-light rounded-circle shadow-sm text-primary"><i class="bi bi-facebook"></i></a>
                        <?php endif; ?>
                        <?php if(defined('SOCIAL_TWITTER')): ?>
                            <a href="<?= SOCIAL_TWITTER ?>" target="_blank" class="btn btn-sm btn-light rounded-circle shadow-sm "><i class="bi bi-twitter-x"></i></a>
                        <?php endif; ?>
                        <?php if(defined('SOCIAL_INSTAGRAM')): ?>
                            <a href="<?= SOCIAL_INSTAGRAM ?>" target="_blank" class="btn btn-sm btn-light rounded-circle shadow-sm text-danger"><i class="bi bi-instagram"></i></a>
                        <?php endif; ?>
                        <?php if(defined('SOCIAL_YOUTUBE')): ?>
                            <a href="<?= SOCIAL_YOUTUBE ?>" target="_blank" class="btn btn-sm btn-light rounded-circle shadow-sm text-danger"><i class="bi bi-youtube"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <hr class="my-4 text-muted opacity-25">

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small text-muted">
                <div class="mb-2 mb-md-0">
                    &copy; <?= date('Y') ?> <strong><?= defined('SITE_NAME') ? SITE_NAME : 'Umoja Sacco' ?></strong>. All rights reserved.
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="d-flex align-items-center gap-1" title="Encrypted Connection"><i class="bi bi-lock-fill text-success"></i> SSL Secured</span>
                    <div class="vr"></div>
                    <span>System v1.2</span>
                </div>
            </div>
        </div>
    </footer>

    <style>
        .hover-success:hover { color: #198754 !important; padding-left: 5px; transition: all 0.2s ease; }
        .footer a { transition: all 0.2s ease; }
        .btn-light:hover { background-color: #e2e6ea; transform: translateY(-2px); }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script src="<?= defined('ASSET_BASE') ? ASSET_BASE : '../public' ?>/assets/js/main.js"></script>
    <script src="<?= defined('ASSET_BASE') ? ASSET_BASE : '../public' ?>/assets/js/sidebar.js"></script>
    <script src="<?= defined('ASSET_BASE') ? ASSET_BASE : '../public' ?>/assets/js/darkmode.js"></script>

    <script>
        // Auto-initialize tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
    </script>
</body>
</html>