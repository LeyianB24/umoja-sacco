<?php
// member/layouts/footer.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<style>
/* ═══════════════════════════════════════════════════════════
   MEMBER FOOTER · HD EDITION · Forest & Lime · Plus Jakarta Sans
   Exact token match with savings / shares / welfare / sidebar
═══════════════════════════════════════════════════════════ */

/* Core palette scoped to footer */
.mf-footer {
    --f:      #0b2419; --fm: #154330; --fs: #1d6044;
    --lime:   #a3e635; --lt: #6a9a1a; --lg: rgba(163,230,53,.14);
    --bg:     #eff5f1; --bg2: #e8f1ec;
    --surf:   #ffffff; --surf2: #f7fbf8;
    --bdr:    rgba(11,36,25,.07); --bdr2: rgba(11,36,25,.04);
    --t1: #0b2419; --t2: #456859; --t3: #8fada0;
    --grn: #16a34a; --red: #dc2626; --amb: #d97706; --blu: #2563eb;
    --grn-bg: rgba(22,163,74,.08);
    --blu-bg: rgba(37,99,235,.08);
    --ease: cubic-bezier(.16,1,.3,1);
    --spring: cubic-bezier(.34,1.56,.64,1);
}
[data-bs-theme="dark"] .mf-footer {
    --bg: #070e0b; --bg2: #0a1510;
    --surf: #0d1d14; --surf2: #0a1810;
    --bdr: rgba(255,255,255,.07); --bdr2: rgba(255,255,255,.04);
    --t1: #d8eee2; --t2: #4d7a60; --t3: #2a4d38;
}
.mf-footer,.mf-footer * {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    -webkit-font-smoothing: antialiased;
}

/* Shell */
.mf-footer {
    background: var(--surf);
    margin-top: auto;
    position: relative;
    overflow: hidden;
}

/* Lime top bar (matches sidebar & hero pages) */
.mf-footer::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 2.5px;
    background: linear-gradient(90deg, var(--f) 0%, var(--lime) 55%, var(--f) 100%);
    z-index: 2;
}

/* Dot grid overlay */
.mf-bg-grid {
    position: absolute; inset: 0; pointer-events: none; z-index: 0;
    background-image: radial-gradient(rgba(11,36,25,.022) 1px, transparent 1px);
    background-size: 22px 22px;
}

/* Upper section */
.mf-upper {
    padding: 52px 52px 40px;
    position: relative; z-index: 1;
}
@media(max-width:767px){ .mf-upper{padding:36px 20px 28px} }

/* ── Brand ── */
.mf-brand-row {
    display: flex; align-items: center; gap: 11px;
    text-decoration: none; margin-bottom: 18px;
}
.mf-brand-icon {
    width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0;
    background: var(--f); border: 1px solid rgba(255,255,255,.06);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 14px rgba(11,36,25,.18);
    overflow: hidden; transition: transform .25s var(--spring);
}
.mf-brand-row:hover .mf-brand-icon { transform: scale(1.06) rotate(4deg); }
.mf-brand-icon img { width: 100%; height: 100%; object-fit: contain; padding: 5px; }
.mf-brand-name {
    font-size: .9rem; font-weight: 800; color: var(--t1);
    letter-spacing: -.2px; line-height: 1.2;
}
.mf-brand-tag {
    font-size: .58rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1px; color: var(--t3); margin-top: 3px;
    display: flex; align-items: center; gap: 5px;
}
.mf-brand-tag::before {
    content: ''; width: 5px; height: 5px; border-radius: 50%;
    background: var(--lime); flex-shrink: 0;
    animation: ftblink 2.5s ease-in-out infinite;
}
@keyframes ftblink{0%,100%{opacity:1}50%{opacity:.2}}

.mf-desc {
    font-size: .8rem; font-weight: 500; color: var(--t3);
    line-height: 1.75; margin-bottom: 24px; max-width: 295px;
}

/* Section label */
.mf-sec-label {
    display: flex; align-items: center; gap: 8px;
    font-size: 9px; font-weight: 800; text-transform: uppercase;
    letter-spacing: 1.1px; color: var(--t3); margin-bottom: 11px;
}
.mf-sec-label::after { content: ''; flex: 1; height: 1px; background: var(--bdr); }

