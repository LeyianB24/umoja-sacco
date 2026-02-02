<?php
// public/index.php
require_once __DIR__ . '/../inc/header.php';
?>

<style>
/* =============================
   LANDING PAGE ENHANCEMENTS
============================= */

/* 1. THEME-AWARE BACKGROUNDS */
.bg-brand-subtle {
    background-color: rgba(13, 131, 75, 0.05); /* Light Green Tint */
}

[data-bs-theme="dark"] .bg-brand-subtle {
    background-color: rgba(255, 255, 255, 0.03); /* Subtle Overlay for Dark Mode */
}

/* 2. HERO ANIMATIONS */
.hero-fade {
    animation: fadeIn 1.4s ease-in-out forwards;
    opacity: 0;
}
@keyframes fadeIn { to { opacity: 1; } }

.slide-up {
    animation: slideUp 1.2s ease forwards;
    transform: translateY(20px);
    opacity: 0;
}
@keyframes slideUp {
    to { transform: translateY(0); opacity: 1; }
}

/* 3. CARD INTERACTIONS */
.landing-card {
    background: var(--surface-1);
    border: 1px solid var(--surface-3);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.landing-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-md) !important;
    border-color: var(--sacco-green);
}

/* 4. HERO STYLING */
.hero-overlay {
    /* Keep consistent brand overlay in both modes, slightly darker in dark mode */
    background: linear-gradient(135deg, rgba(10,107,58,0.92), rgba(6,68,37,0.85));
    backdrop-filter: blur(4px);
}

[data-bs-theme="dark"] .hero-overlay {
    background: linear-gradient(135deg, rgba(6, 50, 28, 0.95), rgba(0, 0, 0, 0.9));
}

/* 5. BUTTON GLOWS */
.btn-gold {
    background-color: var(--sacco-gold);
    color: #000;
    border: none;
}
.btn-gold:hover {
    background-color: var(--sacco-gold-hover);
    box-shadow: 0 0 15px rgba(226, 179, 74, 0.5);
    transform: translateY(-2px);
}

/* 6. STATS RIBBON */
.stats-ribbon {
    background-color: var(--sacco-green);
    color: white;
}
[data-bs-theme="dark"] .stats-ribbon {
    background-color: var(--surface-2);
    border: 1px solid var(--surface-3);
}

/* 7. POKER SLIDESHOW FIX */
.poker-slideshow {
    perspective: 1200px;
    display: flex;
    justify-content: center;
    align-items: center;
}
.poker-card {
    position: absolute;
    width: 280px;
    height: 380px;
    border-radius: 24px;
    background: #fff;
    box-shadow: 0 20px 50px rgba(0,0,0,0.15);
    transition: all 0.6s cubic-bezier(0.23, 1, 0.32, 1);
    cursor: pointer;
    overflow: hidden;
    border: 6px solid #fff;
    transform-origin: bottom center;
}
.poker-card img {
    border-radius: 18px;
}
.poker-card[data-active="true"] {
    z-index: 10;
    transform: rotateY(0deg) translateZ(0) scale(1.1);
    opacity: 1;
}
.poker-card[data-side="left"] {
    z-index: 5;
    transform: rotateY(25deg) translateX(-150px) translateZ(-100px) scale(0.9);
    opacity: 0.6;
}
.poker-card[data-side="right"] {
    z-index: 5;
    transform: rotateY(-25deg) translateX(150px) translateZ(-100px) scale(0.9);
    opacity: 0.6;
}
.poker-card[data-hidden="true"] {
    opacity: 0;
    transform: translateZ(-200px) scale(0.8);
    pointer-events: none;
}

