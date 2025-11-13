// -------------------- POKER CARD SLIDESHOW --------------------
(() => {
  const slideshow = document.querySelector('.poker-slideshow');
  if (!slideshow) return;

  const slides = Array.from(slideshow.querySelectorAll('.poker-card'));
  let current = 0;
  const total = slides.length;

  function refresh() {
    slides.forEach((s, idx) => {
      s.removeAttribute('data-active');
      s.removeAttribute('data-side');
      s.removeAttribute('data-hidden');
    });

    const center = current;
    slides[center].setAttribute('data-active', 'true');

    const left = (center - 1 + total) % total;
    const right = (center + 1) % total;
    const left2 = (center - 2 + total) % total;
    const right2 = (center + 2) % total;

    slides[left].setAttribute('data-side', 'left');
    slides[right].setAttribute('data-side', 'right');
    slides[left2].setAttribute('data-side', 'left2');
    slides[right2].setAttribute('data-side', 'right2');

    slides.forEach((s, idx) => {
      if (![center, left, right, left2, right2].includes(idx)) {
        s.setAttribute('data-hidden', 'true');
      }
    });
  }

  function next() { current = (current + 1) % total; refresh(); }
  function prev() { current = (current - 1 + total) % total; refresh(); }

  const btnNext = document.getElementById('slideshow-next');
  const btnPrev = document.getElementById('slideshow-prev');
  if (btnNext) btnNext.addEventListener('click', next);
  if (btnPrev) btnPrev.addEventListener('click', prev);

  let rot = setInterval(next, 4200);
  slideshow.addEventListener('mouseenter', () => clearInterval(rot));
  slideshow.addEventListener('mouseleave', () => rot = setInterval(next, 4200));

  document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') next();
    if (e.key === 'ArrowLeft') prev();
  });

  refresh();
})();

// -------------------- ADMIN DASHBOARD --------------------
(function(){
  const sidebar = document.getElementById('sidebar');
  const btn = document.getElementById('sidebarToggle');
  if (btn && sidebar) {
    btn.addEventListener('click', function(){ sidebar.classList.toggle('show'); });
  }

  document.addEventListener('click', function(e){
    if (!sidebar) return;
    if (window.innerWidth > 991) return;
    if (!sidebar.classList.contains('show')) return;
    const inside = e.target.closest('.sidebar');
    const toggle = e.target.closest('#sidebarToggle');
    if (!inside && !toggle) sidebar.classList.remove('show');
  });
})();
