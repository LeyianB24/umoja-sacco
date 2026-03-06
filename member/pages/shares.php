<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');
require_member();
$layout = LayoutManager::create('member');

$member_id = $_SESSION['member_id'];

if (isset($_GET['msg']) && $_GET['msg'] === 'exit_requested') {
    $success_msg = "Your SACCO Exit & Share Withdrawal request has been submitted successfully and is awaiting administrative approval.";
}

require_once __DIR__ . '/../../inc/ShareValuationEngine.php';
$svEngine            = new ShareValuationEngine($conn);
$valuation           = $svEngine->getValuation();
$current_share_price = $valuation['price'];
$ownership_pct       = $svEngine->getOwnershipPercentage($member_id);

$stmt = $conn->prepare("SELECT units_owned, total_amount_paid, average_purchase_price FROM member_shareholdings WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$shareholding = $stmt->get_result()->fetch_assoc() ?? ['units_owned' => 0, 'total_amount_paid' => 0, 'average_purchase_price' => 0];

$totalUnits     = (float)$shareholding['units_owned'];
$portfolioValue = $totalUnits * $current_share_price;
$totalGain      = $portfolioValue - (float)$shareholding['total_amount_paid'];
$gainPct        = ($shareholding['total_amount_paid'] > 0) ? ($totalGain / $shareholding['total_amount_paid']) * 100 : 0;

$dividend_rate_projection = 12.5;
$projectedDividend        = $portfolioValue * ($dividend_rate_projection / 100);

require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine            = new FinancialEngine($conn);
$balances          = $engine->getBalances($member_id);
$totalCapital      = $balances['shares'];
$totalUnits        = $totalCapital / $current_share_price;
$projectedDividend = $totalCapital * ($dividend_rate_projection / 100);

$sqlHistory = "SELECT created_at, reference_no, units as share_units, unit_price, total_value, transaction_type
               FROM share_transactions WHERE member_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sqlHistory);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    $format = $_GET['action'] === 'export_excel' ? 'excel' : ($_GET['action'] === 'print_report' ? 'print' : 'pdf');
    $data = [];
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        $units  = $row['total_value'] / $current_share_price;
        $data[] = [
            'Date'       => date('d-M-Y H:i', strtotime($row['created_at'])),
            'Reference'  => $row['reference_no'],
            'Units'      => number_format((float)$units, 2),
            'Unit Price' => number_format((float)$current_share_price, 2),
            'Total Paid' => number_format((float)$row['total_value'], 2),
            'Status'     => 'Confirmed',
        ];
    }
    UniversalExportEngine::handle($format, $data, [
        'title'   => 'Share Capital Statement',
        'module'  => 'Member Portal',
        'headers' => ['Date', 'Reference', 'Units', 'Unit Price', 'Total Paid', 'Status'],
    ]);
    exit;
}

$transactions = [];
$result->data_seek(0);
while ($row = $result->fetch_assoc()) {
    $row['share_units'] = $row['total_value'] / $current_share_price;
    $row['unit_price']  = $current_share_price;
    $transactions[]     = $row;
}
$stmt->close();

$chartLabels   = [];
$chartData     = [];
$runningUnits  = 0;
foreach (array_reverse($transactions) as $txn) {
    if (in_array($txn['transaction_type'], ['purchase', 'migration'])) {
        $runningUnits += (float)$txn['share_units'];
    }
    $chartLabels[] = date('M d', strtotime($txn['created_at']));
    $chartData[]   = $runningUnits * $current_share_price;
}
$jsLabels = json_encode($chartLabels);
$jsData   = json_encode($chartData);

