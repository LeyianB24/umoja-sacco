<style>
/* --- HOPE UI TOPBAR VARIABLES --- */
:root {
    --nav-height: 80px;
    --font-family: 'Plus Jakarta Sans', sans-serif;
    
    /* Colors (Matching Sidebar) */
    --nav-bg: #FFFFFF;
    --nav-border: #F3F4F6;
    --nav-text: #6B7280;
    --nav-text-dark: #111827;
    
    /* Accents */
    --accent-green: #0F392B;  /* Deep Forest */
    --accent-lime: #D0F764;   /* Electric Lime */
    
    /* Interactive Elements */
    --btn-bg: #FFFFFF;
    --btn-border: #E5E7EB;
    --btn-hover-bg: #0F392B;
    --btn-hover-text: #D0F764;
}

[data-bs-theme="dark"] {
    --nav-bg: #0b1210;
    --nav-border: rgba(255,255,255,0.05);
    --nav-text: #9CA3AF;
    --nav-text-dark: #F3F4F6;
    --btn-bg: rgba(255,255,255,0.03);
    --btn-border: transparent;
}

/* --- LAYOUT --- */
.top-navbar {
    height: var(--nav-height);
    background: var(--nav-bg);
    border-bottom: 1px solid var(--nav-border);
    position: sticky;
    top: 0;
    z-index: 1020;
    font-family: var(--font-family);
    transition: background 0.3s ease;
}

/* --- INTERACTIVE ICONS --- */
.btn-icon-nav {
    width: 44px; /* Default */
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    color: var(--nav-text);
    background: var(--btn-bg);
    border: 1px solid var(--btn-border);
    transition: all 0.2s cubic-bezier(0.25, 0.8, 0.25, 1);
    position: relative;
}
/* Overwrite from second block */
.btn-icon-nav {
    background: transparent; border: none; position: relative;
    font-size: 1.3rem; color: var(--nav-text); transition: 0.3s;
    width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%;
}
.btn-icon-nav:hover, .btn-icon-nav[aria-expanded="true"] {
    background-color: rgba(13, 131, 75, 0.1); 
    /* color: var(--iq-secondary); -- Not defined, assume nav-text-dark or accent */
    color: var(--accent-green);
}

/* Badge Dots */
.badge-dot {
    position: absolute;
    top: 10px; right: 10px;
    width: 8px; height: 8px;
    background: #EF4444; /* Standard Red */
    border: 2px solid var(--btn-bg);
    border-radius: 50%;
}
.btn-icon-nav:hover .badge-dot {
    background: var(--accent-lime);
    border-color: var(--accent-green);
}

/* --- USER PROFILE PILL --- */
.user-info-pill {
    padding: 6px 6px 6px 16px;
    background: var(--btn-bg);
    border: 1px solid var(--btn-border);
    border-radius: 50px;
    transition: all 0.2s;
    cursor: pointer;
}
.user-info-pill:hover {
    border-color: var(--accent-green);
}

.profile-avatar {
    width: 38px; height: 38px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

/* Role Badge */
.role-badge {
    background: var(--accent-green);
    color: var(--accent-lime);
    font-size: 0.6rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
    letter-spacing: 0.5px;
}

/* --- DROPDOWNS --- */
.custom-dropdown {
    border: 1px solid var(--nav-border);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    background: var(--nav-bg);
    border-radius: 16px;
    padding: 10px;
    margin-top: 10px !important;
    animation: fadeUp 0.2s ease-out forwards;
}
.dropdown-header-custom {
    padding: 8px 12px;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--nav-text);
    opacity: 0.7;
    letter-spacing: 1px;
}
.list-item-custom {
    display: block;
    padding: 10px 14px;
    border-radius: 10px;
    text-decoration: none;
    color: var(--nav-text-dark);
    transition: all 0.2s;
    position: relative;
}
.list-item-custom:hover {
    background: #F9FAFB;
    color: var(--accent-green);
}
.list-item-custom.unread {
    background: rgba(208, 247, 100, 0.1); /* Lime tint */
}
.list-item-custom.unread::before {
    content: '';
    position: absolute; left: 6px; top: 50%; transform: translateY(-50%);
    width: 4px; height: 4px;
    background: var(--accent-green);
    border-radius: 50%;
}
[data-bs-theme="dark"] .list-item-custom:hover { background: rgba(255,255,255,0.05); }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Additional specific styles */
.msg-avatar { width: 40px; height: 40px; object-fit: cover; border-radius: 50%; }
.notif-icon-box {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: rgba(13, 131, 75, 0.1); color: var(--accent-green); font-size: 1.1rem;
}
</style>
