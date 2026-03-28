<?php
/**
 * admin/welfare.php
 * Unified Welfare Management Suite
 * Combines Case Management, Crowdfunding, and Direct Pool Disbursements.
 */

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

// --- Dependencies ---
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/FinancialEngine.php';
require_once __DIR__ . '/../../inc/SupportTicketWidget.php';

// --- Security ---
\USMS\Middleware\AuthMiddleware::requireModulePermission('savings');
$layout = LayoutManager::create('admin');
$admin_id = $_SESSION['admin_id'];

$pageTitle = "Welfare Management";

// --- Data Aggregation ---
$filter = $_GET['filter'] ?? 'all';
$cases = $conn->query("SELECT c.*, m.full_name, m.phone, m.national_id, m.profile_pic FROM welfare_cases c JOIN members m ON c.related_member_id = m.member_id WHERE c.status = '$filter' OR '$filter' = 'all' ORDER BY c.created_at DESC");

$stats = $conn->query("SELECT 
    COUNT(CASE WHEN status='pending' THEN 1 END) as pending, 
    COUNT(CASE WHEN status='active' THEN 1 END) as active, 
    SUM(CASE WHEN status='disbursed' OR status='funded' THEN total_disbursed ELSE 0 END) as total_disbursed, 
    SUM(CASE WHEN status='active' OR status='funded' OR status='disbursed' THEN total_raised ELSE 0 END) as total_raised 
FROM welfare_cases")->fetch_assoc();

$engine = new FinancialEngine($conn);
$pool_balance = $engine->getWelfarePoolBalance();
?>
<?php $layout->header($pageTitle); ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">

<style>
/* ============================================================
   WELFARE MANAGEMENT — JAKARTA SANS + GLASSMORPHISM THEME
   ============================================================ */
*, *::before, *::after { box-sizing: border-box; }

:root {
    --forest:        #0d2b1f;
    --forest-mid:    #1a3d2b;
    --forest-light:  #234d36;
    --lime:          #b5f43c;
    --lime-soft:     #d6fb8a;
    --lime-glow:     rgba(181,244,60,0.18);
    --lime-glow-sm:  rgba(181,244,60,0.08);
    --surface:       #ffffff;
    --bg-muted:      #f5f8f6;
    --text-primary:  #0d1f15;
    --text-muted:    #6b7c74;
    --border:        rgba(13,43,31,0.07);
    --radius-sm:     8px;
    --radius-md:     14px;
    --radius-lg:     20px;
    --radius-xl:     28px;
    --shadow-sm:     0 2px 8px rgba(13,43,31,0.07);
    --shadow-md:     0 8px 28px rgba(13,43,31,0.11);
    --shadow-lg:     0 20px 60px rgba(13,43,31,0.16);
    --shadow-glow:   0 0 0 3px var(--lime-glow), 0 6px 24px rgba(181,244,60,0.15);
    --transition:    all 0.22s cubic-bezier(0.4,0,0.2,1);
}

body, *, input, select, textarea, button, .btn, table, th, td,
h1,h2,h3,h4,h5,h6,p,span,div,label,a,.modal,.offcanvas {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ── Hero Banner ── */
.welfare-hero {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 55%, #0e3522 100%);
    border-radius: var(--radius-xl);
    padding: 2.6rem 3rem 5rem;
    position: relative;
    overflow: hidden;
    color: #fff;
    margin-bottom: 0;
}
.welfare-hero::before {
    content: '';
    position: absolute; inset: 0;
    background:
        radial-gradient(ellipse 55% 70% at 95% 5%,  rgba(181,244,60,0.13) 0%, transparent 60%),
        radial-gradient(ellipse 35% 45% at 5%  95%, rgba(181,244,60,0.07) 0%, transparent 60%);
    pointer-events: none;
}
.welfare-hero .ring {
    position: absolute;
    border-radius: 50%;
    border: 1px solid rgba(181,244,60,0.1);
    pointer-events: none;
}
.welfare-hero .ring1 { width: 320px; height: 320px; top: -80px; right: -80px; }
.welfare-hero .ring2 { width: 500px; height: 500px; top: -160px; right: -160px; }
.welfare-hero h1 {
    font-weight: 800;
    letter-spacing: -0.03em;
    font-size: 2.2rem !important;
    line-height: 1.15;
    position: relative;
}
.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(181,244,60,0.12);
    border: 1px solid rgba(181,244,60,0.25);
    color: var(--lime-soft);
    border-radius: 100px;
    padding: 0.28rem 0.85rem;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    margin-bottom: 0.9rem;
    position: relative;
}
.hero-badge::before {
    content: '';
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--lime);
    animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.4)} }

