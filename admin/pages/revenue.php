<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');
// admin/revenue.php

require_once __DIR__ . '/../../inc/TransactionHelper.php';
require_once __DIR__ . '/../../inc/ExportHelper.php';
require_once __DIR__ . '/../../inc/InvestmentViabilityEngine.php';
Auth::requireAdmin();
require_permission();

$viability_engine = new InvestmentViabilityEngine($conn);

// 1. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_revenue'])) {
    verify_csrf_token();
    
    $source = $_POST['source_type'];
    $amount = floatval($_POST['amount']);
    $method = $_POST['payment_method'] ?? 'cash';
    $desc   = trim($_POST['description']);
    $date   = $_POST['revenue_date'] ?? date('Y-m-d');
    
    validate_not_future($date, "revenue.php");

    $unified_id = $_POST['unified_asset_id'] ?? 'other_0';
    list($source, $related_id) = explode('_', $unified_id);
    $related_id = (int)$related_id;
    $related_table = 'investments';
    
    if ($related_id <= 0 && $source !== 'other') {
        flash_set("Please select a valid revenue source.", "error");
    } else {
        // Check if investment is active
        if ($source !== 'other') {
            $check_sql = "SELECT status FROM investments WHERE investment_id = ?";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->bind_param("i", $related_id);
            $stmt_check->execute();
            $status_result = $stmt_check->get_result()->fetch_assoc();
            
            if (!$status_result || $status_result['status'] !== 'active') {
                flash_set("Cannot record revenue for inactive or sold assets.", "error");
                header("Location: revenue.php");
                exit;
            }
        }

        $config = [
            'type'           => 'income',
            'category'       => ($source === 'other' ? 'general_income' : 'investment_income'),
            'amount'         => $amount,
            'method'         => $method,
            'notes'          => $desc,
            'related_table'  => ($source === 'other' ? NULL : 'investments'),
            'related_id'     => ($source === 'other' ? 0 : $related_id),
            'transaction_date' => $date
        ];

        if (TransactionHelper::record($config)) {
            flash_set("Revenue recorded successfully!", "success");
            header("Location: revenue.php");
            exit;
        } else {
            flash_set("Failed to record revenue. Ensure Financial Engine is active.", "error");
        }
    }
}

// 2. Fetch Data with Filters
$duration = $_GET['duration'] ?? 'monthly';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

$date_filter = "";
if ($duration !== 'all') {
    switch ($duration) {
        case 'today': $start_date = $end_date = date('Y-m-d'); break;
        case 'weekly': $start_date = date('Y-m-d', strtotime('-7 days')); $end_date = date('Y-m-d'); break;
        case 'monthly': $start_date = date('Y-m-01'); $end_date = date('Y-m-t'); break;
    }
    $date_filter = " AND t.transaction_date BETWEEN '$start_date' AND '$end_date'";
}

$revenue_qry = "SELECT t.*, 
                CASE 
                    WHEN t.related_table = 'investments' THEN (SELECT title FROM investments WHERE investment_id = t.related_id)
                    ELSE 'General Fund' 
                END as source_name 
                FROM transactions t 
                WHERE t.transaction_type = 'income' $date_filter
                ORDER BY t.transaction_date DESC, t.created_at DESC";

$revenue_res = $conn->query($revenue_qry . " LIMIT 100");
$revenue_data = $revenue_res->fetch_all(MYSQLI_ASSOC);

$total_period_rev = 0;
$source_breakdown = [];
foreach ($revenue_data as $r) {
    if (strtotime($r['transaction_date']) >= strtotime($start_date) && strtotime($r['transaction_date']) <= strtotime($end_date)) {
        $total_period_rev += $r['amount'];
        $src = $r['source_name'] ?: 'Other';
        $source_breakdown[$src] = ($source_breakdown[$src] ?? 0) + $r['amount'];
    }
}

$total_all_time = $conn->query("SELECT SUM(amount) FROM transactions WHERE transaction_type IN ('income', 'revenue_inflow')")->fetch_row()[0] ?? 0;

