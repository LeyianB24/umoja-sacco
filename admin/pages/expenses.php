<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');
// accountant/expenses.php

require_once __DIR__ . '/../../inc/TransactionHelper.php';
// 1. Auth Check
require_admin();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');
require_permission();

// 2. Handle Form Submission (Add Expense)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    verify_csrf_token();
    
    $amount     = floatval($_POST['amount']);
    $category   = $_POST['category'];
    $payee      = trim($_POST['payee']); 
    $date       = $_POST['expense_date'];
    $ref_no     = trim($_POST['ref_no']);
    $desc       = trim($_POST['description']);
    $is_pending = isset($_POST['is_pending']); 

    validate_not_future($date, "expenses.php");

    if ($amount <= 0) {
        flash_set("Expense amount must be valid.", "warning");
    } else {
        // Construct Notes
        $notes = "[$category] $payee"; 
        if (!empty($desc)) $notes .= " - $desc";
        if ($is_pending) $notes .= " [PENDING]";

        $conn->begin_transaction();
        try {
            $unified_id = $_POST['unified_asset_id'] ?? '';
            $inv_id = null;
            $related_id = 0;
            $related_table = null;

            if ($unified_id && $unified_id !== 'other_0') {
                list($source, $related_id) = explode('_', $unified_id);
                $related_id = (int)$related_id;
                $related_table = 'investments';
                $inv_id = $related_id;
            }

            $method = $_POST['payment_method'] ?? 'cash';
            
            // Validate asset is active if specified
            if ($related_table && $related_id) {
                $check_sql = "SELECT status FROM investments WHERE investment_id = ?";
                $stmt_check = $conn->prepare($check_sql);
                $stmt_check->bind_param("i", $related_id);
                $stmt_check->execute();
                $status_result = $stmt_check->get_result()->fetch_assoc();
                
                if (!$status_result || $status_result['status'] !== 'active') {
                    throw new Exception("Cannot record expenses for inactive or disposed assets.");
                }
            }
            
            // Record in Central Ledger via TransactionHelper/FinancialEngine
            $ok = TransactionHelper::record([
                'member_id'     => null, // System Expense
                'amount'        => $amount,
                'type'          => 'expense',
                'category'      => $category,
                'method'        => $method,
                'ref_no'        => $ref_no,
                'notes'         => $notes,
                'related_id'    => $related_id,
                'related_table' => $related_table,
            ]);

            if (!$ok) throw new Exception("Ledger recording failed.");

            $conn->commit();
            flash_set("Expense recorded successfully!", "success");
            header("Location: expenses.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            flash_set("Error: " . $e->getMessage(), "error");
        }
    }
}

// 3. Handle Duration Filtering
$duration = $_GET['duration'] ?? '3months'; // Default to last 3 months
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-3 months'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$date_filter = "";

if ($duration !== 'all') {
    switch ($duration) {
        case 'today':
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
            break;
        case 'weekly':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $end_date = date('Y-m-d');
            break;
        case 'monthly':
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-t');
            break;
        case '3months':
            $start_date = date('Y-m-d', strtotime('-3 months'));
            $end_date = date('Y-m-d');
            break;
    }
    $date_filter = " AND created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
}

$where = "transaction_type IN ('expense', 'expense_outflow') $date_filter";
$sql = "SELECT * FROM transactions WHERE $where ORDER BY created_at DESC";
$result = $conn->query($sql);

$expenses = [];
$total_period_expense = 0;
$pending_bills_count = 0;
$cat_breakdown = []; 

if ($result) {
    while($row = $result->fetch_assoc()) {
        $expenses[] = $row;
        $total_period_expense += $row['amount'];
        
        // Parse Category
        preg_match('/\[(.*?)\]/', $row['notes'], $matches);
        $cat = $matches[1] ?? 'Uncategorized';
        
        // Check Pending
        if (stripos($row['notes'], 'pending') !== false) {
            $pending_bills_count++;
        }

        // Chart Data
        if (!isset($cat_breakdown[$cat])) $cat_breakdown[$cat] = 0;
        $cat_breakdown[$cat] += $row['amount'];
    }
}

