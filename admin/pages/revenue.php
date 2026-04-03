<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

use USMS\Services\UniversalExportEngine;

$layout = LayoutManager::create('admin');

require_once __DIR__ . '/../../inc/TransactionHelper.php';
require_once __DIR__ . '/../../inc/InvestmentViabilityEngine.php';
Auth::requireAdmin();
require_permission();

$viability_engine = new InvestmentViabilityEngine($conn);

// 1. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_revenue'])) {
    verify_csrf_token();

    $amount = floatval($_POST['amount']);
    $method = $_POST['payment_method'] ?? 'cash';
    $desc   = trim($_POST['description']);
    $date   = $_POST['revenue_date'] ?? date('Y-m-d');

    validate_not_future($date, "revenue.php");

    $unified_id = $_POST['unified_asset_id'] ?? 'other_0';
    list($source_prefix, $related_id) = explode('_', $unified_id);
    $related_id = (int)$related_id;

    if ($related_id <= 0 && $source_prefix !== 'other') {
        flash_set("Please select a valid revenue source.", "error");
    } else {
        $config = [
            'type'             => 'income',
            'category'         => ($source_prefix === 'other' ? 'general_income' : 'investment_income'),
            'amount'           => $amount,
            'method'           => $method,
            'notes'            => $desc,
            'related_table'    => ($source_prefix === 'other' ? NULL : 'investments'),
            'related_id'       => ($source_prefix === 'other' ? 0 : $related_id),
            'transaction_date' => $date
        ];

        if (TransactionHelper::record($config)) {
            flash_set("Revenue recorded successfully!", "success");
            header("Location: revenue.php");
            exit;
        } else {
            flash_set("Failed to record revenue.", "error");
        }
    }
}

// 2. Fetch Data with Filters
$duration        = $_GET['duration'] ?? 'monthly';
$start_date      = $_GET['start_date'] ?? date('Y-m-01');
$end_date        = $_GET['end_date'] ?? date('Y-m-t');
$filter_asset_id = !empty($_GET['asset_id']) ? (int)$_GET['asset_id'] : 0;

$date_filter = "";
if ($duration !== 'all') {
    switch ($duration) {
        case 'today':   $start_date = $end_date = date('Y-m-d'); break;
        case 'weekly':  $start_date = date('Y-m-d', strtotime('-7 days')); $end_date = date('Y-m-d'); break;
        case 'monthly': $start_date = date('Y-m-01'); $end_date = date('Y-m-t'); break;
        case 'custom':  $start_date = $_GET['start_date'] ?? $start_date; $end_date = $_GET['end_date'] ?? $end_date; break;
    }
    $date_filter = " AND t.transaction_date BETWEEN '$start_date' AND '$end_date'";
}

$rev_types    = "'income', 'revenue_inflow'";
$where_clause = "t.transaction_type IN ($rev_types) $date_filter";
if ($filter_asset_id > 0) {
    $where_clause .= " AND t.related_table = 'investments' AND t.related_id = $filter_asset_id";
}

$revenue_qry  = "SELECT t.*,
                 CASE WHEN t.related_table = 'investments'
                      THEN (SELECT title FROM investments WHERE investment_id = t.related_id)
                      ELSE 'General Fund' END as source_name
                 FROM transactions t WHERE $where_clause
                 ORDER BY t.transaction_date DESC, t.created_at DESC LIMIT 100";
$revenue_res  = $conn->query($revenue_qry);
$revenue_data = $revenue_res->fetch_all(MYSQLI_ASSOC);

$total_period_rev = array_sum(array_column($revenue_data, 'amount'));

$target_summary = $conn->query("SELECT SUM(target_amount) as total_target FROM investments WHERE status = 'active'")->fetch_assoc();
$total_targets  = (float)($target_summary['total_target'] ?? 0);
$global_target_pct = $total_targets > 0 ? min(100, ($total_period_rev / $total_targets) * 100) : 0;

$asset_revenue_sql = "
    SELECT i.investment_id as id, i.title, i.category, i.target_amount, i.target_period, i.viability_status, 'investments' as asset_table,
           SUM(CASE WHEN t.transaction_date BETWEEN ? AND ? THEN t.amount ELSE 0 END) as period_revenue,
           COALESCE(SUM(t.amount), 0) as total_revenue,
           COUNT(t.transaction_id) as transaction_count
    FROM investments i
    LEFT JOIN transactions t ON t.related_table = 'investments' AND t.related_id = i.investment_id AND t.transaction_type IN ($rev_types)
    WHERE i.status = 'active'
    GROUP BY i.investment_id
    ORDER BY period_revenue DESC, total_revenue DESC";

$stmt_assets = $conn->prepare($asset_revenue_sql);
$stmt_assets->bind_param("ss", $start_date, $end_date);
$stmt_assets->execute();
$all_assets_raw = $stmt_assets->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_assets->close();

