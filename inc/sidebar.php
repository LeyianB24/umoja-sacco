<?php
// inc/sidebar.php
// HOPE UI Sidebar - Light Theme & Pill Design
// Logic: 100% Preserved, Links Preserved, Enhanced Toggling

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth.php';

// 1. Role & Path Logic
$role = 'guest';
if (isset($_SESSION['admin_id'])) {
    $role = $_SESSION['role'] ?? 'admin'; 
} elseif (isset($_SESSION['member_id'])) {
    $role = 'member';
}

$base = defined('BASE_URL') ? BASE_URL : '/usms'; 
$assets = defined('ASSET_BASE') ? ASSET_BASE : $base . '/public/assets';

// Active Link Helper
if (!function_exists('is_active')) {
    function is_active($page) {
        $current = basename($_SERVER['PHP_SELF']);
        return $current === $page ? 'active' : '';
    }
}
?>

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
        z-index: 1040;
        background: var(--sb-bg);
        border-right: 1px solid var(--sb-border);
        display: flex;
        flex-direction: column;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); /* Smooth Physics */
        box-shadow: 5px 0 30px rgba(0,0,0,0.02);
    }

    /* --- RESPONSIVE LOGIC (The Fix for "Pages don't toggle well") --- */
    
    /* 1. Desktop: When Body has 'sb-collapsed' class */
    @media (min-width: 992px) {
        body.sb-collapsed .hd-sidebar {
            width: var(--sb-collapsed);
        }
        
        /* THIS forces all pages to adjust margin automatically */
        body.sb-collapsed .main-content-wrapper,
        body.sb-collapsed .main-content,
        body.sb-collapsed main {
            margin-left: var(--sb-collapsed) !important;
        }

        /* Hide Text Elements */
        body.sb-collapsed .hd-brand-text,
        body.sb-collapsed .hd-nav-text,
        body.sb-collapsed .hd-nav-header,
        body.sb-collapsed .hd-support-widget {
            opacity: 0;
            pointer-events: none;
            display: none;
        }

        /* Center Icons */
        body.sb-collapsed .hd-nav-item {
            justify-content: center;
            padding: 14px 0;
            margin: 4px 12px;
        }
        body.sb-collapsed .hd-nav-item i {
            margin-right: 0;
            font-size: 1.4rem;
        }
        body.sb-collapsed .hd-brand {
            padding: 0;
            justify-content: center;
        }
    }

    /* 2. Mobile: Hidden by default */
    @media (max-width: 991px) {
        .hd-sidebar {
            transform: translateX(-100%);
            width: var(--sb-width);
            transition: transform 0.3s ease;
        }
        
        /* Mobile Open State */
        .hd-sidebar.mobile-open {
            transform: translateX(0);
            box-shadow: 0 0 50px rgba(0,0,0,0.2);
        }

        /* Toggle Button hides on mobile sidebar, shows in topbar usually */
        .hd-toggle-btn { display: none !important; }
    }

    /* --- TOGGLE BUTTON (3 Dashes) --- */
    .hd-toggle-btn {
        position: fixed; 
        top: 24px; 
        left: 260px; /* Aligned with sidebar edge */
        z-index: 1045;
        width: 36px; height: 36px; 
        border-radius: 8px;
        background: #FFFFFF; 
        border: 1px solid var(--sb-border);
        color: var(--sb-text);
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        cursor: pointer; 
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .hd-toggle-btn:hover { background: var(--sb-hover-bg); color: var(--sb-hover-text); }
    
    /* Move button when collapsed */
    body.sb-collapsed .hd-toggle-btn { left: 66px; }

    /* --- NAVIGATION STYLES --- */
    .hd-brand { height: 80px; display: flex; align-items: center; padding: 0 24px; }
    .hd-logo-img { width: 40px; height: 40px; border-radius: 12px; object-fit: cover; }
    
    .hd-scroll-area { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 10px 16px; scrollbar-width: none; }
    .hd-scroll-area::-webkit-scrollbar { display: none; }

    .hd-nav-header {
        font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px;
        color: #9CA3AF; margin: 24px 0 8px 12px; white-space: nowrap;
    }

    .hd-nav-item {
        display: flex; align-items: center; padding: 12px 16px;
        color: var(--sb-text); text-decoration: none; border-radius: 50px;
        margin-bottom: 4px; transition: all 0.2s; white-space: nowrap; overflow: hidden;
    }
    
    .hd-nav-item:hover { background: var(--sb-hover-bg); color: var(--sb-hover-text); transform: translateX(3px); }
    
    .hd-nav-item.active {
        background: var(--active-bg); color: var(--active-text);
        box-shadow: 0 4px 15px rgba(15, 57, 43, 0.25);
    }
    .hd-nav-item.active i { color: var(--accent-lime); }

    .hd-nav-item i {
        font-size: 1.25rem; width: 24px; text-align: center; margin-right: 14px;
        flex-shrink: 0; transition: transform 0.2s;
    }
    .hd-nav-text { font-size: 0.95rem; font-weight: 500; opacity: 1; transition: opacity 0.2s; }

    .hd-footer { padding: 20px; background: var(--sb-bg); border-top: 1px solid var(--sb-border); }
    
    .hd-support-widget {
        background: var(--active-bg); color: white; padding: 20px; 
        border-radius: 20px; text-align: center; margin: 20px 0;
        position: relative; overflow: hidden;
    }
    .hd-support-btn {
        display: block; width: 100%; background: var(--accent-lime); color: var(--active-bg);
        font-weight: 700; padding: 10px; border-radius: 50px; text-decoration: none; margin-top: 12px;
    }

    /* Mobile Backdrop */
    .sidebar-backdrop {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); z-index: 1035;
        opacity: 0; visibility: hidden; transition: 0.3s;
    }
    .sidebar-backdrop.show { opacity: 1; visibility: visible; }