/* App store buttons */
.mf-app-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
.mf-app-btn {
    display: inline-flex; align-items: center; gap: 9px;
    background: var(--surf2); border: 1px solid var(--bdr);
    border-radius: 12px; padding: 9px 14px;
    text-decoration: none; transition: all .22s var(--ease); color: var(--t1);
}
.mf-app-btn:hover {
    border-color: rgba(11,36,25,.18); background: var(--lg); color: var(--f);
    transform: translateY(-2px); box-shadow: 0 5px 14px rgba(11,36,25,.1);
}
[data-bs-theme="dark"] .mf-app-btn:hover { border-color: rgba(163,230,53,.35); color: var(--lime); }
.mf-app-btn i { font-size: 1.1rem; color: var(--t3); flex-shrink: 0; transition: color .18s ease; }
.mf-app-btn:hover i { color: var(--f); }
[data-bs-theme="dark"] .mf-app-btn:hover i { color: var(--lime); }
.mf-app-sup  { font-size: .52rem; font-weight: 700; color: var(--t3); text-transform: uppercase; letter-spacing: .5px; display: block; }
.mf-app-name { font-size: .78rem; font-weight: 800; color: var(--t1); display: block; line-height: 1.1; transition: color .18s ease; }
.mf-app-btn:hover .mf-app-name { color: var(--f); }
[data-bs-theme="dark"] .mf-app-btn:hover .mf-app-name { color: var(--lime); }

/* Trust badges */
.mf-trust-row { display: flex; gap: 7px; flex-wrap: wrap; }
.mf-trust-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: .68rem; font-weight: 800;
    background: var(--surf2); border: 1px solid var(--bdr);
    border-radius: 50px; padding: 4px 11px; color: var(--t3);
}
.mf-trust-badge i { font-size: .65rem; }
.mf-tb-secure { background: var(--grn-bg); border-color: rgba(22,163,74,.2); color: var(--grn); }
.mf-tb-mpesa  { background: var(--grn-bg); border-color: rgba(22,163,74,.2); color: var(--grn); }
.mf-tb-cbk    { background: var(--blu-bg); border-color: rgba(37,99,235,.2); color: var(--blu); }

/* Column headings */
.mf-col-head {
    font-size: 9px; font-weight: 800; text-transform: uppercase;
    letter-spacing: 1.1px; color: var(--t3); margin-bottom: 18px;
    display: flex; align-items: center; gap: 8px;
}
.mf-col-head::after { content: ''; flex: 1; height: 1px; background: var(--bdr); }

/* Nav links */
.mf-links { list-style: none; padding: 0; margin: 0; }
.mf-link {
    display: inline-flex; align-items: center; gap: 9px;
    font-size: .82rem; font-weight: 600; color: var(--t3);
    text-decoration: none; padding: 5px 0;
    transition: all .2s var(--ease);
}
.mf-link-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: var(--t3); opacity: .25; flex-shrink: 0;
    transition: all .2s var(--ease);
}
.mf-link:hover { color: var(--f); padding-left: 5px; }
.mf-link:hover .mf-link-dot { opacity: 1; background: var(--lime); transform: scale(1.5); }
[data-bs-theme="dark"] .mf-link:hover { color: var(--lime); }

/* Contact list */
.mf-contact { list-style: none; padding: 0; margin: 0 0 20px; }
.mf-contact li {
    display: flex; align-items: flex-start; gap: 10px;
    margin-bottom: 11px; font-size: .8rem;
    color: var(--t3); font-weight: 500; line-height: 1.55;
}
.mf-ci {
    width: 30px; height: 30px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: .75rem; margin-top: 1px;
    transition: transform .22s var(--spring);
}
.mf-contact li:hover .mf-ci { transform: scale(1.1) rotate(5deg); }
.mf-ci-loc   { background: rgba(217,119,6,.08); color: var(--amb); }
.mf-ci-mail  { background: var(--blu-bg); color: var(--blu); }
.mf-ci-phone { background: var(--grn-bg); color: var(--grn); }
.mf-contact a { color: inherit; text-decoration: none; transition: color .18s ease; }
.mf-contact a:hover { color: var(--f); }
[data-bs-theme="dark"] .mf-contact a:hover { color: var(--lime); }

