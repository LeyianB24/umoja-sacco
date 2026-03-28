<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/SupportTicketWidget.php';
require_once __DIR__ . '/../../inc/functions.php';

$pageTitle = "Investment Management";

require_permission();
Auth::requireAdmin();
$layout = LayoutManager::create('admin');
?>
<?php $layout->header($pageTitle); ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">

<style>
/* ============================================================
   INVESTMENT MANAGEMENT — JAKARTA SANS + GLASSMORPHISM THEME
   ============================================================ */
*, *::before, *::after { box-sizing: border-box; }

:root {
    --forest:       #0d2b1f;
    --forest-mid:   #1a3d2b;
    --forest-light: #234d36;
    --lime:         #b5f43c;
    --lime-soft:    #d6fb8a;
    --lime-glow:    rgba(181,244,60,0.18);
    --lime-glow-sm: rgba(181,244,60,0.08);
    --surface:      #ffffff;
    --bg-muted:     #f5f8f6;
    --text-primary: #0d1f15;
    --text-muted:   #6b7c74;
    --border:       rgba(13,43,31,0.07);
    --radius-sm:    8px;
    --radius-md:    14px;
    --radius-lg:    20px;
    --radius-xl:    28px;
    --shadow-sm:    0 2px 8px rgba(13,43,31,0.07);
    --shadow-md:    0 8px 28px rgba(13,43,31,0.11);
    --shadow-lg:    0 20px 60px rgba(13,43,31,0.16);
    --shadow-glow:  0 0 0 3px var(--lime-glow), 0 6px 24px rgba(181,244,60,0.15);
    --transition:   all 0.22s cubic-bezier(0.4,0,0.2,1);
}

body,*,input,select,textarea,button,.btn,table,th,td,
h1,h2,h3,h4,h5,h6,p,span,div,label,a,.modal,.offcanvas {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ── Hero ── */
.hp-hero {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 55%, #0e3522 100%);
    border-radius: var(--radius-xl);
    padding: 2.6rem 3rem 5rem;
    position: relative; overflow: hidden; color: #fff; margin-bottom: 0;
}
.hp-hero::before {
    content:''; position:absolute; inset:0;
    background:
        radial-gradient(ellipse 55% 70% at 95% 5%,  rgba(181,244,60,0.13) 0%, transparent 60%),
        radial-gradient(ellipse 35% 45% at 5%  95%, rgba(181,244,60,0.06) 0%, transparent 60%);
    pointer-events:none;
}
.hp-hero .ring { position:absolute; border-radius:50%; border:1px solid rgba(181,244,60,0.1); pointer-events:none; }
.hp-hero .ring1 { width:320px;height:320px;top:-80px;right:-80px; }
.hp-hero .ring2 { width:500px;height:500px;top:-160px;right:-160px; }
.hp-hero h1 { font-weight:800; letter-spacing:-0.03em; font-size:2.2rem !important; line-height:1.15; position:relative; }
.hero-badge {
    display:inline-flex; align-items:center; gap:0.45rem;
    background:rgba(181,244,60,0.12); border:1px solid rgba(181,244,60,0.25);
    color:var(--lime-soft); border-radius:100px;
    padding:0.28rem 0.85rem; font-size:0.68rem; font-weight:700;
    letter-spacing:0.12em; text-transform:uppercase; margin-bottom:0.9rem; position:relative;
}
.hero-badge::before { content:''; width:6px;height:6px; border-radius:50%; background:var(--lime); animation:pulse-dot 2s ease-in-out infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.4)} }
.hero-sub-links a { color:rgba(255,255,255,0.6); font-size:0.78rem; font-weight:600; text-decoration:none; transition:var(--transition); }
.hero-sub-links a:hover { color:var(--lime); }
.hero-sub-links i { color:var(--lime); }

/* ── KPI Cards ── */
.glass-stat {
    background:var(--surface); border-radius:var(--radius-lg);
    padding:1.5rem 1.7rem; border:1px solid var(--border);
    box-shadow:var(--shadow-md); position:relative; overflow:hidden;
    transition:var(--transition);
}
.glass-stat:hover { transform:translateY(-3px); box-shadow:var(--shadow-lg); }
.glass-stat::after {
    content:''; position:absolute; bottom:0; left:0; right:0; height:3px;
    border-radius:0 0 var(--radius-lg) var(--radius-lg); opacity:0; transition:var(--transition);
}
.glass-stat:hover::after { opacity:1; }
.glass-stat.s1::after { background:linear-gradient(90deg,var(--lime),var(--lime-soft)); }
.glass-stat.s2::after { background:linear-gradient(90deg,#22c55e,#86efac); }
.glass-stat.s3::after { background:linear-gradient(90deg,var(--lime),var(--lime-soft)); }
.stat-card-dark {
    background:linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%) !important;
    border:none !important;
}
.stat-card-accent {
    background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%) !important;
    border:1px solid rgba(22,163,74,0.12) !important;
}
.stat-icon-wrap {
    width:48px;height:48px; border-radius:var(--radius-sm);
    display:flex;align-items:center;justify-content:center; font-size:1.2rem; margin-bottom:1rem;
}
.kpi-label { font-size:0.67rem; font-weight:800; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:0.3rem; }
.kpi-value { font-size:1.6rem; font-weight:800; letter-spacing:-0.04em; line-height:1; margin-bottom:0.4rem; }
.kpi-sub   { font-size:0.73rem; font-weight:600; }
.kpi-bg-icon { position:absolute; bottom:-12px;right:-8px; font-size:5.5rem; opacity:0.06; pointer-events:none; }