$top_investments = [];
$cat_revenue = [];
foreach ($all_assets_raw as $asset) {
    $perf = $viability_engine->calculatePerformance((int)$asset['id'], $asset['asset_table']);
    if ($perf) {
        $asset['target_achievement'] = $perf['target_achievement_pct'];
        $asset['is_profitable']      = $perf['is_profitable'];
        $asset['net_profit']         = $perf['net_profit'];
        $asset['viability_status']   = $perf['viability_status'];
    } else {
        $asset['target_achievement'] = 0;
        $asset['is_profitable']      = false;
        $asset['net_profit']         = 0;
    }
    $top_investments[] = $asset;
    $cat = ucfirst(str_replace('_', ' ', $asset['category']));
    $cat_revenue[$cat] = ($cat_revenue[$cat] ?? 0) + $asset['period_revenue'];
}

$inv_data_res           = $conn->query("SELECT investment_id, title, target_amount, target_period FROM investments WHERE status = 'active'");
$inv_js_data            = [];
$investments_select_list = [];
while ($row = $inv_data_res->fetch_assoc()) {
    $inv_js_data[$row['investment_id']] = $row;
    $investments_select_list[] = $row;
}

// Handle Export
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    if ($_GET['action'] === 'export_pdf' || $_GET['action'] === 'export_excel') {
        require_once __DIR__ . '/../../inc/ExportHelper.php';
    } else {
        require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    }

    $format = match($_GET['action']) { 'export_excel' => 'excel', 'print_report' => 'print', default => 'pdf' };
    $export_data = [];
    foreach ($revenue_data as $row) {
        $export_data[] = [
            'Date'    => date('d-M-Y', strtotime($row['transaction_date'])),
            'Ref'     => $row['reference_no'],
            'Source'  => $row['source_name'],
            'Method'  => strtoupper($row['payment_method']),
            'Amount'  => number_format((float)$row['amount'], 2),
            'Details' => $row['notes']
        ];
    }

    $title   = 'Revenue_Ledger_' . date('Ymd_His');
    $headers = ['Date', 'Ref', 'Source', 'Method', 'Amount', 'Details'];

    if ($format === 'pdf')       ExportHelper::pdf('Revenue Ledger', $headers, $export_data, $title . '.pdf');
    elseif ($format === 'excel') ExportHelper::csv($title . '.csv', $headers, $export_data);
    else                         UniversalExportEngine::handle($format, $export_data, ['title' => 'Revenue Ledger', 'module' => 'Revenue Analysis', 'headers' => $headers, 'total_value' => $total_period_rev]);
    exit;
}

$pageTitle = "Revenue Portal";
$layout->header($pageTitle);
?>

<!-- ═══════════════════════════════════════════════════════════ PAGE STYLES -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

<style>
/* ── Base ───────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }
body, .main-content-wrapper, .modal-content,
select, input, textarea, button, table {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ── Tokens ─────────────────────────────────────────────────── */
:root {
    --forest:       #1a3a2a;
    --forest-mid:   #234d38;
    --forest-light: #2e6347;
    --lime:         #a8e063;
    --lime-glow:    rgba(168,224,99,.18);
    --ink:          #111c14;
    --muted:        #6b7f72;
    --surface:      #ffffff;
    --surface-2:    #f5f8f5;
    --border:       #e3ebe5;
    --shadow-sm:    0 4px 12px rgba(26,58,42,.08);
    --shadow-md:    0 8px 28px rgba(26,58,42,.12);
    --shadow-lg:    0 16px 48px rgba(26,58,42,.16);
    --radius-sm:    10px;
    --radius-md:    16px;
    --radius-lg:    22px;
    --transition:   all .22s cubic-bezier(.4,0,.2,1);
}

/* ── Scaffold ───────────────────────────────────────────────── */
.page-canvas { background: var(--surface-2); min-height: 100vh; padding: 0 0 60px; }

/* ── Breadcrumb ─────────────────────────────────────────────── */
.breadcrumb { background: none; padding: 0; margin: 0 0 28px; font-size: .8rem; font-weight: 500; }
.breadcrumb-item a { color: var(--muted); text-decoration: none; transition: var(--transition); }
.breadcrumb-item a:hover { color: var(--forest); }
.breadcrumb-item.active { color: var(--ink); font-weight: 600; }
.breadcrumb-item + .breadcrumb-item::before { color: var(--border); }

