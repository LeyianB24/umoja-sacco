/**
 * sidebar.js — Umoja Sacco Admin Sidebar
 * Works with: layout.css (.admin-sidebar, .sidebar-overlay, .main-wrapper)
 * Updated: 2026-02-21
 */
(function () {
    'use strict';

    const STORAGE_KEY = 'usms_sidebar_collapsed';

    let sidebar, overlay, mainWrapper;

    function init() {
        sidebar     = document.querySelector('.admin-sidebar');
        overlay     = document.querySelector('.sidebar-overlay');
        mainWrapper = document.querySelector('.main-wrapper');

        if (!sidebar) return;

        // Restore desktop collapsed state
        if (localStorage.getItem(STORAGE_KEY) === 'true') {
            document.body.classList.add('sb-collapsed');
        }

        // Wire all toggle buttons (desktop + mobile, any selector)
        document.querySelectorAll(
            '#sidebarToggle, #mobileSidebarToggle, #mobileToggle, .sidebar-toggle-btn'
        ).forEach(btn => btn.addEventListener('click', onToggle));

        // Overlay click → close on mobile
        if (overlay) {
            overlay.addEventListener('click', closeOnMobile);
        }

        // Nav links → close sidebar on mobile after navigation
        document.querySelectorAll('.sidebar-nav-item').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) closeOnMobile();
            });
        });

        // Handle window resize — reset mobile state when going wide
        window.addEventListener('resize', debounce(() => {
            if (window.innerWidth >= 992) closeOnMobile();
        }, 150));
    }

    function onToggle(e) {
        e.stopPropagation();
        if (window.innerWidth < 992) {
            toggleMobile();
        } else {
            toggleDesktop();
        }
    }

    function toggleMobile() {
        const isOpen = sidebar.classList.toggle('sidebar-open');
        if (overlay) overlay.classList.toggle('show', isOpen);
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    function closeOnMobile() {
        sidebar.classList.remove('sidebar-open');
        if (overlay) overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    function toggleDesktop() {
        document.body.classList.toggle('sb-collapsed');
        localStorage.setItem(STORAGE_KEY, document.body.classList.contains('sb-collapsed'));
    }

    function debounce(fn, ms) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }

    document.addEventListener('DOMContentLoaded', init);
})();