// Unified Asset Revenue Analytics
$asset_revenue_sql = "
    SELECT 
        i.investment_id as id, i.title, i.category, i.target_amount, i.target_period, i.viability_status, 'investments' as asset_table,
        SUM(CASE WHEN t.transaction_date BETWEEN ? AND ? THEN t.amount ELSE 0 END) as period_revenue,
        COALESCE(SUM(t.amount), 0) as total_revenue,
        COUNT(t.transaction_id) as transaction_count
    FROM investments i
    LEFT JOIN transactions t ON t.related_table = 'investments' AND t.related_id = i.investment_id AND t.transaction_type IN ('income', 'revenue_inflow')
    WHERE i.status = 'active'
    GROUP BY i.investment_id
    ORDER BY period_revenue DESC, total_revenue DESC";

$stmt_assets = $conn->prepare($asset_revenue_sql);
$stmt_assets->bind_param("ss", $start_date, $end_date);
$stmt_assets->execute();
$all_assets_raw = $stmt_assets->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_assets->close();

$top_investments = []; // Reusing variable to minimize UI changes
foreach ($all_assets_raw as $asset) {
    $perf = $viability_engine->calculatePerformance((int)$asset['id'], $asset['asset_table']);
    if ($perf) {
        $asset['target_achievement'] = $perf['target_achievement_pct'];
        $asset['is_profitable'] = $perf['is_profitable'];
        $asset['net_profit'] = $perf['net_profit'];
        $asset['viability_status'] = $perf['viability_status'];
    } else {
        $asset['target_achievement'] = 0;
        $asset['is_profitable'] = false;
        $asset['net_profit'] = 0;
    }
    $top_investments[] = $asset;
}

// Category-wise revenue
$cat_revenue = [];
foreach ($top_investments as $asset) {
    $cat = ucfirst(str_replace('_', ' ', $asset['category']));
    $cat_revenue[$cat] = ($cat_revenue[$cat] ?? 0) + $asset['period_revenue'];
}

// Calculate Global Target Achievement for the period
$target_summary = $conn->query("SELECT SUM(target_amount) as total_target FROM investments WHERE status = 'active'")->fetch_assoc();
$total_targets = (float)($target_summary['total_target'] ?? 0);
$total_achieved = $total_period_rev; 
$global_target_pct = $total_targets > 0 ? min(100, ($total_achieved / $total_targets) * 100) : 0;

// Fetch all active investments for JS target info
$inv_data_res = $conn->query("SELECT investment_id, title, target_amount, target_period FROM investments WHERE status = 'active'");
$inv_js_data = [];
while ($row = $inv_data_res->fetch_assoc()) {
    $inv_js_data[$row['investment_id']] = $row;
}

// 3. Handle Exports
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $export_data = [];
    foreach ($revenue_data as $row) {
        $export_data[] = [
            'Date' => date('d-M-Y', strtotime($row['transaction_date'])),
            'Ref' => $row['reference_no'],
            'Source' => $row['source_name'],
            'Method' => strtoupper($row['payment_method']),
            'Amount' => number_format((float)$row['amount'], 2),
            'Details' => $row['notes']
        ];
    }

    UniversalExportEngine::handle($format, $export_data, [
        'title' => 'Revenue Ledger',
        'module' => 'Revenue Analysis',
        'headers' => ['Date', 'Ref', 'Source', 'Method', 'Amount', 'Details'],
        'total_value' => $total_period_rev
    ]);
    exit;
}

$investments = $conn->query("SELECT investment_id, title FROM investments WHERE status='active'");

