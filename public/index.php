<?php
// public/index.php
require_once __DIR__ . '/../inc/header.php';
?>

<style>
*, *::before, *::after { box-sizing: border-box; }

/* ─── Design Tokens ─── */
:root {
    --forest:       #0F392B;
    --forest-mid:   #1a5c43;
    --forest-light: #2d7a56;
    --lime:         #A3E635;
    --lime-soft:    rgba(163,230,53,0.12);
    --lime-glow:    rgba(163,230,53,0.25);
    --gold:         #F5C842;
    --gold-hover:   #e8b82e;
    --white:        #ffffff;
    --text-muted:   rgba(255,255,255,0.55);
    --text-dim:     rgba(255,255,255,0.35);
    --body-bg:      #F7FBF9;
    --card-bg:      #ffffff;
    --card-border:  #E8F0ED;
    --text-dark:    #0F392B;
    --text-body:    #4a7264;
    --section-alt:  #F0F7F4;
    font-family: 'Plus Jakarta Sans', sans-serif;
}
[data-bs-theme="dark"] {
    --body-bg:    #0B1E17;
    --card-bg:    #0d2018;
    --card-border: rgba(255,255,255,0.07);
    --text-dark:  #e0f0ea;
    --text-body:  #7a9e8e;
    --section-alt: #0d2018;
}

body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--body-bg); }

/* ─── Shared Utilities ─── */
.lp-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: var(--lime-soft);
    border: 1px solid rgba(163,230,53,0.25);
    border-radius: 100px;
    padding: 5px 14px;
    font-size: 0.67rem;
    font-weight: 800;
    color: var(--lime);
    text-transform: uppercase;
    letter-spacing: 1.1px;
    margin-bottom: 18px;
}
.lp-eyebrow-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--lime);
    animation: eyebrowPulse 2s ease-in-out infinite;
}
@keyframes eyebrowPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(0.85)} }

/* ═══════════════════════════════
   HERO
═══════════════════════════════ */
.lp-hero {
    min-height: 100vh;
    position: relative;
    display: flex;
    align-items: center;
    overflow: hidden;
}
.lp-hero-bg {
    position: absolute;
    inset: 0;
    background-image: url('<?= defined('BACKGROUND_IMAGE') ? BACKGROUND_IMAGE : '/assets/images/hero-bg.jpg' ?>');
    background-size: cover;
    background-position: center;
    z-index: 0;
}
.lp-hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(150deg, rgba(11,30,22,0.95) 0%, rgba(15,57,43,0.88) 45%, rgba(10,24,18,0.97) 100%);
    z-index: 1;
}
.lp-hero-glow-a {
    position: absolute;
    top: -120px; right: -80px;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(57,181,74,0.14) 0%, transparent 65%);
    border-radius: 50%;
    z-index: 2;
    pointer-events: none;
}
.lp-hero-glow-b {
    position: absolute;
    bottom: -100px; left: -60px;
    width: 360px; height: 360px;
    background: radial-gradient(circle, rgba(163,230,53,0.08) 0%, transparent 65%);
    border-radius: 50%;
    z-index: 2;
    pointer-events: none;
}
.lp-hero-content {
    position: relative;
    z-index: 3;
    width: 100%;
    padding: 120px 0 100px;
}

/* Hero Text */
.hero-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(163,230,53,0.1);
    border: 1px solid rgba(163,230,53,0.2);
    border-radius: 100px;
    padding: 6px 16px;
    font-size: 0.68rem;
    font-weight: 800;
    color: var(--lime);
    text-transform: uppercase;
    letter-spacing: 1.2px;
    margin-bottom: 22px;
    animation: heroFadeIn 0.8s ease both;
}
.hero-kicker-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--lime);
    animation: eyebrowPulse 2s ease-in-out infinite;
}
.hero-title {
    font-size: clamp(2.4rem, 5vw, 4rem);
    font-weight: 800;
    color: #fff;
    letter-spacing: -1.5px;
    line-height: 1.05;
    margin-bottom: 18px;
    animation: heroFadeIn 0.9s 0.1s ease both;
}
.hero-title .accent { color: var(--lime); }
.hero-tagline {
    font-size: 1.05rem;
    font-weight: 500;
    color: var(--text-muted);
    line-height: 1.75;
    max-width: 520px;
    margin-bottom: 36px;
    animation: heroFadeIn 1s 0.2s ease both;
}
.hero-tagline strong { color: var(--gold); font-weight: 700; }

