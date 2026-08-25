/**
 * mobile-menu.js — Umoja Sacco Mobile Navigation
 * Handles sidebar toggle and mobile menu interactions
 * Auto-detects device type (phone, tablet, desktop)
 * Last updated: 2026-05-11
 */

document.addEventListener('DOMContentLoaded', () => {
    // ═════════════════════════════════════════════════════════════════════════
    // 1. DEVICE DETECTION
    // ═════════════════════════════════════════════════════════════════════════
    
    const DeviceDetector = {
        isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        },
        isTablet() {
            return /iPad|Android(?!.*Mobile)/i.test(navigator.userAgent);
        },
        isPhone() {
            return /iPhone|Android.*Mobile/i.test(navigator.userAgent);
        },
        isDesktop() {
            return !this.isMobile();
        },
        getViewportWidth() {
            return Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        },
        getViewportHeight() {
            return Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
        },
        isPortrait() {
            return window.innerHeight > window.innerWidth;
        },
        isLandscape() {
            return window.innerWidth > window.innerHeight;
        },
        getDeviceType() {
            if (this.isPhone()) return 'phone';
            if (this.isTablet()) return 'tablet';
            return 'desktop';
        },
        // Match Bootstrap's lg breakpoint (992px) — same as d-lg-none on hamburger
        isMobileBreakpoint() {
            return this.getViewportWidth() < 992;
        },
        getBreakpoint() {
            const w = this.getViewportWidth();
            if (w < 576) return 'xs';
            if (w < 768) return 'sm';
            if (w < 992) return 'md';
            if (w < 1200) return 'lg';
            return 'xl';
        }
    };

    // Expose for use in other scripts
    window.DeviceDetector = DeviceDetector;

    // ═════════════════════════════════════════════════════════════════════════
    // 2. MOBILE MENU TOGGLE — wires ALL known sidebar + backdrop selectors
    // ═════════════════════════════════════════════════════════════════════════

    const sidebar  = document.querySelector('.hd-sidebar') || document.querySelector('.admin-sidebar');
    const backdrop = document.querySelector('#sidebarBackdrop') ||
                     document.querySelector('.sidebar-backdrop') ||
                     document.querySelector('.sidebar-overlay');

    const toggleSidebar = (forceShow = null) => {
        if (!sidebar) return;

        if (!DeviceDetector.isMobileBreakpoint()) {
            // On desktop: remove mobile classes, let sidebar.js handle collapse toggle
            sidebar.classList.remove('show', 'mobile-open', 'sidebar-open');
            if (backdrop) backdrop.classList.remove('show');
            document.body.style.overflow = '';
            return;
        }

        const isOpen = sidebar.classList.contains('show') ||
                       sidebar.classList.contains('mobile-open') ||
                       sidebar.classList.contains('sidebar-open');
        const shouldOpen = forceShow !== null ? forceShow : !isOpen;

        sidebar.classList.toggle('show',         shouldOpen);
        sidebar.classList.toggle('mobile-open',  shouldOpen);
        sidebar.classList.toggle('sidebar-open', shouldOpen);

        if (backdrop) backdrop.classList.toggle('show', shouldOpen);
        document.body.style.overflow = shouldOpen ? 'hidden' : '';
    };

    // Wire ALL known hamburger / toggle selectors in a single pass
    const toggleSelectors = [
        '#mobileSidebarToggle',   // Admin topbar hamburger (d-lg-none)
        '#mobileToggle',
        '#sidebarToggle',
        '.mobile-menu-toggle',
        '.mobile-nav-toggle',
        '.sidebar-toggle-btn',
    ];
    document.querySelectorAll(toggleSelectors.join(',')).forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            toggleSidebar();
        });
    });

    // Close on backdrop click
    if (backdrop) {
        backdrop.addEventListener('click', () => toggleSidebar(false));
    }

    // Close on sidebar nav link click (mobile only)
    if (sidebar) {
        sidebar.addEventListener('click', e => {
            if ((e.target.tagName === 'A' || e.target.closest('a')) &&
                DeviceDetector.isMobileBreakpoint()) {
                setTimeout(() => toggleSidebar(false), 100);
            }
        });
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3. RESPONSIVE BEHAVIOR
    // ═════════════════════════════════════════════════════════════════════════

    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            if (!DeviceDetector.isMobileBreakpoint()) toggleSidebar(false);
        }, 150);
    });

    window.addEventListener('orientationchange', () => {
        setTimeout(() => toggleSidebar(false), 100);
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 4. SWIPE SUPPORT (open from left edge, close by swiping left)
    // ═════════════════════════════════════════════════════════════════════════

    let touchStartX = 0;
    let touchEndX   = 0;

    document.addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    document.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        if (!DeviceDetector.isMobileBreakpoint()) return;
        if (touchEndX - touchStartX > 60 && touchStartX < 40) toggleSidebar(true);   // swipe right from edge
        if (touchStartX - touchEndX > 60) toggleSidebar(false);                      // swipe left
    }, { passive: true });

    // ═════════════════════════════════════════════════════════════════════════
    // 5. KEYBOARD (ESC closes sidebar)
    // ═════════════════════════════════════════════════════════════════════════

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && sidebar?.classList.contains('show')) {
            toggleSidebar(false);
        }
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 6. RESPONSIVE TABLES (Data-label injection)
    // ═════════════════════════════════════════════════════════════════════════
    const initResponsiveTables = () => {
        document.querySelectorAll('table.table, table.sh-table, table.staff-table').forEach(table => {
            const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.innerText.trim());
            table.querySelectorAll('tbody tr').forEach((row) => {
                row.querySelectorAll('td').forEach((cell, index) => {
                    if (headers[index] && !cell.hasAttribute('data-label')) {
                        cell.setAttribute('data-label', headers[index]);
                    }
                });
            });
        });
    };

    initResponsiveTables();

    // ═════════════════════════════════════════════════════════════════════════
    // 7. FORM INPUT OPTIMIZATION
    // ═════════════════════════════════════════════════════════════════════════

    // Prevent auto-zoom on iOS when focusing input
    document.addEventListener('touchstart', (e) => {
        if (e.target.matches('input, textarea, select')) {
            e.target.style.fontSize = '16px';
        }
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 8. LOGGER & DEBUG INFO
    // ═════════════════════════════════════════════════════════════════════════

    const MobileDebug = {
        log() {
            console.log('%c📱 Device Detector Info', 'color: #16a34a; font-weight: bold;');
            console.log(`Device Type: ${DeviceDetector.getDeviceType()}`);
            console.log(`Breakpoint: ${DeviceDetector.getBreakpoint()}`);
            console.log(`Viewport: ${DeviceDetector.getViewportWidth()}x${DeviceDetector.getViewportHeight()}`);
            console.log(`Orientation: ${DeviceDetector.isPortrait() ? 'Portrait' : 'Landscape'}`);
            console.log(`User Agent: ${navigator.userAgent}`);
        }
    };

    // Debug info available via console
    window.MobileDebug = MobileDebug;

    // Log on load if in development
    if (document.documentElement.getAttribute('data-bs-theme') === 'development' || 
        window.location.hostname === 'localhost') {
        // Uncomment below for debug logging:
        // MobileDebug.log();
    }
});