// 4. Handle Export Actions
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $export_data = [];
    foreach ($expenses as $ex) {
        preg_match('/\[(.*?)\]/', $ex['notes'], $cat_match);
        $display_cat = $cat_match[1] ?? 'General';
        $clean_notes = trim(str_replace(['[PENDING]', $cat_match[0] ?? ''], '', $ex['notes']));
        $status = (stripos($ex['notes'], 'pending') !== false) ? 'Pending' : 'Paid';
        
        $export_data[] = [
            'Date' => date('d-m-Y', strtotime($ex['created_at'])),
            'Reference' => $ex['reference_no'],
            'Payee/Details' => $clean_notes,
            'Category' => $display_cat,
            'Amount' => number_format((float)$ex['amount'], 2),
            'Status' => $status
        ];
    }
    
    UniversalExportEngine::handle($format, $export_data, [
        'title' => 'Expense Ledger',
        'module' => 'Expense Management',
        'headers' => ['Date', 'Reference', 'Payee/Details', 'Category', 'Amount', 'Status'],
        'record_count' => count($expenses),
        'total_value' => $total_period_expense
    ]);
    exit;
}

// 5. Fetch Assets for Attribution
$investments_list = $conn->query("SELECT investment_id, title, category FROM investments WHERE status = 'active' ORDER BY title ASC");

