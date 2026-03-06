<?php
// inc/footer.php
if (!defined('OFFICE_PHONE')) {
    define('OFFICE_PHONE',    '+254 700 000 000');
    define('OFFICE_EMAIL',    'info@umojadriverssacco.co.ke');
    define('OFFICE_LOCATION', 'Umoja House, Nairobi');
}
?>

<style>
/* ─── Footer ─── */
.lp-footer {
    background: linear-gradient(180deg, #0d2218 0%, #081812 100%);
    border-top: 1px solid rgba(255,255,255,0.06);
    padding: 72px 0 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    position: relative;
    overflow: hidden;
}
.lp-footer::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 360px; height: 360px;
    background: radial-gradient(circle, rgba(57,181,74,0.07) 0%, transparent 65%);
    border-radius: 50%;
    pointer-events: none;
}
.lp-footer::after {
    content: '';
    position: absolute;
    bottom: 60px; left: -60px;
    width: 240px; height: 240px;
    background: radial-gradient(circle, rgba(163,230,53,0.05) 0%, transparent 65%);
    border-radius: 50%;
    pointer-events: none;
}

/* Brand Column */
.ft-logo-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
}
.ft-logo-img {
    width: 44px; height: 44px;
    border-radius: 12px;
    background: #fff;
    padding: 4px;
    object-fit: contain;
    border: 1.5px solid rgba(163,230,53,0.3);
    box-shadow: 0 4px 14px rgba(0,0,0,0.25);
    flex-shrink: 0;
}
.ft-brand-name {
    font-size: 0.92rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.2px;
    line-height: 1.2;
}
.ft-brand-sub {
    font-size: 0.6rem;
    font-weight: 700;
    color: rgba(255,255,255,0.3);
    text-transform: uppercase;
    letter-spacing: 0.8px;
}
.ft-desc {
    font-size: 0.82rem;
    color: rgba(255,255,255,0.42);
    font-weight: 500;
    line-height: 1.75;
    margin-bottom: 22px;
    max-width: 320px;
}
.ft-pillars-label {
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(255,255,255,0.25);
    margin-bottom: 10px;
}
.ft-pillars {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}
.ft-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(163,230,53,0.08);
    border: 1px solid rgba(163,230,53,0.15);
    border-radius: 100px;
    padding: 5px 11px;
    font-size: 0.7rem;
    font-weight: 700;
    color: rgba(255,255,255,0.65);
    transition: all 0.2s;
}
.ft-pill:hover { background: rgba(163,230,53,0.14); color: #A3E635; }
.ft-pill i { color: #A3E635; font-size: 0.72rem; }

/* Column Headings */
.ft-col-heading {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.1px;
    color: rgba(255,255,255,0.35);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ft-col-heading::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(255,255,255,0.06);
}

/* Quick Links */
.ft-links { list-style: none; padding: 0; margin: 0; }
.ft-links li { margin-bottom: 4px; }
.ft-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 0.84rem;
    font-weight: 600;
    color: rgba(255,255,255,0.48);
    text-decoration: none;
    padding: 6px 0;
    transition: all 0.2s;
    border-radius: 8px;
}
.ft-link i {
    font-size: 0.65rem;
    color: rgba(163,230,53,0.5);
    transition: transform 0.2s;
}
.ft-link:hover { color: rgba(255,255,255,0.85); }
.ft-link:hover i { color: #A3E635; transform: translateX(3px); }

/* Contact List */
.ft-contact { list-style: none; padding: 0; margin: 0 0 24px; }
.ft-contact li {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 14px;
    font-size: 0.82rem;
    color: rgba(255,255,255,0.48);
    font-weight: 500;
    line-height: 1.55;
}
.ft-contact-icon {
    width: 30px; height: 30px;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-size: 0.8rem;
    margin-top: 1px;
}
.ft-contact-icon-loc   { background: rgba(245,200,66,0.12); color: #F5C842; }
.ft-contact-icon-phone { background: rgba(57,181,74,0.12);  color: #39B54A; }
.ft-contact-icon-mail  { background: rgba(99,102,241,0.12); color: #818cf8; }
.ft-contact-icon-pay   { background: rgba(57,181,74,0.12);  color: #39B54A; }
.ft-contact a { color: inherit; text-decoration: none; transition: color 0.2s; }
.ft-contact a:hover { color: rgba(255,255,255,0.9); }
.ft-contact .ft-paybill { color: #A3E635; font-weight: 800; font-size: 0.88rem; }

/* Social Buttons */
.ft-social-label {
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(255,255,255,0.25);
    margin-bottom: 10px;
}
.ft-socials { display: flex; gap: 8px; flex-wrap: wrap; }
.ft-social-btn {
    width: 36px; height: 36px;
    border-radius: 10px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.07);
    color: rgba(255,255,255,0.45);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s;
}
.ft-social-btn:hover { background: #0F392B; color: #A3E635; border-color: rgba(163,230,53,0.25); transform: translateY(-2px); }

/* Bottom Bar */
.ft-bottom {
    margin-top: 52px;
    border-top: 1px solid rgba(255,255,255,0.05);
    padding: 20px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.ft-bottom-copy {
    font-size: 0.72rem;
    font-weight: 600;
    color: rgba(255,255,255,0.2);
}
.ft-bottom-powered {
    font-size: 0.72rem;
    font-weight: 600;
    color: rgba(255,255,255,0.2);
}
.ft-bottom-powered span { color: #F5C842; font-weight: 800; }
</style>

<footer class="lp-footer" id="contact">
    <div class="container position-relative" style="z-index:2;">
        <div class="row gy-5">

            <!-- ── Brand Column ── -->
            <div class="col-lg-4 col-md-6">
                <div class="ft-logo-wrap">
                    <img src="<?= ASSET_BASE ?>/images/people_logo.png" alt="Logo" class="ft-logo-img">
                    <div>
                        <div class="ft-brand-name"><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Umoja Sacco' ?></div>
                        <div class="ft-brand-sub">Drivers Sacco Ltd.</div>
                    </div>
                </div>
                <p class="ft-desc">
                    Empowering members through unity, reliable savings, transparent lending, and sustainable investment in the transport sector.
                </p>
                <div class="ft-pillars-label">Our Core Pillars</div>
                <div class="ft-pillars">
                    <span class="ft-pill"><i class="bi bi-piggy-bank-fill"></i> Savings</span>
                    <span class="ft-pill"><i class="bi bi-cash-stack"></i> Loans</span>
                    <span class="ft-pill"><i class="bi bi-graph-up-arrow"></i> Investment</span>
                    <span class="ft-pill"><i class="bi bi-heart-pulse-fill"></i> Welfare</span>
                </div>
            </div>

            <!-- ── Quick Links ── -->
            <div class="col-lg-2 col-md-6">
                <div class="ft-col-heading">Quick Links</div>
                <ul class="ft-links">
                    <li><a href="<?= BASE_URL ?>/public/index.php" class="ft-link"><i class="bi bi-chevron-right"></i> Home</a></li>
                    <li><a href="<?= BASE_URL ?>/public/index.php#about" class="ft-link"><i class="bi bi-chevron-right"></i> About Us</a></li>
                    <li><a href="<?= BASE_URL ?>/public/index.php#services" class="ft-link"><i class="bi bi-chevron-right"></i> Services</a></li>
                    <li><a href="<?= BASE_URL ?>/public/index.php#portfolio" class="ft-link"><i class="bi bi-chevron-right"></i> Assets</a></li>
                    <li><a href="<?= BASE_URL ?>/public/login.php" class="ft-link"><i class="bi bi-chevron-right"></i> Member Login</a></li>
                    <li><a href="<?= BASE_URL ?>/public/register.php" class="ft-link"><i class="bi bi-chevron-right"></i> Join Umoja</a></li>
                </ul>
            </div>

            <!-- ── Contact ── -->
            <div class="col-lg-3 col-md-6">
                <div class="ft-col-heading">Contact</div>
                <ul class="ft-contact">
                    <li>
                        <span class="ft-contact-icon ft-contact-icon-loc"><i class="bi bi-geo-alt-fill"></i></span>
                        <span><?= htmlspecialchars(OFFICE_LOCATION) ?></span>
                    </li>
                    <li>
                        <span class="ft-contact-icon ft-contact-icon-phone"><i class="bi bi-telephone-fill"></i></span>
                        <a href="tel:<?= htmlspecialchars(OFFICE_PHONE) ?>"><?= htmlspecialchars(OFFICE_PHONE) ?></a>
                    </li>
                    <li>
                        <span class="ft-contact-icon ft-contact-icon-mail"><i class="bi bi-envelope-fill"></i></span>
                        <a href="mailto:<?= htmlspecialchars(OFFICE_EMAIL) ?>"><?= htmlspecialchars(OFFICE_EMAIL) ?></a>
                    </li>
                    <li>
                        <span class="ft-contact-icon ft-contact-icon-pay"><i class="bi bi-credit-card-2-front-fill"></i></span>
                        <span class="ft-paybill">Paybill: 247247</span>
                    </li>
                </ul>
            </div>

            <!-- ── Socials ── -->
            <div class="col-lg-3 col-md-6">
                <div class="ft-col-heading">Follow Us</div>
                <div class="ft-social-label">Stay connected</div>
                <div class="ft-socials">
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', defined('COMPANY_PHONE') ? COMPANY_PHONE : OFFICE_PHONE) ?>"
                       class="ft-social-btn" target="_blank" title="WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                    <?php if (defined('SOCIAL_FACEBOOK') && SOCIAL_FACEBOOK): ?>
                    <a href="<?= SOCIAL_FACEBOOK ?>" class="ft-social-btn" target="_blank" title="Facebook"><i class="bi bi-facebook"></i></a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_TWITTER') && SOCIAL_TWITTER): ?>
                    <a href="<?= SOCIAL_TWITTER ?>" class="ft-social-btn" target="_blank" title="Twitter/X"><i class="bi bi-twitter-x"></i></a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_INSTAGRAM') && SOCIAL_INSTAGRAM): ?>
                    <a href="<?= SOCIAL_INSTAGRAM ?>" class="ft-social-btn" target="_blank" title="Instagram"><i class="bi bi-instagram"></i></a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_YOUTUBE') && SOCIAL_YOUTUBE): ?>
                    <a href="<?= SOCIAL_YOUTUBE ?>" class="ft-social-btn" target="_blank" title="YouTube"><i class="bi bi-youtube"></i></a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_TIKTOK') && SOCIAL_TIKTOK): ?>
                    <a href="<?= SOCIAL_TIKTOK ?>" class="ft-social-btn" target="_blank" title="TikTok"><i class="bi bi-tiktok"></i></a>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Bottom Bar -->
        <div class="ft-bottom">
            <div class="ft-bottom-copy">
                &copy; <?= date('Y') ?> <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Umoja Sacco' ?>. All rights reserved.
            </div>
            <div class="ft-bottom-powered">
                Powered by <span>Bezalel Technologies LTD</span>
            </div>
        </div>

    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= ASSET_BASE ?>/js/main.js"></script>
</body>
</html>