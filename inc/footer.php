<?php
// inc/footer.php
?>
<!-- Footer -->
<footer class="site-footer mt-5" style="background:#073b23;color:#fff;">
  <div class="container py-4">
    <div class="row align-items-center">
      <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
        <strong><?= htmlspecialchars(SITE_NAME) ?></strong><br>
        <small style="color:#fff;">Empowering members through unity, savings & investment</small>
      </div>
      <div class="col-md-6 text-center text-md-end">
        <small style="color:#fff;">© <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?> — Designed by Bezalel Technologies LTD</small>
      </div>
    </div>
  </div>
</footer>

<!-- Scripts: Bootstrap + Custom JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= ASSET_BASE ?>/js/main.js"></script>

<!-- Poker slideshow script -->
<script>
(function(){
  const container = document.querySelector('.poker-slideshow');
  if (!container) return;

  const cards = Array.from(container.querySelectorAll('.poker-card'));
  if (!cards.length) return;

  let idx = 0;
  const bringToFront = (i) => {
    cards.forEach((c, j) => {
      const pos = (j - i + cards.length) % cards.length;
      const rotate = -18 + pos * 8;
      const left = 10 + pos * 36;
      const top = pos * 6;
      const scale = 1 - (cards.length - pos - 1) * 0.02;
      c.style.transform = `translate(${left}px, ${top}px) rotate(${rotate}deg) scale(${scale})`;
      c.style.zIndex = 100 + pos;
      c.style.opacity = pos < cards.length ? 1 : 0;
    });
  };

  bringToFront(idx);

  let interval = setInterval(()=>{
    idx = (idx + 1) % cards.length;
    bringToFront(idx);
  }, 3000);

  const prevBtn = document.getElementById('slideshow-prev');
  const nextBtn = document.getElementById('slideshow-next');

  if (prevBtn) prevBtn.addEventListener('click', ()=>{
    idx = (idx - 1 + cards.length) % cards.length;
    bringToFront(idx);
    clearInterval(interval);
    interval = setInterval(()=>{ idx = (idx + 1) % cards.length; bringToFront(idx); }, 3000);
  });

  if (nextBtn) nextBtn.addEventListener('click', ()=>{
    idx = (idx + 1) % cards.length;
    bringToFront(idx);
    clearInterval(interval);
    interval = setInterval(()=>{ idx = (idx + 1) % cards.length; bringToFront(idx); }, 3000);
  });

  container.addEventListener('mouseenter', ()=> clearInterval(interval));
  container.addEventListener('mouseleave', ()=>{
    interval = setInterval(()=>{ idx = (idx + 1) % cards.length; bringToFront(idx); }, 3000);
  });
})();
</script>

</body>
</html>
