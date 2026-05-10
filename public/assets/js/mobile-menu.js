/**
 * mobile-menu.js — Umoja Sacco Mobile Navigation
 * Handles sidebar toggle and mobile menu interactions
 * Auto-detects device type (phone, tablet, desktop)
 * Last updated: 2026-05-08
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
        getBreakpoint() {
            const width = this.getViewportWidth();
            if (width <= 640) return 'mobile';
            if (width <= 1024) return 'tablet';
            return 'desktop';
        }
    };

    // Expose for use in other scripts
    window.DeviceDetector = DeviceDetector;

    // ═════════════════════════════════════════════════════════════════════════
    // 2. MOBILE MENU TOGGLE
    // ═════════════════════════════════════════════════════════════════════════

    const sidebar = document.querySelector('.admin-sidebar') || document.querySelector('.hd-sidebar');
    const mainContent = document.querySelector('.main-content-wrapper');
    
    // Create overlay element for mobile
    const overlay = document.createElement('div');
    overlay.className = 'admin-sidebar-overlay';
    document.body.appendChild(overlay);

    // Toggle sidebar on mobile
    const toggleSidebar = (show = null) => {
        if (DeviceDetector.getBreakpoint() !== 'mobile') {
            sidebar?.classList.remove('show');
            overlay.classList.remove('show');
            return;
        }

        const isShown = sidebar?.classList.contains('show');
        const shouldShow = show !== null ? show : !isShown;

        if (shouldShow) {
            sidebar?.classList.add('show');
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        } else {
            sidebar?.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    };

    // Find or create mobile menu toggle button
    let toggleBtn = document.querySelector('.mobile-menu-toggle');
    
    if (!toggleBtn && sidebar) {
        // Create button if it doesn't exist
        toggleBtn = document.createElement('button');
        toggleBtn.className = 'mobile-menu-toggle';
        toggleBtn.type = 'button';
        toggleBtn.setAttribute('aria-label', 'Toggle navigation menu');
        toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
        
        // Insert before topbar content
        const topbar = document.querySelector('.topbar');
        if (topbar) {
            topbar.insertBefore(toggleBtn, topbar.firstChild);
        } else {
            document.body.insertBefore(toggleBtn, document.body.firstChild);
        }
    }

    // Toggle on button click
    toggleBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleSidebar();
    });

    // Close sidebar when overlay is clicked
    overlay.addEventListener('click', () => {
        toggleSidebar(false);
    });

    // Close sidebar when a link is clicked
    sidebar?.addEventListener('click', (e) => {
        if (e.target.tagName === 'A' || e.target.closest('a')) {
            setTimeout(() => toggleSidebar(false), 100);
        }
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 3. RESPONSIVE BEHAVIOR
    // ═════════════════════════════════════════════════════════════════════════

    // Handle window resize
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            // Close mobile menu when resizing to desktop
            if (DeviceDetector.getBreakpoint() !== 'mobile') {
                toggleSidebar(false);
            }
        }, 150);
    });

    // Handle orientation change
    window.addEventListener('orientationchange', () => {
        setTimeout(() => {
            toggleSidebar(false);
            // Refresh viewport meta
            DeviceDetector.getViewportWidth();
        }, 100);
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 4. TOUCH & SWIPE SUPPORT
    // ═════════════════════════════════════════════════════════════════════════

    let touchStartX = 0;
    let touchEndX = 0;

    document.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    }, false);

    document.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, false);

    const handleSwipe = () => {
        // Swipe right from left edge opens menu
        if (touchEndX - touchStartX > 50 && touchStartX < 50) {
            if (DeviceDetector.getBreakpoint() === 'mobile') {
                toggleSidebar(true);
            }
        }
        // Swipe left closes menu
        if (touchStartX - touchEndX > 50) {
            toggleSidebar(false);
        }
    };

    // ═════════════════════════════════════════════════════════════════════════
    // 5. KEYBOARD SUPPORT (ESC to close menu)
    // ═════════════════════════════════════════════════════════════════════════

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar?.classList.contains('show')) {
            toggleSidebar(false);
        }
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 6. RESPONSIVE TABLE HEADERS
    // ═════════════════════════════════════════════════════════════════════════

    // Add data-label attributes to table cells for mobile display
    const initResponsiveTables = () => {
        document.querySelectorAll('.table').forEach((table) => {
            const headers = Array.from(table.querySelectorAll('thead th')).map(th => 
                th.textContent.trim()
            );

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
