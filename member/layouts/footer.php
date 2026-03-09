<?php
// member/layouts/footer.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>

<style>
/* ── Font ───────────────────────────────────────────────────── */
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

/* ── Tokens ─────────────────────────────────────────────────── */
:root {
    --ft-forest:       #1a3a2a;
    --ft-forest-mid:   #234d38;
    --ft-lime:         #a8e063;
    --ft-lime-glow:    rgba(168,224,99,.14);
    --ft-surface:      #ffffff;
    --ft-surface-2:    #f5f8f5;
    --ft-border:       #e3ebe5;
    --ft-ink:          #111c14;
    --ft-muted:        #6b7f72;
    --ft-shadow:       0 -1px 0 #e3ebe5;
    --ft-radius:       12px;
    --ft-transition:   all .2s cubic-bezier(.4,0,.2,1);
}

[data-bs-theme="dark"] {
    --ft-surface:      #141f18;
    --ft-surface-2:    #1a2820;
    --ft-border:       #2a3d30;
    --ft-ink:          #e4ede6;
    --ft-muted:        #7a9485;
    --ft-shadow:       0 -1px 0 #2a3d30;
}

/* ── Shell ──────────────────────────────────────────────────── */
.mf-footer {
    background: var(--ft-surface);
    box-shadow: var(--ft-shadow);
    padding: 56px 0 0;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    margin-top: auto;
    position: relative;
    overflow: hidden;
}

/* Subtle top mesh */
.mf-footer::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--ft-forest) 0%, var(--ft-lime) 50%, var(--ft-forest) 100%);
}

/* ── Brand column ───────────────────────────────────────────── */
.mf-brand-row { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }

.mf-brand-icon {
    width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--ft-forest), #2e6347);
    display: flex; align-items: center; justify-content: center;
    color: var(--ft-lime); font-size: 1rem;
    box-shadow: 0 4px 12px rgba(26,58,42,.2);
}

.mf-brand-name {
    font-size: .92rem; font-weight: 800; color: var(--ft-ink); letter-spacing: -.2px; line-height: 1;
}
.mf-brand-tagline { font-size: .68rem; font-weight: 600; color: var(--ft-muted); margin-top: 2px; }

.mf-desc {
    font-size: .8rem; font-weight: 500; color: var(--ft-muted);
    line-height: 1.75; margin-bottom: 24px; max-width: 300px;
}

/* Mobile app section label */
.mf-section-label {
    font-size: .6rem; font-weight: 800; letter-spacing: 1.1px;
    text-transform: uppercase; color: var(--ft-muted);
    margin-bottom: 10px; display: flex; align-items: center; gap: 8px;
}
.mf-section-label::after { content: ''; flex: 1; height: 1px; background: var(--ft-border); }

/* App store buttons */
.mf-app-btns { display: flex; gap: 8px; flex-wrap: wrap; }
.mf-app-btn {
    display: inline-flex; align-items: center; gap: 9px;
    background: var(--ft-surface-2); border: 1.5px solid var(--ft-border);
    border-radius: 11px; padding: 9px 14px;
    text-decoration: none; transition: var(--ft-transition);
    color: var(--ft-ink);
}
.mf-app-btn:hover {
    border-color: var(--ft-forest); background: var(--ft-lime-glow);
    color: var(--ft-forest); transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(26,58,42,.12);
}
[data-bs-theme="dark"] .mf-app-btn:hover { border-color: rgba(168,224,99,.4); color: var(--ft-lime); }
.mf-app-btn i { font-size: 1.1rem; color: var(--ft-muted); flex-shrink: 0; transition: var(--ft-transition); }
.mf-app-btn:hover i { color: var(--ft-forest); }
[data-bs-theme="dark"] .mf-app-btn:hover i { color: var(--ft-lime); }
.mf-app-sup  { font-size: .53rem; font-weight: 700; color: var(--ft-muted); text-transform: uppercase; letter-spacing: .5px; display: block; }
.mf-app-name { font-size: .78rem; font-weight: 800; color: var(--ft-ink); display: block; line-height: 1.1; transition: var(--ft-transition); }
.mf-app-btn:hover .mf-app-name { color: var(--ft-forest); }
[data-bs-theme="dark"] .mf-app-btn:hover .mf-app-name { color: var(--ft-lime); }