.slideshow-controls {
    position: absolute;
    bottom: -60px;
    display: flex;
    gap: 15px;
    z-index: 20;
}
.control-btn {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--sacco-green);
    color: #fff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: .3s;
}
.control-btn:hover { background: var(--sacco-gold); color: #000; transform: scale(1.1); }
</style>

<section class="hero-section d-flex align-items-center position-relative text-white" style="min-height: 90vh;">
  
  <div class="position-absolute top-0 start-0 w-100 h-100 hero-overlay" style="z-index:1;"></div>

  <div class="hero-bg position-absolute top-0 start-0 w-100 h-100"
       style="background-image:url('<?= defined('BACKGROUND_IMAGE') ? BACKGROUND_IMAGE : '/assets/images/hero-bg.jpg' ?>'); background-size:cover; background-position:center; z-index:0;">
  </div>

  <div class="container py-5 position-relative hero-fade" style="z-index:2;">
    <div class="row align-items-center">
      
      <div class="col-lg-6 py-5 slide-up">
        
        <h1 class="display-3 fw-bolder mb-3 text-white">
          <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'SACCO' ?>
        </h1>

        <p class="lead mb-4 fw-light text-warning">
          <?= defined('TAGLINE') ? htmlspecialchars(TAGLINE) : 'Empowering You' ?>
        </p>

        <p class="fs-5 fw-light text-white-50">
          Umoja Drivers Sacco is the financial backbone for the region's transport community.
          We’ve transformed from a welfare group into a multi-asset investor owning 
          <strong class="text-warning">fleets, real estate, and agribusiness</strong> — securing dividends 
          and generational wealth for all our members.
        </p>

        <div class="d-flex flex-column flex-md-row gap-3 mt-5">
          <a href="<?= BASE_URL ?>/public/login.php"
             class="btn btn-lg fw-bold px-4 rounded-pill shadow-lg btn-gold">
            <i class="bi bi-box-arrow-in-right me-2"></i> Member Login
          </a>

          <a href="<?= BASE_URL ?>/public/register.php"
             class="btn btn-outline-light btn-lg border-2 rounded-pill px-4">
            <i class="bi bi-person-plus me-2"></i> Join Umoja Today
          </a>
        </div>
      </div>

      <div class="col-lg-6 d-none d-lg-flex justify-content-center slide-up">
    <div class="poker-slideshow w-100 position-relative" style="height:400px;">
      
      <?php
      // I kept the range to 19 as per your previous snippet
      $images = range(1,19); 
      foreach ($images as $i):
          // Using a placeholder that matches typical card aspect ratio (portrait)
          $img = defined('ASSET_BASE') ? ASSET_BASE . "/images/sacco{$i}.jpg" : "https://placehold.co/400x560/0A6B3A/FFC107?text=Asset";
      ?>
        <div class="poker-card" data-offset="<?= $i ?>">
            <img src="<?= htmlspecialchars($img) ?>" 
                 alt="Sacco Asset" 
                 style="width: 100%; height: 100%; object-fit: cover; border-radius: inherit; display: block;" />
            
            </div>
      <?php endforeach; ?>

      <div class="slideshow-controls">
        <button id="slideshow-prev" class="control-btn"><i class="bi bi-chevron-left"></i></button>
        <button id="slideshow-next" class="control-btn"><i class="bi bi-chevron-right"></i></button>
      </div>

    </div>
</div>

    </div>
  </div>
</section>

<section id="wealth-model" class="py-5"> 
  <div class="container">
    <div class="text-center mb-5 slide-up">
      <h5 class="fw-bold text-uppercase ls-2" style="color: var(--sacco-green);">The Umoja Blueprint</h5>
      <h2 class="fw-bolder display-6 text-body-emphasis">How Your Money Grows With Us</h2>
      <p class="text-body-secondary mx-auto" style="max-width:700px;">
        At Umoja, your money works for you. Our proven investment cycle generates long-term wealth.
      </p>
    </div>

    <div class="row g-4 pt-2 slide-up">
      
      <?php
      $steps = [
        ["bi-wallet2", "Mobilization", "Members contribute monthly deposits and share capital, forming a strong fund base."],
        ["bi-buildings", "Investment", "Funds are invested in high-yield assets: fleets, real estate, and agribusiness."],
        ["bi-graph-up-arrow", "Returns", "Assets generate revenue daily through fare, rent, farm income and loan interest."],
        ["bi-pie-chart-fill", "Dividends", "Profits are returned to members yearly as dividends and interest."]
      ];

      $n = 1;
      foreach ($steps as $step):
      ?>
      <div class="col-lg-3 col-md-6">
        <div class="card h-100 landing-card rounded-4 p-4 text-center <?= $n==4?'border-bottom border-5 border-warning':'' ?>">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 text-white"
               style="width:50px;height:50px;background-color:var(--sacco-green);font-size:1.4rem;font-weight:bold;">
            <?= $n ?>
          </div>
          <div class="mb-3"><i class="bi <?= $step[0] ?> fs-1" style="color:var(--sacco-green);"></i></div>
          <h5 class="fw-bold" style="color:var(--sacco-green);"><?= $step[1] ?></h5>
          <p class="small text-body-secondary"><?= $step[2] ?></p>
        </div>
      </div>
      <?php $n++; endforeach; ?>

    </div>

    <div class="row mt-5 justify-content-center slide-up">
      <div class="col-lg-10">
        <div class="stats-ribbon rounded-4 p-4 shadow text-center">
          <div class="row align-items-center">
            <div class="col-md-4 border-end border-white-50">
              <h3 class="fw-bold text-warning mb-0">12%</h3>
              <small class="text-white-50">Avg. Dividend Rate</small>
            </div>
            <div class="col-md-4 border-end border-white-50">
              <h3 class="fw-bold text-warning mb-0">Ksh 500M</h3>
              <small class="text-white-50">Asset Base Goal</small>
            </div>
            <div class="col-md-4">
              <h3 class="fw-bold text-warning mb-0">48hrs</h3>
              <small class="text-white-50">Loan Processing Time</small>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<section id="services" class="py-5 bg-brand-subtle">
  <div class="container slide-up">
    <h3 class="fw-bolder text-center mb-2" style="color:var(--sacco-green);">Core Member Services</h3>
    <p class="text-center text-body-secondary mb-5">Financial empowerment through tailored products.</p>

    <div class="row g-4 justify-content-center">
      <?php
      $services = [
        ["bi-piggy-bank-fill","Voluntary Savings","Save regularly to build your financial foundation. Earn competitive interest."],
        ["bi-cash-coin","Affordable Credit","Get loans at extremely friendly member rates for business or emergencies."],
        ["bi-wallet2","Share Capital","Become a co-owner with voting rights and annual dividends."],
        ["bi-heart-pulse-fill","Welfare & Benevolence","Financial support for members and families in times of need."],
        ["bi-book-half","Financial Literacy","Workshops teaching wealth management and retirement planning."]
      ];

      foreach ($services as $srv):
      ?>
      <div class="col-lg-4 col-md-6">
        <div class="card h-100 landing-card rounded-4 border-0">
          <div class="card-body text-center p-4">
            <i class="bi <?= $srv[0] ?> fs-1" style="color:var(--sacco-green);"></i>
            <h5 class="mt-3 fw-bold text-body-emphasis"><?= $srv[1] ?></h5>
            <p class="text-body-secondary small"><?= $srv[2] ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="portfolio" class="py-5">
  <div class="container slide-up">
    <h3 class="fw-bolder text-center mb-2" style="color:var(--sacco-green);">Our Diversified Assets</h3>
    <p class="text-center text-body-secondary mb-5">Collective investments generating steady returns.</p>

    <div class="row g-4 text-center">

      <?php
      $assets = [
        ["bi-house-door-fill","Real Estate","Modern rental units and commercial plots generating passive income."],
        ["bi-bus-front-fill","Matatu Fleet","A profitable, modern fleet operating on high-demand routes."],
        ["bi-flower1","Agribusiness","Investments in crop farming and value chain production."],
        ["bi-fuel-pump-fill","Fuel Stations","High-demand fueling points providing daily revenue."]
      ];

      foreach ($assets as $item):
      ?>
      <div class="col-lg-3 col-md-6">
        <div class="p-4 rounded-4 shadow-sm h-100 bg-brand-subtle border border-transparent">
          <i class="bi <?= $item[0] ?> fs-1 mb-3 d-block" style="color:var(--sacco-green);"></i>
          <h5 class="fw-bolder text-body-emphasis"><?= $item[1] ?></h5>
          <p class="text-body-secondary small"><?= $item[2] ?></p>
        </div>
      </div>
      <?php endforeach; ?>

    </div>
  </div>
</section>

<section class="py-5 text-white text-center position-relative overflow-hidden"
         style="background-color: var(--sacco-green);">

    <div style="position: absolute; top:0; left:0; width:100%; height:100%; opacity:0.1;
          background: radial-gradient(circle, rgba(255,193,7,0.3) 0%, transparent 70%),
                      repeating-linear-gradient(45deg, rgba(255,193,7,0.05), rgba(255,193,7,0.05) 10px, transparent 10px, transparent 20px);">
    </div>

    <div class="container position-relative slide-up">
        <h2 class="display-5 fw-bolder mb-3 text-warning">Stop Waiting. Start Owning.</h2>

        <p class="lead mb-4 fw-light fs-4 text-white">
            It takes less than 5 minutes to begin. Secure your future with stable dividends and fast credit.
        </p>

        <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
            <span class="badge bg-white p-3 fw-bold rounded-pill shadow-sm text-success">
                <i class="bi bi-award-fill me-2"></i> Guaranteed Dividends
            </span>
            <span class="badge bg-white p-3 fw-bold rounded-pill shadow-sm text-success">
                <i class="bi bi-lightning-fill me-2"></i> Quick Loans
            </span>
            <span class="badge bg-white p-3 fw-bold rounded-pill shadow-sm text-success">
                <i class="bi bi-safe-fill me-2"></i> Secure
            </span>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-center gap-4">
            <a href="<?= BASE_URL ?>/public/register.php"
               class="btn btn-lg shadow-lg rounded-pill px-5 py-3 fs-5 fw-bold btn-gold">
                <i class="bi bi-person-circle me-2"></i> Join Our Community Now
            </a>

            <a href="#contact"
               class="btn btn-outline-light btn-lg border-2 rounded-pill px-5 py-3 fs-5">
                <i class="bi bi-headset me-2"></i> Talk to an Agent
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
<script>
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

    const nextBtn = document.getElementById('slideshow-next');
    const prevBtn = document.getElementById('slideshow-prev');

    if(nextBtn) nextBtn.addEventListener('click', () => {
        currentIndex = (currentIndex + 1) % cards.length;
        updateSlideshow();
    });

    if(prevBtn) prevBtn.addEventListener('click', () => {
        currentIndex = (currentIndex - 1 + cards.length) % cards.length;
        updateSlideshow();
    });

    if(cards.length > 0) {
        setInterval(() => {
            currentIndex = (currentIndex + 1) % cards.length;
            updateSlideshow();
        }, 4000);
        updateSlideshow();
    }
});
</script>
