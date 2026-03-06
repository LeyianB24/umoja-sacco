<?php
// member/layouts/footer.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>

<style>
/* ─── Member Portal Footer (Hope UI — Light/Dark aware) ─── */
.mf-footer {
    background: var(--bs-body-bg);
    border-top: 1px solid var(--bs-border-color-translucent);
    padding: 52px 0 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    margin-top: auto;
}

/* Brand */
.mf-brand-name {
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--bs-body-color);
    letter-spacing: -0.2px;
    margin-bottom: 3px;
}
.mf-brand-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    background: rgba(15,57,43,0.08);
    border: 1px solid rgba(15,57,43,0.1);
    display: flex; align-items: center; justify-content: center;
    color: #0F392B;
    font-size: 1rem;
    flex-shrink: 0;
}
[data-bs-theme="dark"] .mf-brand-icon {
    background: rgba(163,230,53,0.1);
    border-color: rgba(18, 27, 3, 0.15);
    color: #A3E635;
}
.mf-desc {
    font-size: 0.79rem;
    color: var(--bs-secondary-color);
    font-weight: 500;
    line-height: 1.75;
    margin-bottom: 20px;
    max-width: 300px;
}

/* App Buttons */
.mf-app-btn {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color-translucent);
    border-radius: 11px;
    padding: 8px 14px;
    text-decoration: none;
    transition: all 0.2s;
    color: var(--bs-body-color);
}
.mf-app-btn:hover {
    border-color: #0F392B;
    background: rgba(15,57,43,0.05);
    color: #0F392B;
    transform: translateY(-1px);
}
[data-bs-theme="dark"] .mf-app-btn:hover {
    border-color: rgba(163,230,53,0.3);
    background: rgba(163,230,53,0.07);
    color: #A3E635;
}
.mf-app-btn i { font-size: 1.1rem; color: var(--bs-secondary-color); flex-shrink: 0; }
.mf-app-btn .mf-app-sup  { font-size: 0.55rem; font-weight: 700; color: var(--bs-secondary-color); text-transform: uppercase; letter-spacing: 0.5px; display: block; }
.mf-app-btn .mf-app-name { font-size: 0.78rem; font-weight: 800; color: var(--bs-body-color); display: block; line-height: 1.1; }

/* Column heading */
.mf-col-heading {
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.1px;
    color: var(--bs-secondary-color);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.mf-col-heading::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--bs-border-color-translucent);
}

