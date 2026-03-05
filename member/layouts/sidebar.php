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

$base   = defined('BASE_URL')    ? BASE_URL    : '/usms';
$assets = defined('ASSET_BASE')  ? ASSET_BASE  : $base . '/public/assets';

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
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

<style>
/* ─── Variables ─── */
:root {
    --sb-width: 272px;
    --sb-collapsed: 76px;
    --sb-bg: #ffffff;
    --sb-border: #F0F7F4;
    --sb-text: #7a9e8e;
    --sb-hover-bg: #F7FBF9;
    --sb-hover-text: #0F392B;
    --sb-active-bg: #0F392B;
    --sb-active-text: #ffffff;
    --sb-lime: #A3E635;
    --sb-section-color: #b8cec8;
    --sb-radius: 14px;
    --sb-transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1);
}

[data-bs-theme="dark"] {
    --sb-bg: #0c1a14;
    --sb-border: rgba(255,255,255,0.05);
    --sb-text: #7a9e8e;
    --sb-hover-bg: rgba(255,255,255,0.04);
    --sb-hover-text: #e0f0ea;
    --sb-active-bg: #1a5c43;
    --sb-section-color: rgba(255,255,255,0.2);
}

/* ─── Base Sidebar ─── */
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
    font-family: 'Plus Jakarta Sans', sans-serif;
    box-shadow: 2px 0 24px rgba(15,57,43,0.06);
}

/* ─── Collapsed State (Desktop) ─── */
@media (min-width: 992px) {
    body.sb-collapsed .hd-sidebar {
        width: var(--sb-collapsed);
    }
    body.sb-collapsed .main-content-wrapper,
    body.sb-collapsed .main-content,
    body.sb-collapsed main {
        margin-left: var(--sb-collapsed) !important;
    }
    body.sb-collapsed .hd-brand-text,
    body.sb-collapsed .hd-nav-text,
    body.sb-collapsed .hd-section-label,
    body.sb-collapsed .hd-support-widget,
    body.sb-collapsed .hd-logout-label {
        opacity: 0;
        pointer-events: none;
        width: 0;
        overflow: hidden;
    }
    body.sb-collapsed .hd-nav-link {
        justify-content: center;
        padding: 13px 0;
        margin: 2px 10px;
        border-radius: 12px;
    }
    body.sb-collapsed .hd-nav-link .hd-nav-icon {
        margin-right: 0;
    }
    body.sb-collapsed .hd-brand-inner {
        padding: 0;
        justify-content: center;
    }
    body.sb-collapsed .hd-logout-btn {
        justify-content: center;
        padding: 13px 0;
        margin: 0 10px;
    }
}

/* ─── Mobile ─── */
@media (max-width: 991px) {
    .hd-sidebar {
        transform: translateX(-100%);
        width: var(--sb-width);
        transition: transform 0.3s ease;
    }
    .hd-sidebar.mobile-open {
        transform: translateX(0);
        box-shadow: 0 0 60px rgba(0,0,0,0.2);
    }
    .hd-toggle-btn { display: none !important; }
}

/* ─── Toggle Button ─── */
.hd-toggle-btn {
    position: fixed;
    top: 22px;
    left: calc(var(--sb-width) - 18px);
    z-index: 1045;
    width: 32px; height: 32px;
    border-radius: 10px;
    background: var(--sb-bg);
    border: 1px solid var(--sb-border);
    color: var(--sb-text);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 2px 12px rgba(15,57,43,0.08);
    cursor: pointer;
    transition: var(--sb-transition);
    font-size: 0.95rem;
}
.hd-toggle-btn:hover {
    background: var(--sb-active-bg);
    color: var(--sb-lime);
    border-color: var(--sb-active-bg);
}
body.sb-collapsed .hd-toggle-btn {
    left: calc(var(--sb-collapsed) - 16px);
}

/* ─── Brand Header ─── */
.hd-brand {
    height: 72px;
    padding: 0 18px;
    border-bottom: 1px solid var(--sb-border);
    flex-shrink: 0;
    display: flex;
    align-items: center;
}
.hd-brand-inner {
    display: flex;
    align-items: center;
    gap: 11px;
    overflow: hidden;
    width: 100%;
    transition: var(--sb-transition);
}
.hd-logo-wrap {
    width: 38px; height: 38px;
    border-radius: 11px;
    overflow: hidden;
    background: #fff;
    border: 1px solid var(--sb-border);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(15,57,43,0.1);
}
.hd-logo-wrap img { width: 100%; height: 100%; object-fit: contain; padding: 4px; }
.hd-brand-name {
    font-size: 0.88rem;
    font-weight: 800;
    color: #0F392B;
    white-space: nowrap;
    letter-spacing: -0.2px;
    line-height: 1.2;
}
.hd-brand-role {
    font-size: 0.6rem;
    font-weight: 700;
    color: var(--sb-text);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    white-space: nowrap;
}
.hd-brand-text { overflow: hidden; min-width: 0; }

/* ─── Scroll Area ─── */
.hd-scroll-area {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 10px 10px 16px;
    scrollbar-width: none;
}
.hd-scroll-area::-webkit-scrollbar { display: none; }

/* ─── Section Labels ─── */
.hd-section-label {
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.4px;
    color: var(--sb-section-color);
    padding: 18px 10px 6px;
    white-space: nowrap;
    overflow: hidden;
    transition: var(--sb-transition);
    display: block;
}

