/**
 * Global Live Refresh & Idle Timeout Widget
 * Auto-refreshes the page every 15 seconds (with intelligent pausing)
 * Auto-logs out the user after 2 minutes of complete inactivity.
 */
document.addEventListener('DOMContentLoaded', () => {

    /* ==============================================================================
     * 1. Auto-Logout (Idle Timer) Logic
     * Automatically logs the user out after 2 minutes of no interaction.
     * ============================================================================== */
    const IDLE_TIMEOUT_MS = 2 * 60 * 1000; // 2 minutes
    let idleTimer;

    const resetIdleTimer = () => {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(() => {
            // Force logout
            const basePath = window.location.pathname.includes('/usms/') ? '/usms' : '';
            window.location.href = basePath + '/public/logout.php?reason=idle_timeout';
        }, IDLE_TIMEOUT_MS);
    };

    // Listen to standard user interaction events to reset the timer
    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetIdleTimer, true);
    });
    
    // Start idle timer immediately
    resetIdleTimer();


    /* ==============================================================================
     * 2. Live Page Refresh Logic
     * ============================================================================== */
    // Skip if on monitor.php (has its own hardcoded refresh UI) or explicitly disabled
    if (window.location.pathname.includes('monitor.php')) return;
    if (window.DISABLE_LIVE_REFRESH) return;

    const MAX_TIME = 15;
    let countdown = MAX_TIME;
    let isPaused = false;
    let hoverPause = false;
    let inputFocus = false;

    // Create Floating UI Widget
    const widget = document.createElement('div');
    widget.id = 'usms-live-refresh-widget';
    widget.innerHTML = `
        <div style="display:flex;align-items:center;gap:8px;background:var(--surf, var(--tb-bg, var(--tb-surface, #fff)));border:1px solid rgba(13,31,20,0.1);padding:6px 12px;border-radius:50px;box-shadow:0 6px 16px rgba(0,0,0,0.12);cursor:pointer;transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1); backdrop-filter: blur(10px);" title="Live Refresh is active. Click to Pause/Resume">
            <div id="glr-pulse" style="width:8px;height:8px;border-radius:50%;background:#16a34a;box-shadow:0 0 10px rgba(22,163,74,0.6);transition:all 0.3s"></div>
            <span id="glr-text" style="font-size:0.75rem;font-weight:700;color:var(--t1, var(--tb-ink, #000));letter-spacing:0.5px">SYNC: <span id="glr-time">${MAX_TIME}</span>S</span>
        </div>
    `;
    widget.style.position = 'fixed';
    widget.style.bottom = '24px';
    widget.style.left = '24px';
    widget.style.zIndex = '9999';
    widget.style.fontFamily = "'Plus Jakarta Sans', sans-serif";
    widget.style.opacity = '0';
    widget.style.transform = 'translateY(10px)';
    widget.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
    
    document.body.appendChild(widget);

    // Fade in
    setTimeout(() => {
        widget.style.opacity = '1';
        widget.style.transform = 'translateY(0)';
    }, 500);

    const pulse = document.getElementById('glr-pulse');
    const timeEl = () => document.getElementById('glr-time');
    const textEl = document.getElementById('glr-text');

    // Toggle Pause Manually
    widget.addEventListener('click', () => {
        isPaused = !isPaused;
        if (isPaused) {
            pulse.style.background = '#9ca3af'; // muted gray
            pulse.style.boxShadow = 'none';
            textEl.innerHTML = '<span style="color:#6b7280">PAUSED</span>';
        } else {
            pulse.style.background = '#16a34a';
            pulse.style.boxShadow = '0 0 10px rgba(22,163,74,0.6)';
            textEl.innerHTML = 'SYNC: <span id="glr-time">' + MAX_TIME + '</span>S';
            countdown = MAX_TIME;
        }
    });
    
    // Slight bump animation on hover
    widget.addEventListener('mouseenter', () => widget.style.transform = 'translateY(-3px)');
    widget.addEventListener('mouseleave', () => widget.style.transform = 'translateY(0)');

    // Intelligent Interaction Pausing
    document.addEventListener('focusin', (e) => {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
            inputFocus = true;
        }
    });

    document.addEventListener('focusout', () => {
        inputFocus = false;
        countdown = MAX_TIME; 
    });

    document.querySelectorAll('.detail-card, .table-responsive, form, .modal').forEach(el => {
        el.addEventListener('mouseenter', () => hoverPause = true);
        el.addEventListener('mouseleave', () => hoverPause = false);
    });

    // Timer Loop
    setInterval(() => {
        if (isPaused) return;

        // Check if any Bootstrap modal is currently open
        const modalOpen = document.querySelector('.modal.show') !== null;
        
        if (hoverPause || inputFocus || modalOpen) {
            if (timeEl()) {
                textEl.innerHTML = '<span style="color:#d97706">WAITING...</span>';
                pulse.style.background = '#d97706';
                pulse.style.boxShadow = '0 0 10px rgba(217,119,6,0.6)';
            }
            return;
        }

        // Restore active UI if auto-paused previously
        if (textEl.innerText === 'WAITING...') {
            pulse.style.background = '#16a34a';
            pulse.style.boxShadow = '0 0 10px rgba(22,163,74,0.6)';
            textEl.innerHTML = 'SYNC: <span id="glr-time">' + countdown + '</span>S';
        }

        countdown--;

        if (countdown <= 0) {
            textEl.innerHTML = '<span style="color:#16a34a">SYNCING...</span>';
            window.location.replace(window.location.href);
        } else {
            const t = timeEl();
            if(t) t.innerText = countdown;
        }
    }, 1000);
});
