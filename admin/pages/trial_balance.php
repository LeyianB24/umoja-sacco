<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

require_admin();
require_permission();
$layout = LayoutManager::create('admin');

$pageTitle = "Trial Balance Proof";

// 2. Fetch All Ledger Accounts
$sql = "SELECT * FROM ledger_accounts ORDER BY account_type ASC, category ASC, account_name ASC";
$res = $conn->query($sql);
$all_accounts = [];
while ($row = $res->fetch_assoc()) $all_accounts[] = $row;

// 3. Categorise
$assets      = [];
$liabilities = [];
$equity      = [];
$revenue_total = $expense_total = 0;
$total_assets = $total_liabilities = $total_equity = 0;

foreach ($all_accounts as $acc) {
    $bal  = (float)$acc['current_balance'];
    $type = strtolower($acc['account_type']);
    if ($type === 'asset')         { $assets[]      = $acc; $total_assets      += $bal; }
    elseif ($type === 'liability') { $liabilities[] = $acc; $total_liabilities += $bal; }
    elseif ($type === 'equity')    { $equity[]      = $acc; $total_equity      += $bal; }
    elseif ($type === 'revenue')   { $revenue_total += $bal; }
    elseif ($type === 'expense')   { $expense_total += $bal; }
}

// Net Income calculation
$net_income    = $revenue_total - $expense_total;
$balance_check = $total_assets - ($total_liabilities + $total_equity + $net_income);
$is_balanced   = abs($balance_check) < 0.01;

// 4. Export
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    if ($_GET['action'] === 'export_pdf' || $_GET['action'] === 'export_excel') {
        require_once __DIR__ . '/../../inc/ExportHelper.php';
    } else {
        require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    }
    $format = match($_GET['action']) { 'export_excel' => 'excel', 'print_report' => 'print', default => 'pdf' };
    $export_data = [];
    foreach ($assets as $a)      $export_data[] = ['ASSET',     $a['account_name'], number_format((float)$a['current_balance'], 2), ''];
    $export_data[] = ['ASSET',     'TOTAL ASSETS', number_format($total_assets, 2), ''];
    $export_data[] = ['', '', '', ''];
    foreach ($liabilities as $l) $export_data[] = ['LIABILITY', $l['account_name'], '', number_format((float)$l['current_balance'], 2)];
    foreach ($equity as $e)      $export_data[] = ['EQUITY',    $e['account_name'], '', number_format((float)$e['current_balance'], 2)];
    $export_data[] = ['EQUITY', 'Net Income (P&L)', '', number_format($net_income, 2)];
    $export_data[] = ['', 'TOTAL LIAB + EQUITY', '', number_format($total_liabilities + $total_equity, 2)];
    $title   = 'Trial_Balance_Audit_Proof_' . date('Ymd_His');
    $headers = ['Category', 'Account', 'Amount (Dr)', 'Amount (Cr)'];
    if ($format === 'pdf')       ExportHelper::pdf('Trial Balance Audit Proof', $headers, $export_data, $title . '.pdf');
    elseif ($format === 'excel') ExportHelper::csv($title . '.csv', $headers, $export_data);
    else                         UniversalExportEngine::handle($format, array_merge([$headers], $export_data), ['title' => 'Trial Balance Audit Proof', 'module' => 'Internal Audit', 'is_balanced' => $is_balanced, 'difference' => $balance_check]);
    exit;
}

// Category groupings
$asset_cats = $liab_cats = $equity_cats = [];
foreach ($assets      as $a) { $c = $a['category'] ?: 'Uncategorized'; $asset_cats[$c]  = ($asset_cats[$c]  ?? 0) + (float)$a['current_balance']; }
foreach ($liabilities as $l) { $c = $l['category'] ?: 'Uncategorized'; $liab_cats[$c]   = ($liab_cats[$c]   ?? 0) + (float)$l['current_balance']; }
foreach ($equity      as $e) { $c = $e['category'] ?: 'Equity';        $equity_cats[$c] = ($equity_cats[$c] ?? 0) + (float)$e['current_balance']; }
?>
<?php $layout->header($pageTitle); ?>

