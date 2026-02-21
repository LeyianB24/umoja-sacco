<?php
// admin/layouts/sidebar.php
// Canonical Admin Sidebar - Forest & Lime Theme
// Logic: 100% Preserved from inc/sidebar.php
// CSS: Uses layout.css / admin.css classes

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../inc/auth.php';

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

<aside class="admin-sidebar" id="sidebar">
    
    <a href="<?= $base ?>/admin/pages/dashboard.php" class="sidebar-brand">
        <div class="sidebar-brand-logo">U</div>
        <div class="sidebar-brand-text">
            <div><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'UMOJA SACCO' ?></div>
            <div class="sidebar-brand-sub">ADMIN PANEL</div>
        </div>
    </a>

    <div class="sidebar-nav">
        
        <div class="sidebar-section-label">OVERVIEW</div>
        <a href="<?= $base ?>/admin/pages/dashboard.php" class="sidebar-nav-item <?= is_active('dashboard.php') ?>">
            <i class="bi bi-grid-1x2-fill"></i> <span>Dashboard</span>
        </a>

        <?php if (has_permission('member_onboarding.php') || has_permission('members.php')): ?>
            <div class="sidebar-section-label">MEMBER MANAGEMENT</div>
            <?php if (has_permission('member_onboarding.php')): ?>
                <a href="<?= $base ?>/admin/pages/member_onboarding.php" class="sidebar-nav-item <?= is_active('member_onboarding.php') ?>">
                    <i class="bi bi-person-plus"></i> <span>Member Onboarding</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('members.php')): ?>
                <a href="<?= $base ?>/admin/pages/members.php" class="sidebar-nav-item <?= is_active('members.php', ['member_profile.php']) ?>">
                    <i class="bi bi-people-fill"></i> <span>Members List</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('employees.php') || has_permission('users.php') || has_permission('roles.php') || $role === 'superadmin'): ?>
            <div class="sidebar-section-label">PEOPLE & ACCESS</div>
            <?php if (has_permission('employees.php')): ?>
                <a href="<?= $base ?>/admin/pages/employees.php" class="sidebar-nav-item <?= is_active('employees.php') ?>">
                    <i class="bi bi-people-fill"></i> <span>Employees</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('users.php') || $role === 'superadmin'): ?>
                <a href="<?= $base ?>/admin/pages/users.php" class="sidebar-nav-item <?= is_active('users.php') ?>">
                    <i class="bi bi-person-badge"></i> <span>System Users</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('roles.php') || $role === 'superadmin'): ?>
                <a href="<?= $base ?>/admin/pages/roles.php" class="sidebar-nav-item <?= is_active('roles.php') ?>">
                    <i class="bi bi-shield-lock"></i> <span>Access Control</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('payments.php') || has_permission('revenue.php') || has_permission('expenses.php') || has_permission('payroll.php') || has_permission('transactions.php') || has_permission('monitor.php') || has_permission('trial_balance.php') || $role === 'superadmin'): ?>
            <div class="sidebar-section-label">FINANCIALS</div>
            <?php if (has_permission('payments.php')): ?>
                <a href="<?= $base ?>/admin/pages/payments.php" class="sidebar-nav-item <?= is_active('payments.php') ?>">
                    <i class="bi bi-cash-coin"></i> <span>Cashier / Payments</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('revenue.php')): ?>
                <a href="<?= $base ?>/admin/pages/revenue.php" class="sidebar-nav-item <?= is_active('revenue.php') ?>">
                    <i class="bi bi-graph-up"></i> <span>Revenue Inflow</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('expenses.php')): ?>
                <a href="<?= $base ?>/admin/pages/expenses.php" class="sidebar-nav-item <?= is_active('expenses.php') ?>">
                    <i class="bi bi-receipt"></i> <span>Expense Tracker</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('payroll.php') || $role === 'superadmin'): ?>
                <a href="<?= $base ?>/admin/pages/payroll.php" class="sidebar-nav-item <?= is_active('payroll.php') ?>">
                    <i class="bi bi-wallet2"></i> <span>Payroll Processing</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('transactions.php')): ?>
                <a href="<?= $base ?>/admin/pages/transactions.php" class="sidebar-nav-item <?= is_active('transactions.php') ?>">
                    <i class="bi bi-journal-text"></i> <span>Live Ledger</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('monitor.php')): ?>
                <a href="<?= $base ?>/admin/pages/monitor.php" class="sidebar-nav-item <?= is_active('monitor.php') ?>">
                    <i class="bi bi-phone-vibrate"></i> <span>M-Pesa Logs</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('trial_balance.php')): ?>
                <a href="<?= $base ?>/admin/pages/trial_balance.php" class="sidebar-nav-item <?= is_active('trial_balance.php') ?>">
                    <i class="bi bi-balance-scale"></i> <span>Trial Balance</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (has_permission('loans_reviews.php') || has_permission('loans_payouts.php')): ?>
            <div class="sidebar-section-label">LOANS & CREDIT</div>
            <?php if (has_permission('loans_reviews.php')): ?>
                <a href="<?= $base ?>/admin/pages/loans_reviews.php" class="sidebar-nav-item <?= is_active('loans_reviews.php') ?>">
                    <i class="bi bi-bank"></i> <span>Loan Reviews</span>
                </a>
            <?php endif; ?>
            <?php if (has_permission('loans_payouts.php')): ?>
                <a href="<?= $base ?>/admin/pages/loans_payouts.php" class="sidebar-nav-item <?= is_active('loans_payouts.php') ?>">
                    <i class="bi bi-cash-stack"></i> <span>Loan Payouts</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <div class="sidebar-section-label">SYSTEM</div>
        <?php if (has_permission('reports.php') || has_permission('statements.php')): ?>
            <a href="<?= $base ?>/admin/pages/reports.php" class="sidebar-nav-item <?= is_active('reports.php') ?>">
                <i class="bi bi-pie-chart"></i> <span>Analytical Reports</span>
            </a>
        <?php endif; ?>
        <?php if (has_permission('live_monitor.php')): ?>
            <a href="<?= $base ?>/admin/pages/live_monitor.php" class="sidebar-nav-item <?= is_active('live_monitor.php') ?>">
                <i class="bi bi-display"></i> <span>Live Monitor</span>
            </a>
        <?php endif; ?>
        <?php if (has_permission('audit_logs.php')): ?>
            <a href="<?= $base ?>/admin/pages/audit_logs.php" class="sidebar-nav-item <?= is_active('audit_logs.php') ?>">
                <i class="bi bi-activity"></i> <span>Activity Logs</span>
            </a>
        <?php endif; ?>
        <?php if (has_permission('settings.php')): ?>
            <a href="<?= $base ?>/admin/pages/settings.php" class="sidebar-nav-item <?= is_active('settings.php') ?>">
                <i class="bi bi-sliders"></i> <span>Global Settings</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="member-avatar" style="width: 32px; height: 32px; font-size: 0.8rem;">
                <?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'Admin')[0]) ?></div>
                <div class="sidebar-user-role"><?= strtoupper($role) ?></div>
            </div>
        </div>
        <a href="<?= $base ?>/public/logout.php" class="sidebar-nav-item mt-2 text-danger">
            <i class="bi bi-power"></i> <span>Logout</span>
        </a>
    </div>

</aside>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtns = document.querySelectorAll('.sidebar-toggle-btn');

    toggleBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            sidebar.classList.toggle('sidebar-open');
            overlay.classList.toggle('show');
        });
    });

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('sidebar-open');
        overlay.classList.remove('show');
    });
});
</script>
