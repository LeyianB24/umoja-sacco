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

$member_id  = $_SESSION['member_id'];
$typeFilter = $_GET['type']       ?? '';
$startDate  = $_GET['start_date'] ?? '';
$endDate    = $_GET['end_date']   ?? '';

$allowedTypes = ['deposit', 'contribution', 'savings_deposit', 'withdrawal', 'withdrawal_initiate', 'withdrawal_finalize'];
$where  = "WHERE member_id = ? AND transaction_type IN ('" . implode("','", $allowedTypes) . "')";
$params = [$member_id];
$types  = "i";

if ($typeFilter === 'deposit') {
    $where .= " AND transaction_type IN ('deposit', 'contribution', 'savings_deposit')";
} elseif ($typeFilter === 'withdrawal') {
    $where .= " AND transaction_type IN ('withdrawal', 'withdrawal_initiate', 'withdrawal_finalize')";
}
if ($startDate && $endDate) {
    $where   .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types   .= "ss";
}

require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine           = new FinancialEngine($conn);
$balances         = $engine->getBalances($member_id);
$netSavings       = (float)($balances['savings'] ?? 0.0);
$totalSavings     = $engine->getLifetimeCredits($member_id, 'savings');
$totalWithdrawals = $engine->getCategoryWithdrawals($member_id, 'savings');

$sqlHistory = "SELECT * FROM transactions $where ORDER BY created_at DESC";
$stmt = $conn->prepare($sqlHistory);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$history = $stmt->get_result();

if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    $format = $_GET['action'] === 'export_excel' ? 'excel' : ($_GET['action'] === 'print_report' ? 'print' : 'pdf');
    $data = [];
    $history->data_seek(0);
    while ($row = $history->fetch_assoc()) {
        $isDeposit = in_array(strtolower($row['transaction_type']), ['deposit', 'contribution', 'income']);
        $data[] = [
            'Date'   => date('d-M-Y H:i', strtotime($row['created_at'])),
            'Type'   => ucwords(str_replace('_', ' ', $row['transaction_type'])),
            'Notes'  => $row['notes'] ?: '-',
            'Amount' => ($isDeposit ? '+' : '-') . ' ' . number_format((float)$row['amount'], 2),
        ];
    }
    UniversalExportEngine::handle($format, $data, [
        'title'   => 'Savings Statement',
        'module'  => 'Member Portal',
        'headers' => ['Date', 'Type', 'Notes', 'Amount'],
    ]);
    exit;
}

