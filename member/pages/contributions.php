<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');
require_member();
$layout = LayoutManager::create('member');

$member_id   = $_SESSION['member_id'];
$member_name = $_SESSION['member_name'] ?? 'Member';

$page             = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset           = ($page - 1) * $records_per_page;

$filter_from = $_GET['from']  ?? '';
$filter_to   = $_GET['to']    ?? '';
$filter_type = $_GET['type']  ?? '';

// ── Stats ──────────────────────────────────────────────────
$stmt_stats = $conn->prepare("SELECT
    COALESCE(SUM(amount),0) as grand_total,
    COALESCE(SUM(CASE WHEN contribution_type='savings' THEN amount ELSE 0 END),0) as total_savings,
    COALESCE(SUM(CASE WHEN contribution_type='shares'  THEN amount ELSE 0 END),0) as total_shares,
    COALESCE(SUM(CASE WHEN contribution_type='welfare' THEN amount ELSE 0 END),0) as total_welfare,
    COUNT(*) as total_count,
    COUNT(CASE WHEN contribution_type='savings' THEN 1 END) as count_savings,
    COUNT(CASE WHEN contribution_type='shares'  THEN 1 END) as count_shares,
    COUNT(CASE WHEN contribution_type='welfare' THEN 1 END) as count_welfare
    FROM contributions WHERE member_id = ?");
$stmt_stats->bind_param("i", $member_id);
$stmt_stats->execute();
$stats        = $stmt_stats->get_result()->fetch_assoc();
$savings_val  = (float)($stats['total_savings'] ?? 0);
$shares_val   = (float)($stats['total_shares']  ?? 0);
$welfare_val  = (float)($stats['total_welfare'] ?? 0);
$grand_total  = (float)($stats['grand_total']   ?? 0);
$total_count  = (int)($stats['total_count']     ?? 0);
$cnt_savings  = (int)($stats['count_savings']   ?? 0);
$cnt_shares   = (int)($stats['count_shares']    ?? 0);
$cnt_welfare  = (int)($stats['count_welfare']   ?? 0);

// ── Monthly trend – last 7 months ──────────────────────────
$trend_labels   = [];
$trend_savings  = [];
$trend_shares   = [];
$trend_welfare  = [];
for ($i = 6; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("-$i months"));
    $me = date('Y-m-t',  strtotime("-$i months"));
    $trend_labels[] = date('M', strtotime($ms));
    $stmt_t = $conn->prepare("SELECT
        COALESCE(SUM(CASE WHEN contribution_type='savings' THEN amount ELSE 0 END),0) as sv,
        COALESCE(SUM(CASE WHEN contribution_type='shares'  THEN amount ELSE 0 END),0) as sh,
        COALESCE(SUM(CASE WHEN contribution_type='welfare' THEN amount ELSE 0 END),0) as wf
        FROM contributions WHERE member_id=? AND DATE(created_at) BETWEEN ? AND ?");
    $stmt_t->bind_param("iss", $member_id, $ms, $me);
    $stmt_t->execute();
    $tr = $stmt_t->get_result()->fetch_assoc();
    $trend_savings[] = round((float)$tr['sv'], 2);
    $trend_shares[]  = round((float)$tr['sh'], 2);
    $trend_welfare[] = round((float)$tr['wf'], 2);
    $stmt_t->close();
}

// ── Recent streak (days with activity in last 30 days) ─────
$stmt_streak = $conn->prepare("SELECT COUNT(DISTINCT DATE(created_at)) as active_days FROM contributions WHERE member_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt_streak->bind_param("i", $member_id);
$stmt_streak->execute();
$active_days = (int)($stmt_streak->get_result()->fetch_assoc()['active_days'] ?? 0);

// ── Base query ─────────────────────────────────────────────
$sql_base = "FROM contributions WHERE member_id = ?";
$params = [$member_id]; $types = "i";
if (!empty($filter_type)) { $sql_base .= " AND contribution_type = ?"; $params[] = $filter_type; $types .= "s"; }
if (!empty($filter_from) && !empty($filter_to)) { $sql_base .= " AND DATE(created_at) BETWEEN ? AND ?"; $params[] = $filter_from; $params[] = $filter_to; $types .= "ss"; }

$stmt_count = $conn->prepare("SELECT COUNT(*) as total " . $sql_base);
$stmt_count->bind_param($types, ...$params); $stmt_count->execute();
$total_rows  = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = (int)ceil($total_rows / $records_per_page);

$stmt = $conn->prepare("SELECT contribution_id, reference_no, contribution_type, amount, payment_method, created_at, status " . $sql_base . " ORDER BY created_at DESC LIMIT ?, ?");
$stmt->bind_param($types . "ii", ...array_merge($params, [$offset, $records_per_page]));
$stmt->execute();
$result = $stmt->get_result();

// ── Export ─────────────────────────────────────────────────
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf','export_excel','print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    $format = $_GET['action'] === 'export_excel' ? 'excel' : ($_GET['action'] === 'print_report' ? 'print' : 'pdf');
    $stmt_ex = $conn->prepare("SELECT reference_no, contribution_type, amount, payment_method, created_at, status " . $sql_base . " ORDER BY created_at DESC");
    $stmt_ex->bind_param($types, ...$params); $stmt_ex->execute();
    $data = [];
    while ($row = $stmt_ex->get_result()->fetch_assoc()) {
        $data[] = ['Date'=>date('d-M-Y H:i',strtotime($row['created_at'])),'Type'=>ucwords(str_replace('_',' ',$row['contribution_type'])),'Reference'=>$row['reference_no']?:'-','Method'=>$row['payment_method']?:'M-Pesa','Amount'=>'+ '.number_format((float)$row['amount'],2),'Status'=>ucfirst($row['status'])];
    }
    UniversalExportEngine::handle($format, $data, ['title'=>'Contribution History','module'=>'Member Portal','headers'=>['Date','Type','Reference','Method','Amount','Status']]);
    exit;
}

$pageTitle = "My Contributions";
$safe_gt   = $grand_total ?: 1;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'SACCO' ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    /* ── Design tokens ────────────────────────────────── */
    :root {
        --forest:       #0F392B;
        --forest-mid:   #1a5c43;
        --forest-deep:  #0d2e22;
        --lime:         #A3E635;
        --lime-bright:  #bde32a;
        --lime-soft:    rgba(163,230,53,.1);
        --body-bg:      #F2F7F5;
        --card-bg:      #ffffff;
        --card-border:  #E3EDE9;
        --text-dark:    #0F392B;
        --text-body:    #587a6c;
        --text-muted:   #9eb8ae;
        --radius:       22px;
        --radius-sm:    14px;
        --radius-xs:    9px;
        --c-savings:    #059669;
        --c-shares:     #6366f1;
        --c-welfare:    #f43f5e;
        --shadow-card:  0 2px 18px rgba(15,57,43,.07);
        --shadow-hover: 0 10px 32px rgba(15,57,43,.13);
    }
    [data-bs-theme="dark"] {
        --body-bg:     #0B1E17;
        --card-bg:     #0d2018;
        --card-border: rgba(255,255,255,.07);
        --text-dark:   #d8ede4;
        --text-body:   #6a9880;
        --text-muted:  #3d6050;
        --shadow-card: 0 2px 18px rgba(0,0,0,.3);
        --shadow-hover:0 10px 32px rgba(0,0,0,.4);
    }

    /* ── Base ─────────────────────────────────────────── */
    body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--body-bg); color:var(--text-dark); overflow-x:hidden; }
    .main-content-wrapper { margin-left:272px; transition:margin-left .28s cubic-bezier(.4,0,.2,1); min-height:100vh; }
    body.sb-collapsed .main-content-wrapper { margin-left:76px; }
    @media (max-width:991px) { .main-content-wrapper { margin-left:0; } }
    .page-inner { padding:0 0 60px; }

    /* ── Scroll reveal ────────────────────────────────── */
    .sr { opacity:0; transform:translateY(22px); transition:opacity .5s ease, transform .5s ease; }
    .sr.in { opacity:1; transform:none; }
    .sr-d1 { transition-delay:.06s; }
    .sr-d2 { transition-delay:.12s; }
    .sr-d3 { transition-delay:.18s; }
    .sr-d4 { transition-delay:.24s; }

    /* ══════════════════════════════════════════════════
       HERO BANNER
    ══════════════════════════════════════════════════ */
    .hero-banner {
        background: linear-gradient(135deg, #0F392B 0%, #1a5c43 55%, #0d2e22 100%);
        padding: 36px 32px 0;
        position: relative;
        overflow: hidden;
        margin-bottom: 0;
    }
    .hero-banner::before {
        content: '';
        position: absolute;
        top: -80px; right: -80px;
        width: 340px; height: 340px;
        background: radial-gradient(circle, rgba(163,230,53,.14) 0%, transparent 65%);
        border-radius: 50%;
        pointer-events: none;
    }
    .hero-banner::after {
        content: '';
        position: absolute;
        bottom: -40px; left: 30%;
        width: 220px; height: 220px;
        background: radial-gradient(circle, rgba(163,230,53,.06) 0%, transparent 65%);
        border-radius: 50%;
        pointer-events: none;
    }
    /* SVG mesh texture */
    .hero-mesh {
        position: absolute;
        inset: 0;
        opacity: .04;
        background-image:
            repeating-linear-gradient(0deg,   transparent, transparent 40px, rgba(163,230,53,1) 40px, rgba(163,230,53,1) 41px),
            repeating-linear-gradient(90deg,  transparent, transparent 40px, rgba(163,230,53,1) 40px, rgba(163,230,53,1) 41px);
        pointer-events: none;
    }

    .hero-top { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:20px; position:relative; z-index:1; }
    .hero-eyebrow { display:inline-flex; align-items:center; gap:7px; background:rgba(163,230,53,.12); border:1px solid rgba(163,230,53,.22); border-radius:100px; padding:4px 13px; font-size:.62rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:rgba(255,255,255,.7); margin-bottom:10px; }
    .hero-eyebrow-dot { width:6px; height:6px; border-radius:50%; background:var(--lime); animation:pulse-d 2s ease infinite; }
    @keyframes pulse-d { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.5);opacity:.5} }
    .hero-title { font-size:1.75rem; font-weight:800; color:#fff; letter-spacing:-.5px; line-height:1.15; margin-bottom:5px; }
    .hero-sub   { font-size:.8rem; font-weight:500; color:rgba(255,255,255,.5); }
    .hero-actions { display:flex; gap:9px; align-items:center; flex-wrap:wrap; }
    .btn-hero-primary { display:inline-flex; align-items:center; gap:7px; padding:10px 22px; border-radius:var(--radius-sm); background:var(--lime); color:var(--forest); border:none; font-family:'Plus Jakarta Sans',sans-serif; font-size:.82rem; font-weight:800; cursor:pointer; transition:all .2s; text-decoration:none; box-shadow:0 4px 18px rgba(163,230,53,.35); }
    .btn-hero-primary:hover { transform:translateY(-2px); box-shadow:0 8px 26px rgba(163,230,53,.45); color:var(--forest); }
    .btn-hero-ghost { display:inline-flex; align-items:center; gap:7px; padding:9px 18px; border-radius:var(--radius-sm); background:rgba(255,255,255,.08); border:1.5px solid rgba(255,255,255,.18); color:#fff; font-family:'Plus Jakarta Sans',sans-serif; font-size:.82rem; font-weight:700; cursor:pointer; transition:all .2s; text-decoration:none; }
    .btn-hero-ghost:hover { background:rgba(255,255,255,.14); color:#fff; }
    .export-dd { border:1.5px solid var(--card-border) !important; border-radius:15px !important; box-shadow:0 14px 48px rgba(15,57,43,.14) !important; padding:7px !important; font-family:'Plus Jakarta Sans',sans-serif; background:var(--card-bg) !important; min-width:185px; }
    .export-dd-item { display:flex; align-items:center; gap:10px; padding:8px 12px; border-radius:10px; font-size:.8rem; font-weight:700; color:var(--text-dark); text-decoration:none; transition:background .14s; }
    .export-dd-item:hover { background:var(--body-bg); }
    .export-dd-icon { width:28px; height:28px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.78rem; flex-shrink:0; }

    /* ── Hero stat band ────────────────────────────────── */
    .hero-stat-band {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1px;
        background: rgba(255,255,255,.07);
        border-radius: var(--radius) var(--radius) 0 0;
        overflow: hidden;
        margin-top: 28px;
        position: relative;
        z-index: 1;
    }
    @media (max-width:900px) { .hero-stat-band { grid-template-columns: repeat(2,1fr); } }
    @media (max-width:560px) { .hero-stat-band { grid-template-columns: 1fr; } }

    .hero-stat-cell {
        background: rgba(255,255,255,.04);
        padding: 22px 24px;
        transition: background .2s;
    }
    .hero-stat-cell:hover { background: rgba(255,255,255,.08); }
    .hero-stat-cell:first-child { border-radius: var(--radius) 0 0 0; }
    .hero-stat-cell:last-child  { border-radius: 0 var(--radius) 0 0; }

    .hsc-label { font-size:.6rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:rgba(255,255,255,.38); margin-bottom:8px; display:flex; align-items:center; gap:7px; }
    .hsc-icon  { width:22px; height:22px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:.68rem; }
    .hsc-value { font-size:1.55rem; font-weight:800; color:#fff; letter-spacing:-.4px; line-height:1; margin-bottom:6px; }
    .hsc-sub   { font-size:.68rem; font-weight:600; color:rgba(255,255,255,.35); display:flex; align-items:center; gap:5px; }
    .hsc-badge { background:rgba(163,230,53,.18); color:var(--lime); font-size:.62rem; font-weight:800; padding:2px 8px; border-radius:100px; }

    /* ── Content area ─────────────────────────────────── */
    .content-area { padding: 28px 28px 0; }
    @media (max-width:768px) { .content-area { padding:16px 14px 0; } }

    /* ══════════════════════════════════════════════════
       DUAL PANEL — Charts + Breakdown
    ══════════════════════════════════════════════════ */
    .dual-panel { display:grid; grid-template-columns:1fr 340px; gap:20px; margin-bottom:22px; }
    @media (max-width:1080px) { .dual-panel { grid-template-columns:1fr; } }

    /* Trend chart card */
    .chart-card { background:var(--card-bg); border:1.5px solid var(--card-border); border-radius:var(--radius); padding:22px 22px 12px; box-shadow:var(--shadow-card); }
    .chart-card-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:4px; flex-wrap:wrap; gap:10px; }
    .chart-card-title { font-size:.88rem; font-weight:800; color:var(--text-dark); }
    .chart-card-sub   { font-size:.7rem; font-weight:600; color:var(--text-muted); margin-top:2px; }
    .chart-legend { display:flex; gap:14px; flex-wrap:wrap; }
    .chart-legend-item { display:flex; align-items:center; gap:6px; font-size:.7rem; font-weight:700; color:var(--text-body); }
    .cl-dot { width:8px; height:8px; border-radius:2px; }

    /* Breakdown ring card */
    .ring-card { background:var(--card-bg); border:1.5px solid var(--card-border); border-radius:var(--radius); padding:22px; box-shadow:var(--shadow-card); display:flex; flex-direction:column; }
    .ring-card-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
    .ring-card-title { font-size:.88rem; font-weight:800; color:var(--text-dark); }
    .ring-center-label { text-align:center; margin-bottom:16px; }
    .rcl-total { font-size:1.3rem; font-weight:800; color:var(--text-dark); letter-spacing:-.3px; }
    .rcl-sub   { font-size:.65rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.8px; }
    .ring-breakdown { display:flex; flex-direction:column; gap:10px; margin-top:6px; }
    .rb-item { display:grid; grid-template-columns:1fr auto; align-items:center; gap:8px; }
    .rb-left { display:flex; align-items:center; gap:8px; min-width:0; }
    .rb-icon  { width:28px; height:28px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.72rem; flex-shrink:0; }
    .rb-name  { font-size:.75rem; font-weight:700; color:var(--text-body); white-space:nowrap; }
    .rb-val   { font-size:.8rem; font-weight:800; color:var(--text-dark); }
    .rb-bar-wrap { height:3px; background:var(--body-bg); border-radius:100px; overflow:hidden; grid-column:1/-1; margin-top:-2px; }
    .rb-bar { height:100%; border-radius:100px; transition:width 1.4s cubic-bezier(.4,0,.2,1); }

    /* ══════════════════════════════════════════════════
       FILTER + TAB ROW
    ══════════════════════════════════════════════════ */
    .filter-tab-row { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:16px; flex-wrap:wrap; }

    /* Tab pills */
    .type-tabs { display:flex; gap:6px; flex-wrap:wrap; }
    .type-tab { display:inline-flex; align-items:center; gap:6px; padding:7px 15px; border-radius:100px; font-family:'Plus Jakarta Sans',sans-serif; font-size:.75rem; font-weight:800; cursor:pointer; text-decoration:none; transition:all .2s; border:1.5px solid var(--card-border); background:var(--card-bg); color:var(--text-body); }
    .type-tab:hover { border-color:var(--forest); color:var(--forest); }
    .type-tab.active-all      { background:linear-gradient(135deg,#0F392B,#1a5c43); color:#fff; border-color:transparent; box-shadow:0 4px 12px rgba(15,57,43,.25); }
    .type-tab.active-savings  { background:linear-gradient(135deg,#047857,#059669); color:#fff; border-color:transparent; box-shadow:0 4px 12px rgba(5,150,105,.3); }
    .type-tab.active-shares   { background:linear-gradient(135deg,#4f46e5,#6366f1); color:#fff; border-color:transparent; box-shadow:0 4px 12px rgba(99,102,241,.3); }
    .type-tab.active-welfare  { background:linear-gradient(135deg,#be123c,#f43f5e); color:#fff; border-color:transparent; box-shadow:0 4px 12px rgba(244,63,94,.3); }
    .tab-count { font-size:.6rem; background:rgba(255,255,255,.25); padding:1px 6px; border-radius:100px; font-weight:800; }
    .type-tab:not([class*="active"]) .tab-count { background:var(--body-bg); color:var(--text-muted); }

    /* Date filter inline */
    .date-filter-row { display:flex; align-items:flex-end; gap:8px; flex-wrap:wrap; }
    .df-group { display:flex; flex-direction:column; gap:4px; }
    .df-label { font-size:.58rem; font-weight:800; text-transform:uppercase; letter-spacing:.9px; color:var(--text-muted); }
    .df-input { background:var(--card-bg); border:1.5px solid var(--card-border); border-radius:var(--radius-xs); padding:7px 11px; font-family:'Plus Jakarta Sans',sans-serif; font-size:.78rem; font-weight:600; color:var(--text-dark); outline:none; transition:border-color .2s; }
    .df-input:focus { border-color:var(--forest); }
    .df-btn { display:inline-flex; align-items:center; gap:5px; padding:8px 16px; border-radius:var(--radius-xs); background:linear-gradient(135deg,#0F392B,#1a5c43); color:#fff; border:none; font-family:'Plus Jakarta Sans',sans-serif; font-size:.78rem; font-weight:800; cursor:pointer; transition:all .2s; height:36px; }
    .df-btn:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(15,57,43,.3); }
    .df-clear { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:var(--radius-xs); background:var(--card-bg); border:1.5px solid var(--card-border); color:var(--text-muted); text-decoration:none; transition:all .2s; }
    .df-clear:hover { border-color:#dc2626; color:#dc2626; }

    /* Active filter pills */
    .active-pills { display:flex; gap:7px; flex-wrap:wrap; align-items:center; margin-bottom:12px; }
    .ap-label { font-size:.6rem; font-weight:800; text-transform:uppercase; letter-spacing:.9px; color:var(--text-muted); }
    .ap-pill  { display:inline-flex; align-items:center; gap:5px; background:var(--lime-soft); border:1px solid rgba(163,230,53,.22); border-radius:100px; padding:3px 11px; font-size:.7rem; font-weight:700; color:var(--forest); }
    [data-bs-theme="dark"] .ap-pill { color:var(--lime); }
    .ap-pill span { opacity:.6; }

    /* ══════════════════════════════════════════════════
       TRANSACTION TABLE PANEL
    ══════════════════════════════════════════════════ */
    .tx-panel { background:var(--card-bg); border:1.5px solid var(--card-border); border-radius:var(--radius); overflow:hidden; box-shadow:var(--shadow-card); }
    .tx-panel-head { display:flex; align-items:center; justify-content:space-between; padding:18px 22px 16px; border-bottom:1px solid var(--card-border); gap:10px; flex-wrap:wrap; }
    .tx-panel-title { font-size:.92rem; font-weight:800; color:var(--text-dark); }
    .tx-panel-meta  { display:flex; align-items:center; gap:10px; }
    .tx-badge { background:var(--lime-soft); color:var(--forest); font-size:.65rem; font-weight:800; padding:3px 10px; border-radius:100px; }
    [data-bs-theme="dark"] .tx-badge { color:var(--lime); }
    .tx-page-info { font-size:.7rem; font-weight:600; color:var(--text-muted); }

    /* Table */
    .tx-table { width:100%; border-collapse:collapse; }
    .tx-table thead th {
        font-size:.59rem; font-weight:800; text-transform:uppercase; letter-spacing:1px;
        color:var(--text-muted); padding:9px 18px; background:var(--body-bg);
        border-bottom:1px solid var(--card-border); white-space:nowrap;
    }

    /* Date separator row */
    .tx-sep-row td { padding:10px 22px 6px; background:var(--body-bg); border-top:1px solid var(--card-border); border-bottom:1px solid var(--card-border); }
    .tx-sep-row:first-child td { border-top:none; }
    .tx-sep-inner { font-size:.6rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); display:flex; align-items:center; gap:10px; }
    .tx-sep-inner::after { content:''; flex:1; height:1px; background:var(--card-border); }
    .tx-sep-today { color:var(--forest); }
    [data-bs-theme="dark"] .tx-sep-today { color:var(--lime); }

    /* Data rows */
    .tx-row { transition:background .13s; cursor:default; }
    .tx-row:hover { background:var(--body-bg); }
    .tx-row td { padding:12px 18px; border-bottom:1px solid var(--card-border); vertical-align:middle; }
    .tx-row:last-child td { border-bottom:none; }

    /* Colored left stripe per type */
    .tx-row td:first-child { position:relative; }
    .tx-row.type-savings td:first-child { box-shadow:inset 3px 0 0 var(--c-savings); }
    .tx-row.type-shares  td:first-child { box-shadow:inset 3px 0 0 var(--c-shares); }
    .tx-row.type-welfare td:first-child { box-shadow:inset 3px 0 0 var(--c-welfare); }

    /* Icon */
    .tx-icon { width:38px; height:38px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:.88rem; flex-shrink:0; }
    .txi-savings { background:rgba(5,150,105,.1);  color:var(--c-savings); }
    .txi-shares  { background:rgba(99,102,241,.1); color:var(--c-shares); }
    .txi-welfare { background:rgba(244,63,94,.1);  color:var(--c-welfare); }
    .txi-default { background:var(--body-bg); color:var(--text-body); }

    .tx-name   { font-size:.83rem; font-weight:700; color:var(--text-dark); text-transform:capitalize; }
    .tx-method { font-size:.68rem; font-weight:600; color:var(--text-muted); margin-top:2px; display:flex; align-items:center; gap:4px; }
    .tx-method-sep { width:3px; height:3px; border-radius:50%; background:var(--text-muted); display:inline-block; }

    .tx-date { font-size:.78rem; font-weight:700; color:var(--text-dark); }
    .tx-time { font-size:.66rem; font-weight:600; color:var(--text-muted); margin-top:2px; }

    .tx-ref { font-family:monospace; font-size:.68rem; color:var(--text-muted); background:var(--body-bg); padding:3px 8px; border-radius:7px; border:1px solid var(--card-border); display:inline-block; letter-spacing:.2px; }

    /* Status pill */
    .sp { display:inline-flex; align-items:center; gap:5px; font-size:.62rem; font-weight:800; padding:3px 10px; border-radius:100px; text-transform:uppercase; letter-spacing:.5px; }
    .sp::before { content:''; width:5px; height:5px; border-radius:50%; }
    .sp-ok  { background:#D1FAE5; color:#065f46; } .sp-ok::before  { background:#059669; }
    .sp-pnd { background:#FEF3C7; color:#92400e; } .sp-pnd::before { background:#d97706; }
    .sp-err { background:#FEE2E2; color:#991b1b; } .sp-err::before { background:#dc2626; }

    .tx-amount     { font-size:.92rem; font-weight:800; color:#059669; white-space:nowrap; }
    .tx-amount-sub { font-size:.62rem; font-weight:600; color:var(--text-muted); text-align:right; margin-top:2px; }

    /* Row entrance animation */
    @keyframes row-in { from { opacity:0; transform:translateX(-8px); } to { opacity:1; transform:none; } }
    .tx-row { animation: row-in .28s ease both; }
    <?php for($ri=1;$ri<=10;$ri++) echo ".tx-row:nth-child($ri){animation-delay:".($ri*0.04)."s}"; ?>

    /* ── Empty state ───────────────────────────────── */
    .empty-wrap { padding:64px 24px; text-align:center; }
    .empty-icon-box { width:72px; height:72px; border-radius:20px; background:var(--body-bg); display:flex; align-items:center; justify-content:center; margin:0 auto 18px; font-size:2rem; color:var(--text-muted); opacity:.5; }
    .empty-wrap h6 { font-size:.92rem; font-weight:800; color:var(--text-dark); margin-bottom:6px; }
    .empty-wrap p  { font-size:.78rem; font-weight:500; color:var(--text-muted); margin-bottom:18px; }
    .btn-empty-act { display:inline-flex; align-items:center; gap:6px; padding:9px 20px; border-radius:var(--radius-sm); background:linear-gradient(135deg,#0F392B,#1a5c43); color:#fff; text-decoration:none; font-size:.8rem; font-weight:800; box-shadow:0 4px 14px rgba(15,57,43,.25); transition:all .2s; }
    .btn-empty-act:hover { transform:translateY(-2px); color:#fff; }

    /* ── Pagination ─────────────────────────────────── */
    .tx-pagination { display:flex; align-items:center; justify-content:space-between; padding:14px 22px; border-top:1px solid var(--card-border); gap:10px; flex-wrap:wrap; }
    .pag-info { font-size:.7rem; font-weight:600; color:var(--text-muted); }
    .pag-btns { display:flex; gap:4px; }
    .pag-btn { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:.76rem; font-weight:700; text-decoration:none; color:var(--text-body); background:var(--body-bg); border:1.5px solid var(--card-border); transition:all .18s; }
    .pag-btn:hover:not(.pag-active):not(.pag-dis) { border-color:var(--forest); color:var(--forest); }
    .pag-active { background:linear-gradient(135deg,#0F392B,#1a5c43); color:#fff !important; border-color:transparent; box-shadow:0 4px 12px rgba(15,57,43,.3); }
    .pag-dis { opacity:.3; pointer-events:none; }

    @media print { .no-print { display:none !important; } body { background:#fff; } }
    </style>
</head>
<body>
<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content-wrapper">
        <?php $layout->topbar($pageTitle ?? ''); ?>

        <div class="page-inner">

            <!-- ══ HERO BANNER ══════════════════════════════ -->
            <div class="hero-banner sr">
                <div class="hero-mesh"></div>

                <div class="hero-top">
                    <div>
                        <div class="hero-eyebrow"><span class="hero-eyebrow-dot"></span> Financial Record</div>
                        <div class="hero-title">My Contributions</div>
                        <div class="hero-sub">Complete history of your savings, shares &amp; welfare deposits.</div>
                    </div>
                    <div class="hero-actions no-print">
                        <div class="dropdown">
                            <button class="btn-hero-ghost dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-download"></i> Export
                            </button>
                            <ul class="dropdown-menu export-dd">
                                <li><a class="export-dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'export_pdf'])) ?>"><span class="export-dd-icon" style="background:#FEE2E2;color:#dc2626;"><i class="bi bi-file-pdf-fill"></i></span>Export PDF</a></li>
                                <li><a class="export-dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'export_excel'])) ?>"><span class="export-dd-icon" style="background:#D1FAE5;color:#059669;"><i class="bi bi-file-earmark-spreadsheet-fill"></i></span>Export Excel</a></li>
                                <li><a class="export-dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'print_report'])) ?>" target="_blank"><span class="export-dd-icon" style="background:#EEF2FF;color:#6366f1;"><i class="bi bi-printer-fill"></i></span>Print Statement</a></li>
                            </ul>
                        </div>
                        <a href="<?= BASE_URL ?>/member/pages/mpesa_request.php" class="btn-hero-primary">
                            <i class="bi bi-plus-lg"></i> New Deposit
                        </a>
                    </div>
                </div>

                <!-- Hero stat band -->
                <div class="hero-stat-band">
                    <!-- Grand total -->
                    <div class="hero-stat-cell">
                        <div class="hsc-label">
                            <span class="hsc-icon" style="background:rgba(163,230,53,.15);color:var(--lime);"><i class="bi bi-layers-fill"></i></span>
                            Portfolio Total
                        </div>
                        <div class="hsc-value" data-counter="<?= $grand_total ?>" data-prefix="KES " data-decimals="0">KES 0</div>
                        <div class="hsc-sub"><?= $total_count ?> transactions <span class="hsc-badge">All time</span></div>
                    </div>
                    <!-- Savings -->
                    <div class="hero-stat-cell">
                        <div class="hsc-label">
                            <span class="hsc-icon" style="background:rgba(163,230,53,.15);color:var(--lime);"><i class="bi bi-wallet2"></i></span>
                            Savings
                        </div>
                        <div class="hsc-value" data-counter="<?= $savings_val ?>" data-prefix="KES " data-decimals="0">KES 0</div>
                        <div class="hsc-sub"><?= $cnt_savings ?> deposits <span class="hsc-badge"><?= $grand_total > 0 ? round(($savings_val/$safe_gt)*100) : 0 ?>%</span></div>
                    </div>
                    <!-- Shares -->
                    <div class="hero-stat-cell">
                        <div class="hsc-label">
                            <span class="hsc-icon" style="background:rgba(163,230,53,.15);color:var(--lime);"><i class="bi bi-pie-chart-fill"></i></span>
                            Shares Capital
                        </div>
                        <div class="hsc-value" data-counter="<?= $shares_val ?>" data-prefix="KES " data-decimals="0">KES 0</div>
                        <div class="hsc-sub"><?= $cnt_shares ?> deposits <span class="hsc-badge"><?= $grand_total > 0 ? round(($shares_val/$safe_gt)*100) : 0 ?>%</span></div>
                    </div>
                    <!-- Welfare / Activity -->
                    <div class="hero-stat-cell">
                        <div class="hsc-label">
                            <span class="hsc-icon" style="background:rgba(163,230,53,.15);color:var(--lime);"><i class="bi bi-heart-pulse-fill"></i></span>
                            Welfare Fund
                        </div>
                        <div class="hsc-value" data-counter="<?= $welfare_val ?>" data-prefix="KES " data-decimals="0">KES 0</div>
                        <div class="hsc-sub"><?= $active_days ?> active days <span class="hsc-badge">30d</span></div>
                    </div>
                </div>
            </div><!-- /hero-banner -->

            <!-- ══ CONTENT AREA ════════════════════════════ -->
            <div class="content-area">

                <!-- Dual panel: trend chart + ring breakdown -->
                <div class="dual-panel sr sr-d1">

                    <!-- Trend Chart -->
                    <div class="chart-card">
                        <div class="chart-card-head">
                            <div>
                                <div class="chart-card-title">Contribution Trend</div>
                                <div class="chart-card-sub">Monthly breakdown — last 7 months</div>
                            </div>
                            <div class="chart-legend">
                                <div class="chart-legend-item"><span class="cl-dot" style="background:#059669;"></span>Savings</div>
                                <div class="chart-legend-item"><span class="cl-dot" style="background:#6366f1;"></span>Shares</div>
                                <div class="chart-legend-item"><span class="cl-dot" style="background:#f43f5e;"></span>Welfare</div>
                            </div>
                        </div>
                        <div id="trendChart"></div>
                    </div>

                    <!-- Ring Breakdown -->
                    <div class="ring-card">
                        <div class="ring-card-head">
                            <div class="ring-card-title">Portfolio Mix</div>
                        </div>
                        <div id="ringChart"></div>
                        <div class="ring-center-label">
                            <div class="rcl-total">KES <?= number_format($grand_total, 0) ?></div>
                            <div class="rcl-sub">Total Contributed</div>
                        </div>
                        <div class="ring-breakdown">
                            <?php
                            $rb = [
                                ['Savings', $savings_val, 'txi-savings', 'bi-wallet2',        'rgba(5,150,105,.1)',  '#059669', 'linear-gradient(90deg,#047857,#34d399)'],
                                ['Shares',  $shares_val,  'txi-shares',  'bi-pie-chart-fill', 'rgba(99,102,241,.1)','#6366f1', 'linear-gradient(90deg,#4f46e5,#a5b4fc)'],
                                ['Welfare', $welfare_val, 'txi-welfare', 'bi-heart-pulse-fill','rgba(244,63,94,.1)', '#f43f5e', 'linear-gradient(90deg,#be123c,#fda4af)'],
                            ];
                            foreach ($rb as [$name, $val, $icls, $iname, $ibg, $icol, $grad]):
                                $pct = $grand_total > 0 ? round(($val/$safe_gt)*100) : 0;
                            ?>
                            <div>
                                <div class="rb-item">
                                    <div class="rb-left">
                                        <div class="rb-icon" style="background:<?= $ibg ?>;color:<?= $icol ?>;"><i class="bi <?= $iname ?>"></i></div>
                                        <span class="rb-name"><?= $name ?></span>
                                    </div>
                                    <span class="rb-val">KES <?= number_format($val, 0) ?></span>
                                </div>
                                <div class="rb-bar-wrap">
                                    <div class="rb-bar" style="width:0%;background:<?= $grad ?>;" data-width="<?= $pct ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div><!-- /dual-panel -->

                <!-- ══ FILTER + TABS ════════════════════════ -->
                <div class="filter-tab-row sr sr-d2 no-print">
                    <!-- Type tab pills -->
                    <div class="type-tabs">
                        <?php
                        $tabs = [
                            ['', 'All', 'active-all', $total_count],
                            ['savings', 'Savings', 'active-savings', $cnt_savings],
                            ['shares',  'Shares',  'active-shares',  $cnt_shares],
                            ['welfare', 'Welfare', 'active-welfare', $cnt_welfare],
                        ];
                        foreach ($tabs as [$val, $label, $acls, $cnt]):
                            $isActive = ($filter_type === $val);
                            $qp = http_build_query(['type'=>$val,'from'=>$filter_from,'to'=>$filter_to,'page'=>1]);
                        ?>
                        <a href="?<?= $qp ?>"
                           class="type-tab<?= $isActive ? ' '.$acls : '' ?>">
                            <?= $label ?> <span class="tab-count"><?= $cnt ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Date range -->
                    <form method="GET" class="date-filter-row">
                        <input type="hidden" name="type" value="<?= htmlspecialchars($filter_type) ?>">
                        <div class="df-group">
                            <label class="df-label">From</label>
                            <input type="date" name="from" value="<?= htmlspecialchars($filter_from) ?>" class="df-input">
                        </div>
                        <div class="df-group">
                            <label class="df-label">To</label>
                            <input type="date" name="to" value="<?= htmlspecialchars($filter_to) ?>" class="df-input">
                        </div>
                        <button type="submit" class="df-btn"><i class="bi bi-funnel"></i> Apply</button>
                        <?php if (!empty($filter_from)): ?>
                        <a href="?type=<?= urlencode($filter_type) ?>" class="df-clear" title="Clear dates"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Active filter pills -->
                <?php if (!empty($filter_type) || !empty($filter_from)): ?>
                <div class="active-pills no-print sr sr-d2">
                    <span class="ap-label">Filtering:</span>
                    <?php if (!empty($filter_type)): ?>
                    <span class="ap-pill"><span>Type:</span> <?= ucfirst($filter_type) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($filter_from) && !empty($filter_to)): ?>
                    <span class="ap-pill"><span>Date:</span> <?= htmlspecialchars($filter_from) ?> → <?= htmlspecialchars($filter_to) ?></span>
                    <?php endif; ?>
                    <a href="contributions.php" style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-decoration:none;margin-left:4px;">✕ Clear all</a>
                </div>
                <?php endif; ?>

                <!-- ══ TRANSACTION PANEL ═════════════════════ -->
                <div class="tx-panel sr sr-d3">
                    <div class="tx-panel-head">
                        <div class="tx-panel-title">Transaction History</div>
                        <div class="tx-panel-meta">
                            <span class="tx-badge"><?= $total_rows ?> records</span>
                            <span class="tx-page-info">Page <?= $page ?> / <?= max(1,$total_pages) ?></span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="tx-table">
                            <thead>
                                <tr>
                                    <th style="padding-left:22px;width:34%;">Contribution</th>
                                    <th style="width:14%;">Date &amp; Time</th>
                                    <th style="width:20%;">Reference No.</th>
                                    <th style="width:12%;">Status</th>
                                    <th style="text-align:right;padding-right:22px;width:20%;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            if ($result->num_rows > 0):
                                $prev_date = null;
                                while ($row = $result->fetch_assoc()):
                                    $type   = $row['contribution_type'];
                                    $status = strtolower($row['status'] ?? 'completed');
                                    $dt     = new DateTime($row['created_at']);
                                    $dk     = $dt->format('Y-m-d');
                                    $today  = date('Y-m-d');
                                    $yest   = date('Y-m-d', strtotime('-1 day'));

                                    // Date separator
                                    if ($dk !== $prev_date):
                                        $prev_date = $dk;
                                        $grp = match($dk) {
                                            $today => ['Today', true],
                                            $yest  => ['Yesterday', false],
                                            default => [$dt->format('l, d M Y'), false],
                                        };
                            ?>
                            <tr class="tx-sep-row">
                                <td colspan="5">
                                    <div class="tx-sep-inner<?= $grp[1] ? ' tx-sep-today' : '' ?>">
                                        <?= $grp[0] ?>
                                        <?php if ($grp[1]): ?><span style="background:var(--lime-soft);color:var(--forest);font-size:.58rem;padding:2px 7px;border-radius:100px;"><?= $dt->format('d M Y') ?></span><?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif;

                                    $icls  = match($type) { 'savings'=>'txi-savings','shares'=>'txi-shares','welfare'=>'txi-welfare',default=>'txi-default' };
                                    $iname = match($type) { 'savings'=>'bi-wallet2','shares'=>'bi-pie-chart-fill','welfare'=>'bi-heart-pulse-fill',default=>'bi-cash-stack' };
                                    $sc    = match($status) { 'completed','active'=>'sp-ok','pending'=>'sp-pnd',default=>'sp-err' };
                            ?>
                            <tr class="tx-row type-<?= htmlspecialchars($type) ?>">
                                <td style="padding-left:22px;">
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <div class="tx-icon <?= $icls ?>"><i class="bi <?= $iname ?>"></i></div>
                                        <div>
                                            <div class="tx-name"><?= ucfirst(str_replace('_',' ',$type)) ?></div>
                                            <div class="tx-method">
                                                <span class="tx-method-sep"></span>
                                                <?= htmlspecialchars($row['payment_method'] ?? 'M-Pesa') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="tx-date"><?= $dt->format('M d, Y') ?></div>
                                    <div class="tx-time"><?= $dt->format('h:i A') ?></div>
                                </td>
                                <td><span class="tx-ref"><?= htmlspecialchars($row['reference_no'] ?? '—') ?></span></td>
                                <td><span class="sp <?= $sc ?>"><?= ucfirst($status) ?></span></td>
                                <td style="text-align:right;padding-right:22px;">
                                    <div class="tx-amount">+ KES <?= number_format((float)$row['amount'], 2) ?></div>
                                    <div class="tx-amount-sub"><?= ucfirst($type) ?></div>
                                </td>
                            </tr>
                            <?php endwhile;
                            else: ?>
                            <tr><td colspan="5">
                                <div class="empty-wrap">
                                    <div class="empty-icon-box"><i class="bi bi-receipt-cutoff"></i></div>
                                    <h6>No transactions found</h6>
                                    <p>Try changing your filters or make your first deposit.</p>
                                    <?php if (!empty($filter_type)||!empty($filter_from)): ?>
                                    <a href="contributions.php" class="btn-empty-act">Clear Filters</a>
                                    <?php else: ?>
                                    <a href="<?= BASE_URL ?>/member/pages/mpesa_request.php" class="btn-empty-act"><i class="bi bi-plus-lg"></i> Make a Deposit</a>
                                    <?php endif; ?>
                                </div>
                            </td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="tx-pagination no-print">
                        <span class="pag-info">Showing <?= $offset+1 ?>–<?= min($offset+$records_per_page,$total_rows) ?> of <?= $total_rows ?></span>
                        <div class="pag-btns">
                            <a class="pag-btn <?= $page<=1?'pag-dis':'' ?>" href="?page=<?= $page-1 ?>&type=<?= urlencode($filter_type) ?>&from=<?= $filter_from ?>&to=<?= $filter_to ?>"><i class="bi bi-chevron-left"></i></a>
                            <?php
                            $pstart = max(1, $page-2);
                            $pend   = min($total_pages, $page+2);
                            if ($pstart > 1) echo '<span class="pag-btn pag-dis" style="border:none;background:transparent;">…</span>';
                            for ($pi=$pstart; $pi<=$pend; $pi++):
                            ?>
                            <a class="pag-btn <?= $page==$pi?'pag-active':'' ?>" href="?page=<?= $pi ?>&type=<?= urlencode($filter_type) ?>&from=<?= $filter_from ?>&to=<?= $filter_to ?>"><?= $pi ?></a>
                            <?php endfor;
                            if ($pend < $total_pages) echo '<span class="pag-btn pag-dis" style="border:none;background:transparent;">…</span>';
                            ?>
                            <a class="pag-btn <?= $page>=$total_pages?'pag-dis':'' ?>" href="?page=<?= $page+1 ?>&type=<?= urlencode($filter_type) ?>&from=<?= $filter_from ?>&to=<?= $filter_to ?>"><i class="bi bi-chevron-right"></i></a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div><!-- /tx-panel -->

            </div><!-- /content-area -->
        </div><!-- /page-inner -->
        <?php $layout->footer(); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';

    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const textMuted = isDark ? '#3d6050' : '#9eb8ae';
    const textDark  = isDark ? '#d8ede4' : '#0F392B';
    const gridColor = isDark ? 'rgba(255,255,255,.05)' : 'rgba(15,57,43,.06)';
    const fontFam   = "'Plus Jakarta Sans', sans-serif";

    // ── Scroll reveal ────────────────────────────────────
    const srEls = document.querySelectorAll('.sr');
    const obs = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); obs.unobserve(e.target); } });
    }, { threshold: 0.07 });
    srEls.forEach(el => obs.observe(el));

    // ── Animated counters ────────────────────────────────
    function animateCounter(el) {
        const target   = parseFloat(el.dataset.counter) || 0;
        const prefix   = el.dataset.prefix || '';
        const decimals = parseInt(el.dataset.decimals || '0');
        const dur      = 1400;
        const start    = performance.now();
        function step(now) {
            const p   = Math.min((now - start) / dur, 1);
            const ease = 1 - Math.pow(1 - p, 3);
            const val  = target * ease;
            el.textContent = prefix + val.toLocaleString('en-KE', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
    const counterObs = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) { animateCounter(e.target); counterObs.unobserve(e.target); }
        });
    }, { threshold: 0.3 });
    document.querySelectorAll('[data-counter]').forEach(el => counterObs.observe(el));

    // ── Animate ring card progress bars ─────────────────
    setTimeout(() => {
        document.querySelectorAll('.rb-bar').forEach(b => { b.style.width = b.dataset.width; });
    }, 500);

    // ── Trend Chart ──────────────────────────────────────
    new ApexCharts(document.querySelector('#trendChart'), {
        series: [
            { name: 'Savings',  data: <?= json_encode($trend_savings) ?> },
            { name: 'Shares',   data: <?= json_encode($trend_shares)  ?> },
            { name: 'Welfare',  data: <?= json_encode($trend_welfare) ?> },
        ],
        chart: {
            type: 'area', height: 220,
            background: 'transparent',
            toolbar: { show: false },
            animations: { speed: 700, easing: 'easeinout' },
            fontFamily: fontFam,
        },
        colors: ['#059669', '#6366f1', '#f43f5e'],
        stroke: { curve: 'smooth', width: [2.5, 2.5, 2.5] },
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.22, opacityTo: 0.02, stops: [0, 95] }
        },
        xaxis: {
            categories: <?= json_encode($trend_labels) ?>,
            labels: { style: { fontSize: '0.65rem', fontWeight: 700, colors: Array(7).fill(textMuted) } },
            axisBorder: { show: false }, axisTicks: { show: false },
        },
        yaxis: {
            labels: {
                style: { fontSize: '0.65rem', fontWeight: 700, colors: [textMuted] },
                formatter: v => v >= 1000 ? 'K' + Math.round(v/1000) : v
            }
        },
        grid: { borderColor: gridColor, strokeDashArray: 5, xaxis: { lines: { show: false } } },
        dataLabels: { enabled: false },
        legend: { show: false },
        tooltip: {
            theme: isDark ? 'dark' : 'light',
            style: { fontSize: '0.78rem', fontFamily: fontFam },
            y: { formatter: v => 'KES ' + v.toLocaleString() }
        },
        markers: { size: 3, strokeWidth: 0, hover: { size: 5 } },
    }).render();

    // ── Ring / Donut Chart ───────────────────────────────
    new ApexCharts(document.querySelector('#ringChart'), {
        series: [<?= $savings_val ?>, <?= $shares_val ?>, <?= $welfare_val ?>],
        labels: ['Savings', 'Shares', 'Welfare'],
        chart: {
            type: 'donut', height: 180,
            background: 'transparent',
            animations: { speed: 700 },
            fontFamily: fontFam,
        },
        colors: ['#059669', '#6366f1', '#f43f5e'],
        stroke: { width: 3, colors: [isDark ? '#0d2018' : '#ffffff'] },
        dataLabels: { enabled: false },
        legend: { show: false },
        plotOptions: {
            pie: { donut: { size: '72%',
                labels: { show: false }
            }}
        },
        tooltip: {
            theme: isDark ? 'dark' : 'light',
            style: { fontSize: '0.78rem', fontFamily: fontFam },
            y: { formatter: v => 'KES ' + v.toLocaleString() }
        },
    }).render();

})();
</script>
</body>
</html>