<?php
// public/index.php
require_once __DIR__ . '/../inc/header.php';
?>

<!-- HERO SECTION -->
<section class="hero-section d-flex align-items-center" style="min-height:50vh;">
  <div class="container">
    <div class="row align-items-center">
      
      <!-- LEFT TEXT -->
      <div class="col-lg-6 text-white">
        <h1 class="display-4 fw-bold"><?= htmlspecialchars(SITE_NAME) ?></h1>
        <p class="lead mb-3"><?= htmlspecialchars(TAGLINE) ?></p>
        <p>
          Umoja Drivers Sacco began as a welfare chama among matatu drivers.
          Today we own farms, fleets, fuel station, apartments and land parcels — investments that benefit every member.
        </p>

        <div class="d-flex gap-2 mt-4">
          <a href="<?= BASE_URL ?>/public/login.php" class="btn btn-light btn-lg">
            <i class="bi bi-box-arrow-in-right me-2"></i> Member Login
          </a>
          <a href="<?= BASE_URL ?>/public/register.php" class="btn btn-outline-warning btn-lg">
            <i class="bi bi-person-plus me-2"></i> Become a Member
          </a>
        </div>
      </div>

      <!-- RIGHT: SLIDESHOW -->
      <div class="col-lg-6 d-none d-md-block">
        <div class="poker-slideshow position-relative">
          <?php
          $count = 19; // Number of slideshow images
          for ($i = 1; $i <= $count; $i++):
            $img = ASSET_BASE . "/images/sacco{$i}.jpg";
          ?>
            <img src="<?= htmlspecialchars($img) ?>" alt="Sacco Image <?= $i ?>" class="poker-card" />
          <?php endfor; ?>

          <div class="slideshow-controls position-absolute bottom-0 end-0 p-2">
            <button id="slideshow-prev" class="btn btn-sm btn-outline-light"><i class="bi bi-chevron-left"></i></button>
            <button id="slideshow-next" class="btn btn-sm btn-light"><i class="bi bi-chevron-right"></i></button>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ABOUT -->
<section id="about" class="py-5 bg-light">
  <div class="container text-center">
    <h2 class="fw-bold mb-3">About <?= htmlspecialchars(SITE_NAME) ?></h2>
    <p class="text-muted mx-auto" style="max-width:900px;">
      We started as a small drivers' welfare group and over time invested in land, transport assets and businesses.
      Our focus is on member welfare, savings discipline, affordable credit and shared investment.
    </p>
  </div>
</section>

<!-- SERVICES SECTION -->
<section id="services" class="py-5 bg-white">
  <div class="container">
    <h3 class="fw-bold text-center mb-4">Our Core Services</h3>
    <p class="text-center text-muted mb-5">Explore our main member-focused services designed to empower financial growth.</p>

    <div class="row g-4">
      <!-- Savings -->
      <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 service-card" data-service="savings">
          <div class="card-body text-center">
            <i class="bi bi-piggy-bank fs-1 text-success"></i>
            <h5 class="mt-3">Savings</h5>
            <p class="text-muted short-text">
              Save regularly and grow your financial security with competitive member benefits.
            </p>
            <div class="long-text d-none">
              <p class="text-muted">
                Members contribute monthly savings that earn attractive dividends annually.
                Your savings act as security for loans and enable collective investments.
              </p>
            </div>
            <button class="btn btn-outline-success mt-2 toggle-btn">Read More</button>
          </div>
        </div>
      </div>

      <!-- Loans -->
      <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 service-card" data-service="loans">
          <div class="card-body text-center">
            <i class="bi bi-cash-stack fs-1 text-success"></i>
            <h5 class="mt-3">Loans</h5>
            <p class="text-muted short-text">
              Access affordable credit to improve your business, education, or personal growth.
            </p>
            <div class="long-text d-none">
              <p class="text-muted">
                Members can apply for development, emergency, and business loans with flexible
                repayment plans and low interest rates backed by savings.
              </p>
            </div>
            <button class="btn btn-outline-success mt-2 toggle-btn">Read More</button>
          </div>
        </div>
      </div>

      <!-- Investments -->
      <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 service-card" data-service="investments">
          <div class="card-body text-center">
            <i class="bi bi-graph-up-arrow fs-1 text-success"></i>
            <h5 class="mt-3">Investments</h5>
            <p class="text-muted short-text">
              We invest in profitable ventures like real estate, transport, and agribusiness.
            </p>
            <div class="long-text d-none">
              <p class="text-muted">
                Umoja Sacco strategically invests member funds into income-generating projects
                such as land, apartments, matatus, and farming for sustainable growth.
              </p>
            </div>
            <button class="btn btn-outline-success mt-2 toggle-btn">Read More</button>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<script>
  // Toggle service description visibility
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toggle-btn').forEach(button => {
      button.addEventListener('click', function () {
        const card = this.closest('.service-card');
        const shortText = card.querySelector('.short-text');
        const longText = card.querySelector('.long-text');

        if (longText.classList.contains('d-none')) {
          longText.classList.remove('d-none');
          shortText.classList.add('d-none');
          this.textContent = 'Show Less';
        } else {
          longText.classList.add('d-none');
          shortText.classList.remove('d-none');
          this.textContent = 'Read More';
        }
      });
    });
  });
</script>

<!-- CONTACT -->
<section id="contact" class="py-5 bg-light">
  <div class="container text-center">
    <h4 class="fw-bold mb-3">Contact Us</h4>
    <p class="text-muted">For membership, loans or partnerships, reach us via the official Sacco contacts below:</p>
    <p class="small">
      <i class="bi bi-envelope"></i> info@umojasacco.usms.ac.ke
      <i class="bi bi-telephone"></i> +254 796157265
    </p>
  </div>
</section>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