$pageTitle = "My Savings";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

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

    /* ─── Layout ─── */
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
    .page-header h2 {
        font-size: 1.45rem;
        font-weight: 800;
        color: var(--text-dark);
        letter-spacing: -0.4px;
        margin: 0 0 4px;
    }
    .page-header p { font-size: 0.8rem; color: var(--text-body); font-weight: 500; margin: 0; }

    .btn-page-back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: var(--radius-sm);
        background: var(--card-bg);
        border: 1.5px solid var(--card-border);
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.79rem;
        font-weight: 700;
        color: var(--text-body);
        text-decoration: none;
        transition: all 0.2s;
    }
    .btn-page-back:hover { border-color: var(--forest); color: var(--forest); }
    [data-bs-theme="dark"] .btn-page-back:hover { border-color: var(--lime); color: var(--lime); }

    /* ─── Stat Cards ─── */
    .stat-card {
        border-radius: var(--radius);
        padding: 24px;
        height: 100%;
        transition: transform 0.22s, box-shadow 0.22s;
        position: relative;
        overflow: hidden;
    }
    .stat-card:hover { transform: translateY(-3px); }

    .stat-card-forest {
        background: linear-gradient(135deg, #0F392B 0%, #1a5c43 100%);
        box-shadow: 0 8px 28px rgba(15,57,43,0.25);
        color: #fff;
    }
    .stat-card-forest::before {
        content: '';
        position: absolute;
        top: -40px; right: -40px;
        width: 160px; height: 160px;
        background: radial-gradient(circle, rgba(163,230,53,0.15) 0%, transparent 65%);
        border-radius: 50%;
        pointer-events: none;
    }

    .stat-card-neutral {
        background: var(--card-bg);
        border: 1.5px solid var(--card-border);
        box-shadow: 0 4px 16px rgba(15,57,43,0.05);
    }

    .stat-icon {
        width: 42px; height: 42px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
        margin-bottom: 16px;
        flex-shrink: 0;
    }
    .stat-card-forest .stat-icon { background: rgba(163,230,53,0.15); color: var(--lime); }
    .stat-icon-success { background: #D1FAE5; color: #059669; }
    .stat-icon-danger  { background: #FEE2E2; color: #dc2626; }

    .stat-label {
        font-size: 0.63rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        margin-bottom: 6px;
    }
    .stat-card-forest .stat-label { color: rgba(255,255,255,0.55); }
    .stat-card-neutral .stat-label { color: var(--text-muted); }

    .stat-value {
        font-size: 1.55rem;
        font-weight: 800;
        letter-spacing: -0.4px;
        line-height: 1;
        margin-bottom: 14px;
    }
    .stat-card-forest .stat-value { color: #fff; }
    .stat-card-neutral .stat-value { color: var(--text-dark); }

    .stat-progress {
        height: 5px;
        background: rgba(255,255,255,0.15);
        border-radius: 100px;
        overflow: hidden;
        margin-bottom: 6px;
    }
    .stat-card-neutral .stat-progress { background: var(--body-bg); }
    .stat-progress-fill { height: 100%; border-radius: 100px; }

    .stat-meta { font-size: 0.72rem; font-weight: 500; }
    .stat-card-forest .stat-meta { color: rgba(255,255,255,0.45); }
    .stat-card-neutral .stat-meta { color: var(--text-muted); }

    .stat-card-forest .stat-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: rgba(255,255,255,0.12);
        color: rgba(255,255,255,0.8);
        font-size: 0.66rem;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 100px;
        margin-bottom: 14px;
    }
    .forest-divider {
        border-top: 1px solid rgba(255,255,255,0.1);
        padding-top: 14px;
        margin-top: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .forest-divider .left  { font-size: 0.72rem; color: rgba(255,255,255,0.45); font-weight: 500; }
    .forest-divider .right { font-size: 0.72rem; color: var(--lime); font-weight: 700; display: flex; align-items: center; gap: 4px; }

    /* ─── Action Bar ─── */
    .action-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }
    .action-btns { display: flex; gap: 8px; flex-wrap: wrap; }

    .btn-act-primary {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 20px;
        border-radius: var(--radius-sm);
        background: linear-gradient(135deg, #0F392B, #1a5c43);
        color: #fff;
        border: none;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.82rem;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 4px 14px rgba(15,57,43,0.25);
    }
    .btn-act-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(15,57,43,0.35); color: #fff; }

    .btn-act-outline {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 18px;
        border-radius: var(--radius-sm);
        background: var(--card-bg);
        border: 1.5px solid var(--card-border);
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--text-body);
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-act-outline:hover { border-color: var(--forest); color: var(--forest); }
    [data-bs-theme="dark"] .btn-act-outline:hover { border-color: var(--lime); color: var(--lime); }

    /* ─── Filter Bar ─── */
    .filter-card {
        background: var(--card-bg);
        border: 1.5px solid var(--card-border);
        border-radius: var(--radius-sm);
        padding: 8px 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .filter-select,
    .filter-input {
        background: transparent;
        border: none;
        outline: none;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-body);
        padding: 4px 6px;
        min-width: 120px;
    }
    .filter-select option { color: #0F392B; }
    [data-bs-theme="dark"] .filter-select option { color: #e0f0ea; }
    .filter-sep { width: 1px; height: 20px; background: var(--card-border); flex-shrink: 0; }
    .filter-btn {
        width: 34px; height: 34px;
        border-radius: 10px;
        background: linear-gradient(135deg, #0F392B, #1a5c43);
        border: none;
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .filter-btn:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(15,57,43,0.3); }

    /* ─── Table Panel ─── */
    .panel-card {
        background: var(--card-bg);
        border: 1.5px solid var(--card-border);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(15,57,43,0.04);
    }
    .panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 22px;
        border-bottom: 1px solid var(--card-border);
        gap: 12px;
        flex-wrap: wrap;
    }
    .panel-title {
        font-size: 0.92rem;
        font-weight: 800;
        color: var(--text-dark);
        margin: 0;
    }

    /* Export dropdown */
    .btn-export {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border-radius: var(--radius-sm);
        background: var(--body-bg);
        border: 1.5px solid var(--card-border);
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.77rem;
        font-weight: 700;
        color: var(--text-body);
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-export:hover { border-color: var(--forest); color: var(--forest); }
    .export-dd {
        border: 1.5px solid var(--card-border) !important;
        border-radius: 14px !important;
        box-shadow: 0 10px 36px rgba(15,57,43,0.1) !important;
        padding: 6px !important;
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: var(--card-bg) !important;
    }
    .export-dd-item {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 8px 12px;
        border-radius: 10px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-dark);
        text-decoration: none;
        transition: background 0.15s;
    }
    .export-dd-item:hover { background: var(--body-bg); }
    .export-dd-icon {
        width: 26px; height: 26px;
        border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.75rem;
        flex-shrink: 0;
    }

    /* ─── Table ─── */
    .sv-table { width: 100%; border-collapse: collapse; }
    .sv-table thead th {
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        color: var(--text-muted);
        padding: 10px 20px;
        background: var(--body-bg);
        border-bottom: 1px solid var(--card-border);
        white-space: nowrap;
    }
    .sv-table tbody tr { transition: background 0.15s; }
    .sv-table tbody tr:hover { background: var(--body-bg); }
    .sv-table tbody td {
        padding: 14px 20px;
        border-bottom: 1px solid var(--card-border);
        vertical-align: middle;
        font-size: 0.84rem;
    }
    .sv-table tbody tr:last-child td { border-bottom: none; }

    .txn-icon {
        width: 36px; height: 36px;
        border-radius: 11px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem;
        flex-shrink: 0;
    }
    .txn-icon-in  { background: #D1FAE5; color: #059669; }
    .txn-icon-out { background: #FEE2E2; color: #dc2626; }

    .txn-type  { font-size: 0.83rem; font-weight: 700; color: var(--text-dark); text-transform: capitalize; }
    .txn-notes { font-size: 0.71rem; font-weight: 500; color: var(--text-muted); }
    .txn-date  { font-size: 0.8rem; font-weight: 700; color: var(--text-dark); }
    .txn-time  { font-size: 0.68rem; color: var(--text-muted); font-weight: 500; }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #D1FAE5;
        color: #059669;
        font-size: 0.63rem;
        font-weight: 800;
        padding: 3px 10px;
        border-radius: 100px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: #059669; }

    .amount-in  { font-size: 0.9rem; font-weight: 800; color: #059669; }
    .amount-out { font-size: 0.9rem; font-weight: 800; color: #dc2626; }

    /* Empty state */
    .empty-state { padding: 52px 20px; text-align: center; }
    .empty-state i { font-size: 2.2rem; display: block; margin-bottom: 10px; color: var(--text-muted); opacity: 0.5; }
    .empty-state p { font-size: 0.84rem; font-weight: 600; color: var(--text-muted); margin: 0; }
    </style>
</head>
<body>

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content-wrapper">
        <?php $layout->topbar($pageTitle ?? ''); ?>

        <div class="page-inner">

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h2>Savings Overview</h2>
                    <p>Track your financial growth and transaction history.</p>
                </div>
                <a href="<?= BASE_URL ?>/member/pages/dashboard.php" class="btn-page-back">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <!-- Stat Cards -->
            <div class="row g-3 mb-4">

                <!-- Net Balance -->
                <div class="col-xl-4 col-md-12">
                    <div class="stat-card stat-card-forest">
                        <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
                        <div class="stat-badge"><i class="bi bi-circle-fill" style="font-size:0.4rem;"></i> Active</div>
                        <div class="stat-label">Net Balance</div>
                        <div class="stat-value">KES <?= number_format((float)$netSavings, 2) ?></div>
                        <div class="forest-divider">
                            <span class="left">Available for withdrawal</span>
                            <span class="right"><i class="bi bi-graph-up-arrow"></i> Growing</span>
                        </div>
                    </div>
                </div>

                <!-- Total Savings -->
                <div class="col-xl-4 col-md-6">
                    <div class="stat-card stat-card-neutral">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="stat-label">Total Savings</div>
                                <div class="stat-value">KES <?= number_format((float)$totalSavings, 2) ?></div>
                            </div>
                            <div class="stat-icon stat-icon-success"><i class="bi bi-arrow-down-left"></i></div>
                        </div>
                        <div class="stat-progress">
                            <div class="stat-progress-fill bg-success" style="width:75%;"></div>
                        </div>
                        <div class="stat-meta">Lifetime accumulated savings</div>
                    </div>
                </div>

                <!-- Total Withdrawals -->
                <div class="col-xl-4 col-md-6">
                    <div class="stat-card stat-card-neutral">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="stat-label">Total Withdrawals</div>
                                <div class="stat-value">KES <?= number_format((float)$totalWithdrawals, 2) ?></div>
                            </div>
                            <div class="stat-icon stat-icon-danger"><i class="bi bi-arrow-up-right"></i></div>
                        </div>
                        <div class="stat-progress">
                            <div class="stat-progress-fill bg-danger" style="width:25%;"></div>
                        </div>
                        <div class="stat-meta">Funds moved to M-Pesa</div>
                    </div>
                </div>

            </div>

            <!-- Action Bar + Filter -->
            <div class="action-bar">
                <div class="action-btns">
                    <a href="<?= BASE_URL ?>/member/pages/mpesa_request.php?type=savings" class="btn-act-primary">
                        <i class="bi bi-plus-lg"></i> Deposit
                    </a>
                    <a href="<?= BASE_URL ?>/member/pages/withdraw.php?type=savings&source=savings" class="btn-act-outline">
                        <i class="bi bi-dash-lg"></i> Withdraw
                    </a>
                </div>

                <form method="GET" class="filter-card">
                    <select name="type" class="filter-select">
                        <option value="">All Savings & Withdrawals</option>
                        <option value="deposit"    <?= $typeFilter === 'deposit'    ? 'selected' : '' ?>>All Deposits</option>
                        <option value="withdrawal" <?= $typeFilter === 'withdrawal' ? 'selected' : '' ?>>All Withdrawals</option>
                    </select>
                    <div class="filter-sep"></div>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="filter-input" placeholder="From">
                    <div class="filter-sep"></div>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="filter-input" placeholder="To">
                    <button type="submit" class="filter-btn" title="Apply Filter">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>

            <!-- Transaction Table -->
            <div class="panel-card">
                <div class="panel-head">
                    <h6 class="panel-title">Transaction History</h6>
                    <div class="dropdown">
                        <button class="btn-export dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download"></i> Export Statement
                        </button>
                        <ul class="dropdown-menu export-dd">
                            <li>
                                <a class="export-dd-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>">
                                    <span class="export-dd-icon" style="background:#FEE2E2;color:#dc2626;"><i class="bi bi-file-pdf-fill"></i></span> Export PDF
                                </a>
                            </li>
                            <li>
                                <a class="export-dd-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>">
                                    <span class="export-dd-icon" style="background:#D1FAE5;color:#059669;"><i class="bi bi-file-earmark-spreadsheet-fill"></i></span> Export Excel
                                </a>
                            </li>
                            <li>
                                <a class="export-dd-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank">
                                    <span class="export-dd-icon" style="background:#EEF2FF;color:#6366f1;"><i class="bi bi-printer-fill"></i></span> Print Statement
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="sv-table">
                        <thead>
                            <tr>
                                <th style="padding-left:22px;">Details</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th style="text-align:right;padding-right:22px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history->num_rows > 0):
                                while ($row = $history->fetch_assoc()):
                                    $isDeposit = in_array(strtolower($row['transaction_type']), ['deposit','contribution','income']);
                                    $iconClass = $isDeposit ? 'txn-icon-in' : 'txn-icon-out';
                                    $icon      = $isDeposit ? 'bi-arrow-down-left' : 'bi-arrow-up-right';
                                    $sign      = $isDeposit ? '+' : '-';
                                    $amtClass  = $isDeposit ? 'amount-in' : 'amount-out';
                            ?>
                            <tr>
                                <td style="padding-left:22px;">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="txn-icon <?= $iconClass ?>"><i class="bi <?= $icon ?>"></i></div>
                                        <div>
                                            <div class="txn-type"><?= $row['transaction_type'] ?></div>
                                            <div class="txn-notes"><?= htmlspecialchars($row['notes'] ?? 'M-Pesa Transaction') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="txn-date"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                                    <div class="txn-time"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td><span class="status-badge">Completed</span></td>
                                <td style="text-align:right;padding-right:22px;">
                                    <span class="<?= $amtClass ?>"><?= $sign ?> KES <?= number_format((float)$row['amount'], 2) ?></span>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        <p>No records found for this selection.</p>
                                    </div>
                                </td>
                            </tr>
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
</body>
</html>