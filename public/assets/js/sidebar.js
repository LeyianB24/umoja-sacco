document.addEventListener('DOMContentLoaded', () => {
    // --- SIDEBAR TOGGLE LOGIC ---
    const body = document.body;
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const toggleBtn = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const mobileToggleBtn = document.getElementById('mobileToggle'); // Backup ID

    // PERSISTENCE
    if(localStorage.getItem('hd_sidebar_collapsed') === 'true') {
        body.classList.add('sb-collapsed');
    }

    // DESKTOP TOGGLE
    if(toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            body.classList.toggle('sb-collapsed');
            localStorage.setItem('hd_sidebar_collapsed', body.classList.contains('sb-collapsed'));
        });
    }

    // MOBILE TOGGLE
    const toggleMobile = (e) => {
        if(e) e.stopPropagation();
        if(sidebar) sidebar.classList.toggle('show');
        if(backdrop) backdrop.classList.toggle('show');
    };

    if(mobileToggle) mobileToggle.addEventListener('click', toggleMobile);
    if(mobileToggleBtn) mobileToggleBtn.addEventListener('click', toggleMobile);

    // CLICK BACKDROP TO CLOSE
    if(backdrop) {
        backdrop.addEventListener('click', () => {
             if(sidebar) sidebar.classList.remove('show');
             if(backdrop) backdrop.classList.remove('show');
        });
    }

    // CLICK LINK TO CLOSE (ON MOBILE)
    document.querySelectorAll('.hd-nav-item').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                if(sidebar) sidebar.classList.remove('show');
                if(backdrop) backdrop.classList.remove('show');
            }
        });
    });
});
