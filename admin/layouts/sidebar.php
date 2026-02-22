<?php
// admin/layouts/sidebar.php
// Canonical Admin Sidebar - Forest & Lime Theme (Sync with inc/sidebar.php)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/sidebar_styles.php';

// 1. Role & Path Logic
$role = $_SESSION['role'] ?? 'admin';
$base = defined('BASE_URL') ? BASE_URL : '/usms';
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

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<button class="hd-toggle-btn d-none d-lg-flex" id="sidebarToggle" title="Toggle Menu">
    <i class="bi bi-list fs-4"></i>
</button>

<aside class="hd-sidebar" id="sidebar">
    
    <div class="hd-brand">
        <a href="<?= $base ?>/admin/pages/dashboard.php" class="d-flex align-items-center gap-3 text-decoration-none">
            <img src="<?= $assets ?>/images/people_logo.png" alt="Logo" class="hd-logo-img">
            <div class="hd-brand-text lh-1">
                <div class="fw-bold fs-6 tracking-tight text-dark dark-text-white">
                    <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'UMOJA SACCO' ?>
                </div>
                <small class="text-uppercase text-muted" style="font-size: 0.65rem; letter-spacing: 0.5px;">
                    ADMIN PANEL
                </small>
            </div>
        </a>
    </div>

    <div class="hd-scroll-area">
        
        <a href="<?= $base ?>/admin/pages/dashboard.php" class="hd-nav-item <?= is_active('dashboard.php') ?>">
            <i class="bi bi-grid-1x2-fill"></i> <span class="hd-nav-text">Admin Dashboard</span>
        </a>

        <?php if (has_permission('member_onboarding.php') || has_permission('members.php')): ?>
            <div class="hd-nav-header">Member Management</div>
            <?php if (has_permission('member_onboarding.php')): ?>
                <a href="<?= $base ?>/admin/pages/member_onboarding.php" class="hd-nav-item <?= is_active('member_onboarding.php') ?>">
                    <i class="bi bi-person-plus"></i> <span class="hd-nav-text">Member Onboarding</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('members.php')): ?>
                <a href="<?= $base ?>/admin/pages/members.php" class="hd-nav-item <?= is_active('members.php', ['member_profile.php']) ?>">
                    <i class="bi bi-people-fill"></i> <span class="hd-nav-text">Members List</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('employees.php') || has_permission('users.php') || has_permission('roles.php') || $role === 'superadmin'): ?>
            <div class="hd-nav-header">People & Access</div>
            <?php if (has_permission('employees.php')): ?>
                <a href="<?= $base ?>/admin/pages/employees.php" class="hd-nav-item <?= is_active('employees.php') ?>">
                    <i class="bi bi-person-vcard"></i> <span class="hd-nav-text">Employees</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('users.php') || $role === 'superadmin'): ?>
                <a href="<?= $base ?>/admin/pages/users.php" class="hd-nav-item <?= is_active('users.php') ?>">
                    <i class="bi bi-person-badge"></i> <span class="hd-nav-text">System Users</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('roles.php') || $role === 'superadmin'): ?>
                <a href="<?= $base ?>/admin/pages/roles.php" class="hd-nav-item <?= is_active('roles.php') ?>">
                    <i class="bi bi-shield-lock"></i> <span class="hd-nav-text">Access Control</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>


        <?php if (has_permission('payments.php') || has_permission('revenue.php') || has_permission('expenses.php') || has_permission('payroll.php') || has_permission('transactions.php') || has_permission('monitor.php') || has_permission('trial_balance.php') || $role === 'superadmin'): ?>
            <div class="hd-nav-header">Financial Management</div>
            <?php if (has_permission('payments.php')): ?>
                <a href="<?= $base ?>/admin/pages/payments.php" class="hd-nav-item <?= is_active('payments.php') ?>">
                    <i class="bi bi-cash-coin"></i> <span class="hd-nav-text">Cashier / Payments</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('revenue.php')): ?>
                <a href="<?= $base ?>/admin/pages/revenue.php" class="hd-nav-item <?= is_active('revenue.php') ?>">
                    <i class="bi bi-graph-up"></i> <span class="hd-nav-text">Revenue Inflow</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('expenses.php')): ?>
                <a href="<?= $base ?>/admin/pages/expenses.php" class="hd-nav-item <?= is_active('expenses.php') ?>">
                    <i class="bi bi-receipt"></i> <span class="hd-nav-text">Expense Tracker</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('payroll.php') || $role === 'superadmin'): ?>
                <a href="<?= $base ?>/admin/pages/payroll.php" class="hd-nav-item <?= is_active('payroll.php') ?>">
                    <i class="bi bi-wallet2"></i> <span class="hd-nav-text">Payroll Processing</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('transactions.php')): ?>
                <a href="<?= $base ?>/admin/pages/transactions.php" class="hd-nav-item <?= is_active('transactions.php') ?>">
                    <i class="bi bi-journal-text"></i> <span class="hd-nav-text">Live Ledger View</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('monitor.php')): ?>
                <a href="<?= $base ?>/admin/pages/monitor.php" class="hd-nav-item <?= is_active('monitor.php') ?>">
                    <i class="bi bi-phone-vibrate"></i> <span class="hd-nav-text">M-Pesa Logs</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('trial_balance.php')): ?>
                <a href="<?= $base ?>/admin/pages/trial_balance.php" class="hd-nav-item <?= is_active('trial_balance.php') ?>">
                    <i class="bi bi-clipboard-data"></i> <span class="hd-nav-text">Trial Balance</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('loans_reviews.php') || has_permission('loans_payouts.php')): ?>
            <div class="hd-nav-header">Loans & Credit</div>
            <?php if (has_permission('loans_reviews.php')): ?>
                <a href="<?= $base ?>/admin/pages/loans_reviews.php" class="hd-nav-item <?= is_active('loans_reviews.php') ?>">
                    <i class="bi bi-bank"></i> <span class="hd-nav-text">Loan Reviews</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('loans_payouts.php')): ?>
                <a href="<?= $base ?>/admin/pages/loans_payouts.php" class="hd-nav-item <?= is_active('loans_payouts.php') ?>">
                    <i class="bi bi-cash-stack"></i> <span class="hd-nav-text">Loan Payouts</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('welfare.php') || $role === 'superadmin'): ?>
            <div class="hd-nav-header">Welfare Module</div>
            <a href="<?= $base ?>/admin/pages/welfare.php" class="hd-nav-item <?= is_active('welfare.php', ['welfare_cases.php', 'welfare_support.php']) ?>">
                <i class="bi bi-heart-pulse"></i> <span class="hd-nav-text">Welfare Management</span>
            </a>
        <?php endif; ?>

        <?php if (has_permission('investments.php') || has_permission('admin_shares.php')): ?>
            <div class="hd-nav-header">Investments & Assets</div>
            <?php if (has_permission('investments.php')): ?>
                <a href="<?= $base ?>/admin/pages/investments.php" class="hd-nav-item <?= is_active('investments.php') ?>">
                    <i class="bi bi-buildings"></i> <span class="hd-nav-text">Asset Portfolio</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('admin_shares.php')): ?>
                <a href="<?= $base ?>/admin/pages/admin_shares.php" class="hd-nav-item <?= is_active('admin_shares.php') ?>">
                    <i class="bi bi-pie-chart-fill"></i> <span class="hd-nav-text">Equity & Shares</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('reports.php') || has_permission('statements.php')): ?>
            <div class="hd-nav-header">Reports & Exports</div>
            <?php if (has_permission('reports.php')): ?>
                <a href="<?= $base ?>/admin/pages/reports.php" class="hd-nav-item <?= is_active('reports.php') ?>">
                    <i class="bi bi-bar-chart-fill"></i> <span class="hd-nav-text">Analytical Reports</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('statements.php')): ?>
                <a href="<?= $base ?>/admin/pages/statements.php" class="hd-nav-item <?= is_active('statements.php') ?>">
                    <i class="bi bi-file-earmark-spreadsheet"></i> <span class="hd-nav-text">Account Statements</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('live_monitor.php') || has_permission('transaction_monitor.php') || has_permission('system_health.php') || has_permission('backups.php') || has_permission('audit_logs.php') || has_permission('support.php') || has_permission('settings.php')): ?>
            <div class="hd-nav-header">System Maintenance</div>
            <?php if (has_permission('live_monitor.php')): ?>
                <a href="<?= $base ?>/admin/pages/live_monitor.php" class="hd-nav-item <?= is_active('live_monitor.php') ?>">
                    <i class="bi bi-display"></i> <span class="hd-nav-text">Live Monitor</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('transaction_monitor.php')): ?>
                <a href="<?= $base ?>/admin/pages/transaction_monitor.php" class="hd-nav-item <?= is_active('transaction_monitor.php') ?>">
                    <i class="bi bi-exclamation-triangle"></i> <span class="hd-nav-text">Transaction Recovery</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('system_health.php')): ?>
                <a href="<?= $base ?>/admin/pages/system_health.php" class="hd-nav-item <?= is_active('system_health.php') ?>">
                    <i class="bi bi-cpu"></i> <span class="hd-nav-text">System Health</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('backups.php')): ?>
                <a href="<?= $base ?>/admin/pages/backups.php" class="hd-nav-item <?= is_active('backups.php') ?>">
                    <i class="bi bi-database-fill-check"></i> <span class="hd-nav-text">Database Backups</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('audit_logs.php')): ?>
                <a href="<?= $base ?>/admin/pages/audit_logs.php" class="hd-nav-item <?= is_active('audit_logs.php') ?>">
                    <i class="bi bi-journal-code"></i> <span class="hd-nav-text">Activity Logs</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('support.php')): ?>
                <a href="<?= $base ?>/admin/pages/support.php" class="hd-nav-item <?= is_active('support.php', ['support_view.php']) ?>">
                    <i class="bi bi-headset"></i> <span class="hd-nav-text">Tech Support</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('settings.php')): ?>
                <a href="<?= $base ?>/admin/pages/settings.php" class="hd-nav-item <?= is_active('settings.php') ?>">
                    <i class="bi bi-sliders"></i> <span class="hd-nav-text">Global Settings</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('profile.php') || has_permission('notifications.php') || $role === 'superadmin'): ?>
            <div class="hd-nav-header">My Account</div>
            <?php if (has_permission('profile.php') || $role === 'superadmin'): ?>
                <a href="<?= $base ?>/admin/pages/profile.php" class="hd-nav-item <?= is_active('profile.php') ?>">
                    <i class="bi bi-person-circle"></i> <span class="hd-nav-text">My Profile</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('notifications.php') || $role === 'superadmin'): ?>
                <a href="<?= $base ?>/admin/pages/notifications.php" class="hd-nav-item <?= is_active('notifications.php') ?>">
                    <i class="bi bi-bell"></i> <span class="hd-nav-text">Notifications</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

    </div>

    <div class="hd-footer">
        <div class="hd-nav-item d-flex align-items-center gap-3" style="background: transparent; cursor: default; transform: none;">
            <div class="msg-avatar bg-primary text-white d-flex align-items-center justify-content-center fw-bold">
                <?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="hd-brand-text lh-1">
                <div class="fw-bold fs-6 text-dark dark-text-white"><?= htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'Admin')[0]) ?></div>
                <small class="text-uppercase text-muted" style="font-size: 0.6rem;"><?= strtoupper($role) ?></small>
            </div>
        </div>
        <a href="<?= $base ?>/public/logout.php" class="hd-nav-item mt-2 text-danger">
            <i class="bi bi-power"></i> <span class="hd-nav-text fw-bold">Logout</span>
        </a>
    </div>

</aside>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const body = document.body;
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const toggleBtn = document.getElementById('sidebarToggle');

        if(localStorage.getItem('hd_sidebar_collapsed') === 'true') {
            body.classList.add('sb-collapsed');
        }

        if(toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                body.classList.toggle('sb-collapsed');
                const isCollapsed = body.classList.contains('sb-collapsed');
                localStorage.setItem('hd_sidebar_collapsed', isCollapsed);
            });
        }

        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('.mobile-nav-toggle');
            if (trigger) {
                sidebar.classList.add('show');
                backdrop.classList.add('show');
            }
        });

        if(backdrop) {
            backdrop.addEventListener('click', () => {
                sidebar.classList.remove('show');
                backdrop.classList.remove('show');
            });
        }
    });
</script>