/* ── KPI Cards ── */
.kpi-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.4rem 1.6rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-md);
    display: flex;
    align-items: center;
    gap: 1.1rem;
    position: relative;
    overflow: hidden;
    transition: var(--transition);
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
.kpi-card::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    opacity: 0;
    transition: var(--transition);
}
.kpi-card:hover::after { opacity: 1; }
.kpi-card.kpi-pool { background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%); border: none; }
.kpi-card.kpi-pool::after { background: linear-gradient(90deg, var(--lime), var(--lime-soft)); }
.kpi-card.kpi-active::after  { background: linear-gradient(90deg, #f59e0b, #fcd34d); }
.kpi-card.kpi-raised::after  { background: linear-gradient(90deg, #0ea5e9, #7dd3fc); }
.kpi-card.kpi-disbursed::after { background: linear-gradient(90deg, #22c55e, #86efac); }

.kpi-icon {
    width: 48px; height: 48px;
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.kpi-label {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    margin-bottom: 0.25rem;
}
.kpi-value {
    font-size: 1.55rem;
    font-weight: 800;
    letter-spacing: -0.04em;
    line-height: 1;
}
.kpi-pool .kpi-label { color: rgba(255,255,255,0.55); }
.kpi-pool .kpi-value { color: var(--lime); }
.kpi-pool .kpi-icon  { background: rgba(255,255,255,0.12); color: #fff; font-size: 1.4rem; }
.kpi-bg-icon {
    position: absolute;
    bottom: -10px; right: -10px;
    font-size: 5rem;
    opacity: 0.06;
    pointer-events: none;
}

/* ── Main Card ── */
.main-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-md);
    overflow: hidden;
}
.main-card-header {
    padding: 1rem 1.6rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
    flex-wrap: wrap;
    gap: 0.75rem;
}

/* Nav Tabs */
.nav-tabs-custom {
    display: flex;
    gap: 0.2rem;
    list-style: none;
    margin: 0; padding: 0;
    background: var(--bg-muted);
    border-radius: 100px;
    padding: 0.25rem;
}
.nav-tabs-custom .nav-link {
    border-radius: 100px;
    padding: 0.38rem 1.05rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-muted);
    text-decoration: none;
    transition: var(--transition);
    white-space: nowrap;
    border: none;
}
.nav-tabs-custom .nav-link:hover { color: var(--text-primary); background: rgba(13,43,31,0.05); }
.nav-tabs-custom .nav-link.active { background: var(--surface); color: var(--forest); box-shadow: var(--shadow-sm); }

/* New Case Btn */
.btn-forest {
    background: var(--forest);
    color: #fff !important;
    border: none;
    font-weight: 700;
    font-size: 0.82rem;
    transition: var(--transition);
}
.btn-forest:hover {
    background: var(--forest-light);
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
}
.btn-lime {
    background: var(--lime);
    color: var(--forest) !important;
    border: none;
    font-weight: 700;
    transition: var(--transition);
}
.btn-lime:hover { background: var(--lime-soft); box-shadow: var(--shadow-glow); }

/* ── Table ── */
.table-welfare {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 0;
}
.table-welfare thead th {
    background: #f5f8f6;
    color: var(--text-muted);
    font-size: 0.67rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 0.8rem 1rem;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
.table-welfare thead th:first-child { padding-left: 1.8rem; }
.table-welfare thead th:last-child  { padding-right: 1.8rem; }
.table-welfare tbody tr {
    border-bottom: 1px solid rgba(13,43,31,0.04);
    transition: var(--transition);
}
.table-welfare tbody tr:last-child { border-bottom: none; }
.table-welfare tbody tr:hover { background: #f0faf4; }
.table-welfare tbody td {
    padding: 0.85rem 1rem;
    vertical-align: middle;
    font-size: 0.875rem;
    color: var(--text-primary);
}
.table-welfare tbody td:first-child { padding-left: 1.8rem; }
.table-welfare tbody td:last-child  { padding-right: 1.8rem; }

/* Member Cell */
.member-cell { display: flex; align-items: center; gap: 0.75rem; }
.member-avatar-img {
    width: 38px; height: 38px;
    border-radius: 10px;
    object-fit: cover;
    flex-shrink: 0;
    border: 1.5px solid var(--border);
}
.member-name { font-weight: 700; font-size: 0.875rem; color: var(--text-primary); }
.member-sub  { font-size: 0.72rem; color: var(--text-muted); margin-top: 1px; }

/* Type cell */
.case-type  { font-weight: 700; font-size: 0.8rem; text-transform: capitalize; color: var(--text-primary); }
.case-title { font-size: 0.73rem; color: var(--text-muted); margin-top: 1px; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Progress */
.progress-cell .amount-main { font-weight: 800; font-size: 0.875rem; color: var(--forest); }
.progress-cell .amount-sub  { font-size: 0.72rem; color: var(--text-muted); margin-top: 1px; }
.mini-progress {
    height: 4px;
    border-radius: 100px;
    background: #e8f0eb;
    margin-top: 5px;
    overflow: hidden;
    max-width: 110px;
}
.mini-progress-bar { height: 100%; border-radius: 100px; background: var(--lime); transition: width 0.6s ease; }

/* Status badges */
.status-badge {
    display: inline-flex; align-items: center; gap: 0.3rem;
    border-radius: 100px; padding: 0.28rem 0.8rem;
    font-size: 0.67rem; font-weight: 800;
    letter-spacing: 0.07em; text-transform: uppercase; white-space: nowrap;
}
.status-badge::before { content:''; width:5px; height:5px; border-radius:50%; flex-shrink:0; }
.badge-pending   { background:#fffbeb; color:#b45309; border:1px solid rgba(245,158,11,0.2); }
.badge-pending::before   { background:#f59e0b; }
.badge-active    { background:#eff6ff; color:#1d4ed8; border:1px solid rgba(59,130,246,0.2); }
.badge-active::before    { background:#3b82f6; }
.badge-approved  { background:#f0fdf4; color:#166534; border:1px solid rgba(22,163,74,0.2); }
.badge-approved::before  { background:#22c55e; }
.badge-disbursed { background:#f5f3ff; color:#6d28d9; border:1px solid rgba(109,40,217,0.15); }
.badge-disbursed::before { background:#8b5cf6; }
.badge-funded    { background:#f0fdf4; color:#166534; border:1px solid rgba(22,163,74,0.2); }
.badge-funded::before    { background:#22c55e; }
.badge-rejected  { background:#fef2f2; color:#b91c1c; border:1px solid rgba(239,68,68,0.2); }
.badge-rejected::before  { background:#ef4444; }

/* Action buttons */
.action-zone { display:flex; align-items:center; justify-content:flex-end; gap:0.45rem; }
.btn-act-review {
    padding:0.32rem 0.9rem; border-radius:100px; font-size:0.75rem; font-weight:700;
    border:1.5px solid var(--forest); color:var(--forest); background:transparent;
    cursor:pointer; transition:var(--transition);
}
.btn-act-review:hover { background:var(--forest); color:#fff; }
.btn-act-disburse {
    padding:0.32rem 0.9rem; border-radius:100px; font-size:0.75rem; font-weight:700;
    border:none; background:#22c55e; color:#fff;
    cursor:pointer; transition:var(--transition);
}
.btn-act-disburse:hover { background:#16a34a; box-shadow:0 4px 14px rgba(34,197,94,0.3); }
.btn-act-donate {
    padding:0.32rem 0.9rem; border-radius:100px; font-size:0.75rem; font-weight:700;
    border:1.5px solid var(--lime); color:var(--forest); background:var(--lime-glow-sm);
    cursor:pointer; transition:var(--transition);
}
.btn-act-donate:hover { background:var(--lime); box-shadow:0 4px 12px rgba(181,244,60,0.3); }
.btn-act-details {
    padding:0.32rem 0.9rem; border-radius:100px; font-size:0.75rem; font-weight:700;
    border:1px solid var(--border); color:var(--text-muted); background:#f8faf9;
    cursor:pointer; transition:var(--transition);
}
.btn-act-details:hover { background:#f0faf4; color:var(--forest); border-color:rgba(13,43,31,0.15); }

/* Empty state */
.empty-state { text-align:center; padding:4rem 2rem; }
.empty-state-icon {
    width:68px; height:68px; border-radius:16px; background:#f5f8f6;
    display:flex; align-items:center; justify-content:center;
    font-size:1.6rem; color:#c4d4cb; margin:0 auto 1rem;
}

/* Animations */
@keyframes fadeIn  { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes slideUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }
.fade-in  { animation: fadeIn  0.5s ease-out both; }
.slide-up { animation: slideUp 0.5s cubic-bezier(0.4,0,0.2,1) both; }

/* ── Modal Shared ── */
.modal-content { border-radius: var(--radius-xl) !important; border: none !important; overflow: hidden; }
.modal-top-forest {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%);
    padding: 1.5rem 1.8rem;
    position: relative; overflow: hidden;
}
.modal-top-forest::before {
    content:''; position:absolute; inset:0;
    background: radial-gradient(ellipse at 90% 20%, rgba(181,244,60,0.18), transparent 60%);
}
.modal-top-forest h5 { color:#fff; font-weight:800; font-size:1rem; margin:0; position:relative; }
.modal-top-success { background: linear-gradient(135deg, #166534 0%, #16a34a 100%); padding:1.5rem 1.8rem; }
.modal-top-success h5 { color:#fff; font-weight:800; font-size:1rem; margin:0; }
.modal-top-danger { background: linear-gradient(135deg, #991b1b 0%, #dc2626 100%); padding:1.5rem 1.8rem; }
.modal-top-danger h5 { color:#fff; font-weight:800; font-size:1rem; margin:0; }

.modal-body-pad { padding: 1.6rem 1.8rem; background: #fff; }
.modal-footer-pad { padding: 0 1.8rem 1.6rem; background:#fff; display:flex; gap:0.65rem; justify-content:flex-end; }

.field-label {
    font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.09em; color: var(--text-muted); margin-bottom: 0.55rem;
    display: block;
}
.form-control-enh, .form-select-enh {
    border-radius: var(--radius-md);
    border: 1.5px solid rgba(13,43,31,0.1);
    font-size: 0.875rem; font-weight: 500; padding: 0.65rem 1rem;
    width: 100%; color: var(--text-primary); background: #f8faf9;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    transition: var(--transition);
    appearance: none;
}
.form-control-enh:focus, .form-select-enh:focus {
    outline:none; border-color:var(--lime); background:#fff; box-shadow:var(--shadow-glow);
}
.input-group-enh { display:flex; }
.input-group-enh .prefix {
    background:#f0f4f2; border:1.5px solid rgba(13,43,31,0.1); border-right:none;
    border-radius:var(--radius-md) 0 0 var(--radius-md);
    padding:0 1rem; font-size:0.82rem; font-weight:700; color:var(--text-muted);
    display:flex; align-items:center;
}
.input-group-enh .form-control-enh { border-radius:0 var(--radius-md) var(--radius-md) 0; }

/* Process modal amount */
.req-amount-box {
    background: #f5f8f6; border-radius: var(--radius-md);
    padding: 1.1rem; text-align:center; margin-bottom: 1.2rem;
    border: 1px solid var(--border);
}
.req-amount-label { font-size: 0.68rem; font-weight:800; text-transform:uppercase; letter-spacing:0.09em; color:var(--text-muted); margin-bottom:0.3rem; }
.req-amount-value { font-size: 1.8rem; font-weight:800; color:var(--forest); letter-spacing:-0.04em; }

/* Reject section */
.reject-section-inner {
    background:#fef2f2; border-radius:var(--radius-md); padding:1.2rem;
    border:1px solid rgba(239,68,68,0.15);
}

/* Dropdown */
.dropdown-menu {
    border-radius: var(--radius-md) !important;
    border: 1px solid var(--border) !important;
    box-shadow: var(--shadow-lg) !important;
    padding: 0.4rem !important;
}
.dropdown-item {
    border-radius: 8px; font-size:0.84rem; font-weight:600;
    padding: 0.58rem 0.9rem !important; color: var(--text-primary) !important;
    transition: var(--transition);
}
.dropdown-item:hover { background: #f0faf4 !important; }

@media (max-width:768px) {
    .welfare-hero { padding:2rem 1.5rem 4rem; }
    .welfare-hero h1 { font-size:1.7rem !important; }
    .main-card-header { flex-direction:column; align-items:flex-start; }
}
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

        <!-- Hero -->
        <div class="welfare-hero fade-in">
            <div class="ring ring1"></div>
            <div class="ring ring2"></div>
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="hero-badge">Welfare Suite</div>
                    <h1 class="mb-2">Welfare Management</h1>
                    <p class="mb-0" style="color:rgba(255,255,255,0.55); font-size:0.93rem; font-weight:500; position:relative;">
                        Case management, crowdfunding, and pool disbursements in one console.
                    </p>
                </div>
                <div class="col-lg-4 text-end mt-3 mt-lg-0" style="position:relative;">
                    <button class="btn btn-lime rounded-pill px-4 py-2 fw-bold" style="font-size:0.875rem;" data-bs-toggle="modal" data-bs-target="#newCaseModal">
                        <i class="bi bi-plus-lg me-2"></i>New Case
                    </button>
                </div>
            </div>
        </div>

        <div style="margin-top:-36px; position:relative; z-index:10;">

            <!-- KPI Row -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card kpi-pool slide-up" style="animation-delay:0.04s">
                        <div class="kpi-icon"><i class="bi bi-safe2"></i></div>
                        <div>
                            <div class="kpi-label">Welfare Pool</div>
                            <div class="kpi-value"><?= ksh($pool_balance) ?></div>
                        </div>
                        <i class="bi bi-wallet2 kpi-bg-icon" style="color:#fff;"></i>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card kpi-active slide-up" style="animation-delay:0.1s">
                        <div class="kpi-icon" style="background:#fffbeb; color:#b45309;"><i class="bi bi-hourglass-split"></i></div>
                        <div>
                            <div class="kpi-label" style="color:var(--text-muted);">Active Cases</div>
                            <div class="kpi-value" style="color:var(--text-primary);"><?= $stats['active'] ?></div>
                        </div>
                        <i class="bi bi-clipboard-heart kpi-bg-icon"></i>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card kpi-raised slide-up" style="animation-delay:0.16s">
                        <div class="kpi-icon" style="background:#eff6ff; color:#1d4ed8;"><i class="bi bi-heart-fill"></i></div>
                        <div>
                            <div class="kpi-label" style="color:var(--text-muted);">Total Donated</div>
                            <div class="kpi-value" style="color:var(--text-primary);"><?= ksh($stats['total_raised']) ?></div>
                        </div>
                        <i class="bi bi-gift kpi-bg-icon"></i>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card kpi-disbursed slide-up" style="animation-delay:0.22s">
                        <div class="kpi-icon" style="background:#f0fdf4; color:#166534;"><i class="bi bi-check-circle-fill"></i></div>
                        <div>
                            <div class="kpi-label" style="color:var(--text-muted);">Total Disbursed</div>
                            <div class="kpi-value" style="color:var(--text-primary);"><?= ksh($stats['total_disbursed']) ?></div>
                        </div>
                        <i class="bi bi-send-check kpi-bg-icon"></i>
                    </div>
                </div>
            </div>

            <?php flash_render(); ?>

            <?php render_support_ticket_widget($conn, ['welfare'], 'Welfare & Benefits'); ?>

            <!-- Main Table Card -->
            <div class="main-card slide-up" style="animation-delay:0.28s">
                <div class="main-card-header">
                    <ul class="nav-tabs-custom">
                        <li class="nav-item"><a class="nav-link <?= $filter=='all'?'active':'' ?>"      href="?filter=all">All Cases</a></li>
                        <li class="nav-item"><a class="nav-link <?= $filter=='pending'?'active':'' ?>"  href="?filter=pending">Review Queue
                            <?php if((int)$stats['pending'] > 0): ?>
                                <span style="background:#f59e0b; color:#fff; border-radius:100px; padding:0.1rem 0.45rem; font-size:0.62rem; font-weight:800; margin-left:0.3rem;"><?= $stats['pending'] ?></span>
                            <?php endif; ?>
                        </a></li>
                        <li class="nav-item"><a class="nav-link <?= $filter=='active'?'active':'' ?>"   href="?filter=active">Crowdfunding</a></li>
                    </ul>

                    <div style="display:flex; align-items:center; gap:0.6rem;">
                        <span style="background:#f0faf4; color:#166534; border:1px solid rgba(22,163,74,0.15); border-radius:100px; padding:0.2rem 0.7rem; font-size:0.7rem; font-weight:800;">
                            <?= $cases->num_rows ?> cases
                        </span>
                        <button class="btn btn-forest rounded-pill px-4 py-2 fw-bold" style="font-size:0.82rem;" data-bs-toggle="modal" data-bs-target="#newCaseModal">
                            <i class="bi bi-plus-lg me-1"></i> New Case
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table-welfare">
                        <thead>
                            <tr>
                                <th>Beneficiary</th>
                                <th>Case Info</th>
                                <th>Funding</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($cases->num_rows > 0): while($row = $cases->fetch_assoc()):
                                $sc = match($row['status']) {
                                    'pending'  => 'badge-pending',
                                    'active'   => 'badge-active',
                                    'approved' => 'badge-approved',
                                    'disbursed'=> 'badge-disbursed',
                                    'funded'   => 'badge-funded',
                                    'rejected' => 'badge-rejected',
                                    default    => ''
                                };
                                $avatar = !empty($row['profile_pic'])
                                    ? 'data:image/jpeg;base64,'.base64_encode($row['profile_pic'])
                                    : BASE_URL.'/public/assets/images/default_user.png';
                                $pct = ($row['target_amount'] > 0)
                                    ? min(100, round(($row['total_raised'] / $row['target_amount']) * 100))
                                    : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="member-cell">
                                        <img src="<?= $avatar ?>" class="member-avatar-img" alt="">
                                        <div>
                                            <div class="member-name"><?= htmlspecialchars($row['full_name']) ?></div>
                                            <div class="member-sub"><?= htmlspecialchars($row['phone']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="case-type"><?= ucfirst(htmlspecialchars($row['case_type'])) ?></div>
                                    <div class="case-title" title="<?= htmlspecialchars($row['title']) ?>"><?= htmlspecialchars($row['title']) ?></div>
                                </td>
                                <td>
                                    <div class="progress-cell">
                                        <?php if($row['target_amount'] > 0): ?>
                                            <div class="amount-main"><?= ksh($row['total_raised']) ?></div>
                                            <div class="amount-sub">Goal: <?= ksh($row['target_amount']) ?></div>
                                            <div class="mini-progress">
                                                <div class="mini-progress-bar" style="width:<?= $pct ?>%"></div>
                                            </div>
                                        <?php else: ?>
                                            <div class="amount-main" style="color:var(--text-primary); font-size:0.78rem; font-weight:700;">Pool Grant</div>
                                            <div class="amount-sub">Req: <?= ksh($row['requested_amount']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?= $sc ?>"><?= ucfirst($row['status']) ?></span>
                                </td>
                                <td>
                                    <div class="action-zone">
                                        <?php if($row['status'] === 'pending'): ?>
                                            <button class="btn-act-review" onclick='openProcessModal(<?= json_encode($row) ?>)'>
                                                <i class="bi bi-eye me-1" style="font-size:0.7rem;"></i>Review
                                            </button>
                                        <?php elseif($row['status'] === 'approved'): ?>
                                            <form method="POST" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="disburse_case">
                                                <input type="hidden" name="case_id" value="<?= $row['case_id'] ?>">
                                                <button type="submit" class="btn-act-disburse" onclick="return confirm('Disburse funds to member wallet?')">
                                                    <i class="bi bi-send me-1" style="font-size:0.7rem;"></i>Disburse
                                                </button>
                                            </form>
                                        <?php elseif($row['status'] === 'active' || $row['status'] === 'funded'): ?>
                                            <?php if($row['status'] === 'active'): ?>
                                                <button class="btn-act-donate" onclick='openDonationModal(<?= json_encode($row) ?>)'>
                                                    <i class="bi bi-heart me-1" style="font-size:0.7rem;"></i>Donate
                                                </button>
                                            <?php endif; ?>
                                            <form method="POST" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="disburse_donations">
                                                <input type="hidden" name="case_id" value="<?= $row['case_id'] ?>">
                                                <button type="submit" class="btn-act-disburse" onclick="return confirm('Close case and disburse collections?')">
                                                    <i class="bi bi-check-circle me-1" style="font-size:0.7rem;"></i>Close & Pay
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn-act-details" onclick='openViewer(<?= json_encode($row) ?>)'>
                                                Details
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="bi bi-clipboard-heart"></i></div>
                                        <h5 style="font-weight:800; font-size:0.95rem; color:var(--text-primary); margin-bottom:0.3rem;">No Cases Found</h5>
                                        <p style="font-size:0.82rem; color:var(--text-muted); margin:0;">No welfare cases match the current filter.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /overlap -->


        <!-- ═══════════════════════════
             MODAL: NEW CASE
        ═══════════════════════════ -->
        <div class="modal fade" id="newCaseModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
                <form method="POST" class="modal-content">
                    <input type="hidden" name="action" value="create_case">
                    <?= csrf_field() ?>

                    <div class="modal-top-forest d-flex justify-content-between align-items-center">
                        <div style="display:flex; align-items:center; gap:0.65rem;">
                            <div style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
                                <i class="bi bi-clipboard-plus" style="color:#fff; font-size:0.9rem;"></i>
                            </div>
                            <h5>Log Welfare Case</h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body-pad">
                        <div class="mb-3">
                            <label class="field-label">Beneficiary Member</label>
                            <select name="member_id" class="form-select-enh" required>
                                <option value="">— Choose Member —</option>
                                <?php 
                                $mems = $conn->query("SELECT member_id, full_name, national_id FROM members WHERE status='active'");
                                while($m = $mems->fetch_assoc()): ?>
                                    <option value="<?= $m['member_id'] ?>"><?= htmlspecialchars($m['full_name']) ?> (<?= $m['national_id'] ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="field-label">Case Type</label>
                            <select name="type" class="form-select-enh" required>
                                <option value="sickness">🏥 Sickness</option>
                                <option value="bereavement">🕊️ Bereavement</option>
                                <option value="education">📚 Education</option>
                                <option value="accident">⚠️ Accident</option>
                                <option value="other">📎 Other</option>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="field-label">Funding Goal <span style="color:var(--text-muted); text-transform:none; letter-spacing:0; font-weight:500;">(crowdfund)</span></label>
                                <div class="input-group-enh">
                                    <span class="prefix">KES</span>
                                    <input type="number" name="target_amount" class="form-control-enh" placeholder="Optional">
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="field-label">Pool Request <span style="color:var(--text-muted); text-transform:none; letter-spacing:0; font-weight:500;">(grant)</span></label>
                                <div class="input-group-enh">
                                    <span class="prefix">KES</span>
                                    <input type="number" name="requested_amount" class="form-control-enh" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="field-label">Case Title</label>
                            <input type="text" name="title" class="form-control-enh" required placeholder="Brief descriptive title">
                        </div>
                        <div class="mb-1">
                            <label class="field-label">Description</label>
                            <textarea name="description" class="form-control-enh" rows="3" required
                                      placeholder="Provide full details of the welfare case..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer-pad" style="padding-top:0.5rem;">
                        <button type="button" style="background:none; border:1.5px solid var(--border); border-radius:100px; padding:0.55rem 1.2rem; font-weight:700; font-size:0.85rem; cursor:pointer; color:var(--text-muted);" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" style="flex:1; background:var(--forest); color:#fff; border:none; border-radius:100px; padding:0.6rem 1.6rem; font-weight:800; font-size:0.875rem; cursor:pointer; box-shadow:var(--shadow-md);">
                            Submit for Review
                        </button>
                    </div>
                </form>
            </div>
        </div>


        <!-- ═══════════════════════════
             MODAL: PROCESS (Approve/Reject)
        ═══════════════════════════ -->
        <div class="modal fade" id="processModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
                <form method="POST" class="modal-content">
                    <input type="hidden" name="action" value="approve_case" id="proc_action">
                    <?= csrf_field() ?>
                    <input type="hidden" name="case_id" id="proc_case_id">

                    <div class="modal-top-success d-flex justify-content-between align-items-center">
                        <div style="display:flex; align-items:center; gap:0.65rem;">
                            <div style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,0.18);display:flex;align-items:center;justify-content:center;">
                                <i class="bi bi-shield-check" style="color:#fff; font-size:0.9rem;"></i>
                            </div>
                            <h5>Review Case</h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body-pad">
                        <!-- Approval section -->
                        <div id="pool_approval_section">
                            <div class="req-amount-box">
                                <div class="req-amount-label">Requested Amount</div>
                                <div class="req-amount-value" id="proc_req_amt">KES 0.00</div>
                            </div>
                            <div class="mb-3">
                                <label class="field-label">Approve for Pool Payout</label>
                                <div class="input-group-enh">
                                    <span class="prefix">KES</span>
                                    <input type="number" name="approved_amount" id="proc_app_amt" class="form-control-enh">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="field-label">Admin Notes</label>
                                <textarea name="admin_notes" class="form-control-enh" rows="2" placeholder="Optional internal note..."></textarea>
                            </div>
                            <button type="submit" style="width:100%; background:#22c55e; color:#fff; border:none; border-radius:100px; padding:0.65rem; font-weight:800; font-size:0.875rem; cursor:pointer; box-shadow:0 4px 14px rgba(34,197,94,0.3); margin-bottom:0.75rem;">
                                <i class="bi bi-check-circle-fill me-2"></i>Confirm Pool Payout
                            </button>
                            <div style="text-align:center;">
                                <button type="button" style="background:none; border:none; color:#dc2626; font-size:0.8rem; font-weight:700; cursor:pointer; text-decoration:underline; text-underline-offset:3px;" onclick="switchReject()">
                                    Reject this case instead
                                </button>
                            </div>
                        </div>

                        <!-- Reject section -->
                        <div id="reject_section" class="d-none">
                            <div class="reject-section-inner mb-4">
                                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.75rem;">
                                    <i class="bi bi-exclamation-triangle-fill" style="color:#dc2626;"></i>
                                    <span style="font-weight:800; font-size:0.85rem; color:#b91c1c;">Rejecting Case</span>
                                </div>
                                <label class="field-label" style="color:#b91c1c;">Rejection Reason</label>
                                <textarea name="reason" class="form-control-enh" rows="3" placeholder="Provide reason for rejection..."></textarea>
                            </div>
                            <div style="display:flex; gap:0.65rem;">
                                <button type="button" style="flex:1; background:none; border:1.5px solid var(--border); border-radius:100px; padding:0.55rem; font-weight:700; font-size:0.85rem; cursor:pointer; color:var(--text-muted);" onclick="switchApprove()">Back</button>
                                <button type="button" style="flex:2; background:#dc2626; color:#fff; border:none; border-radius:100px; padding:0.6rem; font-weight:800; font-size:0.875rem; cursor:pointer; box-shadow:0 4px 14px rgba(220,38,38,0.3);" onclick="confirmReject()">
                                    Confirm Rejection
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>


        <!-- ═══════════════════════════
             MODAL: DONATION
        ═══════════════════════════ -->
        <div class="modal fade" id="donationModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
                <form method="POST" class="modal-content">
                    <input type="hidden" name="action" value="record_donation">
                    <?= csrf_field() ?>
                    <input type="hidden" name="case_id" id="don_case_id">

                    <div class="modal-top-forest d-flex justify-content-between align-items-center">
                        <div style="display:flex; align-items:center; gap:0.65rem;">
                            <div style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
                                <i class="bi bi-heart-fill" style="color:var(--lime); font-size:0.9rem;"></i>
                            </div>
                            <h5>Record Donation</h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body-pad">
                        <div class="mb-3">
                            <label class="field-label">Donor Member <span style="color:var(--text-muted); text-transform:none; letter-spacing:0; font-weight:500;">(optional)</span></label>
                            <select name="donor_member_id" class="form-select-enh">
                                <option value="0">— Anonymous / External —</option>
                                <?php 
                                $mems2 = $conn->query("SELECT member_id, full_name FROM members WHERE status='active'");
                                while($m = $mems2->fetch_assoc()): ?>
                                    <option value="<?= $m['member_id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="field-label">Donation Amount</label>
                            <div class="input-group-enh">
                                <span class="prefix">KES</span>
                                <input type="number" name="amount" class="form-control-enh" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="mb-1">
                            <label class="field-label">Reference / Notes</label>
                            <input type="text" name="reference_no" class="form-control-enh" placeholder="M-Pesa Ref or Note">
                        </div>
                    </div>

                    <div class="modal-footer-pad" style="padding-top:0.5rem;">
                        <button type="button" style="background:none; border:1.5px solid var(--border); border-radius:100px; padding:0.55rem 1.2rem; font-weight:700; font-size:0.85rem; cursor:pointer; color:var(--text-muted);" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" style="flex:1; background:var(--forest); color:#fff; border:none; border-radius:100px; padding:0.6rem 1.6rem; font-weight:800; font-size:0.875rem; cursor:pointer; box-shadow:var(--shadow-md);">
                            <i class="bi bi-heart me-2"></i>Record Donation
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openProcessModal(data) {
    document.getElementById('proc_case_id').value = data.case_id;
    document.getElementById('proc_req_amt').innerText = 'KES ' + new Intl.NumberFormat('en-KE', {minimumFractionDigits:2}).format(data.requested_amount);
    document.getElementById('proc_app_amt').value = data.requested_amount;
    // Reset to approval view
    document.getElementById('pool_approval_section').classList.remove('d-none');
    document.getElementById('reject_section').classList.add('d-none');
    document.getElementById('proc_action').value = 'approve_case';
    new bootstrap.Modal(document.getElementById('processModal')).show();
}
function openDonationModal(data) {
    document.getElementById('don_case_id').value = data.case_id;
    new bootstrap.Modal(document.getElementById('donationModal')).show();
}
function switchReject() {
    document.getElementById('pool_approval_section').classList.add('d-none');
    document.getElementById('reject_section').classList.remove('d-none');
    document.getElementById('proc_action').value = 'reject';
}
function switchApprove() {
    document.getElementById('reject_section').classList.add('d-none');
    document.getElementById('pool_approval_section').classList.remove('d-none');
    document.getElementById('proc_action').value = 'approve_case';
}
function confirmReject() {
    if (confirm('Are you sure you want to reject this welfare case?')) {
        document.getElementById('processModal').querySelector('form').submit();
    }
}
</script>
</body>
</html>