/* ── Filter Bar ── */
.filter-pill-wrap { display:flex; flex-wrap:wrap; gap:0.35rem; }
.filter-pill {
    padding:0.35rem 1rem; border-radius:100px; font-size:0.78rem; font-weight:700;
    border:1.5px solid var(--border); color:var(--text-muted); background:var(--surface);
    text-decoration:none; transition:var(--transition); white-space:nowrap;
}
.filter-pill:hover { border-color:rgba(13,43,31,0.18); color:var(--text-primary); background:#f0faf4; }
.filter-pill.active { background:var(--forest); color:#fff !important; border-color:var(--forest); box-shadow:var(--shadow-sm); }
.search-wrap { position:relative; }
.search-wrap i { position:absolute; top:50%;left:14px; transform:translateY(-50%); color:var(--text-muted); font-size:0.8rem; pointer-events:none; }
.search-wrap input {
    padding-left:2.4rem; border-radius:100px; border:1.5px solid var(--border);
    background:var(--bg-muted); font-size:0.85rem; font-weight:500;
    height:38px; width:100%; color:var(--text-primary); transition:var(--transition);
}
.search-wrap input:focus { outline:none; border-color:var(--lime); background:#fff; box-shadow:var(--shadow-glow); }

/* ── Asset Cards ── */
.asset-card {
    background:var(--surface); border-radius:var(--radius-lg);
    border:1px solid var(--border); box-shadow:var(--shadow-sm);
    padding:1.5rem; transition:var(--transition); height:100%;
    display:flex; flex-direction:column;
}
.asset-card:hover { transform:translateY(-4px); box-shadow:var(--shadow-lg); border-color:rgba(13,43,31,0.1); }

.asset-card-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem; }
.asset-icon-box {
    width:50px;height:50px; border-radius:12px;
    background:var(--forest); color:var(--lime);
    display:flex;align-items:center;justify-content:center; font-size:1.3rem; flex-shrink:0;
}
.asset-badges { display:flex; flex-direction:column; align-items:flex-end; gap:0.3rem; }

.asset-title { font-weight:800; font-size:0.95rem; color:var(--text-primary); margin-bottom:0.2rem; letter-spacing:-0.01em; }
.asset-category { font-size:0.68rem; font-weight:800; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); margin-bottom:1rem; }

/* Status pills */
.st-pill {
    display:inline-flex;align-items:center;gap:0.3rem; border-radius:100px;
    padding:0.22rem 0.7rem; font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:0.07em;
}
.st-pill::before { content:''; width:5px;height:5px; border-radius:50%; flex-shrink:0; }
.st-active      { background:#f0fdf4; color:#166534; border:1px solid rgba(22,163,74,0.18); }
.st-active::before { background:#22c55e; }
.st-maintenance { background:#fffbeb; color:#b45309; border:1px solid rgba(245,158,11,0.18); }
.st-maintenance::before { background:#f59e0b; }
.st-sold, .st-disposed { background:#fef2f2; color:#b91c1c; border:1px solid rgba(239,68,68,0.18); }
.st-sold::before, .st-disposed::before { background:#ef4444; }

.viability-pill {
    display:inline-flex;align-items:center;gap:0.3rem; border-radius:100px;
    padding:0.2rem 0.6rem; font-size:0.62rem; font-weight:800; text-transform:uppercase; letter-spacing:0.07em;
}
.v-viable        { background:#f0fdf4; color:#166534; border:1px solid rgba(22,163,74,0.15); }
.v-underperform  { background:#fffbeb; color:#b45309; border:1px solid rgba(245,158,11,0.15); }
.v-loss          { background:#fef2f2; color:#b91c1c; border:1px solid rgba(239,68,68,0.15); }
.v-pending       { background:#f5f8f6; color:var(--text-muted); border:1px solid var(--border); }

.suggestion-pill {
    display:inline-flex;align-items:center;gap:0.25rem; border-radius:100px;
    padding:0.18rem 0.6rem; font-size:0.6rem; font-weight:700;
}

/* Metric row */
.metric-grid { display:grid; grid-template-columns:1fr 1fr; gap:0.6rem; margin-bottom:1rem; }
.metric-box {
    background:var(--bg-muted); border-radius:var(--radius-sm);
    padding:0.75rem 0.9rem; border:1px solid var(--border);
}
.metric-box-label { font-size:0.6rem; font-weight:800; text-transform:uppercase; letter-spacing:0.09em; color:var(--text-muted); margin-bottom:0.25rem; }
.metric-box-value { font-size:0.9rem; font-weight:800; letter-spacing:-0.02em; }

/* P&L row */
.pnl-row { display:flex; justify-content:space-between; align-items:center; padding:0.45rem 0; border-bottom:1px solid rgba(13,43,31,0.05); }
.pnl-row:last-child { border-bottom:none; }
.pnl-label { font-size:0.78rem; color:var(--text-muted); font-weight:500; }
.pnl-value { font-size:0.82rem; font-weight:800; }

/* Progress */
.progress-label-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem; }
.progress-label     { font-size:0.7rem; color:var(--text-muted); font-weight:600; }
.progress-pct       { font-size:0.78rem; font-weight:800; }
.asset-progress { height:6px; border-radius:100px; background:#e8f0eb; overflow:hidden; margin-bottom:0.4rem; }
.asset-progress-bar { height:100%; border-radius:100px; transition:width 0.7s ease; }
.progress-meta { display:flex; justify-content:space-between; }
.progress-meta span { font-size:0.65rem; color:var(--text-muted); font-weight:600; }

/* Asset card footer */
.asset-actions { margin-top:auto; padding-top:1rem; border-top:1px solid var(--border); display:flex; gap:0.5rem; }
.btn-act-icon {
    width:34px;height:34px; border-radius:var(--radius-sm); flex-shrink:0;
    display:flex;align-items:center;justify-content:center; font-size:0.8rem;
    border:1.5px solid var(--border); background:transparent; cursor:pointer; transition:var(--transition); color:var(--text-muted);
}
.btn-act-icon:hover { background:#f0faf4; border-color:rgba(13,43,31,0.15); color:var(--forest); }
.btn-act-icon.danger:hover { background:#fef2f2; border-color:rgba(239,68,68,0.2); color:#dc2626; }
.btn-act-main {
    flex:1; padding:0.42rem 0.9rem; border-radius:var(--radius-sm); font-size:0.78rem; font-weight:700;
    border:1.5px solid var(--forest); color:var(--forest); background:transparent;
    cursor:pointer; transition:var(--transition); white-space:nowrap;
}
.btn-act-main:hover { background:var(--forest); color:#fff; }
.btn-act-ledger {
    width:34px;height:34px; border-radius:var(--radius-sm); flex-shrink:0;
    display:flex;align-items:center;justify-content:center; font-size:0.8rem;
    border:1.5px solid var(--border); background:transparent; cursor:pointer; transition:var(--transition); color:var(--text-muted);
    text-decoration:none;
}
.btn-act-ledger:hover { background:#f0faf4; color:var(--forest); border-color:rgba(13,43,31,0.15); }
.btn-act-disposed {
    flex:1; padding:0.42rem 0.9rem; border-radius:var(--radius-sm); font-size:0.78rem; font-weight:700;
    border:1px solid var(--border); color:var(--text-muted); background:var(--bg-muted);
    cursor:not-allowed; opacity:0.6;
}

/* Empty state */
.empty-state { text-align:center; padding:5rem 2rem; }
.empty-state-icon { width:72px;height:72px; border-radius:18px; background:#f5f8f6; display:flex;align-items:center;justify-content:center; font-size:1.8rem; color:#c4d4cb; margin:0 auto 1.1rem; }

/* Buttons */
.btn-lime  { background:var(--lime); color:var(--forest) !important; border:none; font-weight:700; transition:var(--transition); }
.btn-lime:hover  { background:var(--lime-soft); box-shadow:var(--shadow-glow); }
.btn-forest { background:var(--forest); color:#fff !important; border:none; font-weight:700; transition:var(--transition); }
.btn-forest:hover { background:var(--forest-light); box-shadow:var(--shadow-md); }

/* Animations */
@keyframes fadeIn  { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes slideUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }
.fade-in  { animation:fadeIn  0.5s ease-out both; }
.slide-up { animation:slideUp 0.5s cubic-bezier(0.4,0,0.2,1) both; }

/* ── Modal Shared ── */
.modal-content { border-radius:var(--radius-xl) !important; border:none !important; overflow:hidden; }
.modal-top-forest {
    background:linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%);
    padding:1.5rem 1.8rem; position:relative; overflow:hidden;
}
.modal-top-forest::before { content:''; position:absolute; inset:0; background:radial-gradient(ellipse at 90% 20%, rgba(181,244,60,0.18), transparent 60%); }
.modal-top-forest h5 { color:#fff; font-weight:800; font-size:1rem; margin:0; position:relative; }
.modal-top-forest .btn-close { position:relative; }
.modal-top-danger { background:linear-gradient(135deg,#991b1b 0%,#dc2626 100%); padding:1.5rem 1.8rem; }
.modal-top-danger h5 { color:#fff; font-weight:800; font-size:1rem; margin:0; }

.modal-body-pad { padding:1.6rem 1.8rem; background:#fff; }
.modal-footer-pad { padding:0 1.8rem 1.6rem; background:#fff; display:flex; gap:0.65rem; justify-content:flex-end; }

.field-label { font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.09em; color:var(--text-muted); margin-bottom:0.5rem; display:block; }
.form-control-enh, .form-select-enh {
    border-radius:var(--radius-md); border:1.5px solid rgba(13,43,31,0.1);
    font-size:0.875rem; font-weight:500; padding:0.65rem 1rem;
    width:100%; color:var(--text-primary); background:#f8faf9;
    font-family:'Plus Jakarta Sans',sans-serif !important; transition:var(--transition); appearance:none;
}
.form-control-enh:focus, .form-select-enh:focus { outline:none; border-color:var(--lime); background:#fff; box-shadow:var(--shadow-glow); }
.form-select-enh {
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b7c74' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 1rem center; padding-right:2.4rem;
}
.input-group-enh { display:flex; }
.input-group-enh .prefix {
    background:#f0f4f2; border:1.5px solid rgba(13,43,31,0.1); border-right:none;
    border-radius:var(--radius-md) 0 0 var(--radius-md); padding:0 1rem;
    font-size:0.82rem; font-weight:700; color:var(--text-muted); display:flex; align-items:center;
}
.input-group-enh .form-control-enh { border-radius:0 var(--radius-md) var(--radius-md) 0; }

.info-box {
    background:#eff6ff; border:1px solid rgba(59,130,246,0.18);
    border-radius:var(--radius-md); padding:0.8rem 1rem; margin-bottom:1rem;
    font-size:0.8rem; font-weight:600; color:#1d4ed8;
    display:flex; align-items:flex-start; gap:0.6rem;
}
.info-box i { flex-shrink:0; margin-top:1px; }

/* Vehicle section */
.vehicle-section {
    background:var(--bg-muted); border-radius:var(--radius-md);
    border:1.5px dashed rgba(13,43,31,0.15); padding:1.1rem 1.3rem; margin-bottom:0.5rem;
}
.vehicle-section-label { font-size:0.68rem; font-weight:800; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); margin-bottom:0.75rem; }

/* Dropdown */
.dropdown-menu { border-radius:var(--radius-md) !important; border:1px solid var(--border) !important; box-shadow:var(--shadow-lg) !important; padding:0.4rem !important; }
.dropdown-item { border-radius:8px; font-size:0.84rem; font-weight:600; padding:0.58rem 0.9rem !important; color:var(--text-primary) !important; transition:var(--transition); }
.dropdown-item:hover { background:#f0faf4 !important; }

@media (max-width:768px) {
    .hp-hero { padding:2rem 1.5rem 4rem; }
    .hp-hero h1 { font-size:1.7rem !important; }
}
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

<?php
$admin_id = $_SESSION['admin_id'];

// 1. HANDLE POST ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    
    if (isset($_POST['action']) && $_POST['action'] === 'add_asset') {
        $title    = trim($_POST['title']);
        $category = $_POST['category'];
        $cost     = floatval($_POST['purchase_cost']);
        $value    = floatval($_POST['current_value']);
        $date     = $_POST['purchase_date'];
        $desc     = trim($_POST['description']);
        $reg_no   = trim($_POST['reg_no'] ?? '');
        $model    = trim($_POST['model'] ?? '');
        $route    = trim($_POST['assigned_route'] ?? '');
        $target   = floatval($_POST['target_amount'] ?? 0);
        $period   = $_POST['target_period'] ?? 'monthly';
        $target_start = $_POST['target_start_date'] ?? date('Y-m-d');

        if ($target <= 0) {
            flash_set("Revenue target is required for all investments.", 'error');
        } elseif (empty($title) || $cost <= 0) {
            flash_set("Title and valid cost are required.", 'error');
        } else {
            if (!empty($reg_no)) {
                $check_stmt = $conn->prepare("SELECT investment_id FROM investments WHERE reg_no = ? AND status != 'disposed'");
                $check_stmt->bind_param("s", $reg_no);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    flash_set("An active asset with registration number $reg_no already exists.", 'error');
                    header("Location: investments.php"); exit;
                }
            }
            $stmt = $conn->prepare("INSERT INTO investments (title, category, reg_no, model, assigned_route, description, purchase_date, purchase_cost, current_value, target_amount, target_period, target_start_date, viability_status, status, manager_admin_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'active', ?, NOW())");
            $stmt->bind_param("sssssssdddssi", $title, $category, $reg_no, $model, $route, $desc, $date, $cost, $value, $target, $period, $target_start, $admin_id);
            if ($stmt->execute()) { flash_set("Asset registered successfully with performance targets.", 'success'); }
            else { flash_set("Database error: " . $stmt->error, 'error'); }
            header("Location: investments.php"); exit;
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_asset') {
        $inv_id = intval($_POST['investment_id']);
        $val    = floatval($_POST['current_value']);
        $status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE investments SET current_value = ?, status = ? WHERE investment_id = ?");
        $stmt->bind_param("dsi", $val, $status, $inv_id);
        if ($stmt->execute()) {
            $conn->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'update_asset', 'Updated investment #$inv_id status to $status', '{$_SERVER['REMOTE_ADDR']}')");
            flash_set("Asset status updated.", 'success');
        }
        header("Location: investments.php"); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'edit_investment') {
        $inv_id   = intval($_POST['investment_id']);
        $title    = trim($_POST['title']);
        $category = $_POST['category'];
        $desc     = trim($_POST['description'] ?? '');
        $target   = floatval($_POST['target_amount']);
        $period   = $_POST['target_period'];
        if (empty($title) || $target <= 0) {
            flash_set("Title and valid revenue target are required.", 'error');
        } else {
            $stmt = $conn->prepare("UPDATE investments SET title = ?, category = ?, description = ?, target_amount = ?, target_period = ? WHERE investment_id = ?");
            $stmt->bind_param("sssdsi", $title, $category, $desc, $target, $period, $inv_id);
            if ($stmt->execute()) {
                $conn->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'edit_investment', 'Edited investment #$inv_id', '{$_SERVER['REMOTE_ADDR']}')");
                flash_set("Investment updated successfully.", 'success');
            } else { flash_set("Database error: " . $stmt->error, 'error'); }
        }
        header("Location: investments.php"); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'sell_asset') {
        $inv_id = intval($_POST['investment_id']);
        $price  = floatval($_POST['sale_price']);
        $date   = $_POST['sale_date'];
        $reason = trim($_POST['sale_reason']);
        $stmt = $conn->prepare("UPDATE investments SET status = 'sold', sale_price = ?, sale_date = ? WHERE investment_id = ?");
        $stmt->bind_param("dsi", $price, $date, $inv_id);
        if ($stmt->execute()) {
            require_once __DIR__ . '/../../inc/TransactionHelper.php';
            TransactionHelper::record(['type' => 'income','category' => 'asset_sale','amount' => $price,'notes' => "Proceeds from sale of asset #$inv_id. Reason: $reason",'related_table' => 'investments','related_id' => $inv_id]);
            $conn->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'dispose_asset', 'Sold Asset #$inv_id for KES $price', '{$_SERVER['REMOTE_ADDR']}')");
            flash_set("Asset disposed successfully. Sale proceeds recorded in ledger.", 'success');
        }
        header("Location: investments.php"); exit;
    }
}

// 2. DATA AGGREGATION
$filter = $_GET['cat'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$where = []; $params = []; $types = "";
if ($filter !== 'all') { $where[] = "category = ?"; $params[] = $filter; $types .= "s"; }
if ($search) {
    $where[] = "(title LIKE ? OR reg_no LIKE ? OR model LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term; $types .= "sss";
}

// Export Handler
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    if ($_GET['action'] !== 'print_report') { require_once __DIR__ . '/../../inc/ExportHelper.php'; }
    else { require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php'; }
    $where_e = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
    $stmt_e = $conn->prepare("SELECT title, category, reg_no, purchase_cost, current_value, status, purchase_date FROM investments $where_e ORDER BY purchase_date DESC");
    if (!empty($params)) $stmt_e->bind_param($types, ...$params);
    $stmt_e->execute(); $raw_data = $stmt_e->get_result();
    $format = match($_GET['action']) { 'export_excel' => 'excel', 'print_report' => 'print', default => 'pdf' };
    $data = []; $total_val = 0;
    while($a = $raw_data->fetch_assoc()) {
        $total_val += (float)$a['current_value'];
        $data[] = ['Asset' => $a['title'],'Category' => ucfirst(str_replace('_',' ',$a['category'])),'Reg No' => $a['reg_no'] ?: '-','Cost' => number_format((float)$a['purchase_cost']),'Valuation' => number_format((float)$a['current_value']),'Status' => strtoupper($a['status']),'Purchased' => date('d-M-Y', strtotime($a['purchase_date']))];
    }
    $title_f = 'Investment_Portfolio_' . date('Ymd_His');
    $headers = ['Asset','Category','Reg No','Cost','Valuation','Status','Purchased'];
    if ($format === 'pdf') ExportHelper::pdf('Investment Portfolio', $headers, $data, $title_f.'.pdf');
    elseif ($format === 'excel') ExportHelper::csv($title_f.'.csv', $headers, $data);
    else UniversalExportEngine::handle($format, $data, ['title' => 'Investment Portfolio','module' => 'Asset Management','headers' => $headers,'total_value' => $total_val]);
    exit;
}

$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT investment_id as id, title, category, status, purchase_cost, current_value, target_amount, target_period, viability_status, created_at, 'investments' as source_table FROM investments $where_sql ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$portfolio_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$ledger_stats = [];
foreach($portfolio_raw as $p) {
    $tid = $p['id'];
    $s = $conn->query("SELECT SUM(CASE WHEN transaction_type IN ('income','revenue_inflow') THEN amount ELSE 0 END) as rev, SUM(CASE WHEN transaction_type IN ('expense','expense_outflow') THEN amount ELSE 0 END) as exp FROM transactions WHERE related_table='investments' AND related_id=$tid")->fetch_assoc();
    $ledger_stats[$tid] = $s;
}

require_once __DIR__ . '/../../inc/InvestmentViabilityEngine.php';
$viability_engine = new InvestmentViabilityEngine($conn);
$portfolio = [];
foreach($portfolio_raw as $a) {
    $aid = $a['id'];
    $st = $ledger_stats[$aid] ?? ['rev' => 0, 'exp' => 0];
    $a['revenue']  = (float)$st['rev'];
    $a['expenses'] = (float)$st['exp'];
    $a['roi'] = $a['purchase_cost'] > 0
        ? (($a['current_value'] - $a['purchase_cost']) + ($a['revenue'] - $a['expenses'])) / (float)$a['purchase_cost'] * 100
        : 0;
    $perf = $viability_engine->calculatePerformance((int)$aid, 'investments');
    if ($perf) {
        $a['target_achievement'] = $perf['target_achievement_pct'];
        $a['net_profit']         = $perf['net_profit'];
        $a['viability_status']   = $perf['viability_status'];
        $a['is_profitable']      = $perf['is_profitable'];
        if ($a['source_table'] === 'investments' && $a['viability_status'] != $perf['viability_status'])
            $viability_engine->updateViabilityStatus((int)$aid, 'investments');
    } else {
        $a['target_achievement'] = 0;
        $a['net_profit']         = $a['revenue'] - $a['expenses'];
        $a['viability_status']   = 'pending';
        $a['is_profitable']      = $a['net_profit'] > 0;
    }
    $portfolio[] = $a;
}

$cat_stats = [];
foreach($portfolio as $p) {
    $c = ucfirst(str_replace('_', ' ', $p['category']));
    $cat_stats[$c] = ($cat_stats[$c] ?? 0) + (float)$p['current_value'];
}

$global = $conn->query("SELECT COUNT(CASE WHEN status='active' THEN 1 END) as active_count, COUNT(*) as total_count, SUM(CASE WHEN status='active' THEN current_value ELSE 0 END) as active_valuation, SUM(CASE WHEN status='active' THEN purchase_cost ELSE 0 END) as active_cost, SUM(CASE WHEN status='sold' THEN (sale_price - purchase_cost) ELSE 0 END) as realized_gains, SUM(CASE WHEN status='sold' THEN sale_price ELSE 0 END) as total_exit_value FROM investments")->fetch_assoc();
$q_cost = (float)($conn->query("SELECT SUM(purchase_cost) as c FROM investments")->fetch_assoc()['c'] ?? 0) ?: 1;
$total_val_g = ($global['active_valuation'] ?? 0) + ($global['total_exit_value'] ?? 0);
$multiplier = $total_val_g / $q_cost;
?>

        <!-- Hero -->
        <div class="hp-hero fade-in">
            <div class="ring ring1"></div>
            <div class="ring ring2"></div>
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="hero-badge">Capital Assets Portfolio</div>
                    <h1 class="mb-2">Asset Management.</h1>
                    <p style="color:rgba(255,255,255,0.6); font-size:0.93rem; font-weight:500; margin:0; position:relative;">
                        Managing <strong style="color:var(--lime);"><?= $global['total_count'] ?></strong> high-value investments and fleet assets with precision intelligence.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0" style="position:relative;">
                    <button class="btn btn-lime rounded-pill px-4 py-2 shadow-lg fw-bold mb-3" style="font-size:0.875rem;" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                        <i class="bi bi-plus-lg me-2"></i>Register New Asset
                    </button>
                    <div class="hero-sub-links d-flex flex-wrap justify-content-lg-end gap-3 no-print">
                        <a href="revenue.php"><i class="bi bi-cash-coin me-1"></i>Revenue</a>
                        <a href="expenses.php"><i class="bi bi-receipt me-1"></i>Expenses</a>
                        <a href="?action=print_report" target="_blank"><i class="bi bi-printer me-1"></i>Print Matrix</a>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:-36px; position:relative; z-index:10;">

            <?php flash_render(); ?>

            <?php render_support_ticket_widget($conn, ['investments'], 'Investments'); ?>

            <!-- KPIs -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="glass-stat s1 slide-up" style="animation-delay:0.04s">
                        <div class="stat-icon-wrap" style="background:#f0fdf4; color:#166534;"><i class="bi bi-safe2" style="font-size:1.3rem;"></i></div>
                        <div class="kpi-label" style="color:var(--text-muted);">Active Valuation</div>
                        <div class="kpi-value" style="color:var(--forest);">KES <?= number_format((float)($global['active_valuation'] ?? 0)) ?></div>
                        <div class="kpi-sub" style="color:var(--text-muted);"><i class="bi bi-building-check text-success me-1"></i><?= $global['active_count'] ?> live assets</div>
                        <i class="bi bi-safe2 kpi-bg-icon"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-stat stat-card-dark slide-up" style="animation-delay:0.1s">
                        <div class="stat-icon-wrap" style="background:rgba(255,255,255,0.12); color:var(--lime);"><i class="bi bi-cash-coin" style="font-size:1.3rem;"></i></div>
                        <div class="kpi-label" style="color:rgba(255,255,255,0.5);">Realized Exit Gains</div>
                        <div class="kpi-value" style="color:#fff;">KES <?= number_format((float)($global['realized_gains'] ?? 0)) ?></div>
                        <div class="kpi-sub" style="color:rgba(255,255,255,0.45);"><i class="bi bi-check-all me-1" style="color:var(--lime);"></i>Sold profit</div>
                        <i class="bi bi-graph-up kpi-bg-icon" style="color:#fff;"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-stat stat-card-accent s3 slide-up" style="animation-delay:0.16s">
                        <div class="stat-icon-wrap" style="background:rgba(13,43,31,0.06); color:var(--forest);"><i class="bi bi-graph-up-arrow" style="font-size:1.3rem;"></i></div>
                        <div class="kpi-label" style="color:rgba(13,43,31,0.5);">Projected Multiplier</div>
                        <div class="kpi-value" style="color:var(--forest);"><?= number_format($multiplier, 2) ?>x</div>
                        <div class="kpi-sub" style="color:rgba(13,43,31,0.45);"><i class="bi bi-stars me-1"></i>Total growth</div>
                        <i class="bi bi-stars kpi-bg-icon"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-stat slide-up" style="animation-delay:0.22s; text-align:center;">
                        <h6 style="font-size:0.67rem; font-weight:800; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); margin-bottom:1rem;">Asset Mix</h6>
                        <div style="height:130px;">
                            <canvas id="portfolioChart"
                                data-labels='<?= json_encode(array_keys($cat_stats)) ?>'
                                data-values='<?= json_encode(array_values($cat_stats)) ?>'>
                            </canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="row g-3 mb-4 align-items-center">
                <div class="col-lg-6">
                    <div class="filter-pill-wrap">
                        <?php
                        $cats = ['all' => 'All Assets', 'vehicle_fleet' => '🚛 Vehicles', 'farm' => '🌾 Farms', 'apartments' => '🏢 Real Estate', 'petrol_station' => '⛽ Fuel'];
                        foreach($cats as $val => $label):
                        ?>
                        <a href="?cat=<?= $val ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                           class="filter-pill <?= $filter === $val ? 'active' : '' ?>">
                            <?= $label ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-lg-4">
                    <form method="GET" id="searchForm">
                        <?php if($filter !== 'all'): ?><input type="hidden" name="cat" value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
                        <div class="search-wrap">
                            <i class="bi bi-search"></i>
                            <input type="text" name="q" id="assetSearch"
                                   placeholder="Search by name, reg no, model..."
                                   value="<?= htmlspecialchars($search) ?>"
                                   onchange="document.getElementById('searchForm').submit()">
                        </div>
                    </form>
                </div>
                <div class="col-lg-2 text-lg-end">
                    <div class="dropdown">
                        <button class="btn btn-forest rounded-pill px-4 py-2 dropdown-toggle fw-bold" style="font-size:0.82rem;" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-2"></i>Export
                        </button>
                        <ul class="dropdown-menu shadow border-0">
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Portfolio PDF</a></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Spreadsheet (XLS)</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Asset Cards Grid -->
            <div class="row g-4">
                <?php if(empty($portfolio)): ?>
                <div class="col-12">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-box-seam"></i></div>
                        <h5 style="font-weight:800; font-size:1rem; color:var(--text-primary); margin-bottom:0.3rem;">No Assets Found</h5>
                        <p style="font-size:0.83rem; color:var(--text-muted); margin:0;">Register an asset to start tracking its value and performance.</p>
                    </div>
                </div>
                <?php else:
                foreach($portfolio as $idx => $a):
                    $icon = match($a['category']) {
                        'farm'           => 'bi-flower2',
                        'vehicle_fleet'  => 'bi-truck-front',
                        'petrol_station' => 'bi-fuel-pump',
                        'apartments'     => 'bi-building',
                        default          => 'bi-box-seam'
                    };
                    $st_class = match($a['status']) {
                        'active'      => 'st-active',
                        'maintenance' => 'st-maintenance',
                        'sold'        => 'st-sold',
                        'disposed'    => 'st-disposed',
                        default       => 'st-active'
                    };
                    $v_class = match($a['viability_status']) {
                        'viable'          => 'v-viable',
                        'underperforming' => 'v-underperform',
                        'loss_making'     => 'v-loss',
                        default           => 'v-pending'
                    };
                    $v_icon = match($a['viability_status']) {
                        'viable'          => 'bi-check-circle-fill',
                        'underperforming' => 'bi-exclamation-triangle-fill',
                        'loss_making'     => 'bi-x-circle-fill',
                        default           => 'bi-clock-history'
                    };
                    $v_label = match($a['viability_status']) {
                        'viable'          => 'Viable',
                        'underperforming' => 'Underperforming',
                        'loss_making'     => 'Loss Making',
                        default           => 'Pending'
                    };
                    $suggestion = 'Retain Asset';
                    $s_color = '#1d4ed8'; $s_bg = '#eff6ff';
                    if ($a['roi'] > 25) { $suggestion = '↑ Expand/Reinvest'; $s_color = '#166534'; $s_bg = '#f0fdf4'; }
                    elseif ($a['roi'] < 5) { $suggestion = '⚠ Optimize or Sell'; $s_color = '#b91c1c'; $s_bg = '#fef2f2'; }
                    $pct = min(100, $a['target_achievement']);
                    $bar_color = $pct >= 100 ? '#22c55e' : ($pct >= 70 ? '#f59e0b' : '#ef4444');
                    $pct_color = $pct >= 100 ? '#166534' : ($pct >= 70 ? '#b45309' : '#b91c1c');
                ?>
                <div class="col-xl-4 col-md-6">
                    <div class="asset-card slide-up" style="animation-delay:<?= ($idx % 6) * 0.06 ?>s">

                        <!-- Top row -->
                        <div class="asset-card-top">
                            <div class="asset-icon-box"><i class="bi <?= $icon ?>"></i></div>
                            <div class="asset-badges">
                                <span class="st-pill <?= $st_class ?>"><?= ucfirst($a['status']) ?></span>
                                <span class="viability-pill <?= $v_class ?>">
                                    <i class="bi <?= $v_icon ?>" style="font-size:0.6rem;"></i><?= $v_label ?>
                                </span>
                                <?php if($a['status'] === 'active'): ?>
                                <span class="suggestion-pill" style="background:<?= $s_bg ?>; color:<?= $s_color ?>; border:1px solid <?= $s_color ?>22;">
                                    <?= $suggestion ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Identity -->
                        <div class="asset-title"><?= esc($a['title']) ?></div>
                        <div class="asset-category"><?= str_replace('_',' ',$a['category']) ?></div>

                        <!-- Valuation + ROI -->
                        <div class="metric-grid">
                            <div class="metric-box">
                                <div class="metric-box-label">Current Valuation</div>
                                <div class="metric-box-value" style="color:var(--forest);">KES <?= number_format((float)$a['current_value']) ?></div>
                            </div>
                            <div class="metric-box">
                                <div class="metric-box-label">Yield (ROI)</div>
                                <div class="metric-box-value" style="color:<?= $a['roi'] >= 0 ? '#166534' : '#b91c1c' ?>">
                                    <?= ($a['roi'] >= 0 ? '+' : '') . number_format((float)$a['roi'], 1) ?>%
                                </div>
                            </div>
                        </div>

                        <!-- P&L -->
                        <div style="margin-bottom:0.9rem;">
                            <div class="pnl-row">
                                <span class="pnl-label">Net Profit / Loss</span>
                                <span class="pnl-value" style="color:<?= $a['net_profit'] >= 0 ? '#166534' : '#b91c1c' ?>">
                                    <?= ($a['net_profit'] >= 0 ? '+' : '') ?>KES <?= number_format((float)$a['net_profit']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Target Progress -->
                        <div style="margin-bottom:1rem;">
                            <div class="progress-label-row">
                                <span class="progress-label">Target Achievement (<?= ucfirst($a['target_period']) ?>)</span>
                                <span class="progress-pct" style="color:<?= $pct_color ?>;"><?= number_format($pct, 1) ?>%</span>
                            </div>
                            <div class="asset-progress">
                                <div class="asset-progress-bar" style="width:<?= $pct ?>%; background:<?= $bar_color ?>;"></div>
                            </div>
                            <div class="progress-meta">
                                <span>Actual: KES <?= number_format((float)$a['revenue']) ?></span>
                                <span>Target: KES <?= number_format((float)$a['target_amount']) ?></span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="asset-actions">
                            <?php if($a['status'] === 'active'): ?>
                                <?php if($a['source_table'] === 'investments'): ?>
                                <button class="btn-act-icon" onclick="openEditModal(<?= htmlspecialchars(json_encode($a)) ?>)" title="Edit Investment">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn-act-main" onclick="openValuationModal(<?= htmlspecialchars(json_encode($a)) ?>)">
                                    <i class="bi bi-graph-up me-1" style="font-size:0.7rem;"></i>Audit Valuation
                                </button>
                                <button class="btn-act-icon danger" onclick="openDisposeModal(<?= htmlspecialchars(json_encode($a)) ?>)" title="Dispose / Sell Asset">
                                    <i class="bi bi-trash3-fill"></i>
                                </button>
                            <?php else: ?>
                                <span class="btn-act-disposed"><i class="bi bi-archive me-1"></i>Asset Disposed</span>
                            <?php endif; ?>
                            <a href="transactions.php?filter=<?= $a['id'] ?>&related_table=investments" class="btn-act-ledger" title="View Ledger">
                                <i class="bi bi-list-task"></i>
                            </a>
                        </div>

                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>

        </div><!-- /overlap -->

    </div><!-- /container-fluid -->


    <!-- ═══════════════════════════
         MODAL: ADD ASSET
    ═══════════════════════════ -->
    <div class="modal fade" id="addAssetModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-top-forest d-flex justify-content-between align-items-center">
                    <div style="display:flex;align-items:center;gap:0.65rem;">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-box-seam" style="color:var(--lime);font-size:0.9rem;"></i>
                        </div>
                        <h5>Asset Registration</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_asset">
                    <div class="modal-body-pad">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="field-label">Asset Title / Name</label>
                                <input type="text" name="title" class="form-control-enh" placeholder="e.g. Ruiru Apartments Block B" required>
                            </div>
                            <div class="col-md-4">
                                <label class="field-label">Category</label>
                                <select name="category" class="form-select-enh" id="catSelector" onchange="checkVehicle(this.value)" required>
                                    <option value="farm">🌾 Farm / Agriculture</option>
                                    <option value="vehicle_fleet">🚛 Vehicle / Fleet</option>
                                    <option value="petrol_station">⛽ Petroleum / Energy</option>
                                    <option value="apartments">🏢 Real Estate</option>
                                    <option value="other">📎 Miscellaneous</option>
                                </select>
                            </div>
                            <div id="vehExtra" class="col-12 d-none">
                                <div class="vehicle-section">
                                    <div class="vehicle-section-label"><i class="bi bi-truck-front me-1"></i> Vehicle Details</div>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="field-label">Registration No.</label>
                                            <input type="text" name="reg_no" class="form-control-enh" placeholder="KCA 001X">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="field-label">Make / Model</label>
                                            <input type="text" name="model" class="form-control-enh" placeholder="Toyota Hiace">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="field-label">Operational Route</label>
                                            <input type="text" name="assigned_route" class="form-control-enh" placeholder="Nairobi – Thika">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="field-label">Purchase Cost (KES)</label>
                                <div class="input-group-enh"><span class="prefix">KES</span><input type="number" name="purchase_cost" class="form-control-enh" step="1" required></div>
                            </div>
                            <div class="col-md-6">
                                <label class="field-label">Opening Valuation (KES)</label>
                                <div class="input-group-enh"><span class="prefix">KES</span><input type="number" name="current_value" class="form-control-enh" step="1" required></div>
                            </div>
                            <div class="col-12">
                                <div class="info-box">
                                    <i class="bi bi-info-circle-fill"></i>
                                    <span><strong>Performance Targets Required.</strong> Every investment must have measurable financial goals for viability tracking.</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="field-label">Revenue Target (KES) <span style="color:#dc2626;">*</span></label>
                                <div class="input-group-enh"><span class="prefix">KES</span><input type="number" name="target_amount" class="form-control-enh" placeholder="Expected revenue" min="1" step="1" required></div>
                                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.3rem;">Min expected revenue per period</div>
                            </div>
                            <div class="col-md-4">
                                <label class="field-label">Target Period <span style="color:#dc2626;">*</span></label>
                                <select name="target_period" class="form-select-enh" required>
                                    <option value="daily">Daily</option>
                                    <option value="monthly" selected>Monthly</option>
                                    <option value="annually">Annually</option>
                                </select>
                                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.3rem;">Evaluation frequency</div>
                            </div>
                            <div class="col-md-4">
                                <label class="field-label">Target Start Date <span style="color:#dc2626;">*</span></label>
                                <input type="date" name="target_start_date" class="form-control-enh" value="<?= date('Y-m-d') ?>" required>
                                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.3rem;">When tracking begins</div>
                            </div>
                            <div class="col-md-6">
                                <label class="field-label">Purchase Date</label>
                                <input type="date" name="purchase_date" class="form-control-enh" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="field-label">Description / Notes</label>
                                <textarea name="description" class="form-control-enh" rows="2" placeholder="Audit notes, location details, spec sheet..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer-pad" style="padding-top:0.5rem;">
                        <button type="button" style="background:none;border:1.5px solid var(--border);border-radius:100px;padding:0.55rem 1.2rem;font-weight:700;font-size:0.85rem;cursor:pointer;color:var(--text-muted);" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" style="background:var(--lime);color:var(--forest);border:none;border-radius:100px;padding:0.6rem 1.8rem;font-weight:800;font-size:0.875rem;cursor:pointer;box-shadow:0 4px 16px rgba(181,244,60,0.3);">
                            <i class="bi bi-check-circle-fill me-2"></i>Confirm & Register
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- ═══════════════════════════
         MODAL: VALUATION AUDIT
    ═══════════════════════════ -->
    <div class="modal fade" id="valuationModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
            <div class="modal-content">
                <div class="modal-top-forest d-flex justify-content-between align-items-center">
                    <div style="display:flex;align-items:center;gap:0.65rem;">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-graph-up" style="color:var(--lime);font-size:0.9rem;"></i>
                        </div>
                        <h5>Valuation Audit</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_asset">
                    <input type="hidden" name="investment_id" id="val_id">
                    <input type="hidden" name="source_table" id="val_source">
                    <div class="modal-body-pad">
                        <div style="background:var(--bg-muted);border-radius:var(--radius-md);padding:0.9rem 1rem;margin-bottom:1.2rem;border:1px solid var(--border);">
                            <div style="font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);margin-bottom:0.2rem;">Asset</div>
                            <div style="font-weight:800;font-size:0.95rem;color:var(--text-primary);" id="val_title"></div>
                        </div>
                        <div class="mb-3">
                            <label class="field-label">New Market Valuation (KES)</label>
                            <div class="input-group-enh"><span class="prefix">KES</span><input type="number" name="current_value" id="val_input" class="form-control-enh" style="font-size:1.1rem;font-weight:800;" required></div>
                        </div>
                        <div>
                            <label class="field-label">Asset Status</label>
                            <select name="status" id="val_status" class="form-select-enh">
                                <option value="active">✅ Active & Operating</option>
                                <option value="maintenance">🔧 Under Maintenance</option>
                                <option value="disposed">🗑️ Disposed / Terminated</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer-pad" style="padding-top:0.5rem;">
                        <button type="button" style="background:none;border:1.5px solid var(--border);border-radius:100px;padding:0.55rem 1.2rem;font-weight:700;font-size:0.85rem;cursor:pointer;color:var(--text-muted);" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" style="flex:1;background:var(--lime);color:var(--forest);border:none;border-radius:100px;padding:0.6rem;font-weight:800;font-size:0.875rem;cursor:pointer;box-shadow:var(--shadow-glow);">
                            Apply Valuation Change
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- ═══════════════════════════
         MODAL: EDIT INVESTMENT
    ═══════════════════════════ -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-top-forest d-flex justify-content-between align-items-center">
                    <div style="display:flex;align-items:center;gap:0.65rem;">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-pencil-square" style="color:var(--lime);font-size:0.9rem;"></i>
                        </div>
                        <h5>Edit Investment</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="edit_investment">
                    <input type="hidden" name="investment_id" id="edit_id">
                    <div class="modal-body-pad">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="field-label">Investment Title <span style="color:#dc2626;">*</span></label>
                                <input type="text" name="title" id="edit_title" class="form-control-enh" required>
                            </div>
                            <div class="col-md-4">
                                <label class="field-label">Category <span style="color:#dc2626;">*</span></label>
                                <select name="category" id="edit_category" class="form-select-enh" required>
                                    <option value="farm">🌾 Farm</option>
                                    <option value="apartments">🏢 Apartments</option>
                                    <option value="petrol_station">⛽ Petrol Station</option>
                                    <option value="vehicle_fleet">🚛 Vehicle Fleet</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="field-label">Description</label>
                                <textarea name="description" id="edit_description" class="form-control-enh" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="field-label">Revenue Target (KES) <span style="color:#dc2626;">*</span></label>
                                <div class="input-group-enh"><span class="prefix">KES</span><input type="number" name="target_amount" id="edit_target" class="form-control-enh" min="1" step="1" required></div>
                            </div>
                            <div class="col-md-6">
                                <label class="field-label">Target Period <span style="color:#dc2626;">*</span></label>
                                <select name="target_period" id="edit_period" class="form-select-enh" required>
                                    <option value="daily">Daily</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="annually">Annually</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer-pad" style="padding-top:0.5rem;">
                        <button type="button" style="background:none;border:1.5px solid var(--border);border-radius:100px;padding:0.55rem 1.2rem;font-weight:700;font-size:0.85rem;cursor:pointer;color:var(--text-muted);" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" style="background:var(--forest);color:#fff;border:none;border-radius:100px;padding:0.6rem 1.8rem;font-weight:800;font-size:0.875rem;cursor:pointer;box-shadow:var(--shadow-md);">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- ═══════════════════════════
         MODAL: DISPOSE / SELL
    ═══════════════════════════ -->
    <div class="modal fade" id="disposeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
            <div class="modal-content">
                <div class="modal-top-danger d-flex justify-content-between align-items-center">
                    <div style="display:flex;align-items:center;gap:0.65rem;">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-trash3-fill" style="color:#fff;font-size:0.9rem;"></i>
                        </div>
                        <h5>Finalize Asset Disposal</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="sell_asset">
                    <input type="hidden" name="investment_id" id="dispose_id">
                    <input type="hidden" name="source_table" id="dispose_source">
                    <div class="modal-body-pad">
                        <p style="font-size:0.83rem; color:var(--text-muted); margin-bottom:1.1rem; background:#fef2f2; border-radius:var(--radius-sm); padding:0.75rem 0.9rem; border:1px solid rgba(239,68,68,0.12);">
                            You are about to mark <strong id="dispose_title" style="color:var(--text-primary);"></strong> as sold. This will record the proceeds in treasury and halt future revenue tracking.
                        </p>
                        <div class="mb-3">
                            <label class="field-label">Sale Price (KES)</label>
                            <div class="input-group-enh"><span class="prefix">KES</span><input type="number" name="sale_price" id="dispose_price" class="form-control-enh" required></div>
                        </div>
                        <div class="mb-3">
                            <label class="field-label">Sale Date</label>
                            <input type="date" name="sale_date" class="form-control-enh" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div>
                            <label class="field-label">Reason for Sale</label>
                            <textarea name="sale_reason" class="form-control-enh" rows="3" placeholder="e.g. Asset depreciation, fleet upgrade..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer-pad" style="padding-top:0.5rem;">
                        <button type="button" style="background:none;border:1.5px solid var(--border);border-radius:100px;padding:0.55rem 1.2rem;font-weight:700;font-size:0.85rem;cursor:pointer;color:var(--text-muted);" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" style="flex:1;background:#dc2626;color:#fff;border:none;border-radius:100px;padding:0.6rem;font-weight:800;font-size:0.875rem;cursor:pointer;box-shadow:0 4px 14px rgba(220,38,38,0.3);">
                            Confirm Sale & Archive
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    // Portfolio donut chart
    document.addEventListener('DOMContentLoaded', function() {
        const cv = document.getElementById('portfolioChart');
        if (!cv) return;
        const labels = JSON.parse(cv.dataset.labels || '[]');
        const values = JSON.parse(cv.dataset.values || '[]');
        if (!labels.length) return;
        new Chart(cv, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: ['#b5f43c','#0d2b1f','#22c55e','#f59e0b','#3b82f6','#8b5cf6'],
                    borderWidth: 2, borderColor: '#fff'
                }]
            },
            options: {
                cutout: '68%',
                plugins: { legend: { display: false }, tooltip: {
                    callbacks: { label: ctx => ' KES ' + new Intl.NumberFormat().format(ctx.raw) }
                }},
                responsive: true, maintainAspectRatio: false
            }
        });
    });

    function checkVehicle(val) {
        document.getElementById('vehExtra').classList.toggle('d-none', val !== 'vehicle_fleet');
    }

    function openValuationModal(data) {
        document.getElementById('val_id').value     = data.id;
        document.getElementById('val_source').value = data.source_table;
        document.getElementById('val_title').innerText = data.title;
        document.getElementById('val_input').value  = data.current_value;
        document.getElementById('val_status').value = data.status;
        new bootstrap.Modal(document.getElementById('valuationModal')).show();
    }

    function openEditModal(data) {
        document.getElementById('edit_id').value          = data.id;
        document.getElementById('edit_title').value       = data.title;
        document.getElementById('edit_category').value    = data.category;
        document.getElementById('edit_description').value = data.description || '';
        document.getElementById('edit_target').value      = data.target_amount;
        document.getElementById('edit_period').value      = data.target_period;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    function openDisposeModal(data) {
        document.getElementById('dispose_id').value       = data.id;
        document.getElementById('dispose_source').value   = data.source_table;
        document.getElementById('dispose_title').innerText = data.title;
        document.getElementById('dispose_price').value    = data.current_value;
        new bootstrap.Modal(document.getElementById('disposeModal')).show();
    }
    </script>

    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->
</body>
</html>