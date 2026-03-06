<?php
// inc/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';

if (class_exists('USMS\\Middleware\\CsrfMiddleware')) {
    \USMS\Middleware\CsrfMiddleware::token();
}

if (!function_exists('is_active')) {
    function is_active($page_name) {
        return basename($_SERVER['PHP_SELF']) === $page_name ? 'active' : '';
    }
}

$is_logged_in = isset($_SESSION['member_id']) || isset($_SESSION['admin_id']);

$dashboard_link = '/member/pages/dashboard.php';
if (isset($_SESSION['admin_id'])) {
    $role = $_SESSION['role'] ?? 'admin';
    $dashboard_link = match($role) {
        'superadmin' => '/superadmin/dashboard.php',
        'manager'    => '/manager/dashboard.php',
        'accountant' => '/accountant/dashboard.php',
        default      => '/admin/pages/dashboard.php'
    };
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (class_exists('USMS\\Middleware\\CsrfMiddleware')): ?>
        <?= \USMS\Middleware\CsrfMiddleware::metaTag() ?>
    <?php endif; ?>
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Sacco Portal' ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/variables.css">
    <link rel="stylesheet" href="<?= ASSET_BASE ?>/css/style.css">
    <link rel="stylesheet" href="<?= ASSET_BASE ?>/css/darkmode.css">

    <style>
    *, *::before, *::after { box-sizing: border-box; }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background-color: var(--bg-primary, #F7FBF9);
        color: var(--text-main, #0F392B);
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    /* ─── Navbar Shell ─── */
    .site-header {
        position: sticky;
        top: 0;
        z-index: 1050;
    }
    .site-navbar {
        background: linear-gradient(135deg, #0B1E17 0%, #0F392B 60%, #0d2e22 100%);
        border-bottom: 1px solid rgba(163,230,53,0.15);
        padding: 0;
        box-shadow: 0 2px 24px rgba(0,0,0,0.22);
        height: 68px;
        display: flex;
        align-items: center;
    }

    /* ─── Brand ─── */
    .nb-brand {
        display: flex;
        align-items: center;
        gap: 11px;
        text-decoration: none;
        flex-shrink: 0;
    }
    .nb-logo-wrap {
        width: 40px; height: 40px;
        border-radius: 12px;
        background: #fff;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden;
        border: 1.5px solid rgba(163,230,53,0.3);
        box-shadow: 0 4px 14px rgba(0,0,0,0.2);
        flex-shrink: 0;
    }
    .nb-logo-wrap img { width: 100%; height: 100%; object-fit: contain; padding: 4px; display: block; }
    .nb-brand-name {
        font-size: 0.92rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.2px;
        line-height: 1.2;
        white-space: nowrap;
    }
    .nb-brand-tagline {
        font-size: 0.58rem;
        font-weight: 700;
        color: rgba(255,255,255,0.35);
        text-transform: uppercase;
        letter-spacing: 0.9px;
        white-space: nowrap;
    }

    /* ─── Nav Links ─── */
    .nb-nav { list-style: none; display: flex; align-items: center; gap: 2px; margin: 0; padding: 0; flex-wrap: wrap; }
    .nb-nav-link {
        display: inline-flex;
        align-items: center;
        padding: 7px 13px;
        border-radius: 10px;
        font-size: 0.84rem;
        font-weight: 600;
        color: rgba(255,255,255,0.6);
        text-decoration: none;
        transition: all 0.2s;
        position: relative;
        white-space: nowrap;
    }
    .nb-nav-link:hover { color: #fff; background: rgba(255,255,255,0.07); }
    .nb-nav-link.active {
        color: #A3E635;
        background: rgba(163,230,53,0.1);
    }

    /* ─── Vertical Divider ─── */
    .nb-divider {
        width: 1px; height: 22px;
        background: rgba(255,255,255,0.1);
        margin: 0 6px;
        flex-shrink: 0;
    }

    /* ─── Auth Buttons ─── */
    .btn-nb-login {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 10px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.82rem;
        font-weight: 700;
        color: rgba(255,255,255,0.75);
        background: transparent;
        border: 1.5px solid rgba(255,255,255,0.2);
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .btn-nb-login:hover { border-color: rgba(255,255,255,0.5); color: #fff; background: rgba(255,255,255,0.06); }

    .btn-nb-register {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        border-radius: 10px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.82rem;
        font-weight: 800;
        color: #0F392B;
        background: #A3E635;
        border: none;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 4px 14px rgba(163,230,53,0.28);
        white-space: nowrap;
    }
    .btn-nb-register:hover { background: #bde32a; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(163,230,53,0.38); color: #0F392B; }

    /* ─── Theme Toggle ─── */
    .nb-theme-btn {
        width: 36px; height: 36px;
        border-radius: 10px;
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.5);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .nb-theme-btn:hover { background: rgba(163,230,53,0.12); color: #A3E635; border-color: rgba(163,230,53,0.25); }

    /* ─── Account Dropdown ─── */
    .nb-account-trigger {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 6px 12px 6px 7px;
        border-radius: 10px;
        background: rgba(163,230,53,0.1);
        border: 1px solid rgba(163,230,53,0.2);
        color: #A3E635;
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        white-space: nowrap;
    }
    .nb-account-trigger:hover { background: rgba(163,230,53,0.18); color: #A3E635; }
    .nb-account-trigger .nb-avatar {
        width: 26px; height: 26px;
        border-radius: 8px;
        background: linear-gradient(135deg, #0F392B, #1a5c43);
        color: #A3E635;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.7rem;
        font-weight: 800;
        flex-shrink: 0;
    }

    .nb-dropdown {
        border: 1px solid #E8F0ED;
        border-radius: 16px;
        box-shadow: 0 14px 44px rgba(15,57,43,0.12);
        padding: 6px;
        margin-top: 10px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        min-width: 200px;
    }
    .nb-dropdown-item {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 9px 12px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        color: #0F392B;
        text-decoration: none;
        transition: background 0.18s;
    }
    .nb-dropdown-item:hover { background: #F7FBF9; color: #0F392B; }
    .nb-dropdown-item.danger { color: #dc2626; }
    .nb-dropdown-item.danger:hover { background: #FEE2E2; }
    .nb-dropdown-icon {
        width: 26px; height: 26px;
        border-radius: 8px;
        background: #F0F7F4;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.75rem;
        flex-shrink: 0;
        color: #0F392B;
    }
    .nb-dropdown-item.danger .nb-dropdown-icon { background: #FEE2E2; color: #dc2626; }
    .nb-dropdown-divider { height: 1px; background: #E8F0ED; margin: 5px 4px; }

    /* ─── Mobile Toggle Button ─── */
    .nb-mobile-toggle {
        width: 38px; height: 38px;
        border-radius: 10px;
        background: rgba(255,255,255,0.07);
        border: 1px solid rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.7);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .nb-mobile-toggle:hover { background: rgba(163,230,53,0.12); color: #A3E635; border-color: rgba(163,230,53,0.2); }

    /* ─── Mobile Collapse ─── */
    @media (max-width: 991px) {
        .navbar-collapse {
            background: #0d2218;
            border-radius: 16px;
            padding: 16px;
            margin-top: 12px;
            border: 1px solid rgba(255,255,255,0.07);
            box-shadow: 0 16px 40px rgba(0,0,0,0.3);
        }
        .nb-nav { flex-direction: column; align-items: stretch; gap: 2px; }
        .nb-nav-link { padding: 10px 14px; }
        .nb-divider { display: none; }
        .btn-nb-login,
        .btn-nb-register { width: 100%; justify-content: center; margin-bottom: 6px; padding: 11px; }
        .nb-account-trigger { width: 100%; justify-content: center; }
        .nb-theme-btn { margin-left: auto; }
    }
    </style>
</head>
<body>

<header class="site-header">
    <nav class="site-navbar navbar navbar-expand-lg">
        <div class="container">

            <!-- Brand -->
            <a class="nb-brand navbar-brand" href="<?= BASE_URL ?>/public/index.php">
                <div class="nb-logo-wrap">
                    <img src="<?= ASSET_BASE ?>/images/people_logo.png" alt="Logo"
                         onerror="this.style.display='none';this.parentElement.innerText='U'">
                </div>
                <div>
                    <div class="nb-brand-name"><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'UMOJA' ?></div>
                    <div class="nb-brand-tagline d-none d-sm-block"><?= defined('TAGLINE') ? htmlspecialchars(TAGLINE) : 'Drivers Sacco' ?></div>
                </div>
            </a>

            <!-- Mobile Right: theme + toggler -->
            <div class="d-flex align-items-center gap-2 d-lg-none">
                <button class="nb-theme-btn" id="themeToggleMobile" title="Toggle Theme">
                    <i class="bi bi-moon-stars-fill" id="themeIconMobile"></i>
                </button>
                <button class="nb-mobile-toggle navbar-toggler" type="button"
                        data-bs-toggle="collapse" data-bs-target="#mainNav"
                        aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                    <i class="bi bi-list"></i>
                </button>
            </div>

            <!-- Nav (collapses on mobile) -->
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="nb-nav ms-auto align-items-center">
                    <li>
                        <a href="<?= BASE_URL ?>/public/index.php"
                           class="nb-nav-link <?= is_active('index.php') ?>">Home</a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/public/index.php#about"
                           class="nb-nav-link">About Us</a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/public/index.php#services"
                           class="nb-nav-link">Services</a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/public/index.php#contact"
                           class="nb-nav-link">Contact</a>
                    </li>

                    <li class="d-none d-lg-block"><div class="nb-divider"></div></li>

                    <?php if ($is_logged_in): ?>
                        <li class="dropdown">
                            <a class="nb-account-trigger" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="nb-avatar"><i class="bi bi-person-fill"></i></span>
                                Account
                                <i class="bi bi-chevron-down" style="font-size:0.6rem; opacity:0.7;"></i>
                            </a>
                            <ul class="dropdown-menu nb-dropdown dropdown-menu-end">
                                <li>
                                    <a class="nb-dropdown-item" href="<?= BASE_URL . $dashboard_link ?>">
                                        <span class="nb-dropdown-icon"><i class="bi bi-grid-fill"></i></span> Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="nb-dropdown-item" href="<?= BASE_URL ?>/member/pages/profile.php">
                                        <span class="nb-dropdown-icon"><i class="bi bi-person-fill"></i></span> Profile
                                    </a>
                                </li>
                                <li><div class="nb-dropdown-divider"></div></li>
                                <li>
                                    <a class="nb-dropdown-item danger" href="<?= BASE_URL ?>/public/logout.php">
                                        <span class="nb-dropdown-icon"><i class="bi bi-power"></i></span> Sign Out
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li>
                            <a href="<?= BASE_URL ?>/public/login.php" class="btn-nb-login">
                                <i class="bi bi-box-arrow-in-right"></i> Log In
                            </a>
                        </li>
                        <li>
                            <a href="<?= BASE_URL ?>/public/register.php" class="btn-nb-register">
                                <i class="bi bi-person-plus-fill"></i> Join Us
                            </a>
                        </li>
                    <?php endif; ?>

                    <li>
                        <button class="nb-theme-btn ms-1 d-none d-lg-flex" id="themeToggle" title="Toggle Theme">
                            <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                        </button>
                    </li>
                </ul>
            </div>

        </div>
    </nav>
</header>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const html = document.documentElement;

    function updateThemeIcons(theme) {
        ['themeIcon', 'themeIconMobile'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        });
    }

    // Init icons
    updateThemeIcons(html.getAttribute('data-bs-theme') || 'light');

    function toggleTheme() {
        const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', next);
        localStorage.setItem('theme', next);
        updateThemeIcons(next);
        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: next } }));
    }

    document.getElementById('themeToggle')?.addEventListener('click', toggleTheme);
    document.getElementById('themeToggleMobile')?.addEventListener('click', toggleTheme);
});
</script>