$pageTitle = "Revenue Portal";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | USMS Finance</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --forest: #0f2e25;
            --forest-light: #1a4d3d;
            --lime: #d0f35d;
            --lime-dark: #a8cf12;
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(15, 46, 37, 0.05);
            --glass-shadow: 0 10px 40px rgba(15, 46, 37, 0.06);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f4f7f6;
            color: var(--forest);
        }

        .main-content { margin-left: 280px; padding: 30px; transition: 0.3s; }
        
        /* Banner Styles */
        .portal-header {
            background: linear-gradient(135deg, var(--forest) 0%, #1a4d3e 100%);
            border-radius: 30px; padding: 40px; color: white; margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(15, 46, 37, 0.15);
            position: relative; overflow: hidden;
        }

        /* Stat Cards */
        .stat-card {
            background: white; border-radius: 24px; padding: 25px;
            box-shadow: var(--glass-shadow); border: 1px solid var(--glass-border);
            height: 100%; transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(15, 46, 37, 0.08); }

        .icon-circle {
            width: 54px; height: 54px; border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin-bottom: 20px;
        }
        .bg-lime-soft { background: rgba(208, 243, 93, 0.2); color: var(--forest); }
        .bg-forest-soft { background: rgba(15, 46, 37, 0.05); color: var(--forest); }

        /* Ledger Table */
        .ledger-container {
            background: white; border-radius: 28px; 
            box-shadow: var(--glass-shadow); border: 1px solid var(--glass-border);
            overflow: hidden;
        }
        .ledger-header { padding: 30px; border-bottom: 1px solid #f1f5f9; background: #fff; }
        
        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-custom thead th {
            background: #f8fafc; color: #64748b; font-weight: 700;
            text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;
            padding: 18px 25px; border-bottom: 2px solid #edf2f7;
        }
        .table-custom tbody td {
            padding: 20px 25px; border-bottom: 1px solid #f1f5f9;
            vertical-align: middle; font-size: 0.95rem;
        }
        .table-custom tbody tr:hover td { background-color: #fcfdfe; }

        /* Action Buttons */
        .btn-lime {
            background: var(--lime); color: var(--forest);
            border-radius: 14px; font-weight: 800; border: none; padding: 12px 25px;
            transition: 0.3s;
        }
        .btn-lime:hover { background: var(--lime-dark); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(208, 243, 93, 0.3); }

        .btn-outline-forest {
            background: transparent; border: 2px solid var(--forest); color: var(--forest);
            border-radius: 14px; font-weight: 700; padding: 10px 22px; transition: 0.3s;
        }
        .btn-outline-forest:hover { background: var(--forest); color: white; }

        .search-box {
            background: #f8fafc; border: none; border-radius: 15px;
            padding: 12px 20px 12px 45px; width: 100%; transition: 0.3s;
        }

        /* Modal Customization */
        .modal-content { border-radius: 30px; border: none; overflow: hidden; }
        .modal-header { background: var(--forest); color: white; border: none; padding: 25px 35px; }
        .modal-body { padding: 35px; background: #fcfdfe; }
        .form-control, .form-select { border-radius: 15px; padding: 12px 20px; border: 1.5px solid #e2e8f0; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .fade-in { animation: fadeIn 0.6s ease-out; }
        .slide-up { animation: slideUp 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        @media (max-width: 991.98px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content">
        <?php $layout->topbar($pageTitle ?? ''); ?>
        
        <!-- Header Banner -->
        <div class="portal-header fade-in">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3">Treasury V4</span>
                    <h1 class="display-5 fw-800 mb-2">Revenue Dashboard</h1>
                    <p class="opacity-75 fs-5 mb-0">Monitor SACCO inflows and asset performance metrics.</p>
                </div>
                <div class="col-lg-5 text-lg-end mt-4 mt-lg-0">
                    <button class="btn btn-lime shadow-lg px-4" data-bs-toggle="modal" data-bs-target="#recordRevenueModal">
                        <i class="bi bi-plus-lg me-2"></i>Record New Inflow
                    </button>
                    <div class="mt-2">
                        <a href="investments.php" class="text-white opacity-75 small text-decoration-none me-3">
                            <i class="bi bi-briefcase me-1"></i> View Assets
                        </a>
                        <a href="expenses.php" class="text-white opacity-75 small text-decoration-none">
                            <i class="bi bi-receipt me-1"></i> Track Expenses
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php flash_render(); ?>

        <!-- Investment Performance Cards -->
        <?php if (!empty($top_investments)): ?>
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-800 mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>Portfolio Revenue Performance</h5>
                    <a href="investments.php" class="btn btn-outline-forest btn-sm rounded-pill px-3">View All Assets</a>
                </div>
            </div>
            <?php foreach ($top_investments as $inv): 
                $icon = match($inv['category']) {
                    'farm' => 'bi-flower3',
                    'vehicle_fleet' => 'bi-truck-front',
                    'petrol_station' => 'bi-fuel-pump',
                    'apartments' => 'bi-building',
                    default => 'bi-box-seam'
                };
                $v_badge = match($inv['viability_status']) {
                    'viable' => '<span class="badge bg-success-soft text-success px-2 py-1" style="font-size: 0.6rem;">VIABLE</span>',
                    'underperforming' => '<span class="badge bg-warning-soft text-warning px-2 py-1" style="font-size: 0.6rem;">MARGINAL</span>',
                    'loss_making' => '<span class="badge bg-danger-soft text-danger px-2 py-1" style="font-size: 0.6rem;">AT RISK</span>',
                    default => '<span class="badge bg-light text-muted px-2 py-1" style="font-size: 0.6rem;">NEW</span>'
                };
            ?>
            <div class="col-md-4">
                <div class="stat-card slide-up h-100" style="border-left: 4px solid <?= $inv['target_achievement'] >= 100 ? 'var(--success)' : ($inv['target_achievement'] >= 70 ? 'var(--warning)' : 'var(--danger)') ?>;">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="icon-circle bg-lime-soft">
                            <i class="<?= $icon ?>"></i>
                        </div>
                        <?= $v_badge ?>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small fw-bold text-uppercase mb-1"><?= ucfirst(str_replace('_', ' ', $inv['category'])) ?></div>
                        <h6 class="fw-800 mb-0"><?= esc($inv['title']) ?></h6>
                    </div>
                    <div class="h4 fw-800 text-dark mb-1">KES <?= number_format((float)$inv['period_revenue']) ?></div>
                    <div class="small text-muted mb-3">
                        <i class="bi bi-bullseye me-1"></i>Target: KES <?= number_format((float)$inv['target_amount']) ?>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center small mb-1">
                        <span class="text-muted fw-bold">Achievement</span>
                        <span class="fw-800"><?= number_format($inv['target_achievement'], 1) ?>%</span>
                    </div>
                    <div class="progress" style="height: 6px; background: #f1f5f9;">
                        <div class="progress-bar <?= $inv['target_achievement'] >= 100 ? 'bg-success' : ($inv['target_achievement'] >= 70 ? 'bg-warning' : 'bg-danger') ?>" 
                             style="width: <?= min(100, $inv['target_achievement']) ?>%"></div>
                    </div>
                    <div class="mt-3 fs-xs text-muted d-flex justify-content-between">
                        <span><i class="bi bi-clock-history me-1"></i><?= $inv['transaction_count'] ?> inputs</span>
                        <span class="<?= $inv['net_profit'] >= 0 ? 'text-success' : 'text-danger' ?> fw-bold">Profit: <?= number_format($inv['net_profit']) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php include __DIR__ . '/../../inc/finance_nav.php'; ?>

        <!-- KPIs -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card slide-up">
                    <div class="icon-circle bg-lime-soft">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div class="text-muted small fw-bold text-uppercase">Period Total</div>
                    <div class="h2 fw-800 text-dark mt-2 mb-1">KES <?= number_format((float)$total_period_rev) ?></div>
                    <div class="small text-muted"><i class="bi bi-calendar-range me-1"></i> Based on active filters</div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card slide-up" style="animation-delay: 0.1s">
                    <div class="icon-circle bg-forest-soft">
                        <i class="bi bi-bullseye"></i>
                    </div>
                    <div class="text-muted small fw-bold text-uppercase">Portfolio Efficiency</div>
                    <div class="h2 fw-800 text-dark mt-2 mb-1"><?= number_format($global_target_pct, 1) ?>%</div>
                    <div class="progress" style="height: 4px; background: #f1f5f9; margin-bottom: 8px;">
                        <div class="progress-bar bg-forest" style="width: <?= $global_target_pct ?>%"></div>
                    </div>
                    <div class="small text-muted"><i class="bi bi-info-circle me-1"></i> Actual vs. Projected Targets</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card slide-up" style="animation-delay: 0.2s">
                    <form method="GET" id="filterForm" class="h-100 d-flex flex-column justify-content-between">
                        <div>
                            <label class="small text-muted fw-bold text-uppercase mb-2 d-block">Time Analysis</label>
                            <select name="duration" class="form-select form-select-sm border-0 bg-light" onchange="toggleDateInputs(this.value)">
                                <option value="all" <?= $duration === 'all' ? 'selected' : '' ?>>Historical Records</option>
                                <option value="today" <?= $duration === 'today' ? 'selected' : '' ?>>Today's activity</option>
                                <option value="weekly" <?= $duration === 'weekly' ? 'selected' : '' ?>>Past 7 Days</option>
                                <option value="monthly" <?= $duration === 'monthly' ? 'selected' : '' ?>>Current Month</option>
                                <option value="custom" <?= $duration === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                            </select>
                        </div>
                        <div id="customDateRange" class="mt-2 <?= $duration !== 'custom' ? 'd-none' : '' ?>">
                            <div class="d-flex gap-2">
                                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= $start_date ?>">
                                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= $end_date ?>">
                            </div>
                            <button type="submit" class="btn btn-forest btn-sm w-100 mt-2">Apply Date Filter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Table Section -->
            <div class="col-lg-8">
                <div class="ledger-container slide-up" style="animation-delay: 0.3s">
                    <div class="ledger-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div class="position-relative flex-grow-1" style="max-width: 400px;">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                            <input type="text" id="revenueSearch" class="search-box" placeholder="Filter inflows...">
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-outline-dark rounded-pill px-4 dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-download me-2"></i>Export Analysis
                            </button>
                            <ul class="dropdown-menu shadow-lg border-0 mt-2">
                                <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Revenue Ledger (PDF)</a></li>
                                <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Export Spreadsheet</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer me-2"></i>Print Friendly View</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table-custom" id="revenueTable">
                            <thead>
                                <tr>
                                    <th>Date / Ref</th>
                                    <th>Financial Source</th>
                                    <th>Method</th>
                                    <th class="text-end">Credit (KES)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($revenue_data)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <div class="opacity-25 mb-4"><i class="bi bi-receipt-cutoff display-2"></i></div>
                                            <h5 class="fw-bold text-muted">No Revenue Recorded</h5>
                                            <p class="text-muted">Inflows within the selected period will appear here.</p>
                                        </td>
                                    </tr>
                                <?php else: 
                                foreach($revenue_data as $row): ?>
                                    <tr class="revenue-row">
                                        <td>
                                            <div class="fw-bold text-dark"><?= date('d M, Y', strtotime($row['transaction_date'])) ?></div>
                                            <div class="small text-muted font-monospace mt-1"><?= esc($row['reference_no']) ?></div>
                                        </td>
                                        <td>
                                            <a href="transactions.php?filter=<?= $row['related_id'] ?>" class="text-decoration-none" title="Audit Source Transactions">
                                                <div class="fw-600 text-forest"><?= esc($row['source_name'] ?: 'General Fund') ?></div>
                                            </a>
                                            <div class="small text-muted mt-1 opacity-75"><?= esc($row['notes'] ?: 'Revenue Entry') ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small fw-bold">
                                                <i class="bi bi-credit-card-2-back me-1 text-muted"></i><?= strtoupper($row['payment_method']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="fw-800 fs-6 text-success">
                                                + <?= number_format((float)$row['amount']) ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Chart Section -->
            <div class="col-lg-4">
                <div class="stat-card slide-up" style="animation-delay: 0.4s">
                    <h5 class="fw-bold mb-4">Source Distribution</h5>
                    <?php if(empty($source_breakdown)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-pie-chart text-muted display-4 opacity-25"></i>
                            <p class="text-muted small mt-3">Insufficient data for breakdown</p>
                        </div>
                    <?php else: ?>
                        <div style="height: 250px;">
                            <canvas id="revenueChart" 
                                data-labels='<?= json_encode(array_keys($source_breakdown)) ?>' 
                                data-values='<?= json_encode(array_values($source_breakdown)) ?>'>
                            </canvas>
                        </div>
                        <?php if (!empty($cat_revenue)): ?>
                        <div class="mt-4 pt-3 border-top">
                            <h6 class="fw-800 text-dark mb-3">Portfolio Performance Breakdown</h6>
                            <?php 
                            arsort($cat_revenue);
                            $total_cat_rev = array_sum($cat_revenue);
                            foreach ($cat_revenue as $name => $val): 
                                $cat_pct = ($val / ($total_cat_rev ?: 1)) * 100;
                            ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small fw-800 text-forest"><?= $name ?></span>
                                        <span class="small fw-bold text-dark"><?= number_format($cat_pct, 1) ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 10px; border-radius: 20px; background: #eef2f6;">
                                        <div class="progress-bar" style="width: <?= $cat_pct ?>%; background: var(--forest); border-radius: 20px;"></div>
                                    </div>
                                    <div class="text-end small mt-1 text-muted fw-bold" style="font-size: 0.65rem;">
                                        KES <?= number_format((float)$val) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    
                    <?php if (!empty($source_breakdown)): ?>
                        <div class="mt-4">
                            <h6 class="fw-bold mb-3 mt-4">Top Revenue Sources</h6>
                            <?php 
                            arsort($source_breakdown);
                            $count = 0;
                            foreach ($source_breakdown as $name => $val): 
                                if ($count++ >= 5) break;
                            ?>
                                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="badge bg-forest text-lime rounded-circle" style="width: 8px; height: 8px;"></div>
                                        <span class="small fw-600"><?= $name ?></span>
                                    </div>
                                    <span class="small fw-bold">KES <?= number_format((float)$val) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php $layout->footer(); ?>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="recordRevenueModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-2xl">
            <div class="modal-header">
                <h5 class="modal-title fw-800"><i class="bi bi-shield-check me-2"></i>Record Received Inflow</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="record_revenue" value="1">
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label">Select Asset / Revenue Source</label>
                                    <select name="unified_asset_id" id="unified_asset_id" class="form-select search-pill" required onchange="updateTargetInfo()">
                                        <option value="other_0">General Fund / Unassigned</option>
                                        <optgroup label="Active Portfolio">
                                            <?php mysqli_data_seek($investments, 0); while($inv = $investments->fetch_assoc()): ?>
                                                <option value="inv_<?= $inv['investment_id'] ?>"><?= esc($inv['title']) ?></option>
                                            <?php endwhile; ?>
                                        </optgroup>
                                    </select>
                        </div>
                        <div class="col-12 mt-3 d-none" id="target_info_box">
                            <div class="alert alert-info border-0 rounded-4 bg-forest-soft d-flex align-items-center mb-0">
                                <i class="bi bi-info-circle-fill fs-4 me-3 text-forest"></i>
                                <div>
                                    <div class="small fw-800 text-forest text-uppercase mb-1">Asset Performance Target</div>
                                    <div class="fw-bold text-dark" id="targetText">Target: KES 0.00 / undefined</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Amount Received (KES)</label>
                            <input type="number" name="amount" class="form-control fw-bold" min="1" step="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Collection Date</label>
                            <input type="date" name="revenue_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Receiving Method</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="cash">Cash Collection</option>
                                <option value="mpesa">M-Pesa Till/Paybill</option>
                                <option value="bank">Bank Deposit</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Narration / Internal Reference</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="e.g. Daily collection for KCB 123X, Dividend check..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lime rounded-pill px-5 shadow-lg">Confirm & Post Ledger</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>/public/assets/js/main.js?v=<?= time() ?>"></script>
<script>
    const invData = <?= json_encode($inv_js_data) ?>;
    
    function updateTargetInfo() {
        const select = document.getElementById('unified_asset_id');
        const val = select.value;
        const targetDiv = document.getElementById('target_info_box');
        
        if (val.startsWith('inv_')) {
            const id = val.replace('inv_', '');
            const data = invData[id];
            if (data && data.target_amount > 0) {
                targetDiv.innerHTML = `<div class="alert alert-info py-2 small mb-0">
                    <i class="bi bi-info-circle me-1"></i> 
                    Target: <strong>KES ${new Intl.NumberFormat().format(data.target_amount)}</strong> (${data.target_period})
                </div>`;
                targetDiv.classList.remove('d-none');
                return;
            }
        }
        
        targetDiv.classList.add('d-none');
    }

    function toggleDateInputs(val) {
        if(val === 'custom') document.getElementById('customDateRange').classList.remove('d-none');
        else {
            document.getElementById('customDateRange').classList.add('d-none');
            document.getElementById('filterForm').submit();
        }
    }

    // Charting
    document.addEventListener('DOMContentLoaded', () => {
        const ctx = document.getElementById('revenueChart');
        if(ctx) {
            const labels = JSON.parse(ctx.getAttribute('data-labels') || '[]');
            const values = JSON.parse(ctx.getAttribute('data-values') || '[]');
            if(labels.length > 0) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: ['#d0f35d', '#0f2e25', '#1a4d3d', '#a8cf12', '#22c55e'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        cutout: '75%'
                    }
                });
            }
        }
    });

    // Search
    document.getElementById('revenueSearch')?.addEventListener('keyup', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.revenue-row').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    });
</script>
</body>
</html>
