<?php
// inc/sidebar.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../inc/Auth.php';

$role = 'guest';
if (isset($_SESSION['admin_id'])) {
    $role = $_SESSION['role'] ?? 'admin';
} elseif (isset($_SESSION['member_id'])) {
    $role = 'member';
}

$base   = defined('BASE_URL')   ? BASE_URL   : '/usms';
$assets = defined('ASSET_BASE') ? ASSET_BASE : $base . '/public/assets';

if (!function_exists('is_active')) {
    function is_active($page, $aliases = []) {
        $current = basename($_SERVER['PHP_SELF']);
        if ($current === $page) return 'active';
        foreach ($aliases as $alias) {
            if ($current === $alias) return 'active';
        }
        return '';
    }
}
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════════════
   SIDEBAR TOKENS
═══════════════════════════════════════════════════ */
:root {
    --sb-width:          272px;
    --sb-collapsed:      72px;

    /* Light */
    --sb-bg:             #ffffff;
    --sb-bg-2:           #f5f8f5;
    --sb-border:         #e3ebe5;
    --sb-text:           #6b7f72;
    --sb-ink:            #111c14;
    --sb-hover-bg:       #f0f7f2;
    --sb-hover-text:     #1a3a2a;
    --sb-active-bg:      #1a3a2a;
    --sb-active-text:    #ffffff;
    --sb-lime:           #a8e063;
    --sb-lime-glow:      rgba(168,224,99,.15);
    --sb-section:        #b8cec8;
    --sb-shadow:         2px 0 28px rgba(26,58,42,.08);
    --sb-radius:         11px;
    --sb-transition:     all .24s cubic-bezier(.4,0,.2,1);

    /* Scrollbar */
    --sb-scroll-thumb:   #d4e5d9;
    --sb-scroll-track:   transparent;
}

[data-bs-theme="dark"] {
    --sb-bg:             #0e1912;
    --sb-bg-2:           #141f18;
    --sb-border:         rgba(255,255,255,.06);
    --sb-text:           #7a9485;
    --sb-ink:            #e4ede6;
    --sb-hover-bg:       rgba(168,224,99,.05);
    --sb-hover-text:     #d4eedb;
    --sb-active-bg:      #1f4d35;
    --sb-section:        rgba(255,255,255,.18);
    --sb-shadow:         2px 0 28px rgba(0,0,0,.3);
    --sb-scroll-thumb:   rgba(255,255,255,.1);
}

/* ═══════════════════════════════════════════════════
   SHELL
═══════════════════════════════════════════════════ */
.hd-sidebar {
    width: var(--sb-width);
    height: 100vh;
    position: fixed;
    top: 0; left: 0;
    z-index: 1040;
    background: var(--sb-bg);
    border-right: 1px solid var(--sb-border);
    display: flex;
    flex-direction: column;
    transition: var(--sb-transition);
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    box-shadow: var(--sb-shadow);
}

/* Top accent line */
.hd-sidebar::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, var(--sb-active-bg) 0%, var(--sb-lime) 60%, var(--sb-active-bg) 100%);
    z-index: 2;
}

/* ═══════════════════════════════════════════════════
   COLLAPSED (DESKTOP)
═══════════════════════════════════════════════════ */
@media (min-width: 992px) {
    body.sb-collapsed .hd-sidebar { width: var(--sb-collapsed); }
    body.sb-collapsed .main-content-wrapper,
    body.sb-collapsed .main-content,
    body.sb-collapsed main { margin-left: var(--sb-collapsed) !important; }

    body.sb-collapsed .hd-brand-text,
    body.sb-collapsed .hd-nav-text,
    body.sb-collapsed .hd-section-label,
    body.sb-collapsed .hd-support-widget,
    body.sb-collapsed .hd-logout-label,
    body.sb-collapsed .hd-scroll-track { opacity: 0; pointer-events: none; width: 0; overflow: hidden; }

    body.sb-collapsed .hd-nav-link { justify-content: center; padding: 11px 0; margin: 1px 8px; }
    body.sb-collapsed .hd-nav-link .hd-nav-icon { margin-right: 0; }
    body.sb-collapsed .hd-brand-inner { padding: 0; justify-content: center; }
    body.sb-collapsed .hd-logout-btn { justify-content: center; padding: 11px 0; margin: 0 8px; }
    body.sb-collapsed .hd-toggle-btn { left: calc(var(--sb-collapsed) - 14px); }
}