$pageTitle = "Expenses Portal";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | USMS Admin</title>
    
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
            min-height: 100vh;
        }

        .main-content { margin-left: 280px; padding: 30px; transition: 0.3s; }
        
        /* Banner Styles */
        .portal-header {
            background: linear-gradient(135deg, var(--forest) 0%, #1a4d3e 100%);
            border-radius: 30px; padding: 40px; color: white; margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(15, 46, 37, 0.15);
            position: relative; overflow: hidden;
        }
        .portal-header::after {
            content: ''; position: absolute; bottom: -20%; right: -5%; width: 350px; height: 350px;
            background: radial-gradient(circle, rgba(208, 243, 93, 0.1) 0%, transparent 70%);
            border-radius: 50%;
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
        .bg-red-soft { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .bg-forest-soft { background: rgba(15, 46, 37, 0.05); color: var(--forest); }

        /* Filter Controls */
        .filter-card {
            background: white; border-radius: 20px; padding: 20px 30px;
            box-shadow: var(--glass-shadow); border: 1px solid var(--glass-border);
            margin-bottom: 30px;
        }

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

        /* Badges */
        .status-badge {
            padding: 6px 14px; border-radius: 10px; font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .badge-pending { background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; }
        .badge-paid { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

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
        .search-box:focus { background: #fff; box-shadow: 0 0 0 4px rgba(15, 46, 37, 0.05); outline: none; }

        /* Chart Section */
        .chart-card {
            background: white; border-radius: 28px; padding: 35px;
            box-shadow: var(--glass-shadow); border: 1px solid var(--glass-border);
            height: 100%;
        }

        /* Modal Customization */
        .modal-content { border-radius: 30px; border: none; overflow: hidden; }
        .modal-header { background: var(--forest); color: white; border: none; padding: 25px 35px; }
        .modal-body { padding: 35px; background: #fcfdfe; }
        .form-label { font-weight: 700; color: var(--forest); margin-bottom: 10px; font-size: 0.9rem; }
        .form-control, .form-select { border-radius: 15px; padding: 12px 20px; border: 1.5px solid #e2e8f0; }
        .form-control:focus { border-color: var(--forest); box-shadow: 0 0 0 4px rgba(15, 46, 37, 0.05); }

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
                    <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3">Finance Control V18</span>
                    <h1 class="display-5 fw-800 mb-2">Expenses Portal</h1>
                    <p class="opacity-75 fs-5 mb-0">Operational expenditure tracking and financial audit engine.</p>
                </div>
                <div class="col-lg-5 text-lg-end mt-4 mt-lg-0">
                    <button class="btn btn-lime shadow-lg px-4" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                        <i class="bi bi-plus-circle-fill me-2"></i>Record Expenditure
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-card slide-up">
            <form method="GET" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-3">
                    <label class="form-label">Duration</label>
                    <select name="duration" class="form-select" onchange="toggleDateInputs(this.value)">
                        <option value="all" <?= $duration === 'all' ? 'selected' : '' ?>>Historical Archive</option>
                        <option value="today" <?= $duration === 'today' ? 'selected' : '' ?>>Today's activity</option>
                        <option value="weekly" <?= $duration === 'weekly' ? 'selected' : '' ?>>Past 7 Days</option>
                        <option value="monthly" <?= $duration === 'monthly' ? 'selected' : '' ?>>This Month</option>
                        <option value="3months" <?= $duration === '3months' ? 'selected' : '' ?>>Last Quarter (90D)</option>
                        <option value="custom" <?= $duration === 'custom' ? 'selected' : '' ?>>Custom Fiscal Range</option>
                    </select>
                </div>
                <div id="customDateRange" class="col-md-6 <?= $duration !== 'custom' ? 'd-none' : '' ?>">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-forest w-100">Update View</button>
                    <a href="expenses.php" class="btn btn-light rounded-3 px-3 border" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>

        <?php include __DIR__ . '/../../inc/finance_nav.php'; ?>

        <!-- KPIs -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card slide-up" style="animation-delay: 0.1s">
                    <div class="icon-circle bg-lime-soft">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div class="text-muted small fw-bold text-uppercase">Period Spending</div>
                    <div class="h2 fw-800 text-dark mt-2 mb-1">KES <?= number_format((float)$total_period_expense) ?></div>
                    <div class="small text-muted"><i class="bi bi-graph-down-arrow me-1 text-danger"></i> Gross outflows in range</div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card slide-up" style="animation-delay: 0.2s">
                    <div class="icon-circle bg-red-soft">
                        <i class="bi bi-receipt-cutoff"></i>
                    </div>
                    <div class="text-muted small fw-bold text-uppercase">Unsettled Bills</div>
                    <div class="h2 fw-800 text-dark mt-2 mb-1"><?= $pending_bills_count ?> <span class="fs-6 fw-normal text-muted">Records</span></div>
                    <div class="small text-muted"><i class="bi bi-clock-history me-1"></i> Awaiting cash flow authorization</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card slide-up" style="animation-delay: 0.3s">
                    <div class="icon-circle bg-forest-soft">
                        <i class="bi bi-clipboard-data"></i>
                    </div>
                    <div class="text-muted small fw-bold text-uppercase">Record Count</div>
                    <div class="h2 fw-800 text-dark mt-2 mb-1"><?= count($expenses) ?> <span class="fs-6 fw-normal text-muted">Total</span></div>
                    <div class="small text-muted"><i class="bi bi-shield-check me-1 text-success"></i> Audit-ready entries</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Table Section -->
            <div class="col-lg-8">
                <div class="ledger-container slide-up" style="animation-delay: 0.4s">
                    <div class="ledger-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div class="position-relative flex-grow-1" style="max-width: 400px;">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                            <input type="text" id="expenseSearch" class="search-box" placeholder="Quick search ledger...">
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-outline-dark rounded-pill px-4 dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-download me-2"></i>Export Analysis
                            </button>
                            <ul class="dropdown-menu shadow-lg border-0 mt-2">
                                <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Export Ledger (PDF)</a></li>
                                <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Export Spreadsheet</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer me-2"></i>Print Friendly View</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table-custom" id="expenseTable">
                            <thead>
                                <tr>
                                    <th>Ref / Tracking</th>
                                    <th>Payee / Description</th>
                                    <th>Classification</th>
                                    <th class="text-end">Amount (KES)</th>
                                    <th class="text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($expenses)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="opacity-25 mb-4"><i class="bi bi-folder-x display-1"></i></div>
                                            <h5 class="fw-bold text-muted">No Ledger Data Found</h5>
                                            <p class="text-muted">Change your filters or add a new expense entry.</p>
                                        </td>
                                    </tr>
                                <?php else: 
                                foreach($expenses as $ex): 
                                    preg_match('/\[(.*?)\]/', $ex['notes'], $cat_match);
                                    $display_cat = $cat_match[1] ?? 'General';
                                    $clean_notes = trim(str_replace(['[PENDING]', $cat_match[0] ?? ''], '', $ex['notes']));
                                    $is_pending = stripos($ex['notes'], 'pending') !== false;
                                ?>
                                    <tr class="expense-row">
                                        <td>
                                            <div class="fw-bold text-dark"><?= esc($ex['reference_no'] ?: 'GEN-REF') ?></div>
                                            <div class="small text-muted mt-1"><?= date('d M, Y', strtotime($ex['created_at'])) ?></div>
                                        </td>
                                        <td>
                                            <?php if($ex['related_id']): ?>
                                                <a href="transactions.php?filter=<?= $ex['related_id'] ?>" class="text-decoration-none" title="Audit Asset Expenses">
                                                    <div class="fw-600 text-forest"><?= esc($clean_notes ?: 'Operational Expense') ?></div>
                                                </a>
                                            <?php else: ?>
                                                <div class="fw-600 text-dark"><?= esc($clean_notes ?: 'Operational Expense') ?></div>
                                            <?php endif; ?>
                                            <div class="small text-muted mt-1 opacity-75"><?= esc($ex['transaction_type']) ?> Entry</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small">
                                                <i class="bi bi-bookmark-fill me-1 text-muted"></i><?= $display_cat ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="fw-800 fs-6 <?= $is_pending ? 'text-muted' : 'text-danger' ?>">
                                                KES <?= number_format((float)$ex['amount']) ?>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <?php if($is_pending): ?>
                                                <span class="status-badge badge-pending">PENDING</span>
                                            <?php else: ?>
                                                <span class="status-badge badge-paid">SETTLED</span>
                                            <?php endif; ?>
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
                <div class="chart-card slide-up" style="animation-delay: 0.5s">
                    <h5 class="fw-bold mb-4">Expense Categories</h5>
                    <?php if(empty($cat_breakdown)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-pie-chart text-muted display-4 opacity-25"></i>
                            <p class="text-muted small mt-3">No data to display breakdown</p>
                        </div>
                    <?php else: ?>
                        <div style="height: 280px; position: relative;">
                            <canvas id="expenseChart" 
                                data-labels='<?= json_encode(array_keys($cat_breakdown)) ?>' 
                                data-values='<?= json_encode(array_values($cat_breakdown)) ?>'>
                            </canvas>
                        </div>
                        <div class="mt-5">
                            <h6 class="small fw-bold text-uppercase text-muted mb-3">Top Categories</h6>
                            <?php 
                            arsort($cat_breakdown);
                            foreach(array_slice($cat_breakdown, 0, 5) as $name => $val): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--forest);"></div>
                                        <span class="small fw-600"><?= $name ?></span>
                                    </div>
                                    <span class="small fw-bold">KES <?= number_format((float)$val) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
       <?php $layout->footer(); ?>
        </div>
         
    </div>
    
   
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-2xl">
            <div class="modal-header">
                <h5 class="modal-title fw-800"><i class="bi bi-receipt me-2"></i>Record New Expenditure</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_expense">
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Expense Category</label>
                            <select name="category" class="form-select" required>
                                <option value="Maintenance">Vehicle Maintenance</option>
                                <option value="Fuel">Fuel & Petroleum</option>
                                <option value="Salaries">Staff Payroll</option>
                                <option value="Rent">Office / Property Rent</option>
                                <option value="Utilities">Utilities & Bills</option>
                                <option value="Office">Admin & Sundries</option>
                                <option value="Legal">Legal & Professional</option>
                                <option value="Other">Miscellaneous</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Link to Asset (Optional)</label>
                            <select name="unified_asset_id" class="form-select">
                                <option value="other_0">-- General Operational (Unlinked) --</option>
                                <optgroup label="Active Portfolio">
                                    <?php 
                                    mysqli_data_seek($investments_list, 0);
                                    while($inv = $investments_list->fetch_assoc()): ?>
                                        <option value="inv_<?= $inv['investment_id'] ?>">
                                            [<?= strtoupper(str_replace('_',' ',$inv['category'])) ?>] <?= esc($inv['title']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Payee / Vendor Name</label>
                            <input type="text" name="payee" class="form-control" placeholder="e.g. Apex Mechanics, Skyway Landlord" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Amount (KES)</label>
                            <input type="number" name="amount" class="form-control fw-bold" min="1" step="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expense Date</label>
                            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Payment Source</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="cash">Cash Float</option>
                                <option value="mpesa">M-Pesa Business</option>
                                <option value="bank">Bank Wire</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Reference No. (Receipt/Invoice)</label>
                            <input type="text" name="ref_no" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Narration / Internal Notes</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Brief context for audit trail..."></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check p-3 border rounded-4 bg-white d-flex align-items-center">
                                <input class="form-check-input ms-0 me-3" type="checkbox" name="is_pending" id="pendingCheck">
                                <label class="form-check-label fw-700 text-forest mb-0" for="pendingCheck">
                                    Mark as Outstanding Liability (Unpaid Bill)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lime rounded-pill px-5 shadow-lg">Authorize & Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>/public/assets/js/main.js?v=<?= time() ?>"></script>
<script>
    function toggleDateInputs(val) {
        if(val === 'custom') document.getElementById('customDateRange').classList.remove('d-none');
        else {
            document.getElementById('customDateRange').classList.add('d-none');
            document.getElementById('filterForm').submit();
        }
    }

    // Interactive Search
    document.getElementById('expenseSearch')?.addEventListener('keyup', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.expense-row').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
        });
    });

    // Charting Engine
    document.addEventListener('DOMContentLoaded', () => {
        const ctx = document.getElementById('expenseChart');
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
                            backgroundColor: ['#0f2e25', '#d0f35d', '#1a4d3d', '#a8cf12', '#22c55e', '#ef4444', '#3b82f6', '#f59e0b'],
                            borderWidth: 0,
                            hoverOffset: 15
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { 
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(item) {
                                        return ` KES ${item.raw.toLocaleString()}`;
                                    }
                                }
                            }
                        },
                        cutout: '75%'
                    }
                });
            }
        }
    });
</script>
</body>
</html>
