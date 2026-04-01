<?php
// inc/sidebar.php — HD Edition · Forest & Lime · Plus Jakarta Sans
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../inc/Auth.php';

$role = 'guest';
if (isset($_SESSION['admin_id']))       { $role = $_SESSION['role'] ?? 'admin'; }
elseif (isset($_SESSION['member_id'])) { $role = 'member'; }

$base   = defined('BASE_URL')   ? BASE_URL   : '/usms';
$assets = defined('ASSET_BASE') ? ASSET_BASE : $base . '/public/assets';

if (!function_exists('is_active')) {
    function is_active($page, $aliases = []) {
        $current = basename($_SERVER['PHP_SELF']);
        if ($current === $page) return 'active';
        foreach ($aliases as $alias) { if ($current === $alias) return 'active'; }
        return '';
    }
}
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* TOKEN MATCH WITH SAVINGS/SHARES/WELFARE PAGES */
*,*::before,*::after{box-sizing:border-box}
:root{
    --f:#0b2419;--fm:#154330;--fs:#1d6044;
    --lime:#a3e635;--lt:#6a9a1a;--lg:rgba(163,230,53,.14);
    --sb-w:272px;--sb-col:72px;
    --sb-ease:cubic-bezier(.16,1,.3,1);
    --sb-spring:cubic-bezier(.34,1.56,.64,1);
    --sb-r:10px;
    --sb-bg:#fff;--sb-bg2:#f7fbf8;
    --sb-bdr:rgba(11,36,25,.07);--sb-bdr2:rgba(11,36,25,.04);
    --sb-text:#6b8a7a;--sb-ink:#0b2419;--sb-sec:#b0ccc2;
    --sb-sh:2px 0 32px rgba(11,36,25,.09);
    --sb-scroll:rgba(11,36,25,.10);
}
[data-bs-theme="dark"]{
    --sb-bg:#0d1d14;--sb-bg2:#0a1810;
    --sb-bdr:rgba(255,255,255,.07);--sb-bdr2:rgba(255,255,255,.04);
    --sb-text:#5a8a6e;--sb-ink:#d8eee2;--sb-sec:rgba(255,255,255,.2);
    --sb-sh:2px 0 32px rgba(0,0,0,.35);
    --sb-scroll:rgba(255,255,255,.10);
}
.hd-sidebar,.hd-sidebar *{font-family:'Plus Jakarta Sans',sans-serif!important;-webkit-font-smoothing:antialiased}

/* SHELL */
.hd-sidebar{width:var(--sb-w);height:100vh;position:fixed;top:0;left:0;z-index:1040;background:var(--sb-bg);border-right:1px solid var(--sb-bdr);display:flex;flex-direction:column;transition:width .28s var(--sb-ease);box-shadow:var(--sb-sh)}
.hd-sidebar::before{content:'';position:absolute;top:0;left:0;right:0;height:2.5px;z-index:2;background:linear-gradient(90deg,var(--f) 0%,var(--lime) 55%,var(--f) 100%)}

/* COLLAPSED */
@media(min-width:992px){
    body.sb-collapsed .hd-sidebar{width:var(--sb-col)}
    body.sb-collapsed .main-content-wrapper,body.sb-collapsed .main-content,body.sb-collapsed main{margin-left:var(--sb-col)!important}
    body.sb-collapsed .hd-brand-text,body.sb-collapsed .hd-nav-text,body.sb-collapsed .hd-section-label,body.sb-collapsed .hd-support-widget,body.sb-collapsed .hd-logout-label{opacity:0;pointer-events:none;width:0;overflow:hidden}
    body.sb-collapsed .hd-nav-link{justify-content:center;padding:10px 0;margin:1px 8px}
    body.sb-collapsed .hd-nav-link .hd-nav-icon{margin-right:0}
    body.sb-collapsed .hd-brand-inner{justify-content:center;padding:0}
    body.sb-collapsed .hd-logout-btn{justify-content:center;padding:10px 0;margin:0 8px}
    body.sb-collapsed .hd-logout-icon{margin-right:0}
    body.sb-collapsed .hd-toggle-btn{left:calc(var(--sb-col) - 14px)}
}

/* MOBILE */
@media(max-width:991px){
    .hd-sidebar{transform:translateX(-100%);transition:transform .3s var(--sb-ease)}
    .hd-sidebar.mobile-open{transform:translateX(0);box-shadow:0 0 60px rgba(0,0,0,.25)}
    .hd-toggle-btn{display:none!important}
}