/* ═══════════════════════════════════════════════════
   MOBILE
═══════════════════════════════════════════════════ */
@media (max-width: 991px) {
    .hd-sidebar { transform: translateX(-100%); transition: transform .3s ease; }
    .hd-sidebar.mobile-open { transform: translateX(0); box-shadow: 0 0 60px rgba(0,0,0,.25); }
    .hd-toggle-btn { display: none !important; }
}

/* ═══════════════════════════════════════════════════
   TOGGLE BUTTON
═══════════════════════════════════════════════════ */
.hd-toggle-btn {
    position: fixed;
    top: 20px;
    left: calc(var(--sb-width) - 14px);
    z-index: 1045;
    width: 28px; height: 28px;
    border-radius: 8px;
    background: var(--sb-bg);
    border: 1.5px solid var(--sb-border);
    color: var(--sb-text);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 2px 10px rgba(26,58,42,.1);
    cursor: pointer;
    transition: var(--sb-transition);
    font-size: .82rem;
}
.hd-toggle-btn:hover {
    background: var(--sb-active-bg);
    color: var(--sb-lime);
    border-color: var(--sb-active-bg);
    transform: scale(1.05);
}

/* ═══════════════════════════════════════════════════
   BRAND
═══════════════════════════════════════════════════ */
.hd-brand {
    height: 70px;
    padding: 0 16px;
    border-bottom: 1px solid var(--sb-border);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    background: var(--sb-bg);
    position: relative; z-index: 1;
}
.hd-brand-inner {
    display: flex; align-items: center; gap: 11px;
    overflow: hidden; width: 100%;
    transition: var(--sb-transition);
}
.hd-logo-wrap {
    width: 38px; height: 38px; border-radius: 11px; overflow: hidden;
    background: var(--sb-bg-2); border: 1.5px solid var(--sb-border);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; box-shadow: 0 2px 8px rgba(26,58,42,.1);
}
.hd-logo-wrap img { width: 100%; height: 100%; object-fit: contain; padding: 5px; }
.hd-brand-text { overflow: hidden; min-width: 0; }
.hd-brand-name {
    font-size: .88rem; font-weight: 800; color: var(--sb-ink);
    white-space: nowrap; letter-spacing: -.2px; line-height: 1.2;
}
.hd-brand-role {
    font-size: .6rem; font-weight: 700; color: var(--sb-text);
    text-transform: uppercase; letter-spacing: .8px;
    white-space: nowrap; margin-top: 2px; display: flex; align-items: center; gap: 5px;
}
.hd-brand-role::before {
    content: '';
    width: 5px; height: 5px; border-radius: 50%; background: var(--sb-lime);
    flex-shrink: 0; animation: blink 2.5s infinite;
}
@keyframes blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: .3; }
}

/* ═══════════════════════════════════════════════════
   SCROLL AREA + CUSTOM SCROLLBAR
═══════════════════════════════════════════════════ */
.hd-scroll-wrapper {
    flex: 1;
    position: relative;
    overflow: hidden;
    display: flex;
}
.hd-scroll-area {
    flex: 1;
    overflow-y: scroll;
    overflow-x: hidden;
    padding: 8px 10px 16px;
    /* Custom scrollbar — always visible */
    scrollbar-width: thin;
    scrollbar-color: var(--sb-scroll-thumb) var(--sb-scroll-track);
}
/* Webkit custom scrollbar */
.hd-scroll-area::-webkit-scrollbar { width: 5px; }
.hd-scroll-area::-webkit-scrollbar-track { background: var(--sb-scroll-track); margin: 8px 0; }
.hd-scroll-area::-webkit-scrollbar-thumb {
    background: var(--sb-scroll-thumb);
    border-radius: 100px;
}
.hd-scroll-area::-webkit-scrollbar-thumb:hover { background: var(--sb-text); }