/* ─── Nav Links ─── */
.hd-nav-link {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    margin-bottom: 2px;
    border-radius: var(--sb-radius);
    color: var(--sb-text);
    text-decoration: none;
    font-size: 0.84rem;
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
    box-shadow: 0 4px 16px rgba(15,57,43,0.2);
}
.hd-nav-link.active .hd-nav-icon {
    color: var(--sb-lime);
}

/* Icon */
.hd-nav-icon {
    width: 34px; height: 34px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    margin-right: 10px;
    transition: var(--sb-transition);
    background: transparent;
    color: inherit;
}
.hd-nav-link:not(.active):hover .hd-nav-icon {
    background: rgba(15,57,43,0.07);
    color: #0F392B;
}
.hd-nav-link.active .hd-nav-icon {
    background: rgba(163,230,53,0.12);
}

.hd-nav-text {
    flex: 1;
    transition: var(--sb-transition);
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: -0.1px;
}

/* ─── Support Widget ─── */
.hd-support-widget {
    background: linear-gradient(135deg, #0F392B, #1a5c43);
    border-radius: 16px;
    padding: 18px 16px;
    margin: 14px 2px 4px;
    position: relative;
    overflow: hidden;
    text-align: center;
}
.hd-support-widget::before {
    content: '';
    position: absolute;
    top: -30px; right: -30px;
    width: 100px; height: 100px;
    background: radial-gradient(circle, rgba(163,230,53,0.15) 0%, transparent 70%);
    border-radius: 50%;
}
.hd-support-widget h6 {
    font-size: 0.82rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 3px;
    position: relative;
    z-index: 1;
}
.hd-support-widget p {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.5);
    margin: 0 0 12px;
    position: relative;
    z-index: 1;
}
.hd-support-btn {
    display: block;
    background: var(--sb-lime);
    color: #0F392B;
    font-size: 0.76rem;
    font-weight: 800;
    padding: 8px 16px;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.2s;
    position: relative;
    z-index: 1;
}
.hd-support-btn:hover { background: #bde32a; color: #0F392B; transform: translateY(-1px); }

/* ─── Footer / Logout ─── */
.hd-footer {
    padding: 10px 10px 14px;
    border-top: 1px solid var(--sb-border);
    flex-shrink: 0;
}
.hd-logout-btn {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    border-radius: var(--sb-radius);
    color: #dc2626;
    text-decoration: none;
    font-size: 0.84rem;
    font-weight: 700;
    transition: var(--sb-transition);
    width: 100%;
    overflow: hidden;
}
.hd-logout-btn:hover {
    background: #FEE2E2;
    color: #991b1b;
}
.hd-logout-icon {
    width: 34px; height: 34px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    margin-right: 10px;
    background: #FEE2E2;
    color: #dc2626;
    transition: var(--sb-transition);
}
.hd-logout-btn:hover .hd-logout-icon { background: #dc2626; color: #fff; }
.hd-logout-label { white-space: nowrap; transition: var(--sb-transition); overflow: hidden; }

/* ─── Mobile Backdrop ─── */
.sidebar-backdrop {
    position: fixed; top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.45);
    z-index: 1035;
    opacity: 0; visibility: hidden;
    transition: 0.3s;
    backdrop-filter: blur(2px);
}
.sidebar-backdrop.show { opacity: 1; visibility: visible; }
</style>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<button class="hd-toggle-btn d-none d-lg-flex" id="sidebarToggle" title="Toggle Sidebar">
    <i class="bi bi-layout-sidebar-reverse"></i>
</button>

<aside class="hd-sidebar" id="sidebar">

    <!-- Brand -->
    <div class="hd-brand">
        <div class="hd-brand-inner">
            <div class="hd-logo-wrap">
                <img src="<?= $assets ?>/images/people_logo.png" alt="Logo">
            </div>
            <div class="hd-brand-text">
                <div class="hd-brand-name"><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'UMOJA SACCO' ?></div>
                <div class="hd-brand-role">
                    <?= $role === 'member' ? ($_SESSION['reg_no'] ?? 'Member Portal') : 'Admin Panel' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
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
                <p>Our support team is ready</p>
                <a href="<?= $base ?>/member/pages/support.php" class="hd-support-btn">Open Ticket</a>
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
                    <a href="<?= $base ?>/admin/pages/monitor.php" class="hd-nav-link <?= is_active('monitor.php') ?>">
                        <span class="hd-nav-icon"><i class="bi bi-phone-vibrate-fill"></i></span>
                        <span class="hd-nav-text">M-Pesa Logs</span>
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

            <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('settings') || \USMS\Middleware\AuthMiddleware::hasModulePermission('support')): ?>
                <span class="hd-section-label">System Maintenance</span>
                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('settings')): ?>
                    <a href="<?= $base ?>/admin/pages/live_monitor.php" class="hd-nav-link <?= is_active('live_monitor.php') ?>">
                        <span class="hd-nav-icon"><i class="bi bi-display-fill"></i></span>
                        <span class="hd-nav-text">Live Monitor</span>
                    </a>
                    <a href="<?= $base ?>/admin/pages/transaction_monitor.php" class="hd-nav-link <?= is_active('transaction_monitor.php') ?>">
                        <span class="hd-nav-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
                        <span class="hd-nav-text">Transaction Recovery</span>
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

    </div>

    <!-- Footer / Logout -->
    <div class="hd-footer">
        <a href="<?= $base ?>/public/logout.php" class="hd-logout-btn">
            <span class="hd-logout-icon"><i class="bi bi-power"></i></span>
            <span class="hd-logout-label">Logout</span>
        </a>
    </div>

</aside>