</style>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<button class="hd-toggle-btn d-none d-lg-flex" id="sidebarToggle" title="Toggle Menu">
    <i class="bi bi-list fs-4"></i>
</button>

<aside class="hd-sidebar" id="sidebar">
    
    <div class="hd-brand">
        <div class="d-flex align-items-center gap-3">
            <img src="<?= $assets ?>/images/people_logo.png" alt="Logo" class="hd-logo-img">
            <div class="hd-brand-text lh-1">
                <div class="fw-bold fs-6 tracking-tight">
                    <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'UMOJA SACCO' ?>
                </div>
                <small class="text-uppercase text-muted" style="font-size: 0.65rem; letter-spacing: 0.5px;">
                    <?= $role === 'member' ? ($_SESSION['reg_no'] ?? 'MEMBER PANEL') : 'ADMIN PANEL' ?>
                </small>
            </div>
        </div>
    </div>

    <div class="hd-scroll-area">
        
        <?php if ($role === 'member'): ?>
            <a href="<?= $base ?>/member/pages/dashboard.php" class="hd-nav-item <?= is_active('dashboard.php') ?>">
                <i class="bi bi-grid-fill"></i> <span class="hd-nav-text">Dashboard</span>
            </a>
            <a href="<?= $base ?>/member/pages/mpesa_request.php" class="hd-nav-item <?= is_active('mpesa_request.php') ?>">
                <i class="bi bi-phone-vibrate-fill"></i> <span class="hd-nav-text">Pay via M-Pesa</span>
            </a>
            <a href="<?= $base ?>/member/pages/withdraw.php" class="hd-nav-item <?= is_active('withdraw.php') ?>">
                <i class="bi bi-wallet2"></i> <span class="hd-nav-text">Withdraw Funds</span>
            </a>
            
            <div class="hd-nav-header">Finances</div>
            <a href="<?= $base ?>/member/pages/savings.php" class="hd-nav-item <?= is_active('savings.php') ?>">
                <i class="bi bi-piggy-bank"></i> <span class="hd-nav-text">My Savings</span>
            </a>
            <a href="<?= $base ?>/member/pages/loans.php" class="hd-nav-item <?= is_active('loans.php') ?>">
                <i class="bi bi-cash-stack"></i> <span class="hd-nav-text">My Loans</span>
            </a>
            <a href="<?= $base ?>/member/pages/shares.php" class="hd-nav-item <?= is_active('shares.php') ?>">
                <i class="bi bi-pie-chart-fill"></i> <span class="hd-nav-text">Shares</span>
            </a>
            
            <div class="hd-nav-header">Welfare</div>
            <a href="<?= $base ?>/member/pages/welfare_situations.php" class="hd-nav-item <?= is_active('welfare_situations.php') ?>">
                <i class="bi bi-heart-pulse-fill"></i> <span class="hd-nav-text">Welfare Cases</span>
            </a>
            <a href="<?= $base ?>/member/pages/welfare.php" class="hd-nav-item <?= is_active('welfare.php') ?>">
                <i class="bi bi-shield-check"></i> <span class="hd-nav-text">Welfare Fund</span>
            </a>

            <div class="hd-nav-header">History</div>
            <a href="<?= $base ?>/member/pages/transactions.php" class="hd-nav-item <?= is_active('transactions.php') ?>">
                <i class="bi bi-arrow-left-right"></i> <span class="hd-nav-text">Transactions</span>
            </a>
            <a href="<?= $base ?>/member/pages/contributions.php" class="hd-nav-item <?= is_active('contributions.php') ?>">
                <i class="bi bi-journal-text"></i> <span class="hd-nav-text">Contributions</span>
            </a>

            <div class="hd-nav-header">Account</div>
            <a href="<?= $base ?>/member/pages/profile.php" class="hd-nav-item <?= is_active('profile.php') ?>">
                <i class="bi bi-person-circle"></i> <span class="hd-nav-text">My Profile</span>
            </a>
            <a href="<?= $base ?>/member/pages/settings.php" class="hd-nav-item <?= is_active('settings.php') ?>">
                <i class="bi bi-gear-wide-connected"></i> <span class="hd-nav-text">Settings</span>
            </a>
            <a href="<?= $base ?>/member/pages/notifications.php" class="hd-nav-item <?= is_active('notifications.php') ?>">
                <i class="bi bi-bell"></i> <span class="hd-nav-text">Notifications</span>
            </a>
            <a class="hd-nav-item <?= is_active('support.php') ?>" href="<?= $base ?>/member/pages/support.php">
                <i class="bi bi-headset"></i> <span class="hd-nav-text">Support Center</span>
            </a>

        <?php elseif ($role !== 'guest'): ?>
            
            <?php 
                $dash = match($role) {
                    'superadmin' => "$base/admin/pages/dashboard.php",
                    'manager'    => "$base/admin/pages/dashboard.php",
                    'accountant' => "$base/admin/pages/dashboard.php",
                    'admin'      => "$base/admin/pages/dashboard.php",
                    default      => "#"
                };
            ?>
            <a href="<?= $dash ?>" class="hd-nav-item <?= is_active('dashboard.php') ?>">
                <i class="bi bi-grid-1x2-fill"></i> <span class="hd-nav-text">Dashboard</span>
            </a>

            <?php if ($role === 'superadmin' || has_permission('staff_mgmt.php')): ?>
                <div class="hd-nav-header">System</div>
                <a href="<?= $base ?>/admin/pages/staff_mgmt.php" class="hd-nav-item <?= is_active('staff_mgmt.php') ?>">
                    <i class="bi bi-shield-lock"></i> <span class="hd-nav-text">Manage Staff</span>
                </a>
                <a href="<?= $base ?>/admin/pages/roles.php" class="hd-nav-item <?= is_active('roles.php') ?>">
                    <i class="bi bi-grid-3x3-gap"></i> <span class="hd-nav-text">Role Matrix</span>
                </a>
            <?php endif; ?>

            <div class="hd-nav-header">Operations</div>
            <?php if (has_permission('members.php')): ?>
                <a href="<?= $base ?>/admin/pages/members.php" class="hd-nav-item <?= is_active('members.php') ?>">
                    <i class="bi bi-people-fill"></i> <span class="hd-nav-text">Member Directory</span>
                </a>
                <a href="<?= $base ?>/admin/pages/member_onboarding.php" class="hd-nav-item <?= is_active('member_onboarding.php') ?>">
                    <i class="bi bi-person-plus"></i> <span class="hd-nav-text">Member Onboarding</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('loans_reviews.php')): ?>
                <a href="<?= $base ?>/admin/pages/loans_reviews.php" class="hd-nav-item <?= is_active('loans.php') ?>">
                    <i class="bi bi-bank"></i> <span class="hd-nav-text">Loan Reviews</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('welfare_cases.php')): ?>
                <a href="<?= $base ?>/admin/pages/welfare_cases.php" class="hd-nav-item <?= is_active('welfare_cases.php') ?>">
                    <i class="bi bi-heart-pulse"></i> <span class="hd-nav-text">Welfare Cases</span>
                </a>
                <a href="<?= $base ?>/admin/pages/welfare_support.php" class="hd-nav-item <?= is_active('welfare_support.php') ?>">
                    <i class="bi bi-hospital"></i> <span class="hd-nav-text">Welfare Support</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('investments.php')): ?>
                <a href="<?= $base ?>/admin/pages/investments.php" class="hd-nav-item <?= is_active('investments.php') ?>">
                    <i class="bi bi-buildings"></i> <span class="hd-nav-text">Investments</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('investments.php')): ?>
                <a href="<?= $base ?>/admin/pages/investments.php?cat=vehicle_fleet" class="hd-nav-item <?= (isset($_GET['cat']) && $_GET['cat'] === 'vehicle_fleet') ? 'active' : '' ?>">
                    <i class="bi bi-bus-front-fill"></i> <span class="hd-nav-text">Vehicles</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('employees.php')): ?>
                <a href="<?= $base ?>/admin/pages/employees.php" class="hd-nav-item <?= is_active('employees.php') ?>">
                    <i class="bi bi-person-badge"></i> <span class="hd-nav-text">Employees</span>
                </a>
                <a href="<?= $base ?>/admin/pages/payroll.php" class="hd-nav-item <?= is_active('payroll.php') ?>">
                    <i class="bi bi-cash-stack"></i> <span class="hd-nav-text">Payroll Hub</span>
                </a>
            <?php endif; ?>

            <div class="hd-nav-header">Finance</div>
            <?php if (has_permission('revenue.php')): ?>
                <a href="<?= $base ?>/admin/pages/revenue.php" class="hd-nav-item <?= is_active('revenue.php') ?>">
                    <i class="bi bi-graph-up"></i> <span class="hd-nav-text">Revenue Tracking</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('payments.php')): ?>
                <a href="<?= $base ?>/admin/pages/payments.php" class="hd-nav-item <?= is_active('payments.php') ?>">
                    <i class="bi bi-credit-card"></i> <span class="hd-nav-text">Payments</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('transactions.php')): ?>
                <a href="<?= $base ?>/admin/pages/transactions.php" class="hd-nav-item <?= is_active('transactions.php') ?>">
                    <i class="bi bi-journal-text"></i> <span class="hd-nav-text">Golden Ledger</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('expenses.php')): ?>
                <a href="<?= $base ?>/admin/pages/expenses.php" class="hd-nav-item <?= is_active('expenses.php') ?>">
                    <i class="bi bi-receipt"></i> <span class="hd-nav-text">Expenses</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('loans_payouts.php')): ?>
                <a href="<?= $base ?>/admin/pages/loans_payouts.php" class="hd-nav-item <?= is_active('loans.php') ?>">
                    <i class="bi bi-cash-coin"></i> <span class="hd-nav-text">Disbursement</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('statements.php') || has_permission('trial_balance.php')): ?>
                <a href="<?= $base ?>/admin/pages/statements.php" class="hd-nav-item <?= is_active('statements.php') ?>">
                    <i class="bi bi-file-earmark-spreadsheet"></i> <span class="hd-nav-text">Statements</span>
                </a>
                <a href="<?= $base ?>/admin/pages/trial_balance.php" class="hd-nav-item <?= is_active('trial_balance.php') ?>">
                    <i class="bi bi-calculator"></i> <span class="hd-nav-text">Trial Balance</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('reports.php')): ?>
                <a href="<?= $base ?>/admin/pages/reports.php" class="hd-nav-item <?= is_active('reports.php') ?>">
                    <i class="bi bi-pie-chart"></i> <span class="hd-nav-text">Reports</span>
                </a>
            <?php endif; ?>

            <div class="hd-nav-header">Maintenance</div>
            <?php if (has_permission('audit_logs.php')): ?>
                <a href="<?= $base ?>/admin/pages/monitor.php" class="hd-nav-item <?= is_active('monitor.php') ?>">
                    <i class="bi bi-broadcast-pin text-danger"></i> <span class="hd-nav-text">Live Monitor</span>
                </a>
                <a href="<?= $base ?>/admin/pages/audit_logs.php" class="hd-nav-item <?= is_active('audit_logs.php') ?>">
                    <i class="bi bi-activity"></i> <span class="hd-nav-text">Audit Logs</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('settings.php')): ?>
                <a href="<?= $base ?>/admin/pages/settings.php" class="hd-nav-item <?= is_active('settings.php') ?>">
                    <i class="bi bi-sliders"></i> <span class="hd-nav-text">Global Settings</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('backups.php')): ?>
                <a href="<?= $base ?>/admin/pages/backups.php" class="hd-nav-item <?= is_active('backups.php') ?>">
                    <i class="bi bi-database-down"></i> <span class="hd-nav-text">Backups</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('support.php')): ?>
                <a href="<?= $base ?>/admin/pages/support.php" class="hd-nav-item <?= is_active('support.php') ?>">
                    <i class="bi bi-life-preserver"></i> <span class="hd-nav-text">Tech Support</span>
                </a>
            <?php endif; ?>

        <?php endif; ?>

        <?php if ($role === 'member'): ?>
            <div class="hd-support-widget">
                <h6 class="fw-bold mb-1">Need Help?</h6>
                <p class="small opacity-75 mb-0">Contact Support</p>
                <a href="<?= $base ?>/public/support.php" class="hd-support-btn">Open Ticket</a>
            </div>
        <?php endif; ?>

    </div>

    <div class="hd-footer">
        <a href="<?= $base ?>/public/logout.php" class="hd-nav-item justify-content-center" 
           style="background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA;">
            <i class="bi bi-power" style="margin:0"></i> 
            <span class="hd-nav-text ms-2 fw-bold">Logout</span>
        </a>
    </div>

</aside>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const body = document.body;
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const toggleBtn = document.getElementById('sidebarToggle');

        // 1. RESTORE STATE (Instant Fix for "Jumping" pages)
        // We apply the class immediately if saved in localStorage
        if(localStorage.getItem('hd_sidebar_collapsed') === 'true') {
            body.classList.add('sb-collapsed');
        }

        // 2. DESKTOP TOGGLE (Click 3-dashes button)
        if(toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                body.classList.toggle('sb-collapsed');
                // Save state so other pages know to stay collapsed
                const isCollapsed = body.classList.contains('sb-collapsed');
                localStorage.setItem('hd_sidebar_collapsed', isCollapsed);
            });
        }

        // 3. MOBILE TOGGLE LOGIC
        // This listens for ANY button with class 'mobile-nav-toggle' (usually in Topbar)
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('.mobile-nav-toggle');
            if (trigger) {
                sidebar.classList.add('mobile-open');
                backdrop.classList.add('show');
            }
        });

        // Close when clicking outside on mobile
        if(backdrop) {
            backdrop.addEventListener('click', () => {
                sidebar.classList.remove('mobile-open');
                backdrop.classList.remove('show');
            });
        }
    });
</script>