$pageTitle = "My Share Portfolio";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <style>
    *, *::before, *::after { box-sizing: border-box; }

    :root {
        --forest:      #0F392B;
        --forest-mid:  #1a5c43;
        --lime:        #A3E635;
        --lime-soft:   rgba(163,230,53,0.1);
        --body-bg:     #F4F8F6;
        --card-bg:     #ffffff;
        --card-border: #E8F0ED;
        --text-dark:   #0F392B;
        --text-body:   #5a7a6e;
        --text-muted:  #a0b8b0;
        --radius:      20px;
        --radius-sm:   13px;
    }
    [data-bs-theme="dark"] {
        --body-bg:    #0B1E17;
        --card-bg:    #0d2018;
        --card-border: rgba(255,255,255,0.07);
        --text-dark:  #e0f0ea;
        --text-body:  #7a9e8e;
        --text-muted: #4a6a5e;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: var(--body-bg);
        color: var(--text-dark);
    }

    .main-content-wrapper {
        margin-left: 272px;
        transition: margin-left 0.28s cubic-bezier(0.4,0,0.2,1);
        min-height: 100vh;
    }
    body.sb-collapsed .main-content-wrapper { margin-left: 76px; }
    @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; } }

    .page-inner { padding: 28px 28px 48px; }
    @media (max-width: 768px) { .page-inner { padding: 16px 14px 36px; } }

    /* ─── Page Header ─── */
    .page-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 28px;
        gap: 16px;
        flex-wrap: wrap;
    }
    .page-header h2 { font-size: 1.45rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.4px; margin: 0 0 4px; }
    .page-header p  { font-size: 0.8rem; color: var(--text-body); font-weight: 500; margin: 0; }

    .header-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

    .btn-act-primary {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 9px 20px; border-radius: var(--radius-sm);
        background: linear-gradient(135deg, #0F392B, #1a5c43);
        color: #fff; border: none;
        font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.82rem; font-weight: 800;
        text-decoration: none; cursor: pointer; transition: all 0.2s;
        box-shadow: 0 4px 14px rgba(15,57,43,0.25);
    }
    .btn-act-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(15,57,43,0.35); color: #fff; }

    .btn-act-outline {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 8px 16px; border-radius: var(--radius-sm);
        background: var(--card-bg); border: 1.5px solid var(--card-border);
        font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.82rem; font-weight: 700;
        color: var(--text-body); text-decoration: none; cursor: pointer; transition: all 0.2s;
    }
    .btn-act-outline:hover { border-color: var(--forest); color: var(--forest); }
    [data-bs-theme="dark"] .btn-act-outline:hover { border-color: var(--lime); color: var(--lime); }

    .btn-act-danger {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 8px 16px; border-radius: var(--radius-sm);
        background: var(--card-bg); border: 1.5px solid #FECACA;
        font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.82rem; font-weight: 700;
        color: #dc2626; text-decoration: none; cursor: pointer; transition: all 0.2s;
    }
    .btn-act-danger:hover { background: #FEE2E2; border-color: #dc2626; color: #991b1b; }

    /* ─── Flash ─── */
    .flash-success {
        display: flex; align-items: flex-start; gap: 12px;
        background: #D1FAE5; border: 1px solid #A7F3D0; border-radius: var(--radius-sm);
        padding: 14px 18px; margin-bottom: 24px;
        font-size: 0.82rem; font-weight: 600; color: #065f46;
        animation: fadeIn 0.35s ease both;
    }
    @keyframes fadeIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
    .flash-success i { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }

    /* ─── Hero Card ─── */
    .hero-card {
        background: linear-gradient(135deg, #0F392B 0%, #1a5c43 100%);
        border-radius: var(--radius);
        padding: 28px;
        height: 100%;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 32px rgba(15,57,43,0.28);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.22s;
    }
    .hero-card:hover { transform: translateY(-3px); }
    .hero-card::before {
        content: '';
        position: absolute; top: -50px; right: -50px;
        width: 220px; height: 220px;
        background: radial-gradient(circle, rgba(163,230,53,0.15) 0%, transparent 65%);
        border-radius: 50%; pointer-events: none;
    }
    .hero-card-badge {
        display: inline-flex; align-items: center; gap: 7px;
        background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.12);
        border-radius: 100px; padding: 5px 14px;
        font-size: 0.68rem; font-weight: 700; color: rgba(255,255,255,0.75);
        margin-bottom: 18px; width: fit-content;
    }
    .hero-card-value { font-size: 2.3rem; font-weight: 800; color: #fff; letter-spacing: -0.8px; line-height: 1; margin-bottom: 6px; }
    .hero-card-sub   { font-size: 0.78rem; color: rgba(255,255,255,0.5); font-weight: 500; margin-bottom: 22px; }
    .hero-card-divider {
        border-top: 1px solid rgba(255,255,255,0.1); padding-top: 16px;
        display: flex; align-items: center; justify-content: space-between;
    }
    .hero-card-divider .left  { font-size: 0.7rem; color: rgba(255,255,255,0.45); font-weight: 500; }
    .hero-card-divider .right { font-size: 0.82rem; font-weight: 800; color: var(--lime); display: flex; align-items: center; gap: 5px; }

    /* ─── Stat Cards ─── */
    .stat-card {
        background: var(--card-bg);
        border: 1.5px solid var(--card-border);
        border-radius: var(--radius);
        padding: 22px;
        height: 100%;
        box-shadow: 0 4px 16px rgba(15,57,43,0.05);
        transition: transform 0.22s;
    }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-label { font-size: 0.62rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.9px; color: var(--text-muted); margin-bottom: 5px; }
    .stat-value { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.3px; }
    .stat-icon  {
        width: 42px; height: 42px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; flex-shrink: 0;
    }
    .stat-icon-lime    { background: rgba(163,230,53,0.15); color: #65a30d; }
    .stat-icon-success { background: #D1FAE5; color: #059669; }
    .stat-meta { font-size: 0.72rem; font-weight: 600; color: var(--text-muted); margin-top: 6px; }
    .stat-divider { border-top: 1px solid var(--card-border); margin: 12px 0; }

    /* Mini chart card */
    .chart-card {
        background: var(--body-bg);
        border: 1.5px solid var(--card-border);
        border-radius: var(--radius);
        padding: 16px;
        height: 100%;
    }
    .chart-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .chart-card-title { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); }
    .chart-card-badge {
        display: inline-flex; align-items: center; gap: 5px;
        background: #D1FAE5; color: #059669;
        font-size: 0.62rem; font-weight: 800;
        padding: 3px 9px; border-radius: 100px;
    }
    .chart-container { position: relative; height: 130px; width: 100%; }

    /* ─── Panel Card (Table) ─── */
    .panel-card {
        background: var(--card-bg);
        border: 1.5px solid var(--card-border);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(15,57,43,0.04);
        margin-top: 20px;
    }
    .panel-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 18px 22px; border-bottom: 1px solid var(--card-border); gap: 12px; flex-wrap: wrap;
    }
    .panel-title { font-size: 0.92rem; font-weight: 800; color: var(--text-dark); margin: 0; }

    .btn-export {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 14px; border-radius: var(--radius-sm);
        background: var(--body-bg); border: 1.5px solid var(--card-border);
        font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.77rem; font-weight: 700;
        color: var(--text-body); cursor: pointer; transition: all 0.2s;
    }
    .btn-export:hover { border-color: var(--forest); color: var(--forest); }
    .export-dd {
        border: 1.5px solid var(--card-border) !important; border-radius: 14px !important;
        box-shadow: 0 10px 36px rgba(15,57,43,0.1) !important;
        padding: 6px !important; font-family: 'Plus Jakarta Sans', sans-serif; background: var(--card-bg) !important;
    }
    .export-dd-item {
        display: flex; align-items: center; gap: 9px; padding: 8px 12px;
        border-radius: 10px; font-size: 0.8rem; font-weight: 600;
        color: var(--text-dark); text-decoration: none; transition: background 0.15s;
    }
    .export-dd-item:hover { background: var(--body-bg); }
    .export-dd-icon { width: 26px; height: 26px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; flex-shrink: 0; }

    /* ─── Table ─── */
    .sh-table { width: 100%; border-collapse: collapse; }
    .sh-table thead th {
        font-size: 0.62rem; font-weight: 800; text-transform: uppercase;
        letter-spacing: 0.9px; color: var(--text-muted); padding: 10px 18px;
        background: var(--body-bg); border-bottom: 1px solid var(--card-border); white-space: nowrap;
    }
    .sh-table tbody tr { transition: background 0.15s; }
    .sh-table tbody tr:hover { background: var(--body-bg); }
    .sh-table tbody td { padding: 13px 18px; border-bottom: 1px solid var(--card-border); vertical-align: middle; font-size: 0.83rem; }
    .sh-table tbody tr:last-child td { border-bottom: none; }

    .ref-chip {
        display: inline-flex; align-items: center;
        background: var(--body-bg); border: 1px solid var(--card-border);
        border-radius: 8px; padding: 3px 9px;
        font-size: 0.7rem; font-weight: 700; color: var(--text-muted);
        font-family: monospace;
    }
    .unit-chip {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: 0.83rem; font-weight: 700; color: var(--text-dark);
    }
    .unit-chip-icon {
        width: 22px; height: 22px; border-radius: 7px;
        background: #D1FAE5; color: #059669;
        display: flex; align-items: center; justify-content: center; font-size: 0.6rem;
    }
    .status-badge {
        display: inline-flex; align-items: center; gap: 5px;
        background: #D1FAE5; color: #059669;
        font-size: 0.63rem; font-weight: 800; padding: 3px 10px;
        border-radius: 100px; text-transform: uppercase; letter-spacing: 0.5px;
    }
    .status-badge::before { content:''; width:5px; height:5px; border-radius:50%; background:#059669; }

    /* DataTables overrides */
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        background: var(--card-bg); border: 1.5px solid var(--card-border);
        border-radius: 10px; padding: 5px 10px;
        font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.8rem; color: var(--text-dark);
        outline: none;
    }
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_filter label,
    .dataTables_wrapper .dataTables_length label { font-size: 0.78rem; color: var(--text-muted); font-weight: 600; }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: 9px !important; font-size: 0.78rem !important; font-weight: 700 !important;
        color: var(--text-body) !important; border: none !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: var(--forest) !important; color: #fff !important; border: none !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: var(--body-bg) !important; color: var(--forest) !important;
    }
    </style>
</head>
<body>
<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content-wrapper">
        <?php $layout->topbar($pageTitle ?? ''); ?>

        <div class="page-inner">

            <!-- Flash -->
            <?php if (isset($success_msg)): ?>
            <div class="flash-success">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <div style="font-weight:800;margin-bottom:2px;">Request Submitted</div>
                    <?= $success_msg ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h2>Share Portfolio</h2>
                    <p>Equity value based on corporate valuation.</p>
                </div>
                <div class="header-actions">
                    <div class="dropdown">
                        <button class="btn-export dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download"></i> Report
                        </button>
                        <ul class="dropdown-menu export-dd">
                            <li><a class="export-dd-item" href="?<?= http_build_query(array_merge($_GET, ['action'=>'export_pdf'])) ?>">
                                <span class="export-dd-icon" style="background:#FEE2E2;color:#dc2626;"><i class="bi bi-file-pdf-fill"></i></span> Export PDF
                            </a></li>
                            <li><a class="export-dd-item" href="?<?= http_build_query(array_merge($_GET, ['action'=>'export_excel'])) ?>">
                                <span class="export-dd-icon" style="background:#D1FAE5;color:#059669;"><i class="bi bi-file-earmark-spreadsheet-fill"></i></span> Export Excel
                            </a></li>
                            <li><a class="export-dd-item" href="?<?= http_build_query(array_merge($_GET, ['action'=>'print_report'])) ?>" target="_blank">
                                <span class="export-dd-icon" style="background:#EEF2FF;color:#6366f1;"><i class="bi bi-printer-fill"></i></span> Print Report
                            </a></li>
                        </ul>
                    </div>
                    <a href="<?= BASE_URL ?>/member/pages/withdraw.php?type=wallet&source=shares" class="btn-act-outline">
                        <i class="bi bi-cash-stack"></i> Withdraw Dividends
                    </a>
                    <a href="<?= BASE_URL ?>/member/pages/withdraw.php?type=shares&source=shares" class="btn-act-danger">
                        <i class="bi bi-door-open"></i> Quit SACCO
                    </a>
                    <a href="<?= BASE_URL ?>/member/pages/mpesa_request.php?type=shares" class="btn-act-primary">
                        <i class="bi bi-plus-lg"></i> Buy Shares
                    </a>
                </div>
            </div>

            <!-- Stat Cards Row -->
            <div class="row g-3 mb-4">

                <!-- Hero Portfolio Value -->
                <div class="col-xl-4 col-lg-5 col-md-12">
                    <div class="hero-card">
                        <div>
                            <div class="hero-card-badge">
                                <i class="bi bi-wallet2"></i> Capital Balance
                            </div>
                            <div class="hero-card-value">KES <?= number_format((float)$portfolioValue, 2) ?></div>
                            <div class="hero-card-sub">Current Portfolio Value</div>
                        </div>
                        <div class="hero-card-divider">
                            <span class="left">Est. Capital Growth</span>
                            <span class="right"><i class="bi bi-graph-up-arrow"></i> <?= number_format((float)$gainPct, 2) ?>%</span>
                        </div>
                    </div>
                </div>

                <!-- Right column 2 stats + chart -->
                <div class="col-xl-8 col-lg-7 col-md-12">
                    <div class="row g-3 h-100">

                        <!-- Ownership Units -->
                        <div class="col-md-6">
                            <div class="stat-card">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <div>
                                        <div class="stat-label">Ownership Units</div>
                                        <div class="stat-value"><?= number_format((float)$totalUnits, 4) ?></div>
                                    </div>
                                    <div class="stat-icon stat-icon-lime"><i class="bi bi-pie-chart-fill"></i></div>
                                </div>
                                <div class="stat-divider"></div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="stat-meta">Ownership Share</span>
                                    <span style="font-size:0.78rem;font-weight:800;color:var(--forest);"><?= number_format((float)$ownership_pct, 4) ?>% of Sacco</span>
                                </div>
                            </div>
                        </div>

                        <!-- Projected Dividend -->
                        <div class="col-md-6">
                            <div class="stat-card">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <div>
                                        <div class="stat-label">Projected Dividend</div>
                                        <div class="stat-value">KES <?= number_format((float)$projectedDividend, 0) ?></div>
                                    </div>
                                    <div class="stat-icon stat-icon-success"><i class="bi bi-award-fill"></i></div>
                                </div>
                                <div class="stat-divider"></div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="stat-meta">At <?= $dividend_rate_projection ?>% projected rate</span>
                                    <span style="font-size:0.72rem;font-weight:800;color:#059669;">Annual Est.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Growth Chart -->
                        <div class="col-12">
                            <div class="chart-card">
                                <div class="chart-card-head">
                                    <span class="chart-card-title">Portfolio Growth</span>
                                    <span class="chart-card-badge"><i class="bi bi-graph-up-arrow" style="font-size:0.55rem;"></i> Active</span>
                                </div>
                                <div class="chart-container">
                                    <canvas id="growthChart"></canvas>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- Transaction History -->
            <div class="panel-card">
                <div class="panel-head">
                    <h6 class="panel-title">Transaction History</h6>
                    <button class="btn-export" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
                <div class="table-responsive p-3">
                    <table id="historyTable" class="sh-table w-100">
                        <thead>
                            <tr>
                                <th style="padding-left:20px;">Date</th>
                                <th>Reference</th>
                                <th>Units</th>
                                <th>Unit Price</th>
                                <th>Total Paid</th>
                                <th style="text-align:center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $row): ?>
                            <tr>
                                <td style="padding-left:20px;">
                                    <div style="font-size:0.83rem;font-weight:700;color:var(--text-dark);"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                                    <div style="font-size:0.68rem;color:var(--text-muted);font-weight:500;"><?= date('H:i A', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td><span class="ref-chip"><?= htmlspecialchars($row['reference_no']) ?></span></td>
                                <td>
                                    <span class="unit-chip">
                                        <span class="unit-chip-icon"><i class="bi bi-plus-lg"></i></span>
                                        <?= number_format((float)$row['share_units'], 2) ?>
                                    </span>
                                </td>
                                <td style="color:var(--text-body);font-weight:600;">KES <?= number_format((float)$row['unit_price'], 0) ?></td>
                                <td style="font-weight:800;color:var(--text-dark);">KES <?= number_format((float)$row['total_value'], 2) ?></td>
                                <td style="text-align:center;"><span class="status-badge">Confirmed</span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transactions)): ?>
                            <tr><td colspan="6">
                                <div style="padding:44px;text-align:center;">
                                    <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--text-muted);opacity:0.4;"></i>
                                    <p style="font-size:0.82rem;font-weight:600;color:var(--text-muted);margin:0;">No share transactions found.</p>
                                </div>
                            </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php $layout->footer(); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    $('#historyTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        dom: '<"d-flex justify-content-between align-items-center mb-3"fl>t<"d-flex justify-content-between align-items-center mt-3"ip>'
    });
});

// Growth Chart
const ctx = document.getElementById('growthChart').getContext('2d');
const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
const grad = ctx.createLinearGradient(0, 0, 0, 130);
grad.addColorStop(0, 'rgba(163,230,53,0.35)');
grad.addColorStop(1, 'rgba(163,230,53,0.0)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= $jsLabels ?>,
        datasets: [{
            data: <?= $jsData ?>,
            borderColor: '#65a30d',
            borderWidth: 2,
            backgroundColor: grad,
            fill: true,
            tension: 0.4,
            pointRadius: 0,
            pointHoverRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0F392B',
                titleColor: '#A3E635',
                bodyColor: '#fff',
                padding: 10,
                cornerRadius: 8,
                titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
                bodyFont:  { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
                callbacks: { label: ctx => ' KES ' + ctx.parsed.y.toLocaleString() }
            }
        },
        scales: { x: { display: false }, y: { display: false } }
    }
});
</script>
</body>
</html>