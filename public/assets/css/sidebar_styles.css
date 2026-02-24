<style>
    /* --- SIDEBAR THEME VARIABLES --- */
    :root {
        --sb-width: 280px;
        --sb-collapsed: 86px; /* Width when closed */
        
        --sb-bg: #FFFFFF;
        --sb-border: #F3F4F6;
        --sb-text: #6B7280;
        --sb-hover-bg: #F9FAFB;
        --sb-hover-text: #111827;
        
        /* Forest Green & Lime Theme */
        --active-bg: #0F392B; 
        --active-text: #FFFFFF;
        --accent-lime: #D0F764;
    }

    [data-bs-theme="dark"] {
        --sb-bg: #0b1210; 
        --sb-border: rgba(255,255,255,0.05);
        --sb-text: #9CA3AF;
        --sb-hover-bg: rgba(255,255,255,0.05);
        --sb-hover-text: #F3F4F6;
        --active-bg: #134e3b;
    }

    /* --- SIDEBAR CONTAINER --- */
    .hd-sidebar {
        width: var(--sb-width);
        height: 100vh;
        position: fixed;
        top: 0; left: 0;
        z-index: 1050;
        background: var(--sb-bg);
        border-right: 1px solid var(--sb-border);
        display: flex;
        flex-direction: column;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); /* Smooth Physics */
        box-shadow: 5px 0 30px rgba(0,0,0,0.02);
    }

    /* --- RESPONSIVE LOGIC --- */
    @media (min-width: 992px) {
        body.sb-collapsed .hd-sidebar { width: var(--sb-collapsed); }
        body.sb-collapsed .main-content-wrapper,
        body.sb-collapsed .main-content,
        body.sb-collapsed .hp-main-wrapper,
        body.sb-collapsed main { margin-left: var(--sb-collapsed) !important; }
        body.sb-collapsed .hd-brand-text,
        body.sb-collapsed .hd-nav-text,
        body.sb-collapsed .hd-nav-header,
        body.sb-collapsed .hd-support-widget { opacity: 0; pointer-events: none; display: none; }
        body.sb-collapsed .hd-nav-item { justify-content: center; padding: 14px 0; margin: 4px 12px; }
        body.sb-collapsed .hd-nav-item i { margin-right: 0; font-size: 1.4rem; }
        body.sb-collapsed .hd-brand { padding: 0; justify-content: center; }
    }

    @media (max-width: 991px) {
        .hd-sidebar { transform: translateX(-100%); width: var(--sb-width); transition: transform 0.3s ease; }
        .hd-sidebar.show { transform: translateX(0); box-shadow: 0 0 50px rgba(0,0,0,0.2); }
        .hd-toggle-btn { display: none !important; }
    }

    /* --- TOGGLE BUTTON (3 Dashes) --- */
    .hd-toggle-btn {
        position: fixed; top: 24px; left: 260px; z-index: 1045;
        width: 36px; height: 36px; border-radius: 8px; background: #FFFFFF; 
        border: 1px solid var(--sb-border); color: var(--sb-text);
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05); cursor: pointer; transition: all 0.3s;
    }
    .hd-toggle-btn:hover { background: var(--sb-hover-bg); color: var(--sb-hover-text); }
    body.sb-collapsed .hd-toggle-btn { left: 66px; }

    .hd-brand { height: 80px; display: flex; align-items: center; padding: 0 24px; }
    .hd-logo-img { width: 40px; height: 40px; border-radius: 12px; object-fit: cover; }
    .hd-scroll-area { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 10px 16px; scrollbar-width: none; }
    .hd-scroll-area::-webkit-scrollbar { display: none; }

    .hd-nav-header {
        font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px;
        color: #9CA3AF; margin: 24px 0 8px 12px; white-space: nowrap;
    }

    .hd-nav-item {
        display: flex; align-items: center; padding: 12px 16px; color: var(--sb-text);
        text-decoration: none; border-radius: 50px; margin-bottom: 4px; transition: all 0.2s;
        white-space: nowrap; overflow: hidden; font-weight: 500;
    }
    .hd-nav-item:hover { background: var(--sb-hover-bg); color: var(--sb-hover-text); transform: translateX(3px); }
    .hd-nav-item.active { background: var(--active-bg); color: var(--active-text); box-shadow: 0 4px 15px rgba(15, 57, 43, 0.25); }
    .hd-nav-item.active i { color: var(--accent-lime); }
    .hd-nav-item i { font-size: 1.25rem; width: 24px; text-align: center; margin-right: 14px; flex-shrink: 0; }

    .hd-footer { padding: 20px; background: var(--sb-bg); border-top: 1px solid var(--sb-border); }
    .hd-support-widget {
        background: var(--active-bg); color: white; padding: 20px; border-radius: 20px;
        text-align: center; margin: 20px 0; position: relative; overflow: hidden;
    }
    .hd-support-btn {
        display: block; width: 100%; background: var(--accent-lime); color: var(--active-bg);
        font-weight: 700; padding: 10px; border-radius: 50px; text-decoration: none; margin-top: 12px;
    }

    .sidebar-backdrop {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); z-index: 1040; opacity: 0; visibility: hidden; transition: 0.3s;
    }
    .sidebar-backdrop.show { opacity: 1; visibility: visible; }

    .main-content, .hp-main-wrapper { margin-left: var(--sb-width); transition: margin-left 0.3s; }
</style>