/* Hours table */
.mf-hours { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
.mf-hours tr { border-bottom: 1px solid var(--bdr2); }
.mf-hours tr:last-child { border-bottom: none; }
.mf-hours td { padding: 6px 0; font-size: .76rem; }
.mf-hours .hday { font-weight: 600; color: var(--t3); }
.mf-hours .htime{ font-weight: 800; color: var(--t1); text-align: right; }
.mf-hours .htime.closed { color: var(--t3); }

/* Social buttons */
.mf-socials { display: flex; gap: 7px; flex-wrap: wrap; }
.mf-social-btn {
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--surf2); border: 1px solid var(--bdr);
    color: var(--t3); display: flex; align-items: center;
    justify-content: center; font-size: .85rem;
    text-decoration: none; transition: all .22s var(--ease);
}
.mf-social-btn:hover {
    background: var(--f); color: var(--lime); border-color: var(--f);
    transform: translateY(-3px) scale(1.06);
    box-shadow: 0 5px 14px rgba(11,36,25,.22);
}
[data-bs-theme="dark"] .mf-social-btn:hover {
    background: var(--lg); color: var(--lime); border-color: rgba(163,230,53,.3);
}

/* Divider */
.mf-divider { border: none; border-top: 1px solid var(--bdr); margin: 0; }

/* Bottom bar */
.mf-bottom {
    padding: 16px 52px;
    display: flex; align-items: center;
    justify-content: space-between; flex-wrap: wrap; gap: 10px;
    background: var(--surf2); position: relative; z-index: 1;
}
@media(max-width:767px){ .mf-bottom{padding:14px 20px; flex-direction:column; text-align:center; gap:8px} }

.mf-copy {
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
    font-size: .72rem; font-weight: 600; color: var(--t3);
}
.mf-copy strong { color: var(--t1); }
.mf-cdot { width: 3px; height: 3px; border-radius: 50%; background: var(--bdr); }

.mf-meta {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    font-size: .7rem; font-weight: 600; color: var(--t3);
}
.mf-msep { width: 1px; height: 12px; background: var(--bdr); }
.mf-mlink { color: var(--t3); text-decoration: none; transition: color .18s ease; }
.mf-mlink:hover { color: var(--f); }
[data-bs-theme="dark"] .mf-mlink:hover { color: var(--lime); }

.mf-ssl { display: inline-flex; align-items: center; gap: 4px; color: var(--grn); font-size: .68rem; font-weight: 800; }

/* Exec time */
.mf-exec-fast { color: var(--grn); }
.mf-exec-ok   { color: var(--amb); }
.mf-exec-slow { color: var(--red); }

@media print { .mf-footer{display:none !important} }
::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-thumb{background:var(--bdr);border-radius:99px}
</style>