/* TOGGLE BTN */
.hd-toggle-btn{position:fixed;top:20px;left:calc(var(--sb-w) - 14px);z-index:1045;width:28px;height:28px;border-radius:8px;background:var(--sb-bg);border:1px solid var(--sb-bdr);color:var(--sb-text);display:flex;align-items:center;justify-content:center;font-size:.82rem;cursor:pointer;box-shadow:0 2px 10px rgba(11,36,25,.1);transition:all .22s var(--sb-spring)}
.hd-toggle-btn:hover{background:var(--f);color:var(--lime);border-color:var(--f);transform:scale(1.08)}

/* BRAND */
.hd-brand{height:68px;padding:0 16px;border-bottom:1px solid var(--sb-bdr);flex-shrink:0;display:flex;align-items:center;background:var(--sb-bg);position:relative;z-index:1;text-decoration:none}
.hd-brand-inner{display:flex;align-items:center;gap:11px;overflow:hidden;width:100%;transition:all .28s var(--sb-ease)}
.hd-logo-wrap{width:38px;height:38px;border-radius:11px;background:var(--sb-bg2);border:1px solid var(--sb-bdr);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;box-shadow:0 2px 8px rgba(11,36,25,.08)}
.hd-logo-wrap img{width:100%;height:100%;object-fit:contain;padding:5px}
.hd-brand-text{overflow:hidden;min-width:0;transition:all .28s var(--sb-ease)}
.hd-brand-name{font-size:.875rem;font-weight:800;color:var(--sb-ink);white-space:nowrap;letter-spacing:-.2px;line-height:1.2}
.hd-brand-role{font-size:.58rem;font-weight:700;color:var(--sb-text);text-transform:uppercase;letter-spacing:1px;white-space:nowrap;margin-top:3px;display:flex;align-items:center;gap:5px}
.hd-brand-role::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--lime);flex-shrink:0;animation:sb-blink 2.5s ease-in-out infinite}
@keyframes sb-blink{0%,100%{opacity:1}50%{opacity:.2}}

/* SCROLL */
.hd-scroll-wrapper{flex:1;position:relative;overflow:hidden;display:flex}
.hd-scroll-area{flex:1;overflow-y:scroll;overflow-x:hidden;padding:8px 10px 20px;scrollbar-width:thin;scrollbar-color:var(--sb-scroll) transparent}
.hd-scroll-area::-webkit-scrollbar{width:4px}
.hd-scroll-area::-webkit-scrollbar-track{background:transparent;margin:8px 0}
.hd-scroll-area::-webkit-scrollbar-thumb{background:var(--sb-scroll);border-radius:99px}
.hd-scroll-area::-webkit-scrollbar-thumb:hover{background:var(--sb-text)}
.hd-scroll-wrapper::after{content:'';position:absolute;bottom:0;left:0;right:4px;height:36px;background:linear-gradient(transparent,var(--sb-bg));pointer-events:none;z-index:1}

/* SECTION LABELS */
.hd-section-label{display:flex;align-items:center;gap:8px;font-size:.58rem;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:var(--sb-sec);padding:18px 8px 6px;white-space:nowrap;overflow:hidden;transition:all .28s var(--sb-ease)}
.hd-section-label::after{content:'';flex:1;height:1px;background:var(--sb-bdr)}