/* ── Column headings ────────────────────────────────────────── */
.mf-col-heading {
    font-size: .62rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 1.1px; color: var(--ft-muted);
    margin-bottom: 18px; display: flex; align-items: center; gap: 8px;
}
.mf-col-heading::after { content: ''; flex: 1; height: 1px; background: var(--ft-border); }

/* ── Nav links ──────────────────────────────────────────────── */
.mf-links { list-style: none; padding: 0; margin: 0; }
.mf-links li { margin-bottom: 1px; }
.mf-link {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: .82rem; font-weight: 600; color: var(--ft-muted);
    text-decoration: none; padding: 5px 0; transition: var(--ft-transition);
    position: relative;
}
.mf-link-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: var(--ft-forest); opacity: .2; flex-shrink: 0;
    transition: var(--ft-transition);
}
[data-bs-theme="dark"] .mf-link-dot { background: var(--ft-lime); }
.mf-link:hover { color: var(--ft-forest); padding-left: 4px; }
[data-bs-theme="dark"] .mf-link:hover { color: var(--ft-lime); }
.mf-link:hover .mf-link-dot { opacity: 1; background: var(--ft-lime); transform: scale(1.4); }

/* ── Contact ────────────────────────────────────────────────── */
.mf-contact { list-style: none; padding: 0; margin: 0 0 22px; }
.mf-contact li {
    display: flex; align-items: flex-start; gap: 10px;
    margin-bottom: 12px; font-size: .8rem;
    color: var(--ft-muted); font-weight: 500; line-height: 1.5;
}
.mf-contact-icon {
    width: 30px; height: 30px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: .75rem; margin-top: 1px;
}
.mf-ci-loc   { background: rgba(217,119,6,.1);   color: #d97706; }
.mf-ci-mail  { background: rgba(99,102,241,.1);   color: #6366f1; }
.mf-ci-phone { background: rgba(22,163,74,.1);    color: #16a34a; }
.mf-contact a { color: inherit; text-decoration: none; transition: var(--ft-transition); }
.mf-contact a:hover { color: var(--ft-forest); }
[data-bs-theme="dark"] .mf-contact a:hover { color: var(--ft-lime); }

/* ── Socials ────────────────────────────────────────────────── */
.mf-socials { display: flex; gap: 8px; flex-wrap: wrap; }
.mf-social-btn {
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--ft-surface-2); border: 1.5px solid var(--ft-border);
    color: var(--ft-muted); display: flex; align-items: center;
    justify-content: center; font-size: .85rem;
    text-decoration: none; transition: var(--ft-transition);
}
.mf-social-btn:hover {
    background: var(--ft-forest); color: var(--ft-lime);
    border-color: var(--ft-forest); transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26,58,42,.22);
}
[data-bs-theme="dark"] .mf-social-btn:hover {
    background: var(--ft-lime-glow); color: var(--ft-lime);
    border-color: rgba(168,224,99,.4);
}

/* ── Trust badges strip ─────────────────────────────────────── */
.mf-trust-strip {
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap; margin-top: 22px;
}
.mf-trust-badge {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .68rem; font-weight: 700; color: var(--ft-muted);
    background: var(--ft-surface-2); border: 1px solid var(--ft-border);
    border-radius: 100px; padding: 5px 12px;
}
.mf-trust-badge i { font-size: .7rem; }
.mf-trust-badge.secure  { color: #16a34a; background: rgba(22,163,74,.07); border-color: rgba(22,163,74,.2); }
.mf-trust-badge.mpesa   { color: #16a34a; background: rgba(22,163,74,.07); border-color: rgba(22,163,74,.2); }
.mf-trust-badge.central { color: #1d4ed8; background: rgba(29,78,216,.06); border-color: rgba(29,78,216,.15); }

/* ── Bottom bar ─────────────────────────────────────────────── */
.mf-bottom {
    margin-top: 44px;
    border-top: 1px solid var(--ft-border);
    padding: 18px 0;
    display: flex; align-items: center;
    justify-content: space-between; flex-wrap: wrap; gap: 10px;
}
.mf-bottom-copy {
    font-size: .72rem; font-weight: 600; color: var(--ft-muted);
    display: flex; align-items: center; gap: 6px;
}
.mf-bottom-copy strong { color: var(--ft-ink); }
.mf-bottom-copy .copy-dot { width: 3px; height: 3px; border-radius: 50%; background: var(--ft-border); }
.mf-bottom-meta {
    display: flex; align-items: center; gap: 10px;
    font-size: .7rem; font-weight: 600; color: var(--ft-muted);
}
.mf-bottom-divider { width: 1px; height: 12px; background: var(--ft-border); }
.mf-bottom-link { color: var(--ft-muted); text-decoration: none; transition: var(--ft-transition); }
.mf-bottom-link:hover { color: var(--ft-forest); }
[data-bs-theme="dark"] .mf-bottom-link:hover { color: var(--ft-lime); }
.mf-secure-badge { display: flex; align-items: center; gap: 4px; color: #16a34a; }

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 767px) {
    .mf-footer { padding: 40px 0 0; }
    .mf-bottom { flex-direction: column; text-align: center; gap: 6px; }
}
</style>

<footer class="mf-footer" id="contact">
    <div class="container">
        <div class="row gy-5">

            <!-- ── Brand ─────────────────────────────────────── -->
            <div class="col-lg-4 col-md-6">
                <div class="mf-brand-row">
                    <div class="mf-brand-icon"><i class="bi bi-shield-check-fill"></i></div>
                    <div>
                        <div class="mf-brand-name"><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Umoja Sacco' ?></div>
                        <div class="mf-brand-tagline">Trusted Financial Partner</div>
                    </div>
                </div>
                <p class="mf-desc">
                    <?= defined('SITE_TAGLINE') ? htmlspecialchars(SITE_TAGLINE) : 'Empowering our drivers and partners with financial stability.' ?>
                    Save, borrow, and grow with a trusted community partner.
                </p>

                <!-- Mobile App -->
                <div class="mf-section-label">Mobile App</div>
                <div class="mf-app-btns">
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

                <!-- Trust badges -->
                <div class="mf-trust-strip">
                    <span class="mf-trust-badge secure"><i class="bi bi-lock-fill"></i>SSL Secured</span>
                    <span class="mf-trust-badge mpesa"><i class="bi bi-phone-fill"></i>M-Pesa Ready</span>
                    <span class="mf-trust-badge central"><i class="bi bi-bank2"></i>CBK Regulated</span>
                </div>
            </div>

            <!-- ── My Account ─────────────────────────────────── -->
            <div class="col-lg-2 col-md-3 col-6">
                <div class="mf-col-heading">My Account</div>
                <ul class="mf-links">
                    <li><a href="dashboard.php"   class="mf-link"><span class="mf-link-dot"></span>Dashboard</a></li>
                    <li><a href="loans.php"        class="mf-link"><span class="mf-link-dot"></span>My Loans</a></li>
                    <li><a href="savings.php"      class="mf-link"><span class="mf-link-dot"></span>Savings</a></li>
                    <li><a href="transactions.php" class="mf-link"><span class="mf-link-dot"></span>Transactions</a></li>
                    <li><a href="profile.php"      class="mf-link"><span class="mf-link-dot"></span>My Profile</a></li>
                </ul>
            </div>

            <!-- ── Support ───────────────────────────────────── -->
            <div class="col-lg-2 col-md-3 col-6">
                <div class="mf-col-heading">Support</div>
                <ul class="mf-links">
                    <li><a href="tickets.php"                                       class="mf-link"><span class="mf-link-dot"></span>Help Desk</a></li>
                    <li><a href="<?= BASE_URL ?>/member/pages/terms.php"            class="mf-link"><span class="mf-link-dot"></span>Terms of Service</a></li>
                    <li><a href="<?= BASE_URL ?>/member/pages/privacy.php"          class="mf-link"><span class="mf-link-dot"></span>Privacy Policy</a></li>
                    <li><a href="<?= BASE_URL ?>/member/pages/faqs.php"             class="mf-link"><span class="mf-link-dot"></span>FAQs</a></li>
                </ul>
            </div>

            <!-- ── Contact & Social ──────────────────────────── -->
            <div class="col-lg-4 col-md-12">
                <div class="mf-col-heading">Contact Us</div>
                <ul class="mf-contact">
                    <li>
                        <span class="mf-contact-icon mf-ci-loc"><i class="bi bi-geo-alt-fill"></i></span>
                        <span><?= defined('COMPANY_ADDRESS') ? htmlspecialchars(COMPANY_ADDRESS) : 'Nairobi, Kenya' ?></span>
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

                <div class="mf-section-label">Follow Us</div>
                <div class="mf-socials">
                    <?php if (defined('SOCIAL_FACEBOOK') && SOCIAL_FACEBOOK): ?>
                    <a href="<?= SOCIAL_FACEBOOK ?>" target="_blank" rel="noopener" class="mf-social-btn" title="Facebook"><i class="bi bi-facebook"></i></a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_TWITTER') && SOCIAL_TWITTER): ?>
                    <a href="<?= SOCIAL_TWITTER ?>" target="_blank" rel="noopener" class="mf-social-btn" title="Twitter / X"><i class="bi bi-twitter-x"></i></a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_INSTAGRAM') && SOCIAL_INSTAGRAM): ?>
                    <a href="<?= SOCIAL_INSTAGRAM ?>" target="_blank" rel="noopener" class="mf-social-btn" title="Instagram"><i class="bi bi-instagram"></i></a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_YOUTUBE') && SOCIAL_YOUTUBE): ?>
                    <a href="<?= SOCIAL_YOUTUBE ?>" target="_blank" rel="noopener" class="mf-social-btn" title="YouTube"><i class="bi bi-youtube"></i></a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_TIKTOK') && SOCIAL_TIKTOK): ?>
                    <a href="<?= SOCIAL_TIKTOK ?>" target="_blank" rel="noopener" class="mf-social-btn" title="TikTok"><i class="bi bi-tiktok"></i></a>
                    <?php endif; ?>
                    <a href="https://wa.me/<?= defined('COMPANY_PHONE') ? preg_replace('/[^0-9]/', '', COMPANY_PHONE) : '' ?>"
                       target="_blank" rel="noopener" class="mf-social-btn" title="WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                </div>
            </div>

        </div><!-- /row -->

        <!-- ── Bottom bar ──────────────────────────────────────── -->
        <div class="mf-bottom">
            <div class="mf-bottom-copy">
                &copy; <?= date('Y') ?>
                <span class="copy-dot"></span>
                <strong><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Umoja Sacco' ?></strong>
                <span class="copy-dot"></span>
                All rights reserved.
            </div>
            <div class="mf-bottom-meta">
                <span class="mf-secure-badge"><i class="bi bi-lock-fill"></i>SSL Secured</span>
                <span class="mf-bottom-divider"></span>
                <a href="<?= BASE_URL ?>/member/pages/privacy.php" class="mf-bottom-link">Privacy</a>
                <span class="mf-bottom-divider"></span>
                <a href="<?= BASE_URL ?>/member/pages/terms.php" class="mf-bottom-link">Terms</a>
                <span class="mf-bottom-divider"></span>
                <span>v1.2</span>
            </div>
        </div>

    </div><!-- /container -->
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= defined('ASSET_BASE') ? ASSET_BASE : '../public' ?>/assets/js/main.js"></script>
<script src="<?= defined('ASSET_BASE') ? ASSET_BASE : '../public' ?>/assets/js/sidebar.js"></script>
<script src="<?= defined('ASSET_BASE') ? ASSET_BASE : '../public' ?>/assets/js/darkmode.js"></script>
<script>
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>
</body>
</html>