@keyframes heroFadeIn { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

/* Hero Buttons */
.hero-btns {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    animation: heroFadeIn 1s 0.3s ease both;
}
.btn-lp-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--lime);
    color: var(--forest);
    border: none;
    border-radius: 14px;
    padding: 14px 28px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.9rem;
    font-weight: 800;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.22s;
    box-shadow: 0 6px 24px rgba(163,230,53,0.3);
    letter-spacing: 0.1px;
}
.btn-lp-primary:hover { background: #bde32a; transform: translateY(-2px); box-shadow: 0 10px 32px rgba(163,230,53,0.4); color: var(--forest); }
.btn-lp-outline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: transparent;
    color: rgba(255,255,255,0.85);
    border: 1.5px solid rgba(255,255,255,0.25);
    border-radius: 14px;
    padding: 13px 28px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.22s;
}
.btn-lp-outline:hover { border-color: rgba(255,255,255,0.6); background: rgba(255,255,255,0.07); color: #fff; transform: translateY(-2px); }

/* Hero Trust Row */
.hero-trust {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-top: 40px;
    animation: heroFadeIn 1s 0.4s ease both;
}
.hero-trust-divider { width: 1px; height: 32px; background: rgba(255,255,255,0.15); }
.hero-trust-stat { text-align: center; }
.hero-trust-stat .val { font-size: 1.2rem; font-weight: 800; color: var(--lime); line-height: 1; margin-bottom: 3px; }
.hero-trust-stat .lbl { font-size: 0.6rem; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.7px; }

/* ─── Poker Slideshow ─── */
.poker-slideshow {
    perspective: 1200px;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 420px;
    position: relative;
    animation: heroFadeIn 1s 0.25s ease both;
}
.poker-card {
    position: absolute;
    width: 270px; height: 370px;
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 24px 60px rgba(0,0,0,0.2);
    transition: all 0.55s cubic-bezier(0.23,1,0.32,1);
    cursor: pointer;
    overflow: hidden;
    border: 5px solid #fff;
    transform-origin: bottom center;
}
.poker-card img { width: 100%; height: 100%; object-fit: cover; border-radius: 17px; display: block; }
.poker-card[data-active="true"] { z-index:10; transform: rotateY(0deg) translateZ(0) scale(1.08); opacity: 1; }
.poker-card[data-side="left"]  { z-index:5;  transform: rotateY(22deg) translateX(-160px) translateZ(-90px) scale(0.88); opacity: 0.55; }
.poker-card[data-side="right"] { z-index:5;  transform: rotateY(-22deg) translateX(160px) translateZ(-90px) scale(0.88); opacity: 0.55; }
.poker-card[data-hidden="true"] { opacity:0; transform: translateZ(-200px) scale(0.8); pointer-events:none; }

.slideshow-controls {
    position: absolute;
    bottom: -52px;
    display: flex; gap: 12px; z-index: 20;
}
.ctrl-btn {
    width: 42px; height: 42px;
    border-radius: 13px;
    background: rgba(163,230,53,0.12);
    border: 1px solid rgba(163,230,53,0.25);
    color: var(--lime);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
}
.ctrl-btn:hover { background: var(--lime); color: var(--forest); }

/* ─── Stats Ribbon ─── */
.lp-stats-ribbon {
    background: linear-gradient(135deg, #0F392B, #1a5c43);
    padding: 36px 0;
    position: relative;
    overflow: hidden;
}
.lp-stats-ribbon::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(163,230,53,0.1) 0%, transparent 65%);
    border-radius: 50%;
    pointer-events: none;
}
.stat-chip {
    text-align: center;
    padding: 0 28px;
    border-right: 1px solid rgba(255,255,255,0.1);
}
.stat-chip:last-child { border-right: none; }
.stat-chip .val { font-size: 1.9rem; font-weight: 800; color: var(--lime); letter-spacing: -0.5px; line-height: 1; margin-bottom: 5px; }
.stat-chip .lbl { font-size: 0.7rem; font-weight: 600; color: rgba(255,255,255,0.45); text-transform: uppercase; letter-spacing: 0.7px; }

