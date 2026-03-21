<?php
// admin/layouts/sidebar.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/sidebar_styles.php';

// 1. Role & Path Logic
$role   = $_SESSION['role'] ?? 'admin';
$base   = defined('BASE_URL')   ? BASE_URL   : '/usms';
$assets = defined('ASSET_BASE') ? ASSET_BASE : $base . '/public/assets';

// Active Link Helper
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

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

/* ── Sidebar Shell ────────────────────────────────────── */
.hd-sidebar {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    width: 268px;
    height: 100vh;
    position: fixed;
    top: 0; left: 0;
    display: flex;
    flex-direction: column;
    background: #fff;
    border-right: 1px solid #f0f0f0;
    z-index: 1040;
    transition: width 0.3s cubic-bezier(0.16,1,0.3,1),
                transform 0.3s cubic-bezier(0.16,1,0.3,1);
    overflow: hidden;
}

/* Collapsed state */
.sb-collapsed .hd-sidebar { width: 68px; }
.sb-collapsed .hd-nav-text,
.sb-collapsed .hd-nav-header,
.sb-collapsed .hd-brand-text { opacity: 0; width: 0; overflow: hidden; pointer-events: none; }
.sb-collapsed .hd-brand { justify-content: center; padding: 0 0 0 0; }

/* Main content offset */
.main-content-wrapper { margin-left: 268px; transition: margin-left 0.3s cubic-bezier(0.16,1,0.3,1); }
.sb-collapsed .main-content-wrapper { margin-left: 68px; }

/* ── Toggle Button ── */
.hd-toggle-btn {
    position: fixed;
    top: 16px;
    left: 228px;
    z-index: 1050;
    width: 28px; height: 28px;
    border-radius: 8px;
    background: #fff;
    border: 1px solid #e5e7eb;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.9rem;
    box-shadow: 0 1px 6px rgba(0,0,0,0.08);
    transition: left 0.3s cubic-bezier(0.16,1,0.3,1),
                color 0.15s ease, background 0.15s ease;
}

.hd-toggle-btn:hover { background: rgba(15,46,37,0.05); color: var(--forest, #0f2e25); }
.sb-collapsed .hd-toggle-btn { left: 48px; }

/* ── Brand ── */
.hd-brand {
    display: flex;
    align-items: center;
    padding: 20px 18px 16px;
    border-bottom: 1px solid #f3f4f6;
    flex-shrink: 0;
    gap: 12px;
    text-decoration: none;
    min-height: 72px;
}

.hd-logo-img {
    width: 34px; height: 34px;
    border-radius: 10px;
    object-fit: cover;
    flex-shrink: 0;
}

.hd-brand-text {
    transition: opacity 0.2s ease, width 0.2s ease;
}

.hd-brand-name {
    font-size: 0.9rem;
    font-weight: 800;
    color: #111827;
    letter-spacing: -0.2px;
    line-height: 1.2;
    white-space: nowrap;
}

.hd-brand-sub {
    font-size: 9.5px;
    font-weight: 600;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    color: #9ca3af;
    margin-top: 2px;
    display: block;
}

/* ── Scroll Area ── */
.hd-scroll-area {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 10px 10px 6px;
    scrollbar-width: thin;
    scrollbar-color: #e5e7eb transparent;
}

.hd-scroll-area::-webkit-scrollbar { width: 4px; }
.hd-scroll-area::-webkit-scrollbar-track { background: transparent; }
.hd-scroll-area::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 99px; }

/* ── Section Headers ── */
.hd-nav-header {
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #c4c9d4;
    padding: 14px 10px 5px;
    white-space: nowrap;
    overflow: hidden;
    transition: opacity 0.2s ease;
}

/* ── Nav Items ── */
.hd-nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    border-radius: 10px;
    text-decoration: none;
    color: #4b5563;
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 1px;
    transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
    white-space: nowrap;
    position: relative;
    overflow: hidden;
}

.hd-nav-item i {
    font-size: 1rem;
    flex-shrink: 0;
    width: 20px;
    text-align: center;
    transition: color 0.18s ease;
}