<footer class="mf-footer" id="contact">
    <div class="mf-bg-grid"></div>

    <div class="mf-upper">
        <div class="row gy-5">

            <!-- ── Brand ── -->
            <div class="col-lg-4 col-md-6">

                <a href="<?= BASE_URL ?>/public/index.php" class="mf-brand-row">
                    <div class="mf-brand-icon">
                        <img src="<?= defined('SITE_LOGO') ? SITE_LOGO : '' ?>"
                             alt="<?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Logo' ?>">
                    </div>
                    <div>
                        <div class="mf-brand-name"><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Umoja Sacco' ?></div>
                        <div class="mf-brand-tag">Trusted Financial Partner</div>
                    </div>
                </a>

                <p class="mf-desc">
                    <?= defined('SITE_TAGLINE') ? htmlspecialchars(SITE_TAGLINE) : 'Empowering our members with financial stability.' ?>
                    Save, borrow, and grow with a trusted community partner.
                </p>

                <div class="mf-sec-label">Mobile App</div>
                <div class="mf-app-row">
                    <a href="#" class="mf-app-btn">
                        <i class="bi bi-google-play"></i>
                        <div><span class="mf-app-sup">Get it on</span><span class="mf-app-name">Google Play</span></div>
                    </a>
                    <a href="#" class="mf-app-btn">
                        <i class="bi bi-apple"></i>
                        <div><span class="mf-app-sup">Download on</span><span class="mf-app-name">App Store</span></div>
                    </a>
                </div>

                <div class="mf-trust-row">
                    <span class="mf-trust-badge mf-tb-secure"><i class="bi bi-shield-lock-fill"></i> SSL Secured</span>
                    <span class="mf-trust-badge mf-tb-mpesa"><i class="bi bi-phone-fill"></i> M-Pesa Ready</span>
                    <span class="mf-trust-badge mf-tb-cbk"><i class="bi bi-bank2"></i> CBK Regulated</span>
                </div>

            </div>

            <!-- ── My Account ── -->
            <div class="col-lg-2 col-md-3 col-6">
                <div class="mf-col-head">My Account</div>
                <ul class="mf-links">
                    <?php foreach ([
                        ['dashboard.php',    'Dashboard'],
                        ['savings.php',      'Savings'],
                        ['shares.php',       'Shares'],
                        ['loans.php',        'My Loans'],
                        ['contributions.php','Contributions'],
                        ['transactions.php', 'Transactions'],
                        ['welfare.php',      'Welfare Hub'],
                    ] as [$href,$lbl]): ?>
                    <li><a href="<?= $href ?>" class="mf-link"><span class="mf-link-dot"></span><?= $lbl ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- ── Help & Legal ── -->
            <div class="col-lg-2 col-md-3 col-6">
                <div class="mf-col-head">Help & Legal</div>
                <ul class="mf-links">
                    <?php foreach ([
                        ['support.php',                        'Help Center'],
                        ['notifications.php',                  'Notifications'],
                        ['profile.php',                        'My Profile'],
                        ['settings.php',                       'Settings'],
                        [BASE_URL.'/member/pages/terms.php',   'Terms of Service'],
                        [BASE_URL.'/member/pages/privacy.php', 'Privacy Policy'],
                        [BASE_URL.'/member/pages/faqs.php',    'FAQs'],
                    ] as [$href,$lbl]): ?>
                    <li><a href="<?= $href ?>" class="mf-link"><span class="mf-link-dot"></span><?= $lbl ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- ── Contact & Social ── -->
            <div class="col-lg-4 col-md-12">
                <div class="mf-col-head">Get in Touch</div>

                <ul class="mf-contact">
                    <li>
                        <span class="mf-ci mf-ci-loc"><i class="bi bi-geo-alt-fill"></i></span>
                        <span><?= defined('COMPANY_ADDRESS') ? htmlspecialchars(COMPANY_ADDRESS) : 'Nairobi, Kenya' ?></span>
                    </li>
                    <li>
                        <span class="mf-ci mf-ci-mail"><i class="bi bi-envelope-fill"></i></span>
                        <a href="mailto:<?= defined('COMPANY_EMAIL') ? htmlspecialchars(COMPANY_EMAIL) : '#' ?>">
                            <?= defined('COMPANY_EMAIL') ? htmlspecialchars(COMPANY_EMAIL) : 'support@example.com' ?>
                        </a>
                    </li>
                    <li>
                        <span class="mf-ci mf-ci-phone"><i class="bi bi-telephone-fill"></i></span>
                        <a href="tel:<?= defined('COMPANY_PHONE') ? preg_replace('/[^0-9+]/', '', COMPANY_PHONE) : '#' ?>">
                            <?= defined('COMPANY_PHONE') ? htmlspecialchars(COMPANY_PHONE) : '+254 700 000 000' ?>
                        </a>
                    </li>
                </ul>

                <!-- Office hours -->
                <div class="mf-sec-label">Office Hours</div>
                <table class="mf-hours">
                    <?php foreach ([
                        ['Mon – Fri', '08:00 – 17:00', false],
                        ['Saturday',  '09:00 – 13:00', false],
                        ['Sunday',    'Closed',         true],
                    ] as [$day,$hrs,$cl]): ?>
                    <tr>
                        <td class="hday"><?= $day ?></td>
                        <td class="htime<?= $cl?' closed':'' ?>"><?= $hrs ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <!-- Social -->
                <div class="mf-sec-label">Follow Us</div>
                <div class="mf-socials">
                    <?php if (defined('SOCIAL_FACEBOOK')  && SOCIAL_FACEBOOK):  ?><a href="<?= SOCIAL_FACEBOOK ?>"  target="_blank" rel="noopener" class="mf-social-btn" title="Facebook"><i class="bi bi-facebook"></i></a><?php endif; ?>
                    <?php if (defined('SOCIAL_TWITTER')   && SOCIAL_TWITTER):   ?><a href="<?= SOCIAL_TWITTER ?>"   target="_blank" rel="noopener" class="mf-social-btn" title="Twitter / X"><i class="bi bi-twitter-x"></i></a><?php endif; ?>
                    <?php if (defined('SOCIAL_INSTAGRAM') && SOCIAL_INSTAGRAM): ?><a href="<?= SOCIAL_INSTAGRAM ?>" target="_blank" rel="noopener" class="mf-social-btn" title="Instagram"><i class="bi bi-instagram"></i></a><?php endif; ?>
                    <?php if (defined('SOCIAL_YOUTUBE')   && SOCIAL_YOUTUBE):   ?><a href="<?= SOCIAL_YOUTUBE ?>"   target="_blank" rel="noopener" class="mf-social-btn" title="YouTube"><i class="bi bi-youtube"></i></a><?php endif; ?>
                    <?php if (defined('SOCIAL_TIKTOK')    && SOCIAL_TIKTOK):    ?><a href="<?= SOCIAL_TIKTOK ?>"    target="_blank" rel="noopener" class="mf-social-btn" title="TikTok"><i class="bi bi-tiktok"></i></a><?php endif; ?>
                    <a href="https://wa.me/<?= defined('COMPANY_PHONE') ? preg_replace('/[^0-9]/', '', COMPANY_PHONE) : '' ?>"
                       target="_blank" rel="noopener" class="mf-social-btn" title="WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                    <a href="<?= BASE_URL ?>/member/pages/support.php" class="mf-social-btn" title="Support Portal">
                        <i class="bi bi-headset"></i>
                    </a>
                </div>

            </div>
        </div>
    </div>

    <hr class="mf-divider">

    <!-- Bottom bar -->
    <div class="mf-bottom">
        <div class="mf-copy">
            &copy; <?= date('Y') ?>
            <span class="mf-cdot"></span>
            <strong><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Umoja Sacco' ?></strong>
            <span class="mf-cdot"></span>
            All rights reserved.
            <?php if (defined('APP_START_TIME')): $ms = round((microtime(true)-APP_START_TIME)*1000,1); $cls = $ms<200?'mf-exec-fast':($ms<600?'mf-exec-ok':'mf-exec-slow'); ?>
            <span class="mf-cdot"></span>
            <span class="<?= $cls ?>" style="font-size:.65rem"><i class="bi bi-lightning-charge-fill"></i> <?= $ms ?>ms</span>
            <?php endif; ?>
        </div>
        <div class="mf-meta">
            <span class="mf-ssl"><i class="bi bi-shield-lock-fill"></i> SSL Secured</span>
            <span class="mf-msep"></span>
            <a href="<?= BASE_URL ?>/member/pages/privacy.php" class="mf-mlink">Privacy</a>
            <span class="mf-msep"></span>
            <a href="<?= BASE_URL ?>/member/pages/terms.php"   class="mf-mlink">Terms</a>
            <span class="mf-msep"></span>
            <a href="<?= BASE_URL ?>/member/pages/faqs.php"    class="mf-mlink">FAQs</a>
            <span class="mf-msep"></span>
            <span style="font-size:.65rem;color:var(--t3)">v1.2</span>
        </div>
    </div>

</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= defined('ASSET_BASE') ? ASSET_BASE : '../public/assets' ?>/js/main.js"></script>
<script src="<?= defined('ASSET_BASE') ? ASSET_BASE : '../public/assets' ?>/js/sidebar.js"></script>
<script src="<?= defined('ASSET_BASE') ? ASSET_BASE : '../public/assets' ?>/js/darkmode.js"></script>
<script src="<?= defined('ASSET_BASE') ? ASSET_BASE : '../public/assets' ?>/js/live-refresh.js"></script>
<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>
</body>
</html>