/* Links */
.mf-links { list-style: none; padding: 0; margin: 0; }
.mf-links li { margin-bottom: 3px; }
.mf-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 0.81rem;
    font-weight: 600;
    color: var(--bs-secondary-color);
    text-decoration: none;
    padding: 5px 0;
    transition: all 0.2s;
}
.mf-link i { font-size: 0.65rem; color: #0F392B; opacity: 0.35; transition: transform 0.2s, opacity 0.2s; }
[data-bs-theme="dark"] .mf-link i { color: #A3E635; }
.mf-link:hover { color: #0F392B; }
[data-bs-theme="dark"] .mf-link:hover { color: #A3E635; }
.mf-link:hover i { opacity: 1; transform: translateX(3px); }

/* Contact */
.mf-contact { list-style: none; padding: 0; margin: 0 0 20px; }
.mf-contact li {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 11px;
    font-size: 0.8rem;
    color: var(--bs-secondary-color);
    font-weight: 500;
    line-height: 1.5;
}
.mf-contact-icon {
    width: 28px; height: 28px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-size: 0.75rem;
    margin-top: 1px;
}
.mf-ci-loc   { background: rgba(245,200,66,0.12); color: #d97706; }
.mf-ci-mail  { background: rgba(99,102,241,0.12);  color: #6366f1; }
.mf-ci-phone { background: rgba(22,163,74,0.1);    color: #16a34a; }
.mf-contact a { color: inherit; text-decoration: none; transition: color 0.2s; }
.mf-contact a:hover { color: #0F392B; }
[data-bs-theme="dark"] .mf-contact a:hover { color: #A3E635; }

/* Social */
.mf-socials { display: flex; gap: 7px; flex-wrap: wrap; }
.mf-social-btn {
    width: 34px; height: 34px;
    border-radius: 9px;
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color-translucent);
    color: var(--bs-secondary-color);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.2s;
}
.mf-social-btn:hover {
    background: #0F392B;
    color: #fff;
    border-color: #0F392B;
    transform: translateY(-2px);
}

/* Bottom bar */
.mf-bottom {
    margin-top: 40px;
    border-top: 1px solid var(--bs-border-color-translucent);
    padding: 16px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.mf-bottom-copy {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--bs-secondary-color);
}
.mf-bottom-meta {
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--bs-secondary-color);
}
.mf-secure { display: flex; align-items: center; gap: 5px; color: #16a34a; }
.mf-secure i { font-size: 0.72rem; }
.mf-bottom-divider { width: 1px; height: 12px; background: var(--bs-border-color-translucent); }
</style>

<footer class="mf-footer" id="contact">
    <div class="container">
        <div class="row gy-5">

            <!-- ── Brand ── -->
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-10 mb-14" style="gap:10px;margin-bottom:14px;">
                    <div class="mf-brand-icon"><i class="bi bi-shield-check-fill"></i></div>
                    <div class="mf-brand-name"><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Umoja Sacco' ?></div>
                </div>
                <p class="mf-desc">
                    <?= defined('SITE_TAGLINE') ? htmlspecialchars(SITE_TAGLINE) : 'Empowering our drivers and partners with financial stability.' ?>
                    Save, borrow, and grow with a trusted community partner.
                </p>

                <div style="font-size:0.6rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,0.22);margin-bottom:10px;">
                    Mobile App
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="#" class="mf-app-btn">
                        <i class="bi bi-google-play"></i>
                        <div>
                            <span class="mf-app-sup">Get it on</span>
                            <span class="mf-app-name">Google Play</span>
                        </div>
                    </a>
                    <a href="#" class="mf-app-btn">
                        <i class="bi bi-apple"></i>
                        <div>
                            <span class="mf-app-sup">Download on</span>
                            <span class="mf-app-name">App Store</span>
                        </div>
                    </a>
                </div>
            </div>

            <!-- ── My Account ── -->
            <div class="col-lg-2 col-md-3 col-6">
                <div class="mf-col-heading">My Account</div>
                <ul class="mf-links">
                    <li><a href="dashboard.php"    class="mf-link"><i class="bi bi-chevron-right"></i> Dashboard</a></li>
                    <li><a href="loans.php"         class="mf-link"><i class="bi bi-chevron-right"></i> My Loans</a></li>
                    <li><a href="savings.php"       class="mf-link"><i class="bi bi-chevron-right"></i> Savings</a></li>
                    <li><a href="transactions.php"  class="mf-link"><i class="bi bi-chevron-right"></i> Transactions</a></li>
                    <li><a href="profile.php"       class="mf-link"><i class="bi bi-chevron-right"></i> My Profile</a></li>
                </ul>
            </div>

            <!-- ── Support ── -->
            <div class="col-lg-2 col-md-3 col-6">
                <div class="mf-col-heading">Support</div>
                <ul class="mf-links">
                    <li><a href="tickets.php"                                        class="mf-link"><i class="bi bi-chevron-right"></i> Help Desk</a></li>
                    <li><a href="<?= BASE_URL ?>/member/pages/terms.php"             class="mf-link"><i class="bi bi-chevron-right"></i> Terms of Service</a></li>
                    <li><a href="<?= BASE_URL ?>/member/pages/privacy.php"           class="mf-link"><i class="bi bi-chevron-right"></i> Privacy Policy</a></li>
                    <li><a href="<?= BASE_URL ?>/member/pages/faqs.php"              class="mf-link"><i class="bi bi-chevron-right"></i> FAQs</a></li>
                </ul>
            </div>

            <!-- ── Contact & Social ── -->
            <div class="col-lg-4 col-md-12">
                <div class="mf-col-heading">Contact Us</div>
                <ul class="mf-contact">
                    <li>
                        <span class="mf-contact-icon mf-ci-loc"><i class="bi bi-geo-alt-fill"></i></span>
                        <?= defined('COMPANY_ADDRESS') ? htmlspecialchars(COMPANY_ADDRESS) : 'Nairobi, Kenya' ?>
                    </li>
                    <li>
                        <span class="mf-contact-icon mf-ci-mail"><i class="bi bi-envelope-fill"></i></span>
                        <a href="mailto:<?= defined('COMPANY_EMAIL') ? COMPANY_EMAIL : '#' ?>">
                            <?= defined('COMPANY_EMAIL') ? htmlspecialchars(COMPANY_EMAIL) : 'support@example.com' ?>
                        </a>
                    </li>
                    <li>
                        <span class="mf-contact-icon mf-ci-phone"><i class="bi bi-telephone-fill"></i></span>
                        <a href="tel:<?= defined('COMPANY_PHONE') ? str_replace(' ', '', COMPANY_PHONE) : '#' ?>">
                            <?= defined('COMPANY_PHONE') ? htmlspecialchars(COMPANY_PHONE) : '+254 700 000 000' ?>
                        </a>
                    </li>
                </ul>

                <div style="font-size:0.6rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,0.22);margin-bottom:10px;">
                    Follow Us
                </div>
                <div class="mf-socials">
                    <?php if (defined('SOCIAL_FACEBOOK') && SOCIAL_FACEBOOK): ?>
                    <a href="<?= SOCIAL_FACEBOOK ?>" target="_blank" class="mf-social-btn" title="Facebook"><i class="bi bi-facebook"></i></a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_TWITTER') && SOCIAL_TWITTER): ?>
                    <a href="<?= SOCIAL_TWITTER ?>" target="_blank" class="mf-social-btn" title="Twitter/X"><i class="bi bi-twitter-x"></i></a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_INSTAGRAM') && SOCIAL_INSTAGRAM): ?>
                    <a href="<?= SOCIAL_INSTAGRAM ?>" target="_blank" class="mf-social-btn" title="Instagram"><i class="bi bi-instagram"></i></a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_YOUTUBE') && SOCIAL_YOUTUBE): ?>
                    <a href="<?= SOCIAL_YOUTUBE ?>" target="_blank" class="mf-social-btn" title="YouTube"><i class="bi bi-youtube"></i></a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_TIKTOK') && SOCIAL_TIKTOK): ?>
                    <a href="<?= SOCIAL_TIKTOK ?>" target="_blank" class="mf-social-btn" title="TikTok"><i class="bi bi-tiktok"></i></a>
                    <?php endif; ?>
                    <a href="https://wa.me/<?= defined('COMPANY_PHONE') ? preg_replace('/[^0-9]/', '', COMPANY_PHONE) : '' ?>"
                       target="_blank" class="mf-social-btn" title="WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                </div>
            </div>

        </div>

        <!-- Bottom Bar -->
        <div class="mf-bottom">
            <div class="mf-bottom-copy">
                &copy; <?= date('Y') ?> <strong><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Umoja Sacco' ?></strong>. All rights reserved.
            </div>
            <div class="mf-bottom-meta">
                <span class="mf-secure"><i class="bi bi-lock-fill"></i> SSL Secured</span>
                <span class="mf-bottom-divider"></span>
                <span>System v1.2</span>
            </div>
        </div>

    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= defined('ASSET_BASE') ? ASSET_BASE : '../public' ?>/assets/js/main.js"></script>
<script src="<?= defined('ASSET_BASE') ? ASSET_BASE : '../public' ?>/assets/js/sidebar.js"></script>
<script src="<?= defined('ASSET_BASE') ? ASSET_BASE : '../public' ?>/assets/js/darkmode.js"></script>
<script>
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));
</script>
</body>
</html>