.hd-nav-item:hover {
    background: rgba(15,46,37,0.05);
    color: var(--forest, #0f2e25);
    transform: translateX(2px);
    text-decoration: none;
}

.hd-nav-item:hover i { color: var(--forest, #0f2e25); }

/* Active state */
.hd-nav-item.active {
    background: rgba(15,46,37,0.08);
    color: var(--forest, #0f2e25);
    font-weight: 700;
}

.hd-nav-item.active i { color: var(--forest, #0f2e25); }

.hd-nav-item.active::before {
    content: '';
    position: absolute;
    left: 0; top: 20%; bottom: 20%;
    width: 3px;
    background: var(--forest, #0f2e25);
    border-radius: 0 3px 3px 0;
}

/* Collapsed tooltip hint */
.sb-collapsed .hd-nav-item { justify-content: center; padding: 9px 0; }
.sb-collapsed .hd-nav-item::before { display: none; }

/* ── Footer ── */
.hd-footer {
    padding: 10px 10px 14px;
    border-top: 1px solid #f3f4f6;
    flex-shrink: 0;
}

.hd-user-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 12px;
    background: #fafafa;
    border: 1px solid #f0f0f0;
    margin-bottom: 6px;
}

.hd-avatar {
    width: 34px; height: 34px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--forest, #0f2e25), #1a5c42);
    color: #a3e635;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 800;
    flex-shrink: 0;
}

.hd-user-name {
    font-size: 0.85rem;
    font-weight: 700;
    color: #111827;
    line-height: 1.2;
    white-space: nowrap;
}

.hd-user-role {
    font-size: 9.5px;
    font-weight: 600;
    letter-spacing: 0.7px;
    text-transform: uppercase;
    color: #9ca3af;
    display: block;
}

.hd-logout {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    border-radius: 10px;
    text-decoration: none;
    color: #dc2626;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background 0.15s ease;
}

.hd-logout:hover {
    background: #fef2f2;
    color: #dc2626;
    text-decoration: none;
}

.hd-logout i { font-size: 1rem; flex-shrink: 0; width: 20px; text-align: center; }

/* ── Backdrop (mobile) ── */
.sidebar-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.3);
    backdrop-filter: blur(2px);
    z-index: 1039;
}

.sidebar-backdrop.show { display: block; }

/* ── Mobile ── */
@media (max-width: 991px) {
    .hd-sidebar {
        transform: translateX(-100%);
        width: 268px !important;
        box-shadow: 12px 0 40px rgba(0,0,0,0.12);
    }
    .hd-sidebar.show { transform: translateX(0); }
    .main-content-wrapper { margin-left: 0 !important; }
    .hd-toggle-btn { display: none !important; }
    .hd-nav-text, .hd-nav-header, .hd-brand-text { opacity: 1 !important; width: auto !important; }
}

/* ── Dark Mode ── */
[data-bs-theme="dark"] .hd-sidebar {
    background: #161b22;
    border-right-color: #21262d;
}
[data-bs-theme="dark"] .hd-brand { border-bottom-color: #21262d; }
[data-bs-theme="dark"] .hd-brand-name { color: #f0f6fc; }
[data-bs-theme="dark"] .hd-nav-item { color: #8b949e; }
[data-bs-theme="dark"] .hd-nav-item:hover { background: rgba(52,211,153,0.07); color: #34d399; }
[data-bs-theme="dark"] .hd-nav-item:hover i { color: #34d399; }
[data-bs-theme="dark"] .hd-nav-item.active { background: rgba(52,211,153,0.1); color: #34d399; }
[data-bs-theme="dark"] .hd-nav-item.active i { color: #34d399; }
[data-bs-theme="dark"] .hd-nav-item.active::before { background: #34d399; }
[data-bs-theme="dark"] .hd-nav-header { color: #30363d; }
[data-bs-theme="dark"] .hd-user-card { background: #0d1117; border-color: #21262d; }
[data-bs-theme="dark"] .hd-user-name { color: #f0f6fc; }
[data-bs-theme="dark"] .hd-footer { border-top-color: #21262d; }
[data-bs-theme="dark"] .hd-toggle-btn {
    background: #161b22;
    border-color: #21262d;
    color: #8b949e;
}

    /* Live Status Dot */
    .nav-live-dot { width: 6px; height: 6px; border-radius: 50%; background: #22c55e; display: inline-block; margin-left: 0.5rem; position: relative; }
    .nav-live-dot::after { content: ''; position: absolute; inset: -3px; border-radius: 50%; background: inherit; opacity: 0.4; animation: nav-pulse-glow 2s infinite; }
    @keyframes nav-pulse-glow { 0% { transform: scale(1); opacity: 0.5; } 100% { transform: scale(2.5); opacity: 0; } }
</style>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<button class="hd-toggle-btn d-none d-lg-flex" id="sidebarToggle" title="Toggle Menu">
    <i class="bi bi-layout-sidebar-inset"></i>
</button>

<aside class="hd-sidebar" id="sidebar">

    <!-- ─── Brand ── -->
    <a href="<?= $base ?>/public/index.php" class="hd-brand">
        <img src="<?= $assets ?>/images/people_logo.png" alt="Logo" class="hd-logo-img">
        <div class="hd-brand-text">
            <div class="hd-brand-name"><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'UMOJA SACCO' ?></div>
            <span class="hd-brand-sub">Admin Panel</span>
        </div>
    </a>

    <!-- ─── Nav ── -->
    <div class="hd-scroll-area">

        <a href="<?= $base ?>/admin/pages/dashboard.php" class="hd-nav-item <?= is_active('dashboard.php') ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span class="hd-nav-text">Dashboard</span>
        </a>

        <?php if (has_permission('member_onboarding.php') || has_permission('members.php')): ?>
            <div class="hd-nav-header">Member Management</div>
            <?php if (has_permission('member_onboarding.php')): ?>
                <a href="<?= $base ?>/admin/pages/member_onboarding.php" class="hd-nav-item <?= is_active('member_onboarding.php') ?>">
                    <i class="bi bi-person-plus-fill"></i>
                    <span class="hd-nav-text">Member Onboarding</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('members.php')): ?>
                <a href="<?= $base ?>/admin/pages/members.php" class="hd-nav-item <?= is_active('members.php', ['member_profile.php']) ?>">
                    <i class="bi bi-people-fill"></i>
                    <span class="hd-nav-text">Members List</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('employees.php') || has_permission('users.php') || has_permission('roles.php') || $role === 'superadmin'): ?>
            <div class="hd-nav-header">People &amp; Access</div>
            <?php if (has_permission('employees.php')): ?>
                <a href="<?= $base ?>/admin/pages/employees.php" class="hd-nav-item <?= is_active('employees.php') ?>">
                    <i class="bi bi-person-vcard-fill"></i>
                    <span class="hd-nav-text">Employees</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('users.php') || $role === 'superadmin'): ?>
                <a href="<?= $base ?>/admin/pages/users.php" class="hd-nav-item <?= is_active('users.php') ?>">
                    <i class="bi bi-person-badge-fill"></i>
                    <span class="hd-nav-text">System Users</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('roles.php') || $role === 'superadmin'): ?>
                <a href="<?= $base ?>/admin/pages/roles.php" class="hd-nav-item <?= is_active('roles.php') ?>">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span class="hd-nav-text">Access Control</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('payments.php') || has_permission('revenue.php') || has_permission('expenses.php') || has_permission('payroll.php') || has_permission('transactions.php') || has_permission('monitor.php') || has_permission('trial_balance.php') || $role === 'superadmin'): ?>
            <div class="hd-nav-header">Financial Management</div>
            <?php if (has_permission('payments.php')): ?>
                <a href="<?= $base ?>/admin/pages/payments.php" class="hd-nav-item <?= is_active('payments.php') ?>">
                    <i class="bi bi-cash-coin"></i>
                    <span class="hd-nav-text">Cashier / Payments</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('revenue.php')): ?>
                <a href="<?= $base ?>/admin/pages/revenue.php" class="hd-nav-item <?= is_active('revenue.php') ?>">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span class="hd-nav-text">Revenue Inflow</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('expenses.php')): ?>
                <a href="<?= $base ?>/admin/pages/expenses.php" class="hd-nav-item <?= is_active('expenses.php') ?>">
                    <i class="bi bi-receipt-cutoff"></i>
                    <span class="hd-nav-text">Expense Tracker</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('payroll.php') || $role === 'superadmin'): ?>
                <a href="<?= $base ?>/admin/pages/payroll.php" class="hd-nav-item <?= is_active('payroll.php') ?>">
                    <i class="bi bi-wallet2"></i>
                    <span class="hd-nav-text">Payroll Processing</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('transactions.php')): ?>
                <a href="<?= $base ?>/admin/pages/transactions.php" class="hd-nav-item <?= is_active('transactions.php') ?>">
                    <i class="bi bi-journal-text"></i>
                    <span class="hd-nav-text">Live Ledger View</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('trial_balance.php')): ?>
                <a href="<?= $base ?>/admin/pages/trial_balance.php" class="hd-nav-item <?= is_active('trial_balance.php') ?>">
                    <i class="bi bi-clipboard-data-fill"></i>
                    <span class="hd-nav-text">Trial Balance</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('loans_reviews.php') || has_permission('loans_payouts.php')): ?>
            <div class="hd-nav-header">Loans &amp; Credit</div>
            <?php if (has_permission('loans_reviews.php')): ?>
                <a href="<?= $base ?>/admin/pages/loans_reviews.php" class="hd-nav-item <?= is_active('loans_reviews.php') ?>">
                    <i class="bi bi-bank2"></i>
                    <span class="hd-nav-text">Loan Reviews</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('loans_payouts.php')): ?>
                <a href="<?= $base ?>/admin/pages/loans_payouts.php" class="hd-nav-item <?= is_active('loans_payouts.php') ?>">
                    <i class="bi bi-cash-stack"></i>
                    <span class="hd-nav-text">Loan Payouts</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('welfare.php') || $role === 'superadmin'): ?>
            <div class="hd-nav-header">Welfare Module</div>
            <a href="<?= $base ?>/admin/pages/welfare.php" class="hd-nav-item <?= is_active('welfare.php') ?>">
                <i class="bi bi-heart-pulse-fill"></i>
                <span class="hd-nav-text">Welfare Management</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('investments.php') || has_permission('admin_shares.php')): ?>
            <div class="hd-nav-header">Investments &amp; Assets</div>
            <?php if (has_permission('investments.php')): ?>
                <a href="<?= $base ?>/admin/pages/investments.php" class="hd-nav-item <?= is_active('investments.php') ?>">
                    <i class="bi bi-buildings-fill"></i>
                    <span class="hd-nav-text">Asset Portfolio</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('admin_shares.php')): ?>
                <a href="<?= $base ?>/admin/pages/admin_shares.php" class="hd-nav-item <?= is_active('admin_shares.php') ?>">
                    <i class="bi bi-pie-chart-fill"></i>
                    <span class="hd-nav-text">Equity &amp; Shares</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('reports.php') || has_permission('statements.php')): ?>
            <div class="hd-nav-header">Reports &amp; Exports</div>
            <?php if (has_permission('reports.php')): ?>
                <a href="<?= $base ?>/admin/pages/reports.php" class="hd-nav-item <?= is_active('reports.php') ?>">
                    <i class="bi bi-bar-chart-fill"></i>
                    <span class="hd-nav-text">Analytical Reports</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('statements.php')): ?>
                <a href="<?= $base ?>/admin/pages/statements.php" class="hd-nav-item <?= is_active('statements.php') ?>">
                    <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                    <span class="hd-nav-text">Account Statements</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('live_monitor.php') || has_permission('monitor.php')): ?>
            <div class="hd-nav-header">System Control Center</div>
            <?php if (has_permission('live_monitor.php')): ?>
                <a href="<?= $base ?>/admin/pages/live_monitor.php" class="hd-nav-item <?= is_active('live_monitor.php') ?>">
                    <i class="bi bi-display-fill"></i>
                    <span class="hd-nav-text">
                        Operations &amp; Health
                        <span class="nav-live-dot"></span>
                    </span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('monitor.php')): ?>
                <a href="<?= $base ?>/admin/pages/monitor.php" class="hd-nav-item <?= is_active('monitor.php') ?>">
                    <i class="bi bi-phone-vibrate-fill"></i>
                    <span class="hd-nav-text">Transaction Monitor</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('backups.php') || has_permission('support.php') || has_permission('settings.php')): ?>
            <div class="hd-nav-header">Maintenance &amp; Config</div>
            <?php if (has_permission('backups.php')): ?>
                <a href="<?= $base ?>/admin/pages/backups.php" class="hd-nav-item <?= is_active('backups.php') ?>">
                    <i class="bi bi-database-fill-check"></i>
                    <span class="hd-nav-text">Database Backups</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('support.php')): ?>
                <a href="<?= $base ?>/admin/pages/support.php" class="hd-nav-item <?= is_active('support.php', ['support_view.php']) ?>">
                    <i class="bi bi-headset"></i>
                    <span class="hd-nav-text">Tech Support</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('settings.php')): ?>
                <a href="<?= $base ?>/admin/pages/settings.php" class="hd-nav-item <?= is_active('settings.php') ?>">
                    <i class="bi bi-sliders2"></i>
                    <span class="hd-nav-text">Global Settings</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('profile.php') || has_permission('notifications.php') || $role === 'superadmin'): ?>
            <div class="hd-nav-header">My Account</div>
            <?php if (has_permission('profile.php') || $role === 'superadmin'): ?>
                <a href="<?= $base ?>/admin/pages/profile.php" class="hd-nav-item <?= is_active('profile.php') ?>">
                    <i class="bi bi-person-circle"></i>
                    <span class="hd-nav-text">My Profile</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('notifications.php') || $role === 'superadmin'): ?>
                <a href="<?= $base ?>/admin/pages/notifications.php" class="hd-nav-item <?= is_active('notifications.php') ?>">
                    <i class="bi bi-bell-fill"></i>
                    <span class="hd-nav-text">Notifications</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

    </div><!-- /hd-scroll-area -->

    <!-- ─── Footer ── -->
    <div class="hd-footer">
        <div class="hd-user-card">
            <div class="hd-avatar">
                <?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="hd-brand-text">
                <div class="hd-user-name"><?= htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'Admin')[0]) ?></div>
                <span class="hd-user-role"><?= strtoupper($role) ?></span>
            </div>
        </div>
        <a href="<?= $base ?>/public/logout.php" class="hd-logout">
            <i class="bi bi-box-arrow-right"></i>
            <span class="hd-nav-text">Sign Out</span>
        </a>
    </div>

</aside>

<?php // Sidebar state restoration handled by assets/js/sidebar.js ?>