/* Fade hints for overflow */
.hd-scroll-wrapper::after {
    content: '';
    position: absolute; bottom: 0; left: 0; right: 5px; height: 40px;
    background: linear-gradient(transparent, var(--sb-bg));
    pointer-events: none; z-index: 1;
}

/* ═══════════════════════════════════════════════════
   SECTION LABELS
═══════════════════════════════════════════════════ */
.hd-section-label {
    font-size: .58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.4px;
    color: var(--sb-section);
    padding: 20px 8px 6px;
    white-space: nowrap;
    overflow: hidden;
    transition: var(--sb-transition);
    display: flex; align-items: center; gap: 8px;
}
.hd-section-label::after {
    content: '';
    flex: 1; height: 1px; background: var(--sb-border);
}

/* ═══════════════════════════════════════════════════
   NAV LINKS
═══════════════════════════════════════════════════ */
.hd-nav-link {
    display: flex;
    align-items: center;
    padding: 9px 11px;
    margin-bottom: 1px;
    border-radius: var(--sb-radius);
    color: var(--sb-text);
    text-decoration: none;
    font-size: .83rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    transition: var(--sb-transition);
    position: relative;
}
.hd-nav-link:hover {
    background: var(--sb-hover-bg);
    color: var(--sb-hover-text);
    transform: translateX(2px);
}
.hd-nav-link.active {
    background: var(--sb-active-bg);
    color: var(--sb-active-text);
    box-shadow: 0 4px 14px rgba(26,58,42,.2);
}
.hd-nav-link.active .hd-nav-icon { color: var(--sb-lime); background: rgba(168,224,99,.12); }

/* Active indicator bar */
.hd-nav-link.active::before {
    content: '';
    position: absolute; left: 0; top: 20%; bottom: 20%;
    width: 3px; border-radius: 0 3px 3px 0;
    background: var(--sb-lime);
}

/* Icon tile */
.hd-nav-icon {
    width: 32px; height: 32px;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem;
    flex-shrink: 0;
    margin-right: 10px;
    transition: var(--sb-transition);
    background: transparent;
    color: inherit;
}
.hd-nav-link:not(.active):hover .hd-nav-icon {
    background: rgba(26,58,42,.08);
    color: var(--sb-hover-text);
}
[data-bs-theme="dark"] .hd-nav-link:not(.active):hover .hd-nav-icon {
    background: rgba(168,224,99,.08);
    color: var(--sb-lime);
}

.hd-nav-text {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: -.1px;
    transition: var(--sb-transition);
}