/* ── Hero ───────────────────────────────────────────────────── */
.page-header {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 55%, var(--forest-light) 100%);
    border-radius: var(--radius-lg); padding: 36px 40px; margin-bottom: 28px;
    position: relative; overflow: hidden; box-shadow: var(--shadow-lg);
    animation: fadeUp .35s ease both;
}
.page-header::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse 60% 80% at 90% -10%, rgba(168,224,99,.22) 0%, transparent 60%),
                radial-gradient(ellipse 40% 50% at -5% 100%, rgba(168,224,99,.08) 0%, transparent 55%);
    pointer-events: none;
}
.page-header::after {
    content: ''; position: absolute; right: -60px; top: -60px;
    width: 260px; height: 260px; border-radius: 50%;
    border: 1px solid rgba(168,224,99,.1); pointer-events: none;
}
.hero-inner   { position: relative; z-index: 1; display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 24px; }
.hero-chip    { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.15); color: rgba(255,255,255,.8); font-size: .72rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; border-radius: 100px; padding: 5px 14px; margin-bottom: 14px; }
.hero-title   { font-size: clamp(1.5rem, 2.5vw, 2rem); font-weight: 800; color: #fff; letter-spacing: -.5px; margin: 0 0 6px; }
.hero-sub     { font-size: .85rem; color: rgba(255,255,255,.65); font-weight: 500; margin: 0 0 22px; }
.hero-stats   { display: flex; gap: 12px; flex-wrap: wrap; }
.hero-stat    { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); border-radius: var(--radius-sm); padding: 10px 18px; backdrop-filter: blur(4px); }
.hero-stat-label { font-size: .65rem; font-weight: 600; letter-spacing: .5px; text-transform: uppercase; color: rgba(255,255,255,.5); margin-bottom: 3px; }
.hero-stat-value { font-size: 1.1rem; font-weight: 800; color: #fff; }
.hero-stat-value.lime { color: var(--lime); }
.hero-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
.btn-lime { background: var(--lime); color: var(--ink); border: none; font-weight: 700; font-size: .85rem; transition: var(--transition); box-shadow: 0 4px 14px rgba(168,224,99,.4); }
.btn-lime:hover { background: #baea78; color: var(--ink); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(168,224,99,.5); }
.btn-outline-hero { background: rgba(255,255,255,.1); color: rgba(255,255,255,.9); border: 1px solid rgba(255,255,255,.25); font-weight: 600; font-size: .83rem; transition: var(--transition); }
.btn-outline-hero:hover { background: rgba(255,255,255,.18); color: #fff; transform: translateY(-1px); }

/* ── Section heading ────────────────────────────────────────── */
.section-heading { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
.section-title { font-size: .7rem; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; color: var(--forest); display: flex; align-items: center; gap: 8px; }
.section-title i { width: 28px; height: 28px; border-radius: 8px; background: var(--lime-glow); color: var(--forest); display: flex; align-items: center; justify-content: center; font-size: .9rem; }

/* ── Portfolio cards ────────────────────────────────────────── */
.portfolio-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 22px;
    box-shadow: var(--shadow-sm); height: 100%;
    transition: var(--transition); position: relative; overflow: hidden;
    animation: fadeUp .4s ease both;
}
.portfolio-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.portfolio-card::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0;
    width: 4px; border-radius: 4px 0 0 4px;
}
.portfolio-card.viable::before      { background: #22c55e; }
.portfolio-card.marginal::before    { background: #eab308; }
.portfolio-card.risk::before        { background: #ef4444; }
.portfolio-card.new-asset::before   { background: var(--border); }

.portfolio-card-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 14px; }
.asset-icon { width: 40px; height: 40px; border-radius: 10px; background: var(--lime-glow); color: var(--forest); display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
.viability-pill { font-size: .62rem; font-weight: 800; letter-spacing: .4px; text-transform: uppercase; border-radius: 100px; padding: 4px 10px; }
.viability-pill.viable   { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.viability-pill.marginal { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
.viability-pill.risk     { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.viability-pill.new-pill { background: var(--surface-2); color: var(--muted); border: 1px solid var(--border); }

.asset-category { font-size: .68rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: var(--muted); margin-bottom: 3px; }
.asset-title    { font-size: .92rem; font-weight: 800; color: var(--ink); margin-bottom: 12px; }
.asset-revenue  { font-size: 1.25rem; font-weight: 800; color: var(--ink); letter-spacing: -.3px; margin-bottom: 2px; }
.asset-target   { font-size: .74rem; color: var(--muted); font-weight: 500; margin-bottom: 14px; display: flex; align-items: center; gap: 5px; }

.achieve-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.achieve-label { font-size: .72rem; font-weight: 600; color: var(--muted); }
.achieve-pct   { font-size: .78rem; font-weight: 800; color: var(--ink); background: var(--lime-glow); padding: 3px 10px; border-radius: 100px; }

.progress-track { height: 6px; background: var(--surface-2); border-radius: 100px; overflow: hidden; border: 1px solid var(--border); }
.progress-fill-g  { height: 100%; border-radius: 100px; transition: width .6s ease; }
.pf-success { background: linear-gradient(90deg, #22c55e, #4ade80); }
.pf-warning { background: linear-gradient(90deg, #eab308, #facc15); }
.pf-danger  { background: linear-gradient(90deg, #ef4444, #f87171); }

.asset-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 12px; font-size: .73rem; }
.txn-count  { color: var(--muted); font-weight: 500; display: flex; align-items: center; gap: 4px; }
.profit-val { font-weight: 800; }
.profit-val.pos { color: #1a7a3f; }
.profit-val.neg { color: #c0392b; }

/* ── KPI strip ──────────────────────────────────────────────── */
.kpi-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 24px;
    box-shadow: var(--shadow-sm); height: 100%;
    transition: var(--transition); animation: fadeUp .4s ease both;
}
.kpi-card:hover { box-shadow: var(--shadow-md); border-color: #d0ddd4; }
.kpi-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--lime-glow); color: var(--forest); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 14px; }
.kpi-label { font-size: .68rem; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
.kpi-value { font-size: 1.6rem; font-weight: 800; color: var(--ink); letter-spacing: -.5px; line-height: 1; margin-bottom: 6px; }
.kpi-sub   { font-size: .75rem; color: var(--muted); font-weight: 500; display: flex; align-items: center; gap: 5px; }

.kpi-progress-track { height: 5px; background: var(--surface-2); border-radius: 100px; overflow: hidden; border: 1px solid var(--border); margin: 10px 0 8px; }
.kpi-progress-fill  { height: 100%; background: linear-gradient(90deg, var(--forest-light), var(--lime)); border-radius: 100px; }

/* ── Filter card ────────────────────────────────────────────── */
.filter-panel { display: flex; flex-direction: column; height: 100%; justify-content: space-between; gap: 12px; }
.filter-label { font-size: .68rem; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
.filter-select {
    width: 100%; height: 40px;
    border: 1.5px solid var(--border); border-radius: 10px;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .83rem; font-weight: 600; color: var(--ink); background: var(--surface-2);
    padding: 0 12px; transition: var(--transition); cursor: pointer; appearance: auto;
}
.filter-select:focus { outline: none; border-color: var(--forest); background: #fff; box-shadow: 0 0 0 3px rgba(26,58,42,.07); }
.date-row { display: flex; gap: 8px; }
.date-row input {
    flex: 1; height: 38px;
    border: 1.5px solid var(--border); border-radius: 9px;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .8rem; font-weight: 500; color: var(--ink); background: var(--surface-2);
    padding: 0 10px; transition: var(--transition);
}
.date-row input:focus { outline: none; border-color: var(--forest); background: #fff; box-shadow: 0 0 0 3px rgba(26,58,42,.07); }
.btn-apply {
    width: 100%; height: 40px; border-radius: 10px; border: none;
    background: var(--forest); color: #fff;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-weight: 700; font-size: .84rem; cursor: pointer; transition: var(--transition); margin-top: 4px;
}
.btn-apply:hover { background: var(--forest-light); transform: translateY(-1px); }

/* ── Ledger section ─────────────────────────────────────────── */
.detail-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); overflow: hidden;
    box-shadow: var(--shadow-sm); transition: var(--transition);
    animation: fadeUp .4s ease both; animation-delay: .16s;
}
.detail-card:hover { box-shadow: var(--shadow-md); border-color: #d0ddd4; }

.card-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; }
.card-toolbar-title { font-size: .7rem; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; color: var(--forest); display: flex; align-items: center; gap: 8px; }
.card-toolbar-title i { width: 28px; height: 28px; border-radius: 8px; background: var(--lime-glow); color: var(--forest); display: flex; align-items: center; justify-content: center; font-size: .9rem; }

/* Search */
.search-wrap { position: relative; flex: 1; max-width: 280px; }
.search-wrap i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: .82rem; pointer-events: none; }
.search-wrap input {
    width: 100%; padding: 8px 12px 8px 32px;
    font-size: .82rem; font-weight: 500;
    border: 1.5px solid var(--border); border-radius: 100px;
    background: var(--surface-2); color: var(--ink); transition: var(--transition);
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}
.search-wrap input:focus { outline: none; border-color: var(--forest); background: #fff; box-shadow: 0 0 0 3px rgba(26,58,42,.07); }

/* Revenue table */
.rev-table { width: 100%; border-collapse: collapse; }
.rev-table thead th { font-size: .67rem; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); background: var(--surface-2); padding: 13px 16px; border-bottom: 2px solid var(--border); }
.rev-table tbody tr { border-bottom: 1px solid var(--border); transition: var(--transition); }
.rev-table tbody tr:last-child { border-bottom: none; }
.rev-table tbody tr:hover { background: #f9fcf9; }
.rev-table td { padding: 14px 16px; vertical-align: middle; }

.rev-date { font-size: .88rem; font-weight: 700; color: var(--ink); }
.rev-ref  { font-size: .73rem; color: var(--muted); font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; margin-top: 2px; }
.rev-source { font-size: .85rem; font-weight: 700; color: var(--ink); }
.rev-notes  { font-size: .74rem; color: var(--muted); margin-top: 2px; }

.method-badge {
    display: inline-flex; align-items: center;
    font-size: .7rem; font-weight: 700; letter-spacing: .3px;
    border: 1.5px solid var(--border); border-radius: 8px; padding: 4px 12px;
    background: var(--surface-2); color: var(--ink);
}
.amount-cell { text-align: right; }
.amount-val  { font-size: .95rem; font-weight: 800; color: #1a7a3f; }

/* Empty state */
.empty-state { text-align: center; padding: 52px 24px; color: var(--muted); }
.empty-state i { font-size: 2.5rem; opacity: .2; display: block; margin-bottom: 12px; }
.empty-state p { font-size: .84rem; margin: 0; }

/* ── Chart card ─────────────────────────────────────────────── */
.chart-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 24px;
    box-shadow: var(--shadow-sm); height: 100%;
    transition: var(--transition); animation: fadeUp .4s ease both; animation-delay: .2s;
}
.chart-card:hover { box-shadow: var(--shadow-md); }
.chart-card h6 { font-size: .7rem; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; color: var(--forest); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
.chart-card h6 i { width: 26px; height: 26px; border-radius: 7px; background: var(--lime-glow); color: var(--forest); display: flex; align-items: center; justify-content: center; font-size: .8rem; }

.source-list { margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border); }
.source-row { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); }
.source-row:last-child { border-bottom: none; }
.source-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--lime); flex-shrink: 0; }
.source-name { font-size: .8rem; font-weight: 600; color: var(--ink); margin-left: 8px; flex: 1; }
.source-amt  { font-size: .78rem; font-weight: 800; color: var(--forest); }

/* ── Alert ──────────────────────────────────────────────────── */
.alert-custom { border: 0; border-radius: var(--radius-sm); padding: 14px 18px; font-size: .84rem; font-weight: 600; display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
.alert-custom.success { background: #f0fff6; color: #1a7a3f; border-left: 3px solid #2e6347; }
.alert-custom.danger  { background: #fef0f0; color: #c0392b; border-left: 3px solid #e74c3c; }

/* ── Modal ──────────────────────────────────────────────────── */
.modal-content { border: 0 !important; border-radius: var(--radius-lg) !important; overflow: hidden; box-shadow: var(--shadow-lg); }
.modal-header-forest { background: linear-gradient(135deg, var(--forest), var(--forest-light)); color: #fff; padding: 24px 28px; }
.modal-header-forest h5 { font-size: 1rem; font-weight: 800; color: #fff; margin: 0; display: flex; align-items: center; gap: 8px; }
.modal-body   { padding: 24px 28px !important; }
.modal-footer { border-top: 1px solid var(--border) !important; padding: 16px 28px !important; }
.form-label   { font-size: .78rem; font-weight: 700; color: var(--ink); margin-bottom: 7px; }
.form-control, .form-select {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .85rem; font-weight: 500;
    border: 1.5px solid var(--border); border-radius: 10px;
    padding: 10px 14px; color: var(--ink); background: var(--surface-2);
    transition: var(--transition);
}
.form-control-lg, .form-select-lg { font-size: .9rem !important; padding: 12px 16px !important; }
.form-control:focus, .form-select:focus { border-color: var(--forest); background: #fff; box-shadow: 0 0 0 3px rgba(26,58,42,.08); outline: none; }
textarea.form-control { resize: vertical; min-height: 80px; }
.target-info-box { background: #f0f8ff; border: 1px solid #bae6fd; border-radius: 10px; padding: 12px 16px; margin-bottom: 4px; display: flex; align-items: center; gap: 12px; }
.target-info-box i { color: #0284c7; font-size: 1.1rem; flex-shrink: 0; }
.target-info-label { font-size: .68rem; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; color: #0284c7; margin-bottom: 3px; }
.target-info-val   { font-size: .85rem; font-weight: 700; color: var(--ink); }

/* ── Animate ────────────────────────────────────────────────── */
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

/* ── Utils ──────────────────────────────────────────────────── */
.fw-800 { font-weight: 800 !important; }
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4 page-canvas">

        <!-- Breadcrumb -->
        <nav class="mb-1" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Revenue Portal</li>
            </ol>
        </nav>

        <?php flash_render(); ?>

        <!-- ═══ HERO ══════════════════════════════════════════════════════ -->
        <div class="page-header mb-4">
            <div class="hero-inner">
                <div>
                    <div class="hero-chip"><i class="bi bi-graph-up-arrow"></i>Treasury</div>
                    <h1 class="hero-title">Revenue Dashboard</h1>
                    <p class="hero-sub">Monitor SACCO inflows and asset performance metrics.</p>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-label">Period Revenue</div>
                            <div class="hero-stat-value lime">KES <?= number_format($total_period_rev) ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Target Rate</div>
                            <div class="hero-stat-value"><?= number_format($global_target_pct, 1) ?>%</div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Active Assets</div>
                            <div class="hero-stat-value"><?= count($top_investments) ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Period</div>
                            <div class="hero-stat-value"><?= ucwords($duration) ?></div>
                        </div>
                    </div>
                </div>
                <div class="hero-actions">
                    <button class="btn btn-lime rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#recordRevenueModal">
                        <i class="bi bi-plus-circle-fill me-2"></i>Record New Inflow
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-outline-hero rounded-pill px-4 fw-bold dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-2"></i>Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;overflow:hidden">
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf me-2 text-danger"></i>Revenue Ledger (PDF)</a></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-spreadsheet me-2 text-success"></i>Export Spreadsheet</a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer me-2 text-muted"></i>Print Report</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ PORTFOLIO PERFORMANCE ════════════════════════════════════ -->
        <?php if (!empty($top_investments)): ?>
        <div class="mb-4">
            <div class="section-heading">
                <div class="section-title">
                    <i class="bi bi-trophy-fill d-flex" style="background:#fef9c3;color:#854d0e"></i>
                    Portfolio Revenue Performance
                </div>
                <a href="investments.php" class="btn btn-sm rounded-pill px-3 fw-700" style="border:1.5px solid var(--border);color:var(--forest);font-size:.78rem;font-weight:700;transition:var(--transition)">
                    <i class="bi bi-arrow-right me-1"></i>View All Assets
                </a>
            </div>
            <div class="row g-4">
            <?php foreach ($top_investments as $idx => $inv):
                $icon = match($inv['category']) {
                    'farm'           => 'bi-flower3',
                    'vehicle_fleet'  => 'bi-truck-front',
                    'petrol_station' => 'bi-fuel-pump',
                    'apartments'     => 'bi-building',
                    default          => 'bi-box-seam'
                };
                $ach  = (float)$inv['target_achievement'];
                $v    = $inv['viability_status'];
                $card_class = $v === 'viable' ? 'viable' : ($v === 'underperforming' ? 'marginal' : ($v === 'loss_making' ? 'risk' : 'new-asset'));
                $pill_class = $v === 'viable' ? 'viable' : ($v === 'underperforming' ? 'marginal' : ($v === 'loss_making' ? 'risk' : 'new-pill'));
                $pill_text  = $v === 'viable' ? 'Viable' : ($v === 'underperforming' ? 'Marginal' : ($v === 'loss_making' ? 'At Risk' : 'New'));
                $pf_class   = $ach >= 100 ? 'pf-success' : ($ach >= 70 ? 'pf-warning' : 'pf-danger');
            ?>
            <div class="col-md-4" style="animation-delay:<?= $idx * .06 ?>s">
                <div class="portfolio-card <?= $card_class ?>">
                    <div class="portfolio-card-top">
                        <div class="asset-icon"><i class="bi <?= $icon ?>"></i></div>
                        <span class="viability-pill <?= $pill_class ?>"><?= $pill_text ?></span>
                    </div>
                    <div class="asset-category"><?= ucwords(str_replace('_', ' ', $inv['category'])) ?></div>
                    <div class="asset-title"><?= htmlspecialchars($inv['title']) ?></div>
                    <div class="asset-revenue">KES <?= number_format((float)$inv['period_revenue']) ?></div>
                    <div class="asset-target"><i class="bi bi-bullseye"></i>Target: KES <?= number_format((float)$inv['target_amount']) ?></div>
                    <div class="achieve-row">
                        <span class="achieve-label">Achievement</span>
                        <span class="achieve-pct"><?= number_format($ach, 1) ?>%</span>
                    </div>
                    <div class="progress-track"><div class="progress-fill-g <?= $pf_class ?>" style="width:<?= min(100, $ach) ?>%"></div></div>
                    <div class="asset-footer">
                        <span class="txn-count"><i class="bi bi-clock-history"></i><?= $inv['transaction_count'] ?> inputs</span>
                        <span class="profit-val <?= $inv['net_profit'] >= 0 ? 'pos' : 'neg' ?>">Profit: KES <?= number_format($inv['net_profit']) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Finance Nav -->

        <!-- ═══ KPI STRIP ═════════════════════════════════════════════════ -->
        <div class="row g-4 mb-4">
            <!-- Period Total -->
            <div class="col-md-4">
                <div class="kpi-card">
                    <div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    <div class="kpi-label">Period Total</div>
                    <div class="kpi-value">KES <?= number_format($total_period_rev) ?></div>
                    <div class="kpi-sub"><i class="bi bi-calendar-range"></i><?= ucwords($duration) ?> aggregation</div>
                </div>
            </div>

            <!-- Portfolio Efficiency -->
            <div class="col-md-4">
                <div class="kpi-card" style="animation-delay:.08s">
                    <div class="kpi-icon" style="background:#e8f4fd;color:#0284c7"><i class="bi bi-bullseye"></i></div>
                    <div class="kpi-label">Portfolio Efficiency</div>
                    <div class="kpi-value"><?= number_format($global_target_pct, 1) ?>%</div>
                    <div class="kpi-progress-track"><div class="kpi-progress-fill" style="width:<?= $global_target_pct ?>%"></div></div>
                    <div class="kpi-sub"><i class="bi bi-info-circle"></i>Actual vs. investment targets</div>
                </div>
            </div>

            <!-- Analysis Filters -->
            <div class="col-md-4">
                <div class="kpi-card" style="animation-delay:.16s">
                    <div class="kpi-label">Analysis Filters</div>
                    <form method="GET" id="filterForm" class="filter-panel">
                        <div>
                            <select name="duration" class="filter-select" onchange="toggleDateInputs(this.value)">
                                <option value="all"     <?= $duration === 'all'     ? 'selected' : '' ?>>Historical Records</option>
                                <option value="today"   <?= $duration === 'today'   ? 'selected' : '' ?>>Today's Activity</option>
                                <option value="weekly"  <?= $duration === 'weekly'  ? 'selected' : '' ?>>Past 7 Days</option>
                                <option value="monthly" <?= $duration === 'monthly' ? 'selected' : '' ?>>Current Month</option>
                                <option value="custom"  <?= $duration === 'custom'  ? 'selected' : '' ?>>Custom Range</option>
                            </select>
                            <div style="margin-top:8px">
                                <select name="asset_id" class="filter-select" onchange="this.form.submit()">
                                    <option value="0">All Investment Sources</option>
                                    <?php foreach ($investments_select_list as $inv): ?>
                                        <option value="<?= $inv['investment_id'] ?>" <?= $filter_asset_id == $inv['investment_id'] ? 'selected' : '' ?>><?= htmlspecialchars($inv['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="customDateRange" class="date-row mt-2 <?= $duration !== 'custom' ? 'd-none' : '' ?>">
                                <input type="date" name="start_date" value="<?= $start_date ?>">
                                <input type="date" name="end_date" value="<?= $end_date ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn-apply"><i class="bi bi-funnel-fill me-2"></i>Apply Filters</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ═══ LEDGER + CHART ═══════════════════════════════════════════ -->
        <div class="row g-4 mb-5">

            <!-- Ledger table -->
            <div class="col-lg-8">
                <div class="detail-card">
                    <div class="card-toolbar">
                        <div class="card-toolbar-title">
                            <i class="bi bi-receipt-cutoff d-flex"></i>
                            Revenue Ledger
                        </div>
                        <div class="search-wrap">
                            <i class="bi bi-search"></i>
                            <input type="text" id="revenueSearch" placeholder="Filter inflows…">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="rev-table" id="revenueTable">
                            <thead>
                                <tr>
                                    <th style="padding-left:20px">Date / Ref</th>
                                    <th>Financial Source</th>
                                    <th>Method</th>
                                    <th style="text-align:right;padding-right:20px">Credit (KES)</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($revenue_data)): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <i class="bi bi-receipt-cutoff"></i>
                                            <p>No revenue recorded for the selected period.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: foreach ($revenue_data as $row): ?>
                                <tr class="revenue-row">
                                    <td style="padding-left:20px">
                                        <div class="rev-date"><?= date('d M Y', strtotime($row['transaction_date'])) ?></div>
                                        <div class="rev-ref"><?= htmlspecialchars($row['reference_no']) ?></div>
                                    </td>
                                    <td>
                                        <div class="rev-source"><?= htmlspecialchars($row['source_name'] ?: 'General Fund') ?></div>
                                        <div class="rev-notes"><?= htmlspecialchars($row['notes'] ?: 'Revenue Entry') ?></div>
                                    </td>
                                    <td>
                                        <span class="method-badge"><?= strtoupper((string)($row['payment_method'] ?? 'N/A')) ?></span>
                                    </td>
                                    <td class="amount-cell" style="padding-right:20px">
                                        <div class="amount-val">+ KES <?= number_format((float)$row['amount'], 2) ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Chart + Source list -->
            <div class="col-lg-4">
                <div class="chart-card">
                    <h6><i class="bi bi-pie-chart-fill d-flex"></i>Source Distribution</h6>
                    <?php
                    $source_breakdown = [];
                    foreach ($revenue_data as $r) {
                        $src = $r['source_name'] ?: 'Other';
                        $source_breakdown[$src] = ($source_breakdown[$src] ?? 0) + $r['amount'];
                    }
                    ?>
                    <?php if (empty($source_breakdown)): ?>
                        <div class="empty-state">
                            <i class="bi bi-pie-chart"></i>
                            <p>Insufficient data for chart.</p>
                        </div>
                    <?php else: ?>
                        <div style="height:220px;position:relative">
                            <canvas id="revenueChart"
                                data-labels='<?= json_encode(array_keys($source_breakdown)) ?>'
                                data-values='<?= json_encode(array_values($source_breakdown)) ?>'>
                            </canvas>
                        </div>
                        <div class="source-list">
                            <?php
                            arsort($source_breakdown);
                            $cnt = 0;
                            $palette = ['#a8e063','#2e6347','#1a3a2a','#6b7f72','#d4f0a0'];
                            foreach ($source_breakdown as $sname => $sval):
                                if ($cnt >= 5) break;
                            ?>
                            <div class="source-row">
                                <div class="source-dot" style="background:<?= $palette[$cnt] ?? '#a8e063' ?>"></div>
                                <span class="source-name"><?= htmlspecialchars($sname) ?></span>
                                <span class="source-amt">KES <?= number_format((float)$sval) ?></span>
                            </div>
                            <?php $cnt++; endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ═══ RECORD REVENUE MODAL ════════════════════════════════════= -->
        <div class="modal fade" id="recordRevenueModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header-forest">
                        <div style="display:flex;align-items:center;justify-content:space-between;width:100%">
                            <h5><i class="bi bi-shield-check"></i>Record Received Inflow</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                    </div>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="record_revenue" value="1">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Asset / Revenue Source <span class="text-danger">*</span></label>
                                <select name="unified_asset_id" id="unified_asset_id" class="form-select form-select-lg" required onchange="updateTargetInfo()">
                                    <option value="other_0">General Fund / Unassigned</option>
                                    <optgroup label="Active Portfolio">
                                        <?php foreach ($investments_select_list as $inv): ?>
                                            <option value="inv_<?= $inv['investment_id'] ?>"><?= htmlspecialchars($inv['title']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>

                            <div class="target-info-box d-none mb-3" id="target_info_box">
                                <i class="bi bi-info-circle-fill"></i>
                                <div>
                                    <div class="target-info-label">Asset Performance Target</div>
                                    <div class="target-info-val" id="targetText">Target: KES 0.00</div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Amount Received (KES) <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" class="form-control form-control-lg fw-bold" min="0.01" step="0.01" required placeholder="0.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Collection Date</label>
                                    <input type="date" name="revenue_date" class="form-control form-control-lg" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Receiving Method</label>
                                    <select name="payment_method" class="form-select form-select-lg" required>
                                        <option value="cash">Cash Collection</option>
                                        <option value="mpesa">M-Pesa Business</option>
                                        <option value="bank">Bank Deposit</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-1">
                                <label class="form-label">Narration / Reference <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea name="description" class="form-control" placeholder="e.g. Daily collection, Dividend check…"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer justify-content-end gap-2">
                            <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-lime rounded-pill fw-bold px-5 text-dark">Confirm & Post Ledger</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const invData = <?= json_encode($inv_js_data) ?>;

function updateTargetInfo() {
    const val = document.getElementById('unified_asset_id').value;
    const box = document.getElementById('target_info_box');
    const txt = document.getElementById('targetText');

    if (val.startsWith('inv_')) {
        const id   = val.replace('inv_', '');
        const data = invData[id];
        if (data && data.target_amount > 0) {
            txt.innerHTML = `Target: <strong>KES ${new Intl.NumberFormat().format(data.target_amount)}</strong> (${data.target_period})`;
            box.classList.remove('d-none');
            return;
        }
    }
    box.classList.add('d-none');
}

function toggleDateInputs(val) {
    const range = document.getElementById('customDateRange');
    if (val === 'custom') {
        range.classList.remove('d-none');
    } else {
        range.classList.add('d-none');
        document.getElementById('filterForm').submit();
    }
}

// Doughnut chart
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        const labels = JSON.parse(ctx.getAttribute('data-labels') || '[]');
        const values = JSON.parse(ctx.getAttribute('data-values') || '[]');
        if (labels.length > 0) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#a8e063', '#2e6347', '#1a3a2a', '#6b7f72', '#d4f0a0'],
                        borderWidth: 0,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` KES ${new Intl.NumberFormat().format(ctx.raw)}` } } },
                    cutout: '72%'
                }
            });
        }
    }
});

// Ledger live search
document.getElementById('revenueSearch')?.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.revenue-row').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
</body>
</html>