/* ─── Section Base ─── */
.lp-section { padding: 88px 0; }
.lp-section-alt { background: var(--section-alt); }

.lp-section-head { text-align: center; margin-bottom: 52px; }
.lp-section-head h2 {
    font-size: clamp(1.7rem, 3vw, 2.3rem);
    font-weight: 800;
    color: var(--text-dark);
    letter-spacing: -0.5px;
    margin-bottom: 12px;
}
.lp-section-head p {
    font-size: 0.94rem;
    color: var(--text-body);
    font-weight: 500;
    max-width: 560px;
    margin: 0 auto;
    line-height: 1.7;
}

/* ─── Blueprint Cards ─── */
.blueprint-card {
    background: var(--card-bg);
    border: 1.5px solid var(--card-border);
    border-radius: 20px;
    padding: 32px 24px;
    text-align: center;
    position: relative;
    transition: all 0.25s;
    height: 100%;
}
.blueprint-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 44px rgba(15,57,43,0.1);
    border-color: #A3E635;
}
.blueprint-card.featured { border-color: var(--gold); box-shadow: 0 8px 28px rgba(245,200,66,0.15); }
.bp-num {
    width: 40px; height: 40px;
    border-radius: 12px;
    background: var(--forest);
    color: var(--lime);
    font-size: 0.85rem;
    font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
}
.bp-icon { font-size: 2rem; color: var(--forest); margin-bottom: 12px; display: block; }
[data-bs-theme="dark"] .bp-icon { color: var(--lime); }
.blueprint-card h5 {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 8px;
}
.blueprint-card p { font-size: 0.82rem; color: var(--text-body); line-height: 1.65; margin: 0; }

/* ─── Service Cards ─── */
.service-card {
    background: var(--card-bg);
    border: 1.5px solid var(--card-border);
    border-radius: 20px;
    padding: 28px 24px;
    transition: all 0.25s;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}