/* ═══════════════════════════════════════════════════
   SUPPORT WIDGET
═══════════════════════════════════════════════════ */
.hd-support-widget {
    background: linear-gradient(135deg, #1a3a2a 0%, #2e6347 100%);
    border-radius: 14px;
    padding: 18px 16px;
    margin: 16px 2px 4px;
    position: relative; overflow: hidden;
    text-align: center;
    border: 1px solid rgba(168,224,99,.15);
}
.hd-support-widget::before {
    content: '';
    position: absolute; top: -30px; right: -30px;
    width: 110px; height: 110px;
    background: radial-gradient(circle, rgba(168,224,99,.18) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.hd-support-widget::after {
    content: '';
    position: absolute; bottom: -20px; left: -20px;
    width: 80px; height: 80px;
    background: radial-gradient(circle, rgba(168,224,99,.1) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.hd-support-widget h6 {
    font-size: .82rem; font-weight: 800; color: #fff;
    margin: 0 0 3px; position: relative; z-index: 1;
}
.hd-support-widget p {
    font-size: .71rem; color: rgba(255,255,255,.5);
    margin: 0 0 13px; position: relative; z-index: 1; line-height: 1.5;
}
.hd-support-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    background: var(--sb-lime); color: #1a3a2a;
    font-size: .76rem; font-weight: 800;
    padding: 8px 18px; border-radius: 9px;
    text-decoration: none; transition: var(--sb-transition);
    position: relative; z-index: 1;
    box-shadow: 0 3px 10px rgba(168,224,99,.35);
}
.hd-support-btn:hover { background: #baea78; color: #1a3a2a; transform: translateY(-1px); box-shadow: 0 5px 14px rgba(168,224,99,.45); }

/* ═══════════════════════════════════════════════════
   FOOTER / LOGOUT
═══════════════════════════════════════════════════ */
.hd-footer {
    padding: 10px 10px 14px;
    border-top: 1px solid var(--sb-border);
    flex-shrink: 0; background: var(--sb-bg);
}
.hd-logout-btn {
    display: flex; align-items: center;
    padding: 10px 11px;
    border-radius: var(--sb-radius);
    color: #c0392b;
    text-decoration: none;
    font-size: .83rem; font-weight: 700;
    transition: var(--sb-transition);
    width: 100%; overflow: hidden;
}
.hd-logout-btn:hover { background: #fef0f0; color: #991b1b; }
.hd-logout-icon {
    width: 32px; height: 32px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; flex-shrink: 0; margin-right: 10px;
    background: #fee2e2; color: #c0392b;
    transition: var(--sb-transition);
}
.hd-logout-btn:hover .hd-logout-icon { background: #c0392b; color: #fff; }
.hd-logout-label { white-space: nowrap; transition: var(--sb-transition); overflow: hidden; }

/* ═══════════════════════════════════════════════════
   MOBILE BACKDROP
═══════════════════════════════════════════════════ */
.sidebar-backdrop {
    position: fixed; top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,.45);
    z-index: 1035;
    opacity: 0; visibility: hidden;
    transition: .3s; backdrop-filter: blur(2px);
}
.sidebar-backdrop.show { opacity: 1; visibility: visible; }

/* ═══════════════════════════════════════════════════
   MAIN CONTENT OFFSET
═══════════════════════════════════════════════════ */
.main-content-wrapper,
.main-content,
main {
    margin-left: var(--sb-width);
    transition: var(--sb-transition);
}
</style>

<!-- Backdrop -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- Toggle button -->
<button class="hd-toggle-btn d-none d-lg-flex" id="sidebarToggle" title="Collapse sidebar" aria-label="Toggle sidebar">
    <i class="bi bi-layout-sidebar-reverse"></i>
</button>

<aside class="hd-sidebar" id="sidebar" role="navigation" aria-label="Main Navigation">

    <!-- ── Brand ──────────────────────────────────────────── -->
    <a href="<?= $base ?>/public/index.php" class="hd-brand text-decoration-none">
        <div class="hd-brand-inner">
            <div class="hd-logo-wrap">
                <img src="<?= $assets ?>/images/people_logo.png" alt="<?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Logo' ?>">
            </div>
            <div class="hd-brand-text">
                <div class="hd-brand-name"><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'UMOJA SACCO' ?></div>
                <div class="hd-brand-role">
                    <?= $role === 'member' ? ($_SESSION['reg_no'] ?? 'Member Portal') : 'Admin Panel' ?>
                </div>
            </div>
        </div>
    </a>

    <!-- ── Scroll area ────────────────────────────────────── -->
    <div class="hd-scroll-wrapper">
        <div class="hd-scroll-area">

            <?php if ($role === 'member'): ?>

                <a href="<?= $base ?>/member/pages/dashboard.php" class="hd-nav-link <?= is_active('dashboard.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-grid-fill"></i></span>
                    <span class="hd-nav-text">Dashboard</span>
                </a>

                <span class="hd-section-label">Personal Finances</span>
                <a href="<?= $base ?>/member/pages/savings.php" class="hd-nav-link <?= is_active('savings.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-piggy-bank-fill"></i></span>
                    <span class="hd-nav-text">Savings</span>
                </a>
                <a href="<?= $base ?>/member/pages/shares.php" class="hd-nav-link <?= is_active('shares.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-pie-chart-fill"></i></span>
                    <span class="hd-nav-text">Shares Portfolio</span>
                </a>
                <a href="<?= $base ?>/member/pages/loans.php" class="hd-nav-link <?= is_active('loans.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-cash-stack"></i></span>
                    <span class="hd-nav-text">My Loans</span>
                </a>
                <a href="<?= $base ?>/member/pages/contributions.php" class="hd-nav-link <?= is_active('contributions.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-calendar-check-fill"></i></span>
                    <span class="hd-nav-text">Contributions</span>
                </a>

                <span class="hd-section-label">Welfare & Solidarity</span>
                <a href="<?= $base ?>/member/pages/welfare.php" class="hd-nav-link <?= is_active('welfare.php', ['welfare_situations.php']) ?>">
                    <span class="hd-nav-icon"><i class="bi bi-heart-pulse-fill"></i></span>
                    <span class="hd-nav-text">Welfare Hub</span>
                </a>

                <span class="hd-section-label">Utilities</span>
                <a href="<?= $base ?>/member/pages/mpesa_request.php?type=savings" class="hd-nav-link <?= (isset($_GET['type']) && $_GET['type'] === 'savings') ? 'active' : '' ?>">
                    <span class="hd-nav-icon"><i class="bi bi-phone-vibrate-fill"></i></span>
                    <span class="hd-nav-text">Pay Via M-Pesa</span>
                </a>
                <a href="<?= $base ?>/member/pages/withdraw.php" class="hd-nav-link <?= is_active('withdraw.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-wallet2"></i></span>
                    <span class="hd-nav-text">Withdraw Funds</span>
                </a>
                <a href="<?= $base ?>/member/pages/transactions.php" class="hd-nav-link <?= is_active('transactions.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-arrow-left-right"></i></span>
                    <span class="hd-nav-text">All Transactions</span>
                </a>
                <a href="<?= $base ?>/member/pages/notifications.php" class="hd-nav-link <?= is_active('notifications.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-bell-fill"></i></span>
                    <span class="hd-nav-text">Notifications</span>
                </a>

                <span class="hd-section-label">Account</span>
                <a href="<?= $base ?>/member/pages/profile.php" class="hd-nav-link <?= is_active('profile.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-person-circle"></i></span>
                    <span class="hd-nav-text">My Profile</span>
                </a>
                <a href="<?= $base ?>/member/pages/settings.php" class="hd-nav-link <?= is_active('settings.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-gear-wide-connected"></i></span>
                    <span class="hd-nav-text">Settings</span>
                </a>

                <div class="hd-support-widget">
                    <h6>Need Help?</h6>
                    <p>Our support team is ready to assist you</p>
                    <a href="<?= $base ?>/member/pages/support.php" class="hd-support-btn">
                        <i class="bi bi-headset"></i> Open Ticket
                    </a>
                </div>

            <?php elseif ($role !== 'guest'): ?>

                <a href="<?= $base ?>/admin/pages/dashboard.php" class="hd-nav-link <?= is_active('dashboard.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-grid-1x2-fill"></i></span>
                    <span class="hd-nav-text">Admin Dashboard</span>
                </a>

                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('members')): ?>
                    <span class="hd-section-label">Member Management</span>
                    <a href="<?= $base ?>/admin/pages/member_onboarding.php" class="hd-nav-link <?= is_active('member_onboarding.php') ?>">
                        <span class="hd-nav-icon"><i class="bi bi-person-plus-fill"></i></span>
                        <span class="hd-nav-text">Member Onboarding</span>
                    </a>
                    <a href="<?= $base ?>/admin/pages/members.php" class="hd-nav-link <?= is_active('members.php', ['member_profile.php']) ?>">
                        <span class="hd-nav-icon"><i class="bi bi-people-fill"></i></span>
                        <span class="hd-nav-text">Members List</span>
                    </a>
                <?php endif; ?>

                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('payroll') || \USMS\Middleware\AuthMiddleware::hasModulePermission('settings')): ?>
                    <span class="hd-section-label">People & Access</span>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('payroll')): ?>
                        <a href="<?= $base ?>/admin/pages/employees.php" class="hd-nav-link <?= is_active('employees.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-person-badge-fill"></i></span>
                            <span class="hd-nav-text">Employees</span>
                        </a>
                    <?php endif; ?>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('settings')): ?>
                        <a href="<?= $base ?>/admin/pages/users.php" class="hd-nav-link <?= is_active('users.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-person-badge"></i></span>
                            <span class="hd-nav-text">System Users</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/roles.php" class="hd-nav-link <?= is_active('roles.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-shield-lock-fill"></i></span>
                            <span class="hd-nav-text">Access Control (RBAC)</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('finance') || \USMS\Middleware\AuthMiddleware::hasModulePermission('payroll')): ?>
                    <span class="hd-section-label">Financial Management</span>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('finance')): ?>
                        <a href="<?= $base ?>/admin/pages/payments.php" class="hd-nav-link <?= is_active('payments.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-cash-coin"></i></span>
                            <span class="hd-nav-text">Cashier / Payments</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/revenue.php" class="hd-nav-link <?= is_active('revenue.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-graph-up-arrow"></i></span>
                            <span class="hd-nav-text">Revenue Inflow</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/expenses.php" class="hd-nav-link <?= is_active('expenses.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-receipt"></i></span>
                            <span class="hd-nav-text">Expense Tracker</span>
                        </a>
                    <?php endif; ?>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('payroll')): ?>
                        <a href="<?= $base ?>/admin/pages/payroll.php" class="hd-nav-link <?= is_active('payroll.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-wallet2"></i></span>
                            <span class="hd-nav-text">Payroll Processing</span>
                        </a>
                    <?php endif; ?>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('finance')): ?>
                        <a href="<?= $base ?>/admin/pages/transactions.php" class="hd-nav-link <?= is_active('transactions.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-journal-text"></i></span>
                            <span class="hd-nav-text">Live Ledger View</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/trial_balance.php" class="hd-nav-link <?= is_active('trial_balance.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-scale"></i></span>
                            <span class="hd-nav-text">Trial Balance</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('loans')): ?>
                    <span class="hd-section-label">Loans & Credit</span>
                    <a href="<?= $base ?>/admin/pages/loans_reviews.php" class="hd-nav-link <?= is_active('loans_reviews.php') ?>">
                        <span class="hd-nav-icon"><i class="bi bi-bank"></i></span>
                        <span class="hd-nav-text">Loan Reviews</span>
                    </a>
                    <a href="<?= $base ?>/admin/pages/loans_payouts.php" class="hd-nav-link <?= is_active('loans_payouts.php') ?>">
                        <span class="hd-nav-icon"><i class="bi bi-cash-stack"></i></span>
                        <span class="hd-nav-text">Loan Payouts</span>
                    </a>
                <?php endif; ?>

                <span class="hd-section-label">Welfare Module</span>
                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('savings')): ?>
                    <a href="<?= $base ?>/admin/pages/welfare.php" class="hd-nav-link <?= is_active('welfare.php', ['welfare_cases.php', 'welfare_support.php']) ?>">
                        <span class="hd-nav-icon"><i class="bi bi-heart-pulse-fill"></i></span>
                        <span class="hd-nav-text">Welfare Management</span>
                    </a>
                <?php endif; ?>

                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('finance') || \USMS\Middleware\AuthMiddleware::hasModulePermission('shares')): ?>
                    <span class="hd-section-label">Investments & Assets</span>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('finance')): ?>
                        <a href="<?= $base ?>/admin/pages/investments.php" class="hd-nav-link <?= is_active('investments.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-buildings-fill"></i></span>
                            <span class="hd-nav-text">Asset Portfolio</span>
                        </a>
                    <?php endif; ?>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('shares')): ?>
                        <a href="<?= $base ?>/admin/pages/admin_shares.php" class="hd-nav-link <?= is_active('admin_shares.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-pie-chart-fill"></i></span>
                            <span class="hd-nav-text">Equity & Shares</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('finance')): ?>
                    <span class="hd-section-label">Reports & Exports</span>
                    <a href="<?= $base ?>/admin/pages/reports.php" class="hd-nav-link <?= is_active('reports.php') ?>">
                        <span class="hd-nav-icon"><i class="bi bi-pie-chart"></i></span>
                        <span class="hd-nav-text">Analytical Reports</span>
                    </a>
                    <a href="<?= $base ?>/admin/pages/statements.php" class="hd-nav-link <?= is_active('statements.php') ?>">
                        <span class="hd-nav-icon"><i class="bi bi-file-earmark-spreadsheet-fill"></i></span>
                        <span class="hd-nav-text">Account Statements</span>
                    </a>
                <?php endif; ?>

                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('settings') || \USMS\Middleware\AuthMiddleware::hasModulePermission('finance') || \USMS\Middleware\AuthMiddleware::hasModulePermission('support')): ?>
                    <span class="hd-section-label">System Maintenance</span>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('settings')): ?>
                        <a href="<?= $base ?>/admin/pages/live_monitor.php" class="hd-nav-link <?= is_active('live_monitor.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-display-fill"></i></span>
                            <span class="hd-nav-text">Live Monitor</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/monitor.php" class="hd-nav-link <?= is_active('monitor.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-phone-vibrate-fill"></i></span>
                            <span class="hd-nav-text">Transaction Monitor</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/system_health.php" class="hd-nav-link <?= is_active('system_health.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-activity"></i></span>
                            <span class="hd-nav-text">System Health</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/backups.php" class="hd-nav-link <?= is_active('backups.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-database-fill-check"></i></span>
                            <span class="hd-nav-text">Database Backups</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/audit_logs.php" class="hd-nav-link <?= is_active('audit_logs.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-list-check"></i></span>
                            <span class="hd-nav-text">Activity Logs</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/settings.php" class="hd-nav-link <?= is_active('settings.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-sliders2"></i></span>
                            <span class="hd-nav-text">Global Settings</span>
                        </a>
                    <?php endif; ?>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('support')): ?>
                        <a href="<?= $base ?>/admin/pages/support.php" class="hd-nav-link <?= is_active('support.php', ['support_view.php']) ?>">
                            <span class="hd-nav-icon"><i class="bi bi-headset"></i></span>
                            <span class="hd-nav-text">Tech Support</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

            <?php endif; ?>

        </div><!-- /hd-scroll-area -->
    </div><!-- /hd-scroll-wrapper -->

    <!-- ── Footer / Logout ───────────────────────────────── -->
    <div class="hd-footer">
        <a href="<?= $base ?>/public/logout.php" class="hd-logout-btn">
            <span class="hd-logout-icon"><i class="bi bi-box-arrow-right"></i></span>
            <span class="hd-logout-label">Sign Out</span>
        </a>
    </div>

</aside>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar    = document.getElementById('sidebar');
    const backdrop   = document.getElementById('sidebarBackdrop');
    const toggleBtn  = document.getElementById('sidebarToggle');
    const mobileBtn  = document.getElementById('mobileSidebarToggle');

    // Desktop collapse
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            document.body.classList.toggle('sb-collapsed');
            const collapsed = document.body.classList.contains('sb-collapsed');
            localStorage.setItem('sb_collapsed', collapsed ? '1' : '0');
            toggleBtn.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
        });
    }

    // Restore state
    if (localStorage.getItem('sb_collapsed') === '1') {
        document.body.classList.add('sb-collapsed');
    }

    // Mobile open
    if (mobileBtn) {
        mobileBtn.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            backdrop.classList.toggle('show');
        });
    }

    // Backdrop close
    if (backdrop) {
        backdrop.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            backdrop.classList.remove('show');
        });
    }

    // Scroll-to-active link
    const activeLink = sidebar?.querySelector('.hd-nav-link.active');
    if (activeLink) {
        activeLink.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
});
</script>