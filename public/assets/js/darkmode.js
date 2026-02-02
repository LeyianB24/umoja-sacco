document.addEventListener('DOMContentLoaded', () => {
    const html = document.documentElement;
    const themeIcon = document.getElementById('themeIcon');
    const saved = localStorage.getItem('theme') || 'light';

    // Apply saved theme immediately
    html.setAttribute('data-bs-theme', saved);
    if(themeIcon) updateIcon(saved);

    const toggleBtn = document.getElementById('themeToggle');
    if(toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const current = html.getAttribute('data-bs-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-bs-theme', next);
            localStorage.setItem('theme', next);
            if(themeIcon) updateIcon(next);
            
            // Dispatch event
            window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: next } }));
        });
    }

    function updateIcon(theme) {
        if(!themeIcon) return;
        if(theme === 'dark'){
            themeIcon.classList.replace('bi-moon-stars', 'bi-sun');
        } else {
            themeIcon.classList.replace('bi-sun', 'bi-moon-stars');
        }
    }
});