.service-card:hover { transform: translateY(-4px); box-shadow: 0 14px 40px rgba(15,57,43,0.1); border-color: #A3E635; }
.service-icon {
    width: 48px; height: 48px;
    border-radius: 14px;
    background: #E8F5E9;
    color: var(--forest);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    margin-bottom: 16px;
    flex-shrink: 0;
}
[data-bs-theme="dark"] .service-icon { background: rgba(163,230,53,0.1); color: var(--lime); }
.service-card h5 { font-size: 0.94rem; font-weight: 800; color: var(--text-dark); margin-bottom: 7px; }
.service-card p  { font-size: 0.8rem; color: var(--text-body); line-height: 1.65; margin: 0; }

/* ─── Asset Cards ─── */
.asset-card {
    background: var(--card-bg);
    border: 1.5px solid var(--card-border);
    border-radius: 20px;
    padding: 28px 20px;
    text-align: center;
    transition: all 0.25s;
    height: 100%;
}
.asset-card:hover { transform: translateY(-4px); box-shadow: 0 14px 40px rgba(15,57,43,0.1); border-color: #A3E635; }
.asset-icon {
    width: 56px; height: 56px;
    border-radius: 16px;
    background: linear-gradient(135deg, #0F392B, #1a5c43);
    color: var(--lime);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    margin: 0 auto 16px;
    box-shadow: 0 6px 18px rgba(15,57,43,0.2);
}
.asset-card h5 { font-size: 0.94rem; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; }
.asset-card p  { font-size: 0.8rem; color: var(--text-body); line-height: 1.65; margin: 0; }

/* ─── CTA Section ─── */
.lp-cta {
    background: linear-gradient(135deg, #0F392B 0%, #1a5c43 60%, #0d2e22 100%);
    padding: 88px 0;
    position: relative;
    overflow: hidden;
    text-align: center;
}
.lp-cta::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(163,230,53,0.1) 0%, transparent 65%);
    border-radius: 50%;
    pointer-events: none;
}
.lp-cta::after {
    content: '';
    position: absolute;
    bottom: -60px; left: -60px;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(245,200,66,0.07) 0%, transparent 65%);
    border-radius: 50%;
    pointer-events: none;
}
.lp-cta h2 {
    font-size: clamp(1.9rem, 4vw, 3rem);
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.8px;
    line-height: 1.1;
    margin-bottom: 16px;
}
.lp-cta h2 span { color: var(--lime); }
.lp-cta p { font-size: 1rem; color: rgba(255,255,255,0.6); font-weight: 500; max-width: 500px; margin: 0 auto 32px; line-height: 1.7; }
.cta-badges {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
    margin-bottom: 36px;
}
.cta-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 100px;
    padding: 8px 16px;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255,255,255,0.8);
}
.cta-badge i { color: var(--lime); }
.cta-buttons { display: flex; flex-wrap: wrap; justify-content: center; gap: 14px; position: relative; z-index: 1; }
.btn-lp-cta-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--lime);
    color: var(--forest);
    border: none;
    border-radius: 14px;
    padding: 15px 32px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.92rem;
    font-weight: 800;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.22s;
    box-shadow: 0 6px 24px rgba(163,230,53,0.28);
}
.btn-lp-cta-primary:hover { background: #bde32a; transform: translateY(-2px); box-shadow: 0 10px 32px rgba(163,230,53,0.4); color: var(--forest); }
.btn-lp-cta-outline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: transparent;
    color: rgba(255,255,255,0.8);
    border: 1.5px solid rgba(255,255,255,0.2);
    border-radius: 14px;
    padding: 14px 28px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.92rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.22s;
}
.btn-lp-cta-outline:hover { border-color: rgba(255,255,255,0.55); background: rgba(255,255,255,0.06); color: #fff; transform: translateY(-2px); }

/* Scroll reveal */
.reveal { opacity: 0; transform: translateY(28px); transition: opacity 0.6s ease, transform 0.6s cubic-bezier(0.16,1,0.3,1); }
.reveal.visible { opacity: 1; transform: translateY(0); }
</style>

<!-- ══ HERO ══ -->
<section class="lp-hero">
    <div class="lp-hero-bg"></div>
    <div class="lp-hero-overlay"></div>
    <div class="lp-hero-glow-a"></div>
    <div class="lp-hero-glow-b"></div>

    <div class="lp-hero-content">
        <div class="container">
            <div class="row align-items-center g-5">

                <!-- Text Column -->
                <div class="col-lg-6">
                    <div class="hero-kicker">
                        <span class="hero-kicker-dot"></span>
                        Est. Umoja Drivers Sacco Ltd.
                    </div>
                    <h1 class="hero-title">
                        Financial<br>Freedom<br><span class="accent">Starts Here.</span>
                    </h1>
                    <p class="hero-tagline">
                        <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Umoja Sacco' ?> is the financial backbone for the transport community — owning <strong>fleets, real estate, and agribusiness</strong> and delivering generational wealth to every member.
                    </p>
                    <div class="hero-btns">
                        <a href="<?= BASE_URL ?>/public/login.php" class="btn-lp-primary">
                            <i class="bi bi-box-arrow-in-right"></i> Member Login
                        </a>
                        <a href="<?= BASE_URL ?>/public/register.php" class="btn-lp-outline">
                            <i class="bi bi-person-plus"></i> Join Today
                        </a>
                    </div>
                    <div class="hero-trust">
                        <div class="hero-trust-stat">
                            <div class="val">12%</div>
                            <div class="lbl">Avg. Dividend</div>
                        </div>
                        <div class="hero-trust-divider"></div>
                        <div class="hero-trust-stat">
                            <div class="val">48hr</div>
                            <div class="lbl">Loan Approval</div>
                        </div>
                        <div class="hero-trust-divider"></div>
                        <div class="hero-trust-stat">
                            <div class="val">100%</div>
                            <div class="lbl">Secure</div>
                        </div>
                        <div class="hero-trust-divider"></div>
                        <div class="hero-trust-stat">
                            <div class="val">Ksh 500M</div>
                            <div class="lbl">Asset Target</div>
                        </div>
                    </div>
                </div>

                <!-- Slideshow Column -->
                <div class="col-lg-6 d-none d-lg-flex justify-content-center">
                    <div class="poker-slideshow w-100">
                        <?php
                        $images = range(1, 19);
                        foreach ($images as $i):
                            $img = defined('ASSET_BASE') ? ASSET_BASE . "/images/sacco{$i}.jpg" : "https://placehold.co/400x560/0F392B/A3E635?text=Asset+{$i}";
                        ?>
                        <div class="poker-card" data-offset="<?= $i ?>">
                            <img src="<?= htmlspecialchars($img) ?>" alt="Sacco Asset">
                        </div>
                        <?php endforeach; ?>
                        <div class="slideshow-controls">
                            <button id="slideshow-prev" class="ctrl-btn"><i class="bi bi-chevron-left"></i></button>
                            <button id="slideshow-next" class="ctrl-btn"><i class="bi bi-chevron-right"></i></button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- ══ STATS RIBBON ══ -->
<div class="lp-stats-ribbon">
    <div class="container">
        <div class="row justify-content-center">
            <?php
            $stats = [
                ['12%', 'Avg. Dividend Rate'],
                ['Ksh 500M', 'Asset Base Goal'],
                ['48 hrs', 'Loan Processing'],
                ['24/7', 'Member Access'],
            ];
            foreach ($stats as $s):
            ?>
            <div class="col-6 col-md-3">
                <div class="stat-chip">
                    <div class="val"><?= $s[0] ?></div>
                    <div class="lbl"><?= $s[1] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ══ BLUEPRINT ══ -->
<section class="lp-section" id="wealth-model">
    <div class="container">
        <div class="lp-section-head reveal">
            <div class="lp-eyebrow"><span class="lp-eyebrow-dot"></span> The Umoja Blueprint</div>
            <h2>How Your Money Grows With Us</h2>
            <p>A proven four-step investment cycle that turns monthly contributions into lasting wealth.</p>
        </div>
        <div class="row g-4 reveal">
            <?php
            $steps = [
                ["bi-wallet2",         "Mobilization", "Members contribute monthly deposits and share capital, forming a strong fund base."],
                ["bi-buildings",       "Investment",   "Funds are invested in high-yield assets: fleets, real estate, and agribusiness."],
                ["bi-graph-up-arrow",  "Returns",      "Assets generate revenue daily through fares, rent, farm income, and loan interest."],
                ["bi-pie-chart-fill",  "Dividends",    "Profits are returned to members yearly as dividends and interest on savings."],
            ];
            $n = 1;
            foreach ($steps as $step):
            ?>
            <div class="col-lg-3 col-md-6">
                <div class="blueprint-card <?= $n==4 ? 'featured' : '' ?>">
                    <div class="bp-num"><?= $n ?></div>
                    <i class="bi <?= $step[0] ?> bp-icon"></i>
                    <h5><?= $step[1] ?></h5>
                    <p><?= $step[2] ?></p>
                </div>
            </div>
            <?php $n++; endforeach; ?>
        </div>
    </div>
</section>

<!-- ══ SERVICES ══ -->
<section class="lp-section lp-section-alt" id="services">
    <div class="container">
        <div class="lp-section-head reveal">
            <div class="lp-eyebrow"><span class="lp-eyebrow-dot"></span> Core Services</div>
            <h2>Financial Products Built for Members</h2>
            <p>Tailored savings, credit, and welfare products designed around the transport community.</p>
        </div>
        <div class="row g-4 reveal">
            <?php
            $services = [
                ["bi-piggy-bank-fill",  "Voluntary Savings",   "Save regularly to build your financial foundation and earn competitive interest on every deposit."],
                ["bi-cash-coin",        "Affordable Credit",   "Get loans at extremely friendly member rates for business growth or personal emergencies."],
                ["bi-wallet2",          "Share Capital",       "Become a co-owner with full voting rights and receive annual dividends from profits."],
                ["bi-heart-pulse-fill", "Welfare & Benevolence","Structured financial support for members and their families during difficult times."],
                ["bi-book-half",        "Financial Literacy",  "Ongoing workshops teaching wealth management, investment basics, and retirement planning."],
            ];
            foreach ($services as $srv):
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="service-icon"><i class="bi <?= $srv[0] ?>"></i></div>
                    <h5><?= $srv[1] ?></h5>
                    <p><?= $srv[2] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══ PORTFOLIO ══ -->
<section class="lp-section" id="portfolio">
    <div class="container">
        <div class="lp-section-head reveal">
            <div class="lp-eyebrow"><span class="lp-eyebrow-dot"></span> Diversified Assets</div>
            <h2>Collective Investments That Deliver</h2>
            <p>Every shilling you contribute is deployed into real assets generating steady income streams.</p>
        </div>
        <div class="row g-4 reveal">
            <?php
            $assets = [
                ["bi-house-door-fill",  "Real Estate",   "Modern rental units and commercial plots generating passive monthly income."],
                ["bi-bus-front-fill",   "Matatu Fleet",  "A modern, profitable fleet operating on the region's highest-demand routes."],
                ["bi-flower1",          "Agribusiness",  "Strategic investments in crop farming and agricultural value chains."],
                ["bi-fuel-pump-fill",   "Fuel Stations", "High-traffic fueling points providing reliable daily revenue."],
            ];
            foreach ($assets as $item):
            ?>
            <div class="col-lg-3 col-md-6">
                <div class="asset-card">
                    <div class="asset-icon"><i class="bi <?= $item[0] ?>"></i></div>
                    <h5><?= $item[1] ?></h5>
                    <p><?= $item[2] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══ CTA ══ -->
<section class="lp-cta" id="contact">
    <div class="container position-relative" style="z-index:2;">
        <div class="reveal">
            <div class="lp-eyebrow d-inline-flex mb-4"><span class="lp-eyebrow-dot"></span> Join Today</div>
            <h2>Stop Waiting.<br><span>Start Owning.</span></h2>
            <p>It takes less than 5 minutes to begin. Secure your future with stable dividends, fast credit, and real ownership.</p>
            <div class="cta-badges">
                <span class="cta-badge"><i class="bi bi-award-fill"></i> Guaranteed Dividends</span>
                <span class="cta-badge"><i class="bi bi-lightning-fill"></i> Quick Loans</span>
                <span class="cta-badge"><i class="bi bi-safe-fill"></i> Fully Secure</span>
                <span class="cta-badge"><i class="bi bi-people-fill"></i> Community Owned</span>
            </div>
            <div class="cta-buttons">
                <a href="<?= BASE_URL ?>/public/register.php" class="btn-lp-cta-primary">
                    <i class="bi bi-person-circle"></i> Join Our Community
                </a>
                <a href="#contact" class="btn-lp-cta-outline">
                    <i class="bi bi-headset"></i> Talk to an Agent
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>

<script>
// ─── Poker Slideshow ───
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.poker-card');
    let currentIndex = 0;

    function updateSlideshow() {
        cards.forEach((card, index) => {
            card.removeAttribute('data-active');
            card.removeAttribute('data-side');
            card.setAttribute('data-hidden', 'true');
            if (index === currentIndex) {
                card.removeAttribute('data-hidden');
                card.setAttribute('data-active', 'true');
            } else if (index === (currentIndex - 1 + cards.length) % cards.length) {
                card.removeAttribute('data-hidden');
                card.setAttribute('data-side', 'left');
            } else if (index === (currentIndex + 1) % cards.length) {
                card.removeAttribute('data-hidden');
                card.setAttribute('data-side', 'right');
            }
        });
    }

    document.getElementById('slideshow-next')?.addEventListener('click', () => {
        currentIndex = (currentIndex + 1) % cards.length;
        updateSlideshow();
    });
    document.getElementById('slideshow-prev')?.addEventListener('click', () => {
        currentIndex = (currentIndex - 1 + cards.length) % cards.length;
        updateSlideshow();
    });
    if (cards.length > 0) {
        setInterval(() => { currentIndex = (currentIndex + 1) % cards.length; updateSlideshow(); }, 4000);
        updateSlideshow();
    }

    // ─── Scroll Reveal ───
    const reveals = document.querySelectorAll('.reveal');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, i) => {
            if (entry.isIntersecting) {
                setTimeout(() => entry.target.classList.add('visible'), i * 60);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    reveals.forEach(el => observer.observe(el));
});
</script>