/* NAV LINKS */
.hd-nav-link{display:flex;align-items:center;padding:8px 10px;margin-bottom:1px;border-radius:var(--sb-r);color:var(--sb-text);font-size:.82rem;font-weight:600;text-decoration:none;white-space:nowrap;overflow:hidden;position:relative;transition:all .2s var(--sb-ease)}
.hd-nav-link:hover{background:rgba(11,36,25,.04);color:var(--sb-ink);transform:translateX(2px);text-decoration:none}
.hd-nav-link.active{background:var(--f);color:#fff;font-weight:700;box-shadow:0 3px 14px rgba(11,36,25,.18)}
.hd-nav-link.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:2.5px;border-radius:0 3px 3px 0;background:var(--lime)}
.hd-nav-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;margin-right:10px;background:transparent;color:inherit;transition:all .22s var(--sb-ease)}
.hd-nav-link.active .hd-nav-icon{background:rgba(163,230,53,.14);color:var(--lime)}
.hd-nav-link:not(.active):hover .hd-nav-icon{background:rgba(11,36,25,.07);color:var(--sb-ink)}
[data-bs-theme="dark"] .hd-nav-link:not(.active):hover{background:rgba(163,230,53,.06);color:#c8f56a}
[data-bs-theme="dark"] .hd-nav-link:not(.active):hover .hd-nav-icon{background:rgba(163,230,53,.09);color:var(--lime)}
.hd-nav-text{flex:1;overflow:hidden;text-overflow:ellipsis;letter-spacing:-.1px;transition:all .28s var(--sb-ease)}

/* SUPPORT WIDGET */
.hd-support-widget{background:var(--f);border-radius:14px;padding:18px 16px 16px;margin:14px 2px 2px;position:relative;overflow:hidden;text-align:center;border:1px solid rgba(163,230,53,.12)}
.hd-support-widget .sw-dots{position:absolute;inset:0;pointer-events:none;background-image:radial-gradient(rgba(255,255,255,.045) 1px,transparent 1px);background-size:18px 18px;border-radius:14px}
.hd-support-widget::before{content:'';position:absolute;top:-40px;right:-40px;width:130px;height:130px;background:radial-gradient(circle,rgba(163,230,53,.14) 0%,transparent 65%);border-radius:50%;pointer-events:none}
.hd-support-widget::after{content:'';position:absolute;bottom:-24px;left:-24px;width:90px;height:90px;background:radial-gradient(circle,rgba(163,230,53,.09) 0%,transparent 65%);border-radius:50%;pointer-events:none}
.hd-support-widget h6{font-size:.82rem;font-weight:800;color:#fff;margin:0 0 4px;position:relative;z-index:1}
.hd-support-widget p{font-size:.7rem;color:rgba(255,255,255,.4);margin:0 0 12px;line-height:1.55;position:relative;z-index:1}
.hd-support-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;background:var(--lime);color:var(--f);font-size:.76rem;font-weight:800;padding:8px 18px;border-radius:9px;text-decoration:none;box-shadow:0 3px 12px rgba(163,230,53,.3);position:relative;z-index:1;transition:all .22s var(--sb-spring)}
.hd-support-btn:hover{background:#b8f059;color:var(--f);transform:translateY(-2px) scale(1.03);box-shadow:0 6px 18px rgba(163,230,53,.4)}

/* FOOTER */
.hd-footer{padding:10px 10px 14px;border-top:1px solid var(--sb-bdr);flex-shrink:0;background:var(--sb-bg)}
.hd-logout-btn{display:flex;align-items:center;padding:9px 10px;border-radius:var(--sb-r);color:#dc2626;font-size:.82rem;font-weight:700;text-decoration:none;width:100%;overflow:hidden;transition:all .2s var(--sb-ease)}
.hd-logout-btn:hover{background:rgba(220,38,38,.07);color:#b91c1c}
.hd-logout-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;margin-right:10px;background:rgba(220,38,38,.09);color:#dc2626;transition:all .22s var(--sb-ease)}
.hd-logout-btn:hover .hd-logout-icon{background:#dc2626;color:#fff;transform:scale(1.08)}
.hd-logout-label{white-space:nowrap;overflow:hidden;transition:all .28s var(--sb-ease)}

/* BACKDROP */
.sidebar-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1035;opacity:0;visibility:hidden;transition:.28s ease;backdrop-filter:blur(2px)}
.sidebar-backdrop.show{opacity:1;visibility:visible}

/* MAIN CONTENT OFFSET */
.main-content-wrapper,.main-content,main{margin-left:var(--sb-w);transition:margin-left .28s var(--sb-ease)}

/* DARK PATCHES */
[data-bs-theme="dark"] .hd-sidebar{background:var(--sb-bg);border-right-color:var(--sb-bdr)}
[data-bs-theme="dark"] .hd-brand{background:var(--sb-bg);border-bottom-color:var(--sb-bdr)}
[data-bs-theme="dark"] .hd-brand-name{color:var(--sb-ink)}
[data-bs-theme="dark"] .hd-toggle-btn{background:var(--sb-bg);border-color:var(--sb-bdr)}
[data-bs-theme="dark"] .hd-footer{background:var(--sb-bg);border-top-color:var(--sb-bdr)}
[data-bs-theme="dark"] .hd-scroll-wrapper::after{background:linear-gradient(transparent,var(--sb-bg))}
[data-bs-theme="dark"] .hd-nav-link.active{background:var(--fm);box-shadow:0 3px 14px rgba(0,0,0,.3)}
[data-bs-theme="dark"] .hd-logo-wrap{background:rgba(255,255,255,.04);border-color:var(--sb-bdr)}
</style>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<button class="hd-toggle-btn d-none d-lg-flex" id="sidebarToggle" title="Collapse sidebar" aria-label="Toggle sidebar">
    <i class="bi bi-layout-sidebar-reverse"></i>
</button>

<aside class="hd-sidebar" id="sidebar" role="navigation" aria-label="Main Navigation">

    <a href="<?= $base ?>/public/index.php" class="hd-brand">
        <div class="hd-brand-inner">
            <div class="hd-logo-wrap">
                <img src="<?= $assets ?>/images/people_logo.png" alt="<?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Logo' ?>">
            </div>
            <div class="hd-brand-text">
                <div class="hd-brand-name"><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'UMOJA SACCO' ?></div>
                <div class="hd-brand-role"><?= $role === 'member' ? ($_SESSION['reg_no'] ?? 'Member Portal') : 'Admin Panel' ?></div>
            </div>
        </div>
    </a>

    <div class="hd-scroll-wrapper">
        <div class="hd-scroll-area">

            <?php if ($role === 'member'): ?>
                <a href="<?= $base ?>/member/pages/dashboard.php" class="hd-nav-link <?= is_active('dashboard.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-grid-fill"></i></span><span class="hd-nav-text">Dashboard</span>
                </a>

                <span class="hd-section-label">Personal Finances</span>
                <a href="<?= $base ?>/member/pages/savings.php" class="hd-nav-link <?= is_active('savings.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-piggy-bank-fill"></i></span><span class="hd-nav-text">Savings</span>
                </a>
                <a href="<?= $base ?>/member/pages/shares.php" class="hd-nav-link <?= is_active('shares.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-pie-chart-fill"></i></span><span class="hd-nav-text">Shares Portfolio</span>
                </a>
                <a href="<?= $base ?>/member/pages/loans.php" class="hd-nav-link <?= is_active('loans.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-cash-stack"></i></span><span class="hd-nav-text">My Loans</span>
                </a>
                <a href="<?= $base ?>/member/pages/contributions.php" class="hd-nav-link <?= is_active('contributions.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-calendar-check-fill"></i></span><span class="hd-nav-text">Contributions</span>
                </a>

                <span class="hd-section-label">Welfare &amp; Solidarity</span>
                <a href="<?= $base ?>/member/pages/welfare.php" class="hd-nav-link <?= is_active('welfare.php', ['welfare_situations.php']) ?>">
                    <span class="hd-nav-icon"><i class="bi bi-heart-pulse-fill"></i></span><span class="hd-nav-text">Welfare Hub</span>
                </a>

                <span class="hd-section-label">Utilities</span>
                <a href="<?= $base ?>/member/pages/mpesa_request.php?type=savings" class="hd-nav-link <?= (isset($_GET['type']) && $_GET['type']==='savings') ? 'active' : '' ?>">
                    <span class="hd-nav-icon"><i class="bi bi-phone-vibrate-fill"></i></span><span class="hd-nav-text">Pay Via M-Pesa</span>
                </a>
                <a href="<?= $base ?>/member/pages/withdraw.php" class="hd-nav-link <?= is_active('withdraw.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-wallet2"></i></span><span class="hd-nav-text">Withdraw Funds</span>
                </a>
                <a href="<?= $base ?>/member/pages/transactions.php" class="hd-nav-link <?= is_active('transactions.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-arrow-left-right"></i></span><span class="hd-nav-text">All Transactions</span>
                </a>
                <a href="<?= $base ?>/member/pages/notifications.php" class="hd-nav-link <?= is_active('notifications.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-bell-fill"></i></span><span class="hd-nav-text">Notifications</span>
                </a>

                <span class="hd-section-label">Account</span>
                <a href="<?= $base ?>/member/pages/profile.php" class="hd-nav-link <?= is_active('profile.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-person-circle"></i></span><span class="hd-nav-text">My Profile</span>
                </a>
                <a href="<?= $base ?>/member/pages/settings.php" class="hd-nav-link <?= is_active('settings.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-gear-wide-connected"></i></span><span class="hd-nav-text">Settings</span>
                </a>

                <div class="hd-support-widget">
                    <div class="sw-dots"></div>
                    <h6>Need Help?</h6>
                    <p>Our support team is ready to assist you.</p>
                    <a href="<?= $base ?>/member/pages/support.php" class="hd-support-btn">
                        <i class="bi bi-headset"></i> Open Ticket
                    </a>
                </div>

            <?php elseif ($role !== 'guest'): ?>
                <a href="<?= $base ?>/admin/pages/dashboard.php" class="hd-nav-link <?= is_active('dashboard.php') ?>">
                    <span class="hd-nav-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="hd-nav-text">Admin Dashboard</span>
                </a>

                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('members')): ?>
                    <span class="hd-section-label">Member Management</span>
                    <a href="<?= $base ?>/admin/pages/member_onboarding.php" class="hd-nav-link <?= is_active('member_onboarding.php') ?>">
                        <span class="hd-nav-icon"><i class="bi bi-person-plus-fill"></i></span><span class="hd-nav-text">Member Onboarding</span>
                    </a>
                    <a href="<?= $base ?>/admin/pages/members.php" class="hd-nav-link <?= is_active('members.php', ['member_profile.php']) ?>">
                        <span class="hd-nav-icon"><i class="bi bi-people-fill"></i></span><span class="hd-nav-text">Members List</span>
                    </a>
                <?php endif; ?>

                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('payroll') || \USMS\Middleware\AuthMiddleware::hasModulePermission('settings')): ?>
                    <span class="hd-section-label">People &amp; Access</span>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('payroll')): ?>
                        <a href="<?= $base ?>/admin/pages/employees.php" class="hd-nav-link <?= is_active('employees.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-person-badge-fill"></i></span><span class="hd-nav-text">Employees</span>
                        </a>
                    <?php endif; ?>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('settings')): ?>
                        <a href="<?= $base ?>/admin/pages/users.php" class="hd-nav-link <?= is_active('users.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-person-badge"></i></span><span class="hd-nav-text">System Users</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/roles.php" class="hd-nav-link <?= is_active('roles.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-shield-lock-fill"></i></span><span class="hd-nav-text">Access Control</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('finance') || \USMS\Middleware\AuthMiddleware::hasModulePermission('payroll')): ?>
                    <span class="hd-section-label">Financial Management</span>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('finance')): ?>
                        <a href="<?= $base ?>/admin/pages/payments.php" class="hd-nav-link <?= is_active('payments.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-cash-coin"></i></span><span class="hd-nav-text">Cashier / Payments</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/revenue.php" class="hd-nav-link <?= is_active('revenue.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-graph-up-arrow"></i></span><span class="hd-nav-text">Revenue Inflow</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/expenses.php" class="hd-nav-link <?= is_active('expenses.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-receipt"></i></span><span class="hd-nav-text">Expense Tracker</span>
                        </a>
                    <?php endif; ?>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('payroll')): ?>
                        <a href="<?= $base ?>/admin/pages/payroll.php" class="hd-nav-link <?= is_active('payroll.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-wallet2"></i></span><span class="hd-nav-text">Payroll Processing</span>
                        </a>
                    <?php endif; ?>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('finance')): ?>
                        <a href="<?= $base ?>/admin/pages/transactions.php" class="hd-nav-link <?= is_active('transactions.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-journal-text"></i></span><span class="hd-nav-text">Live Ledger View</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/trial_balance.php" class="hd-nav-link <?= is_active('trial_balance.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-scale"></i></span><span class="hd-nav-text">Trial Balance</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('loans')): ?>
                    <span class="hd-section-label">Loans &amp; Credit</span>
                    <a href="<?= $base ?>/admin/pages/loans_reviews.php" class="hd-nav-link <?= is_active('loans_reviews.php') ?>">
                        <span class="hd-nav-icon"><i class="bi bi-bank"></i></span><span class="hd-nav-text">Loan Reviews</span>
                    </a>
                    <a href="<?= $base ?>/admin/pages/loans_payouts.php" class="hd-nav-link <?= is_active('loans_payouts.php') ?>">
                        <span class="hd-nav-icon"><i class="bi bi-cash-stack"></i></span><span class="hd-nav-text">Loan Payouts</span>
                    </a>
                <?php endif; ?>

                <span class="hd-section-label">Welfare Module</span>
                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('savings')): ?>
                    <a href="<?= $base ?>/admin/pages/welfare.php" class="hd-nav-link <?= is_active('welfare.php', ['welfare_cases.php','welfare_support.php']) ?>">
                        <span class="hd-nav-icon"><i class="bi bi-heart-pulse-fill"></i></span><span class="hd-nav-text">Welfare Management</span>
                    </a>
                <?php endif; ?>

                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('finance') || \USMS\Middleware\AuthMiddleware::hasModulePermission('shares')): ?>
                    <span class="hd-section-label">Investments &amp; Assets</span>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('finance')): ?>
                        <a href="<?= $base ?>/admin/pages/investments.php" class="hd-nav-link <?= is_active('investments.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-buildings-fill"></i></span><span class="hd-nav-text">Asset Portfolio</span>
                        </a>
                    <?php endif; ?>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('shares')): ?>
                        <a href="<?= $base ?>/admin/pages/admin_shares.php" class="hd-nav-link <?= is_active('admin_shares.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-pie-chart-fill"></i></span><span class="hd-nav-text">Equity &amp; Shares</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('finance')): ?>
                    <span class="hd-section-label">Reports &amp; Exports</span>
                    <a href="<?= $base ?>/admin/pages/reports.php" class="hd-nav-link <?= is_active('reports.php') ?>">
                        <span class="hd-nav-icon"><i class="bi bi-bar-chart-fill"></i></span><span class="hd-nav-text">Analytical Reports</span>
                    </a>
                    <a href="<?= $base ?>/admin/pages/statements.php" class="hd-nav-link <?= is_active('statements.php') ?>">
                        <span class="hd-nav-icon"><i class="bi bi-file-earmark-spreadsheet-fill"></i></span><span class="hd-nav-text">Account Statements</span>
                    </a>
                <?php endif; ?>

                <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('settings') || \USMS\Middleware\AuthMiddleware::hasModulePermission('support')): ?>
                    <span class="hd-section-label">System Maintenance</span>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('settings')): ?>
                        <a href="<?= $base ?>/admin/pages/live_monitor.php" class="hd-nav-link <?= is_active('live_monitor.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-display-fill"></i></span><span class="hd-nav-text">Live Monitor</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/monitor.php" class="hd-nav-link <?= is_active('monitor.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-phone-vibrate-fill"></i></span><span class="hd-nav-text">Transaction Monitor</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/system_health.php" class="hd-nav-link <?= is_active('system_health.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-activity"></i></span><span class="hd-nav-text">System Health</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/backups.php" class="hd-nav-link <?= is_active('backups.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-database-fill-check"></i></span><span class="hd-nav-text">Database Backups</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/audit_logs.php" class="hd-nav-link <?= is_active('audit_logs.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-list-check"></i></span><span class="hd-nav-text">Activity Logs</span>
                        </a>
                        <a href="<?= $base ?>/admin/pages/settings.php" class="hd-nav-link <?= is_active('settings.php') ?>">
                            <span class="hd-nav-icon"><i class="bi bi-sliders2"></i></span><span class="hd-nav-text">Global Settings</span>
                        </a>
                    <?php endif; ?>
                    <?php if (\USMS\Middleware\AuthMiddleware::hasModulePermission('support')): ?>
                        <a href="<?= $base ?>/admin/pages/support.php" class="hd-nav-link <?= is_active('support.php', ['support_view.php']) ?>">
                            <span class="hd-nav-icon"><i class="bi bi-headset"></i></span><span class="hd-nav-text">Tech Support</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>

    <div class="hd-footer">
        <a href="<?= $base ?>/public/logout.php" class="hd-logout-btn">
            <span class="hd-logout-icon"><i class="bi bi-box-arrow-right"></i></span>
            <span class="hd-logout-label">Sign Out</span>
        </a>
    </div>

</aside>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar   = document.getElementById('sidebar');
    const backdrop  = document.getElementById('sidebarBackdrop');
    const toggleBtn = document.getElementById('sidebarToggle');
    const mobileBtn = document.getElementById('mobileSidebarToggle');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            document.body.classList.toggle('sb-collapsed');
            const c = document.body.classList.contains('sb-collapsed');
            localStorage.setItem('sb_collapsed', c ? '1' : '0');
            toggleBtn.title = c ? 'Expand sidebar' : 'Collapse sidebar';
        });
    }

    if (localStorage.getItem('sb_collapsed') === '1') document.body.classList.add('sb-collapsed');

    if (mobileBtn) {
        mobileBtn.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            backdrop.classList.toggle('show');
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            backdrop.classList.remove('show');
        });
    }

    const active = sidebar?.querySelector('.hd-nav-link.active');
    const area   = sidebar?.querySelector('.hd-scroll-area');
    if (active && area) {
        // Only scroll if really needed, and use a more stable method
        const rect = active.getBoundingClientRect();
        const areaRect = area.getBoundingClientRect();
        if (rect.top < areaRect.top || rect.bottom > areaRect.bottom) {
            active.scrollIntoView({ block: 'nearest' });
        }
    }
});
</script>