<!-- ═══════════════════════════════════════════════════════════ PAGE STYLES -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

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
    --success-bg:   #dcfce7;
    --success-text: #166534;
    --success-border:#86efac;
    --danger-bg:    #fee2e2;
    --danger-text:  #991b1b;
    --danger-border:#fca5a5;
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
.hero-stat-value { font-size: 1rem; font-weight: 800; color: #fff; }
.hero-stat-value.lime   { color: var(--lime); }
.hero-stat-value.balanced { color: var(--lime); }
.hero-stat-value.danger { color: #fca5a5; }
.hero-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
.btn-lime { background: var(--lime); color: var(--ink); border: none; font-weight: 700; font-size: .85rem; transition: var(--transition); box-shadow: 0 4px 14px rgba(168,224,99,.4); }
.btn-lime:hover { background: #baea78; color: var(--ink); transform: translateY(-1px); }
.btn-outline-hero { background: rgba(255,255,255,.1); color: rgba(255,255,255,.9); border: 1px solid rgba(255,255,255,.25); font-weight: 600; font-size: .83rem; transition: var(--transition); }
.btn-outline-hero:hover { background: rgba(255,255,255,.18); color: #fff; transform: translateY(-1px); }

/* ── KPI cards ──────────────────────────────────────────────── */
.kpi-row { display: flex; gap: 20px; margin-bottom: 24px; flex-wrap: wrap; animation: fadeUp .4s ease both; animation-delay: .06s; }
.kpi-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 24px;
    box-shadow: var(--shadow-sm); flex: 1; min-width: 200px;
    transition: var(--transition); position: relative; overflow: hidden;
}
.kpi-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.kpi-card.balanced-card { background: linear-gradient(135deg, var(--forest) 0%, var(--forest-light) 100%); border-color: var(--forest); }
.kpi-card.lime-card { background: var(--lime); border-color: rgba(168,224,99,.6); }

.kpi-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 14px; }
.kpi-icon.green  { background: rgba(34,197,94,.12); color: #15803d; }
.kpi-icon.forest { background: rgba(255,255,255,.15); color: #fff; }
.kpi-icon.ink    { background: rgba(15,57,43,.1); color: var(--forest); }

.kpi-label { font-size: .68rem; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
.kpi-card.balanced-card .kpi-label,
.kpi-card.balanced-card .kpi-sub { color: rgba(255,255,255,.65); }
.kpi-card.lime-card .kpi-label { color: rgba(26,58,42,.65); }

.kpi-value { font-size: 1.35rem; font-weight: 800; color: var(--ink); letter-spacing: -.4px; line-height: 1; margin-bottom: 6px; }
.kpi-card.balanced-card .kpi-value { color: #fff; }
.kpi-card.lime-card .kpi-value { color: var(--forest); }

.kpi-sub { font-size: .75rem; color: var(--muted); font-weight: 600; display: flex; align-items: center; gap: 5px; }
.kpi-card.balanced-card .kpi-sub { color: rgba(255,255,255,.55); }
.kpi-card.lime-card .kpi-sub { color: rgba(26,58,42,.65); }

/* Balance status pill on kpi card */
.kpi-balance-pill {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .3px;
    border-radius: 100px; padding: 4px 12px; margin-top: 10px;
}
.kpi-balance-pill.ok  { background: rgba(255,255,255,.15); color: var(--lime); border: 1px solid rgba(168,224,99,.3); }
.kpi-balance-pill.bad { background: rgba(239,68,68,.15); color: #fca5a5; border: 1px solid rgba(239,68,68,.3); }

/* Background decor icon on kpi */
.kpi-decor-icon { position: absolute; right: -12px; bottom: -18px; font-size: 5rem; opacity: .05; pointer-events: none; }

/* ── Chart card ─────────────────────────────────────────────── */
.chart-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 24px;
    box-shadow: var(--shadow-sm); height: 100%;
    transition: var(--transition); animation: fadeUp .4s ease both; animation-delay: .1s;
}
.chart-card:hover { box-shadow: var(--shadow-md); }
.chart-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.chart-title  { font-size: .7rem; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; color: var(--forest); display: flex; align-items: center; gap: 8px; }
.chart-title i { width: 26px; height: 26px; border-radius: 7px; background: var(--lime-glow); color: var(--forest); display: flex; align-items: center; justify-content: center; font-size: .8rem; }
.balance-verdict-pill { display: inline-flex; align-items: center; gap: 5px; font-size: .68rem; font-weight: 800; text-transform: uppercase; border-radius: 100px; padding: 5px 14px; }
.bvp-ok  { background: var(--success-bg); color: var(--success-text); border: 1px solid var(--success-border); }
.bvp-bad { background: var(--danger-bg);  color: var(--danger-text);  border: 1px solid var(--danger-border); }

/* ── Ledger panels ──────────────────────────────────────────── */
.ledger-panel {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); overflow: hidden;
    box-shadow: var(--shadow-sm); transition: var(--transition);
    animation: fadeUp .4s ease both;
}
.ledger-panel:hover { box-shadow: var(--shadow-md); border-color: #d0ddd4; }

.ledger-panel-header {
    padding: 20px 24px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 12px;
}
.ledger-panel-header-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
.lph-assets  { background: rgba(34,197,94,.12); color: #15803d; }
.lph-liab    { background: rgba(59,130,246,.12); color: #1d4ed8; }
.ledger-panel-title { font-size: .7rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; color: var(--forest); }
.ledger-panel-sub   { font-size: .7rem; color: var(--muted); font-weight: 500; margin-top: 2px; }

/* Section divider inside right panel */
.panel-section-label { font-size: .65rem; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); background: var(--surface-2); padding: 10px 20px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }

/* Ledger row */
.ledger-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 15px 20px; border-bottom: 1px solid var(--border);
    cursor: pointer; transition: var(--transition);
    text-decoration: none;
}
.ledger-row:last-child { border-bottom: none; }
.ledger-row:hover { background: var(--surface-2); }
.ledger-row:hover .ledger-row-arrow { opacity: 1; transform: translateX(0); }

.ledger-row-label { font-size: .84rem; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: 8px; }
.ledger-row-label i { font-size: .7rem; color: var(--muted); opacity: .6; }
.ledger-row-right { display: flex; align-items: center; gap: 12px; }
.ledger-row-val { font-size: .88rem; font-weight: 800; color: var(--ink); font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; }
.ledger-row-val.asset { color: #15803d; }
.ledger-row-arrow { font-size: .7rem; color: var(--muted); opacity: 0; transform: translateX(-4px); transition: var(--transition); }

/* Net income special row */
.net-income-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 15px 20px; background: rgba(168,224,99,.06);
    border-top: 1px solid var(--border);
}
.net-income-label { font-size: .84rem; font-weight: 800; color: var(--forest); display: flex; align-items: center; gap: 7px; }
.net-income-val   { font-size: .9rem; font-weight: 800; font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; }

/* Panel footer */
.ledger-panel-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; background: var(--surface-2); border-top: 2px solid var(--border);
}
.footer-label { font-size: .67rem; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); }
.footer-total { font-size: 1.2rem; font-weight: 800; color: var(--ink); letter-spacing: -.3px; font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; }
.footer-total.green { color: #15803d; }

/* ── Verdict banner ─────────────────────────────────────────── */
.verdict-banner {
    border-radius: var(--radius-md); padding: 40px 24px; text-align: center;
    margin: 24px 0; animation: fadeUp .4s ease both; animation-delay: .2s;
}
.verdict-banner.balanced { background: linear-gradient(135deg, rgba(34,197,94,.08) 0%, rgba(168,224,99,.12) 100%); border: 2px solid rgba(168,224,99,.4); }
.verdict-banner.imbalanced { background: linear-gradient(135deg, rgba(239,68,68,.06) 0%, rgba(239,68,68,.1) 100%); border: 2px solid rgba(239,68,68,.3); }

.verdict-icon { font-size: 3rem; margin-bottom: 12px; }
.verdict-icon.ok  { color: #15803d; }
.verdict-icon.bad { color: #c0392b; }
.verdict-title { font-size: 1.5rem; font-weight: 800; letter-spacing: -.5px; margin-bottom: 14px; }
.verdict-title.ok  { color: var(--forest); }
.verdict-title.bad { color: #c0392b; }
.verdict-eq-pill {
    display: inline-flex; align-items: center; gap: 10px;
    border-radius: 100px; padding: 10px 24px;
    font-family: 'DM Mono', monospace, 'Plus Jakarta Sans' !important;
    font-size: .88rem; font-weight: 700;
}
.verdict-eq-pill.ok  { background: var(--success-bg); color: var(--success-text); border: 1px solid var(--success-border); }
.verdict-eq-pill.bad { background: var(--danger-bg); color: var(--danger-text); border: 1px solid var(--danger-border); }

/* ── Audit warning ──────────────────────────────────────────── */
.audit-warn {
    background: #fef2f2; border-left: 4px solid #ef4444; border-radius: var(--radius-sm);
    padding: 18px 20px; display: flex; align-items: flex-start; gap: 16px;
    margin-bottom: 20px; animation: fadeUp .4s ease both; animation-delay: .24s;
}
.audit-warn-icon { font-size: 1.4rem; color: #ef4444; flex-shrink: 0; margin-top: 2px; }
.audit-warn-title { font-size: .9rem; font-weight: 800; color: #991b1b; margin-bottom: 5px; }
.audit-warn-body  { font-size: .83rem; color: var(--ink); line-height: 1.6; opacity: .8; }

/* ── Footer note ────────────────────────────────────────────── */
.audit-footer { text-align: center; font-size: .75rem; color: var(--muted); font-weight: 500; padding: 28px 0 10px; }

/* ── Modal ──────────────────────────────────────────────────── */
.modal-content { border: 0 !important; border-radius: var(--radius-lg) !important; overflow: hidden; box-shadow: var(--shadow-lg); }
.modal-header  { border-bottom: 0 !important; padding: 24px 24px 0 !important; }
.modal-body    { padding: 0 !important; }
.modal-footer  { border-top: 1px solid var(--border) !important; padding: 14px 20px !important; }
.modal-header-icon { width: 40px; height: 40px; border-radius: 11px; background: var(--lime-glow); color: var(--forest); display: flex; align-items: center; justify-content: center; font-size: 1rem; margin-bottom: 12px; }

.audit-table { width: 100%; border-collapse: collapse; }
.audit-table thead th { font-size: .67rem; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); background: var(--surface-2); padding: 12px 20px; border-bottom: 2px solid var(--border); }
.audit-table tbody tr { border-bottom: 1px solid var(--border); transition: var(--transition); }
.audit-table tbody tr:last-child { border-bottom: none; }
.audit-table tbody tr:hover { background: var(--surface-2); }
.audit-table td { padding: 12px 20px; font-size: .83rem; font-weight: 500; color: var(--ink); }
.audit-table tfoot td { padding: 14px 20px; font-size: .86rem; font-weight: 800; background: var(--forest); color: #fff; }
.audit-table tfoot .total-val { font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; color: var(--lime); }

/* ── Animate ────────────────────────────────────────────────── */
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
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
                <li class="breadcrumb-item active">Trial Balance</li>
            </ol>
        </nav>

        <!-- ═══ HERO ══════════════════════════════════════════════════════ -->
        <div class="page-header mb-4">
            <div class="hero-inner">
                <div>
                    <div class="hero-chip"><i class="bi bi-shield-check"></i>Audit Protocol V10.5</div>
                    <h1 class="hero-title">Trial Balance Proof</h1>
                    <p class="hero-sub">Mathematically verifying that Assets = Liabilities + Equity.</p>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-label">Total Assets</div>
                            <div class="hero-stat-value">KES <?= number_format($total_assets, 0) ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Liab + Equity</div>
                            <div class="hero-stat-value">KES <?= number_format($total_liabilities + $total_equity, 0) ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Net Income</div>
                            <div class="hero-stat-value lime">KES <?= number_format($net_income, 0) ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Balance Status</div>
                            <div class="hero-stat-value <?= $is_balanced ? 'balanced' : 'danger' ?>">
                                <?= $is_balanced ? '✓ Balanced' : '✗ Imbalanced' ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hero-actions">
                    <div class="dropdown">
                        <button class="btn btn-lime rounded-pill px-4 fw-bold shadow-sm dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-2"></i>Export Analysis
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;overflow:hidden">
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf me-2 text-danger"></i>Export PDF</a></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-spreadsheet me-2 text-success"></i>Export Excel</a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer me-2 text-muted"></i>Print Friendly</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>


        <!-- ═══ KPI STRIP + CHART ════════════════════════════════════════ -->
        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <div class="kpi-row h-100 mb-0" style="animation-delay:.04s">
                    <!-- Assets -->
                    <div class="kpi-card" style="animation-delay:.04s">
                        <div class="kpi-icon green"><i class="bi bi-safe2"></i></div>
                        <div class="kpi-label">Total Assets</div>
                        <div class="kpi-value">KES <?= number_format($total_assets, 2) ?></div>
                        <div class="kpi-sub"><i class="bi bi-graph-up-arrow"></i>Debit Balance</div>
                        <i class="bi bi-safe2 kpi-decor-icon"></i>
                    </div>

                    <!-- Liab + Equity (forest or danger) -->
                    <div class="kpi-card <?= $is_balanced ? 'balanced-card' : '' ?>" style="<?= !$is_balanced ? 'border-color:#fca5a5;background:#fef2f2' : '' ?>;animation-delay:.08s">
                        <div class="kpi-icon forest" style="<?= !$is_balanced ? 'background:rgba(239,68,68,.1);color:#c0392b' : '' ?>"><i class="bi bi-shield-lock"></i></div>
                        <div class="kpi-label">Liabilities & Equity</div>
                        <div class="kpi-value" style="<?= !$is_balanced ? 'color:#c0392b' : '' ?>">KES <?= number_format($total_liabilities + $total_equity, 2) ?></div>
                        <div class="kpi-sub"><i class="bi bi-shield-check"></i>Credit Balance</div>
                        <div class="kpi-balance-pill <?= $is_balanced ? 'ok' : 'bad' ?>">
                            <i class="bi <?= $is_balanced ? 'bi-check2-all' : 'bi-x-circle' ?>"></i>
                            <?= $is_balanced ? 'Balanced' : 'Imbalanced' ?>
                        </div>
                        <i class="bi bi-shield-lock kpi-decor-icon" style="<?= $is_balanced ? 'color:rgba(255,255,255,.08)' : 'color:rgba(239,68,68,.06)' ?>"></i>
                    </div>

                    <!-- Net Income (lime) -->
                    <div class="kpi-card lime-card" style="animation-delay:.12s">
                        <div class="kpi-icon ink"><i class="bi bi-graph-up-arrow"></i></div>
                        <div class="kpi-label">Net Income (P&L)</div>
                        <div class="kpi-value" style="<?= $net_income < 0 ? 'color:#c0392b' : '' ?>">KES <?= number_format($net_income, 2) ?></div>
                        <div class="kpi-sub"><i class="bi bi-stars"></i>Recorded Surplus<?= $net_income < 0 ? '/Deficit' : '' ?></div>
                        <i class="bi bi-graph-up-arrow kpi-decor-icon"></i>
                    </div>
                </div>
            </div>

            <!-- Chart -->
            <div class="col-xl-4">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title"><i class="bi bi-pie-chart-fill d-flex"></i>Equation Balance</div>
                        <span class="balance-verdict-pill <?= $is_balanced ? 'bvp-ok' : 'bvp-bad' ?>">
                            <?= $is_balanced ? 'Balanced' : 'Imbalanced' ?>
                        </span>
                    </div>
                    <div style="position:relative;height:220px">
                        <canvas id="trialBalanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ LEDGER PANELS ════════════════════════════════════════════ -->
        <div class="row g-4 mb-4">

            <!-- Assets column -->
            <div class="col-lg-6" style="animation-delay:.14s">
                <div class="ledger-panel h-100">
                    <div class="ledger-panel-header">
                        <div class="ledger-panel-header-icon lph-assets"><i class="bi bi-safe2"></i></div>
                        <div>
                            <div class="ledger-panel-title">Assets — Debit Balance</div>
                            <div class="ledger-panel-sub"><?= count($asset_cats) ?> category groups</div>
                        </div>
                    </div>
                    <?php foreach ($asset_cats as $cat_name => $cat_val):
                        $safe_id = str_replace([' ','/','-'], '_', (string)$cat_name); ?>
                    <div class="ledger-row" data-bs-toggle="modal" data-bs-target="#modal_<?= $safe_id ?>">
                        <div class="ledger-row-label">
                            <?= htmlspecialchars(strtoupper($cat_name)) ?> GROUP
                            <i class="bi bi-layers"></i>
                        </div>
                        <div class="ledger-row-right">
                            <span class="ledger-row-val asset">KES <?= number_format($cat_val, 2) ?></span>
                            <i class="bi bi-chevron-right ledger-row-arrow"></i>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="ledger-panel-footer">
                        <span class="footer-label">Total Debit Entries</span>
                        <span class="footer-total green">KES <?= number_format($total_assets, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Liabilities & Equity column -->
            <div class="col-lg-6" style="animation-delay:.18s">
                <div class="ledger-panel h-100">
                    <div class="ledger-panel-header">
                        <div class="ledger-panel-header-icon lph-liab"><i class="bi bi-shield-lock"></i></div>
                        <div>
                            <div class="ledger-panel-title">Liabilities & Equity — Credit Balance</div>
                            <div class="ledger-panel-sub"><?= count($liab_cats) + count($equity_cats) ?> category groups</div>
                        </div>
                    </div>

                    <?php foreach ($liab_cats as $cat_name => $cat_val):
                        $safe_id = str_replace([' ','/','-'], '_', (string)$cat_name); ?>
                    <div class="ledger-row" data-bs-toggle="modal" data-bs-target="#modal_<?= $safe_id ?>">
                        <div class="ledger-row-label">
                            <?= htmlspecialchars(strtoupper($cat_name)) ?> OBLIGATIONS
                            <i class="bi bi-layers"></i>
                        </div>
                        <div class="ledger-row-right">
                            <span class="ledger-row-val">KES <?= number_format($cat_val, 2) ?></span>
                            <i class="bi bi-chevron-right ledger-row-arrow"></i>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (!empty($equity_cats)): ?>
                    <div class="panel-section-label">Equity & Reserves</div>
                    <?php foreach ($equity_cats as $cat_name => $cat_val):
                        $safe_id = str_replace([' ','/','-'], '_', (string)$cat_name); ?>
                    <div class="ledger-row" data-bs-toggle="modal" data-bs-target="#modal_<?= $safe_id ?>">
                        <div class="ledger-row-label">
                            <?= htmlspecialchars(strtoupper($cat_name)) ?> PORTFOLIO
                            <i class="bi bi-layers"></i>
                        </div>
                        <div class="ledger-row-right">
                            <span class="ledger-row-val">KES <?= number_format($cat_val, 2) ?></span>
                            <i class="bi bi-chevron-right ledger-row-arrow"></i>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Net income row -->
                    <div class="net-income-row">
                        <div class="net-income-label"><i class="bi bi-stars"></i>Net Income (Surplus/Deficit)</div>
                        <span class="net-income-val <?= $net_income < 0 ? 'text-danger' : 'text-success' ?>">
                            KES <?= number_format($net_income, 2) ?>
                        </span>
                    </div>

                    <div class="ledger-panel-footer">
                        <span class="footer-label">Total Credit Entries</span>
                        <span class="footer-total">KES <?= number_format($total_liabilities + $total_equity, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ VERDICT ══════════════════════════════════════════════════ -->
        <div class="verdict-banner <?= $is_balanced ? 'balanced' : 'imbalanced' ?>">
            <div class="verdict-icon <?= $is_balanced ? 'ok' : 'bad' ?>">
                <i class="bi <?= $is_balanced ? 'bi-patch-check-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
            </div>
            <div class="verdict-title <?= $is_balanced ? 'ok' : 'bad' ?>">
                <?= $is_balanced ? 'SYSTEM BALANCED' : 'IMBALANCE DETECTED' ?>
            </div>
            <div class="verdict-eq-pill <?= $is_balanced ? 'ok' : 'bad' ?>">
                <span>Equation Difference:</span>
                <strong><?= number_format($balance_check, 4) ?></strong>
            </div>
        </div>

        <?php if (!$is_balanced): ?>
        <div class="audit-warn">
            <i class="bi bi-info-circle-fill audit-warn-icon"></i>
            <div>
                <div class="audit-warn-title">Audit Recommendation</div>
                <div class="audit-warn-body">
                    A difference of more than <strong>0.01</strong> suggests a database-level manual entry that bypassed the Double-Entry system. Please review recent manual SQL updates to the
                    <code style="background:var(--surface-2);padding:2px 8px;border-radius:5px;font-size:.8rem;color:#c0392b">ledger_accounts</code>
                    or
                    <code style="background:var(--surface-2);padding:2px 8px;border-radius:5px;font-size:.8rem;color:#c0392b">transactions</code>
                    tables.
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="audit-footer">
            Internal Audit Protocol V10.4 &bull; Generated <?= date('d M Y, H:i:s') ?> &bull; <?= SITE_NAME ?> Finance
        </div>

        <!-- ═══ CATEGORY DETAIL MODALS ═══════════════════════════════════ -->
        <?php
        $all_cats = array_unique(array_column($all_accounts, 'category'));
        foreach ($all_cats as $cat_name):
            $safe_id   = str_replace([' ','/','-'], '_', (string)$cat_name);
            $cat_total = 0;
        ?>
        <div class="modal fade" id="modal_<?= $safe_id ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <div class="modal-header-icon"><i class="bi bi-journal-text"></i></div>
                            <h5 class="fw-800 mb-1" style="color:var(--ink)">Audit Detail</h5>
                            <p class="text-muted mb-0" style="font-size:.8rem"><?= htmlspecialchars($cat_name ?: 'General') ?> group breakdown</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <table class="audit-table">
                            <thead>
                                <tr>
                                    <th>Account Name</th>
                                    <th style="text-align:right">Balance (KES)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_accounts as $acc):
                                    if ($acc['category'] != $cat_name) continue;
                                    $cat_total += (float)$acc['current_balance'];
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($acc['account_name']) ?></td>
                                    <td style="text-align:right;font-family:'DM Mono',monospace;font-weight:700"><?= number_format((float)$acc['current_balance'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Group Total</td>
                                    <td style="text-align:right" class="total-val">KES <?= number_format($cat_total, 2) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('trialBalanceChart');
    if (!ctx) return;

    const totalAssets = <?= (float)$total_assets ?>;
    const totalLiabEq = <?= (float)($total_liabilities + $total_equity) ?>;
    const isBalanced  = <?= $is_balanced ? 'true' : 'false' ?>;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Total Assets (Dr)', 'Liabilities & Equity (Cr)'],
            datasets: [{
                data: [totalAssets, totalLiabEq],
                backgroundColor: [
                    '#22c55e',
                    isBalanced ? '#1a3a2a' : '#ef4444'
                ],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 18,
                        font: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '700' }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ' KES ' + new Intl.NumberFormat('en-KE').format(ctx.raw)
                    },
                    backgroundColor: 'rgba(15,57,43,.92)',
                    titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
                    bodyFont:  { family: "'Plus Jakarta Sans', sans-serif", size: 13, weight: 'bold' },
                    padding: 12, cornerRadius: 8, displayColors: false
                }
            }
        }
    });
});
</script>
</body>
</html>