<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Umoja Sacco - Welcome</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --accent:#0d6efd;
      --green:#007b5e;
      --card-width:760px;
      --card-height:430px;
    }

    body{
      margin:0;
      font-family: "Poppins", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      background: linear-gradient(180deg,#f4f7fb 0%, #ffffff 100%);
      color:#1f2937;
      -webkit-font-smoothing:antialiased;
    }

    /* Header */
    .site-header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:14px 28px;
      background: linear-gradient(90deg,var(--green), #00a86b);
      box-shadow: 0 6px 20px rgba(0,0,0,0.08);
      position:sticky;
      top:0;
      z-index:999;
    }
    .brand {
      display:flex;
      align-items:center;
      gap:12px;
      color:#fff;
      text-decoration:none;
      font-weight:700;
      font-size:1.25rem;
    }
    .brand img{
      width:64px;
      height:64px;
      border-radius:50%;
      object-fit:cover;
      border:3px solid rgba(255,255,255,0.85);
      box-shadow: 0 6px 18px rgba(0,0,0,0.12);
      background:#fff;
    }
    .auth-buttons a{
      color:#fff;
      border:1px solid rgba(255,255,255,0.18);
      padding:8px 14px;
      border-radius:24px;
      margin-left:10px;
      text-decoration:none;
      font-weight:600;
      display:inline-block;
      transition:all .18s ease;
    }
    .auth-buttons a:hover{
      transform:translateY(-2px);
      background:rgba(255,255,255,0.95);
      color:var(--green);
      box-shadow:0 6px 18px rgba(0,0,0,0.12);
    }

    /* Slider area */
    .slider-wrap{
      width:100%;
      max-width:1100px;
      margin:48px auto 18px;
      padding:0 20px;
    }

    .cards-stage{
      position:relative;
      width:100%;
      height:var(--card-height);
      max-width:var(--card-width);
      margin:0 auto;
      perspective:1200px;
    }

    .card-item{
      position:absolute;
      top:0;
      left:50%;
      transform-origin:50% 50%;
      width:70%;
      max-width:760px;
      height:100%;
      transition: transform 700ms cubic-bezier(.2,.9,.25,1), opacity 500ms ease, z-index 0ms;
      border-radius:16px;
      overflow:hidden;
      box-shadow: 0 10px 30px rgba(0,0,0,0.18);
      cursor:pointer;
      background:#eee;
      will-change: transform, opacity;
    }

    .card-item img{
      width:100%;
      height:100%;
      object-fit:cover;
      display:block;
    }

    /* controls */
    .ctrl {
      position:absolute;
      top:50%;
      transform:translateY(-50%);
      width:48px;
      height:48px;
      border-radius:12px;
      display:flex;
      align-items:center;
      justify-content:center;
      background:rgba(255,255,255,0.9);
      box-shadow:0 6px 18px rgba(0,0,0,0.12);
      cursor:pointer;
      z-index:2000;
    }
    .ctrl:hover{ transform:translateY(-50%) scale(1.03); }
    .ctrl.prev{ left: calc(50% - var(--card-width)/2 - 16px); }
    .ctrl.next{ right: calc(50% - var(--card-width)/2 - 16px); }

    /* indicators */
    .indicators{
      display:flex;
      gap:8px;
      justify-content:center;
      margin-top:18px;
    }
    .dot{
      width:10px;height:10px;border-radius:50%;
      background:rgba(0,0,0,0.15);
      transition:all .18s ease;
    }
    .dot.active{ transform:scale(1.3); background:var(--green); }

    /* info section */
    .info-section{
      padding:64px 10px 80px;
      max-width:1100px;
      margin:0 auto 48px;
      text-align:center;
      background:linear-gradient(180deg, rgba(234,243,255,0.6), rgba(255,255,255,0.0));
      border-radius:12px;
    }
    .info-section h2{ color:var(--green); font-weight:700; margin-bottom:12px; }
    .feature-row{ margin-top:36px; display:flex; gap:20px; justify-content:center; flex-wrap:wrap; }
    .feature-card{
      width:280px;
      border-radius:14px;
      padding:22px;
      text-align:center;
      background:#fff;
      box-shadow:0 6px 24px rgba(0,0,0,0.06);
    }
    .feature-icon{ font-size:36px; color:var(--green); margin-bottom:12px; }

    /* responsive */
    @media (max-width: 1000px){
      :root{ --card-width: 720px; --card-height:360px }
      .card-item{ width:80% }
      .ctrl.prev{ left:8px; } .ctrl.next{ right:8px; }
    }
    @media (max-width: 768px){
      :root{ --card-width: 100%; --card-height:300px }
      .card-item{ width:90% }
      .auth-buttons a{ padding:7px 10px; font-size:14px; }
    }
  </style>
</head>
<body>

  <!-- Header -->
  <header class="site-header">
    <a class="brand" href="#">
      <img src="images/people_logo.png" alt="Umoja Sacco logo">
      <span>Umoja Sacco</span>
    </a>

    <div class="auth-buttons">
      <a href="login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a>
      <a href="register.php"><i class="bi bi-person-plus"></i> Register</a>
    </div>
  </header>

  <!-- Slider -->
  <main class="slider-wrap">
    <div class="cards-stage" id="cardsStage" aria-hidden="false"></div>

    <!-- prev / next controls -->
    <div class="ctrl prev" id="prevBtn" title="Previous" role="button" aria-label="Previous">
      <i class="bi bi-chevron-left" style="font-size:20px;color:#333;"></i>
    </div>
    <div class="ctrl next" id="nextBtn" title="Next" role="button" aria-label="Next">
      <i class="bi bi-chevron-right" style="font-size:20px;color:#333;"></i>
    </div>

    <!-- indicators -->
    <div class="indicators" id="indicators"></div>
  </main>

  <!-- Info / About -->
  <section class="info-section">
    <div class="container">
      <h2>About Umoja Sacco</h2>
      <p style="max-width:900px;margin:0 auto;color:#4b5563">
        Umoja Sacco is a member-driven financial institution focused on promoting savings, offering affordable loans,
        and fostering financial stability among our members. With a foundation built on trust and community spirit,
        we empower individuals and businesses to achieve their financial goals.
      </p>

      <div class="feature-row">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-piggy-bank"></i></div>
          <h5 class="mt-2">Save</h5>
          <p class="text-muted">Secure your future with flexible saving plans tailored to your goals.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-cash-stack"></i></div>
          <h5 class="mt-2">Borrow</h5>
          <p class="text-muted">Access low-interest loans with quick approval and flexible repayment terms.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-graph-up-arrow"></i></div>
          <h5 class="mt-2">Grow</h5>
          <p class="text-muted">Invest in your dreams and grow financially with Umoja Sacco by your side.</p>
        </div>
      </div>
    </div>
  </section>

  <footer style="text-align:center;padding:18px 10px;background:linear-gradient(90deg,var(--green),#00a86b);color:#fff;">
    Designed by <strong>Bezalel Leyian</strong>
  </footer>

  <script>
    // SETTINGS
    const TOTAL = 19;               // number of sacco images (sacco1..sacco19)
    const VISIBLE = 7;              // how many cards to keep visible (center +/-)
    const AUTO_PLAY_MS = 3000;
    const stage = document.getElementById('cardsStage');
    const indicatorsEl = document.getElementById('indicators');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    let current = 0;
    let nodes = [];
    let autoTimer = null;
    let isHover = false;

    // create slides
    function buildSlides() {
      for (let i = 0; i < TOTAL; i++) {
        const div = document.createElement('div');
        div.className = 'card-item';
        div.dataset.index = i;
        const img = document.createElement('img');
        img.src = `images/sacco${i+1}.jpg`;
        img.alt = `Umoja Sacco ${i+1}`;
        div.appendChild(img);
        div.addEventListener('click', () => {
          moveTo(i);
        });
        stage.appendChild(div);
        nodes.push(div);

        // indicator
        const dot = document.createElement('span');
        dot.className = 'dot';
        dot.dataset.index = i;
        dot.addEventListener('click', () => moveTo(i));
        indicatorsEl.appendChild(dot);
      }
      render();
    }

    // normalize difference into range -TOTAL/2..TOTAL/2
    function wrapDiff(diff){
      if(diff > TOTAL/2) diff -= TOTAL;
      if(diff < -TOTAL/2) diff += TOTAL;
      return diff;
    }

    function render() {
      // center position (left base)
      const centerX = stage.clientWidth / 2;
      const cardWidth = stage.clientWidth * 0.7; // card-width fraction
      const offsetX = Math.min(160, stage.clientWidth * 0.12); // horizontal spacing

      nodes.forEach((node, i) => {
        let diff = i - current;
        // wrap for circular
        if (diff > TOTAL/2) diff -= TOTAL;
        if (diff < -TOTAL/2) diff += TOTAL;

        const absd = Math.abs(diff);

        if (absd > Math.floor(VISIBLE/2)) {
          // hide far cards
          node.style.opacity = 0;
          node.style.pointerEvents = 'none';
          node.style.transform = `translateX(-50%) translateY(20px) scale(.7) rotateY(${diff * 6}deg)`;
          node.style.zIndex = 10;
        } else {
          const sign = diff < 0 ? -1 : 1;
          const x = diff * offsetX;
          const scale = 1 - (absd * 0.08);
          const rotateY = diff * -6; // slight Y tilt
          const rotateZ = diff * -2; // small twist
          const translateZ = -Math.abs(diff) * 30; // depth
          const topOffset = Math.abs(diff) * 8; // vertical stack offset

          node.style.opacity = 1;
          node.style.pointerEvents = 'auto';
          node.style.zIndex = (100 - absd);
          node.style.transform = `translateX(calc(${x}px - 50%)) translateY(${topOffset}px) translateZ(${translateZ}px) scale(${scale}) rotateZ(${rotateZ}deg) rotateY(${rotateY}deg)`;
        }
      });

      // update indicators
      Array.from(indicatorsEl.children).forEach((dot, idx) => {
        dot.classList.toggle('active', idx === current);
      });
    }

    function prev() {
      current = (current - 1 + TOTAL) % TOTAL;
      render();
    }
    function next() {
      current = (current + 1) % TOTAL;
      render();
    }
    function moveTo(i) {
      current = i % TOTAL;
      render();
      resetAuto();
    }

    // autoplay
    function startAuto() {
      stopAuto();
      autoTimer = setInterval(() => {
        if (!isHover) next();
      }, AUTO_PLAY_MS);
    }
    function stopAuto(){ if(autoTimer) clearInterval(autoTimer); autoTimer = null; }
    function resetAuto(){ startAuto(); }

    // bind controls
    prevBtn.addEventListener('click', () => { prev(); resetAuto(); });
    nextBtn.addEventListener('click', () => { next(); resetAuto(); });

    // pause on hover
    stage.addEventListener('mouseenter', ()=> isHover = true);
    stage.addEventListener('mouseleave', ()=> isHover = false);

    // keyboard navigation
    window.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft') { prev(); resetAuto(); }
      if (e.key === 'ArrowRight') { next(); resetAuto(); }
    });

    // build on load
    buildSlides();
    startAuto();

    // responsiveness: re-render on resize
    window.addEventListener('resize', render);
  </script>

</body>
</html>
