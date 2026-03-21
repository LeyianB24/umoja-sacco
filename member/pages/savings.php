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
        --forest-light:#2d7a5e;
        --lime:        #A3E635;
        --lime-soft:   rgba(163,230,53,0.1);
        --body-bg:     #F4F8F6;
        --card-bg:     #ffffff;
        --card-bdr:    #E8F0ED;
        --text-dark:   #0F392B;
        --text-body:   #5a7a6e;
        --text-muted:  #a0b8b0;
        --radius:      24px;
        --radius-sm:   14px;
        --shadow:      0 4px 20px rgba(15,57,43,0.06);
        --shadow-h:    0 12px 36px rgba(15,57,43,0.12);
        --transition:  all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    [data-bs-theme="dark"] {
        --body-bg:    #0B1E17;
        --card-bg:    #0d2018;
        --card-bdr:   rgba(255,255,255,0.07);
        --text-dark:  #e0f0ea;
        --text-body:  #7a9e8e;
        --text-muted: #4a6a5e;
        --shadow:     0 4px 20px rgba(0,0,0,0.3);
        --shadow-h:   0 12px 36px rgba(0,0,0,0.5);
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: var(--body-bg);
        color: var(--text-dark);
        opacity: 0;
        animation: fadeIn 0.6s ease-out forwards;
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

    /* ─── Layout ─── */
    .main-content-wrapper {
        margin-left: 272px;
        transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        min-height: 100vh;
    }
    body.sb-collapsed .main-content-wrapper { margin-left: 76px; }
    @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; } }

    .page-inner { padding: 32px 32px 60px; }
    @media (max-width: 768px) { .page-inner { padding: 20px 16px 40px; } }

    /* ─── Page Header ─── */
    .page-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 32px;
        gap: 20px;
        flex-wrap: wrap;
    }
    .page-header h2 {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text-dark);
        letter-spacing: -0.6px;
        margin: 0 0 6px;
    }
    .page-header p { font-size: 0.85rem; color: var(--text-body); font-weight: 500; margin: 0; }

    .btn-page-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: var(--radius-sm);
        background: var(--card-bg);
        border: 1.5px solid var(--card-bdr);
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-body);
        text-decoration: none;
        transition: var(--transition);
        box-shadow: var(--shadow);
    }
    .btn-page-back:hover { 
        border-color: var(--forest); 
        color: var(--forest); 
        transform: translateX(-4px);
        box-shadow: var(--shadow-h);
    }
    [data-bs-theme="dark"] .btn-page-back:hover { border-color: var(--lime); color: var(--lime); }

    /* ─── Stat Cards ─── */
    .stat-card {
        border-radius: var(--radius);
        padding: 28px;
        height: 100%;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
        border: 1.5px solid transparent;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-h); }

    .stat-card-forest {
        background: linear-gradient(135deg, #0F392B 0%, #1a5c43 100%);
        color: #fff;
        box-shadow: 0 10px 30px rgba(15,57,43,0.25);
    }
    /* Mesh Gradient Effect */
    .stat-card-forest::after {
        content: '';
        position: absolute;
        top: -50%; left: -50%;
        width: 200%; height: 200%;
        background: radial-gradient(circle, rgba(163,230,53,0.1) 0%, transparent 60%);
        pointer-events: none;
        animation: meshRotate 10s linear infinite;
    }
    @keyframes meshRotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

    .stat-card-neutral {
        background: var(--card-bg);
        border: 1.5px solid var(--card-bdr);
        box-shadow: var(--shadow);
    }

    .stat-icon {
        width: 48px; height: 48px;
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem;
        margin-bottom: 20px;
        flex-shrink: 0;
        transition: var(--transition);
    }
    .stat-card:hover .stat-icon { transform: scale(1.1) rotate(5deg); }

    .stat-card-forest .stat-icon { background: rgba(163,230,53,0.15); color: var(--lime); }
    .stat-icon-success { background: #D1FAE5; color: #059669; }
    .stat-icon-danger  { background: #FEE2E2; color: #dc2626; }

    .stat-label {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        margin-bottom: 8px;
        color: var(--text-muted);
    }
    .stat-card-forest .stat-label { color: rgba(255,255,255,0.5); }

    .stat-value {
        font-size: 1.85rem;
        font-weight: 800;
        letter-spacing: -0.8px;
        line-height: 1.1;
        margin-bottom: 16px;
    }
    .stat-card-forest .stat-value { color: #fff; }
    .stat-card-neutral .stat-value { color: var(--text-dark); }

    .stat-progress {
        height: 6px;
        background: rgba(255,255,255,0.1);
        border-radius: 100px;
        overflow: hidden;
        margin-bottom: 8px;
    }
    .stat-card-neutral .stat-progress { background: var(--body-bg); }
    .stat-progress-fill { height: 100%; border-radius: 100px; transition: width 1s ease-out; }

    .stat-meta { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); }
    .stat-card-forest .stat-meta { color: rgba(255,255,255,0.4); }

    .stat-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.9);
        font-size: 0.65rem;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 100px;
        margin-bottom: 16px;
        backdrop-filter: blur(4px);
    }
    
    .forest-divider {
        border-top: 1px solid rgba(255,255,255,0.1);
        padding-top: 16px;
        margin-top: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .forest-divider .left  { font-size: 0.75rem; color: rgba(255,255,255,0.4); font-weight: 500; }
    .forest-divider .right { font-size: 0.75rem; color: var(--lime); font-weight: 700; display: flex; align-items: center; gap: 6px; }

    /* ─── Action Bar ─── */
    .action-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }
    .action-btns { display: flex; gap: 12px; flex-wrap: wrap; }

    .btn-act {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 12px 24px;
        border-radius: var(--radius-sm);
        font-size: 0.85rem;
        font-weight: 800;
        text-decoration: none;
        transition: var(--transition);
        cursor: pointer;
    }
    .btn-act-primary {
        background: linear-gradient(135deg, #0F392B, #1a5c43);
        color: #fff;
        border: none;
        box-shadow: 0 8px 20px rgba(15,57,43,0.2);
    }
    .btn-act-primary:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 12px 28px rgba(15,57,43,0.3); 
        color: #fff; 
    }

    .btn-act-outline {
        background: var(--card-bg);
        border: 1.5px solid var(--card-bdr);
        color: var(--text-body);
        box-shadow: var(--shadow);
    }
    .btn-act-outline:hover { 
        border-color: var(--forest); 
        color: var(--forest); 
        transform: translateY(-2px);
        box-shadow: var(--shadow-h);
    }
    [data-bs-theme="dark"] .btn-act-outline:hover { border-color: var(--lime); color: var(--lime); }

    /* ─── Filter Bar ─── */
    .filter-card {
        background: var(--card-bg);
        border: 1.5px solid var(--card-bdr);
        border-radius: var(--radius-sm);
        padding: 6px 10px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        box-shadow: var(--shadow);
    }
    .filter-group { display: flex; align-items: center; gap: 8px; }
    .filter-icon { font-size: 0.85rem; color: var(--text-muted); }

    .filter-select,
    .filter-input {
        background: transparent;
        border: none;
        outline: none;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-body);
        padding: 6px;
        min-width: 130px;
        cursor: pointer;
    }
    .filter-select option { background: var(--card-bg); color: var(--text-dark); }

    .filter-sep { width: 1.5px; height: 24px; background: var(--card-bdr); flex-shrink: 0; }

    .filter-btn {
        width: 38px; height: 38px;
        border-radius: 12px;
        background: linear-gradient(135deg, #0F392B, #2d7a5e);
        border: none;
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        transition: var(--transition);
        cursor: pointer;
    }
    .filter-btn:hover { 
        transform: scale(1.08) rotate(3deg); 
        box-shadow: 0 8px 20px rgba(15,57,43,0.3); 
    }

    /* ─── Table Panel ─── */
    .panel-card {
        background: var(--card-bg);
        border: 1.5px solid var(--card-bdr);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    .panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 24px 28px;
        border-bottom: 1px solid var(--card-bdr);
        gap: 16px;
        flex-wrap: wrap;
    }
    .panel-title {
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--text-dark);
        margin: 0;
        letter-spacing: -0.3px;
    }

    .btn-export {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 18px;
        border-radius: 100px;
        background: var(--body-bg);
        border: 1.5px solid var(--card-bdr);
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-body);
        cursor: pointer;
        transition: var(--transition);
    }
    .btn-export:hover { border-color: var(--forest-light); color: var(--forest); background: #fff; }

    /* ─── Table ─── */
    .sv-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .sv-table thead th {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: var(--text-muted);
        padding: 16px 28px;
        background: var(--body-bg);
        border-bottom: 1px solid var(--card-bdr);
        white-space: nowrap;
    }
    .sv-table tbody tr { transition: var(--transition); }
    .sv-table tbody tr:hover { background: rgba(163,230,53,0.03); }
    .sv-table tbody td {
        padding: 18px 28px;
        border-bottom: 1px solid var(--card-bdr);
        vertical-align: middle;
        font-size: 0.88rem;
    }
    .sv-table tbody tr:last-child td { border-bottom: none; }

    .txn-info-wrap { display: flex; align-items: center; gap: 14px; }
    .txn-icon {
        width: 40px; height: 40px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
        transition: var(--transition);
    }
    .sv-table tbody tr:hover .txn-icon { transform: scale(1.1); }
    
    .txn-icon-in  { background: #D1FAE5; color: #059669; }
    .txn-icon-out { background: #FEE2E2; color: #dc2626; }

    .txn-type  { font-size: 0.9rem; font-weight: 700; color: var(--text-dark); margin-bottom: 2px; }
    .txn-notes { font-size: 0.72rem; font-weight: 500; color: var(--text-muted); }
    .txn-date  { font-size: 0.85rem; font-weight: 700; color: var(--text-dark); }
    .txn-time  { font-size: 0.7rem; color: var(--text-muted); font-weight: 500; }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #D1FAE5;
        color: #059669;
        font-size: 0.65rem;
        font-weight: 800;
        padding: 4px 12px;
        border-radius: 100px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }
    .status-badge i { font-size: 0.6rem; }

    .amount-in  { font-size: 0.95rem; font-weight: 800; color: #059669; }
    .amount-out { font-size: 0.95rem; font-weight: 800; color: #dc2626; }

    .empty-state { padding: 80px 20px; text-align: center; }
    .empty-state i { font-size: 3rem; display: block; margin-bottom: 16px; color: var(--text-muted); opacity: 0.3; }
    .empty-state p { font-size: 1rem; font-weight: 600; color: var(--text-muted); margin: 0; }
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
                    <h2>Savings Portfolio</h2>
                    <p>Comprehensive overview of your financial growth and activity.</p>
                </div>
                <a href="<?= BASE_URL ?>/member/pages/dashboard.php" class="btn-page-back">
                    <i class="bi bi-arrow-left"></i> <span>Dashboard</span>
                </a>
            </div>

            <!-- Stat Cards -->
            <div class="row g-4 mb-5">

                <!-- Net Balance -->
                <div class="col-xl-4">
                    <div class="stat-card stat-card-forest">
                        <div>
                            <div class="stat-icon"><i class="bi bi-shield-lock-fill"></i></div>
                            <div class="stat-badge"><i class="bi bi-patch-check-fill"></i> Secure Wallet</div>
                            <div class="stat-label">Net Balance</div>
                            <div class="stat-value">KES <?= number_format((float)$netSavings, 2) ?></div>
                        </div>
                        <div class="forest-divider">
                            <span class="left">Withdrawable Funds</span>
                            <span class="right"><i class="bi bi-caret-up-fill"></i> 2.4% APR</span>
                        </div>
                    </div>
                </div>

                <!-- Total Savings -->
                <div class="col-xl-4 col-md-6">
                    <div class="stat-card stat-card-neutral">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <div class="stat-label">Total Savings</div>
                                <div class="stat-value">KES <?= number_format((float)$totalSavings, 2) ?></div>
                            </div>
                            <div class="stat-icon stat-icon-success"><i class="bi bi-plus-square-fill"></i></div>
                        </div>
                        <div class="stat-progress">
                            <div class="stat-progress-fill bg-success" style="width:100%;"></div>
                        </div>
                        <div class="stat-meta">Cumulative deposits & contributions</div>
                    </div>
                </div>

                <!-- Total Withdrawals -->
                <div class="col-xl-4 col-md-6">
                    <div class="stat-card stat-card-neutral">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <div class="stat-label">Total Withdrawals</div>
                                <div class="stat-value">KES <?= number_format((float)$totalWithdrawals, 2) ?></div>
                            </div>
                            <div class="stat-icon stat-icon-danger"><i class="bi bi-dash-square-fill"></i></div>
                        </div>
                        <div class="stat-progress">
                            <div class="stat-progress-fill bg-danger" style="width:100%;"></div>
                        </div>
                        <div class="stat-meta">Total funds transferred to M-Pesa</div>
                    </div>
                </div>

            </div>

            <!-- Action Bar -->
            <div class="action-bar">
                <div class="action-btns">
                    <a href="<?= BASE_URL ?>/member/pages/mpesa_request.php?type=savings" class="btn-act btn-act-primary">
                        <i class="bi bi-plus-circle-fill"></i> <span>Add Funds</span>
                    </a>
                    <a href="<?= BASE_URL ?>/member/pages/withdraw.php?type=savings&source=savings" class="btn-act btn-act-outline">
                        <i class="bi bi-arrow-up-right-circle-fill"></i> <span>Withdraw</span>
                    </a>
                </div>

                <form method="GET" class="filter-card">
                    <div class="filter-group ps-2">
                        <i class="bi bi-funnel-fill filter-icon"></i>
                        <select name="type" class="filter-select">
                            <option value="">All Transactions</option>
                            <option value="deposit"    <?= $typeFilter === 'deposit'    ? 'selected' : '' ?>>Savings Only</option>
                            <option value="withdrawal" <?= $typeFilter === 'withdrawal' ? 'selected' : '' ?>>Withdrawals Only</option>
                        </select>
                    </div>
                    <div class="filter-sep"></div>
                    <div class="filter-group">
                        <i class="bi bi-calendar-event filter-icon"></i>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="filter-input">
                    </div>
                    <div class="filter-sep"></div>
                    <div class="filter-group pe-1">
                        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="filter-input">
                        <button type="submit" class="filter-btn">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Transaction Table -->
            <div class="panel-card mb-5">
                <div class="panel-head">
                    <h6 class="panel-title">Recent Activity</h6>
                    <div class="dropdown">
                        <button class="btn-export" data-bs-toggle="dropdown">
                            <i class="bi bi-cloud-download-fill"></i> <span>Export Data</span>
                        </button>
                        <ul class="dropdown-menu export-dd dropdown-menu-end">
                            <li>
                                <a class="export-dd-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>">
                                    <span class="export-dd-icon" style="background:rgba(220,38,38,0.1);color:#dc2626;"><i class="bi bi-file-pdf"></i></span> PDF Document
                                </a>
                            </li>
                            <li>
                                <a class="export-dd-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>">
                                    <span class="export-dd-icon" style="background:rgba(5,150,105,0.1);color:#059669;"><i class="bi bi-file-earmark-excel"></i></span> Excel Sheet
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="export-dd-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank">
                                    <span class="export-dd-icon" style="background:rgba(79,70,229,0.1);color:#4f46e5;"><i class="bi bi-printer"></i></span> Print Layout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="sv-table">
                        <thead>
                            <tr>
                                <th style="padding-left:32px;">Transaction Details</th>
                                <th>Timestamp</th>
                                <th>Status</th>
                                <th style="text-align:right;padding-right:32px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history->num_rows > 0):
                                while ($row = $history->fetch_assoc()):
                                    $isDeposit = in_array(strtolower($row['transaction_type']), ['deposit','contribution','income','savings_deposit']);
                                    $iconClass = $isDeposit ? 'txn-icon-in' : 'txn-icon-out';
                                    $icon      = $isDeposit ? 'bi-plus-circle' : 'bi-dash-circle';
                                    $sign      = $isDeposit ? '+' : '-';
                                    $amtClass  = $isDeposit ? 'amount-in' : 'amount-out';
                                    $label     = ucwords(str_replace('_', ' ', $row['transaction_type']));
                            ?>
                            <tr>
                                <td style="padding-left:32px;">
                                    <div class="txn-info-wrap">
                                        <div class="txn-icon <?= $iconClass ?>"><i class="bi <?= $icon ?>"></i></div>
                                        <div>
                                            <div class="txn-type"><?= $label ?></div>
                                            <div class="txn-notes"><?= htmlspecialchars($row['notes'] ?: 'Completed Transaction') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="txn-date"><?= date('D, d M Y', strtotime($row['created_at'])) ?></div>
                                    <div class="txn-time"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td>
                                    <span class="status-badge">
                                        <i class="bi bi-check-circle-fill"></i> Completed
                                    </span>
                                </td>
                                <td style="text-align:right;padding-right:32px;">
                                    <span class="<?= $amtClass ?>"><?= $sign ?> <?= number_format((float)$row['amount'], 2) ?></span>
                                    <div class="text-muted" style="font-size:0.65rem;font-weight:700;">KES</div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="bi bi-clipboard-x"></i>
                                        <p>No activity found for this period.</p>
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