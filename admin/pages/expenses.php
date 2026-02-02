<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

// DEBUG: Add debug info at the very top
error_log("=== EXPENSES PAGE DEBUG ===");
error_log("Session ID: " . session_id());
error_log("Admin ID: " . ($_SESSION['admin_id'] ?? 'NOT SET'));
error_log("Role ID: " . ($_SESSION['role_id'] ?? 'NOT SET'));
error_log("Request URI: " . $_SERVER['REQUEST_URI']);

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');
// accountant/expenses.php

require_once __DIR__ . '/../../inc/TransactionHelper.php';
// 1. Auth Check
error_log("Before require_admin()");
require_admin();
error_log("After require_admin() - passed auth check");

// Initialize Layout Manager
$layout = LayoutManager::create('admin');
error_log("Before require_permission()");
require_permission();
error_log("After require_permission() - passed permission check");

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
            $inv_id = !empty($_POST['investment_id']) ? intval($_POST['investment_id']) : NULL;
            $method = $_POST['payment_method'] ?? 'cash';
            
            // Record in Central Ledger via TransactionHelper/FinancialEngine
            $ok = TransactionHelper::record([
                'member_id'     => null, // System Expense
                'amount'        => $amount,
                'type'          => 'expense',
                'category'      => $category,
                'method'        => $method,
                'ref_no'        => $ref_no,
                'notes'         => $notes,
                'related_id'    => $inv_id,
                'related_table' => ($inv_id ? 'investments' : null),
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
$duration = $_GET['duration'] ?? 'monthly'; // Default to this month for expenses
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$date_filter = "";
$params = [];
$types = "";

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
        case 'custom':
            // keep start_date and end_date as provided
            break;
    }

    if ($start_date && $end_date) {
        $date_filter = " AND created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
    }
}

$where = "transaction_type = 'expense' $date_filter";

$sql = "SELECT * FROM transactions WHERE $where ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Debug: Check database connection and results
error_log("Expenses query result count: " . $result->num_rows);
error_log("Expenses SQL: " . $sql);
if (!empty($params)) {
    error_log("Expenses Params: " . print_r($params, true));
}

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection error: " . $conn->connect_error);
} else {
    error_log("Database connection: OK");
}

// Check if transactions table exists
$table_check = $conn->query("SHOW TABLES LIKE 'transactions'");
if ($table_check->num_rows == 0) {
    error_log("Transactions table does not exist!");
} else {
    error_log("Transactions table exists");
    $total_transactions = $conn->query("SELECT COUNT(*) as count FROM transactions")->fetch_assoc()['count'];
    error_log("Total transactions in database: " . $total_transactions);
    
    // Check expense transactions specifically
    $expense_count = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE transaction_type = 'expense'")->fetch_assoc()['count'];
    error_log("Total expense transactions: " . $expense_count);
}

$expenses = [];
$total_period_expense = 0;
$pending_bills_count = 0;
$cat_breakdown = []; 

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

// DEBUG: Log the results
error_log("Expenses processed: " . count($expenses));
error_log("Total period expense: " . $total_period_expense);
error_log("Pending bills count: " . $pending_bills_count);
error_log("Category breakdown: " . print_r($cat_breakdown, true));

// 4. Fetch Investments for Attribution
$investments_list = $conn->query("SELECT investment_id, title, category, reg_no FROM investments WHERE status = 'active' ORDER BY category, title ASC");

$pageTitle = "Expense Management";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom Assets -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css?v=<?= time() ?>">
    
    <style>
        body { 
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        /* =============================
           ENHANCED ANIMATIONS
        ============================= */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }
            100% {
                background-position: 1000px 0;
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 0.8;
            }
            50% {
                transform: scale(1.05);
                opacity: 1;
            }
        }
        
        @keyframes countUp {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .animate-slide-up {
            animation: slideInUp 0.6s ease-out;
        }
        
        .animate-slide-left {
            animation: slideInLeft 0.6s ease-out;
        }
        
        .animate-fade-scale {
            animation: fadeInScale 0.5s ease-out;
        }
        
        .animate-pulse {
            animation: pulse 2s ease-in-out infinite;
        }
        
        /* =============================
           ENHANCED STAT CARDS
        ============================= */
        .card-custom {
            border: none;
            border-radius: 16px;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .card-custom::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shimmer 3s infinite;
        }
        
        .card-custom:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(15, 46, 37, 0.15);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .card-custom:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .icon-expense {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .icon-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .h4.fw-bold {
            background: linear-gradient(135deg, var(--forest-deep), var(--hope-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: countUp 1s ease-out;
        }
        
        /* =============================
           ENHANCED TABLE
        ============================= */
        .table-custom {
            border-radius: 16px;
            overflow: hidden;
        }
        
        .table-custom thead {
            background: linear-gradient(135deg, var(--forest-deep), var(--hope-green));
            color: white;
        }
        
        .table-custom thead th {
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }
        
        .table-custom tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .table-custom tbody tr:hover {
            background: rgba(15, 46, 37, 0.03);
            transform: scale(1.01);
            box-shadow: 0 5px 20px rgba(15, 46, 37, 0.1);
        }
        
        /* =============================
           ENHANCED CATEGORY ICONS
        ============================= */
        .cat-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--forest-deep), var(--hope-green));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .table-custom tbody tr:hover .cat-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        /* =============================
           ENHANCED BADGES
        ============================= */
        .badge-status {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .badge-status::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .badge-status:hover::before {
            left: 100%;
        }
        
        .badge-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .badge-paid {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        /* =============================
           ENHANCED BUTTONS
        ============================= */
        .btn-lime {
            background: linear-gradient(135deg, var(--lime-vibrant), #84cc16);
            border: none;
            color: var(--forest-deep);
            font-weight: 600;
            border-radius: 50px;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-lime::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-lime:hover::before {
            left: 100%;
        }
        
        .btn-lime:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(132, 204, 22, 0.3);
        }
        
        .btn-forest {
            background: linear-gradient(135deg, var(--forest-deep), var(--hope-green));
            border: none;
            color: white;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-forest:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(15, 46, 37, 0.3);
        }
        
        /* =============================
           ENHANCED FORM ELEMENTS
        ============================= */
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--hope-green);
            box-shadow: 0 0 0 0.2rem rgba(15, 46, 37, 0.1);
        }
        
        /* =============================
           ENHANCED MODAL
        ============================= */
        .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--forest-deep), var(--hope-green));
            color: white;
            border: none;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        /* =============================
           ENHANCED CHART CONTAINER
        ============================= */
        .chart-container {
            position: relative;
            height: 250px;
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        /* =============================
           RESPONSIVE DESIGN
        ============================= */
        @media (max-width: 768px) {
            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
            
            .table-custom thead th,
            .table-custom tbody td {
                padding: 0.75rem 0.5rem;
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body class="expenses-body">

<div class="d-flex">
        <?php $layout->sidebar(); ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php $layout->topbar($pageTitle ?? ''); ?>
            
            <div class="container-fluid">

            <!-- DEBUG INFO -->
            <div class="alert alert-info mb-3">
                <strong>DEBUG INFO:</strong><br>
                Session ID: <?= session_id() ?><br>
                Admin ID: <?= $_SESSION['admin_id'] ?? 'NOT SET' ?><br>
                Role ID: <?= $_SESSION['role_id'] ?? 'NOT SET' ?><br>
                Expenses Found: <?= count($expenses) ?><br>
                Total Expense: KES <?= number_format($total_period_expense) ?>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4 animate-slide-up">
                <div>
                    <h2 class="fw-bold mb-1" style="color: var(--forest-dark);">Expense Management</h2>
                    <p class="text-muted small mb-0">Track spending and manage operational costs efficiently.</p>
                </div>
                <button class="btn btn-lime shadow-sm animate-pulse" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                    <i class="bi bi-plus-lg me-2"></i>Record Expense
                </button>
            </div>

            <?php flash_render(); ?>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card-custom p-3 d-flex flex-row align-items-center gap-3 animate-slide-up">
                        <div class="stat-icon icon-expense">
                            <i class="bi bi-graph-down-arrow"></i>
                        </div>
                        <div>
                            <div class="small text-muted fw-bold text-uppercase">Period Spend</div>
                            <div class="h4 fw-bold mb-0 text-dark">KES <?= number_format((float)$total_period_expense) ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card-custom p-3 d-flex flex-row align-items-center gap-3 animate-slide-up" style="animation-delay: 0.1s">
                        <div class="stat-icon icon-pending">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div>
                            <div class="small text-muted fw-bold text-uppercase">Pending Bills</div>
                            <div class="h4 fw-bold mb-0 text-dark"><?= $pending_bills_count ?> <span class="fs-6 fw-normal text-muted">Records</span></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card-custom p-3 h-100 d-flex align-items-center animate-slide-up" style="animation-delay: 0.2s">
                        <form method="GET" class="w-100 d-flex gap-2 align-items-center" id="filterForm">
                            <div class="grow">
                                <label class="small text-muted fw-bold mb-1 d-block">Duration</label>
                                <select name="duration" class="form-select form-select-sm d-inline-block w-auto" onchange="toggleDateInputs(this.value)">
                                    <option value="all" <?= $duration === 'all' ? 'selected' : '' ?>>All Time</option>
                                    <option value="today" <?= $duration === 'today' ? 'selected' : '' ?>>Today</option>
                                    <option value="weekly" <?= $duration === 'weekly' ? 'selected' : '' ?>>Last 7 Days</option>
                                    <option value="monthly" <?= $duration === 'monthly' ? 'selected' : '' ?>>This Month</option>
                                    <option value="3months" <?= $duration === '3months' ? 'selected' : '' ?>>Last 3 Months</option>
                                    <option value="custom" <?= $duration === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                                </select>
                            </div>
                            <div id="customDateRange" class="d-flex gap-2 <?= $duration !== 'custom' ? 'd-none' : '' ?>">
                                <div>
                                    <label class="small text-muted fw-bold mb-1 d-block">Start</label>
                                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
                                </div>
                                <div>
                                    <label class="small text-muted fw-bold mb-1 d-block">End</label>
                                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-forest btn-sm px-3">Filter</button>
                                <a href="expenses.php" class="btn btn-light btn-sm ms-1 text-muted" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                
                <div class="col-lg-8">
                    <div class="card-custom p-0 overflow-hidden h-100 animate-slide-left">
                        <div class="p-4 border-bottom border-light d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-dark">Expense Ledger</h6>
                            <button class="btn btn-sm btn-light border text-muted">
                                <i class="bi bi-download me-2"></i>Export
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Date & Ref</th>
                                        <th>Details (Payee)</th>
                                        <th>Category</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end pe-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($expenses)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                                                No expenses recorded for this period.
                                            </td>
                                        </tr>
                                    <?php else: foreach($expenses as $ex): 
                                        // Parse Data
                                        preg_match('/\[(.*?)\]/', $ex['notes'], $cat_match);
                                        $display_cat = $cat_match[1] ?? 'General';
                                        $clean_notes = trim(str_replace(['[PENDING]', $cat_match[0] ?? ''], '', $ex['notes']));
                                        $is_pending = stripos($ex['notes'], 'pending') !== false;
                                        
                                        // Icon Logic
                                        $icon = 'bi-receipt';
                                        if($display_cat == 'Rent') $icon = 'bi-house';
                                        if($display_cat == 'Utilities') $icon = 'bi-lightning';
                                        if($display_cat == 'Salaries') $icon = 'bi-people';
                                        if($display_cat == 'Transport') $icon = 'bi-car-front';
                                    ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-medium text-dark"><?= date('M d, Y', strtotime($ex['created_at'])) ?></div>
                                                <div class="small text-muted font-monospace"><?= esc($ex['reference_no']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark text-truncate" style="max-width: 200px;">
                                                    <?= esc($clean_notes ?: 'Expense Record') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="cat-icon">
                                                        <i class="bi <?= $icon ?>"></i>
                                                    </div>
                                                    <span class="small fw-medium text-muted"><?= $display_cat ?></span>
                                                </div>
                                            </td>
                                            <td class="text-end fw-bold" style="color: var(--expense-red);">
                                                -<?= number_format((float)$ex['amount']) ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <?php if($is_pending): ?>
                                                    <span class="badge-status badge-pending">
                                                        <i class="bi bi-clock me-1"></i>Pending
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge-status badge-paid">
                                                        <i class="bi bi-check2 me-1"></i>Paid
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card-custom p-4 h-100 animate-fade-scale">
                        <h6 class="fw-bold mb-4 text-dark">Breakdown by Category</h6>
                        <?php if(empty($cat_breakdown)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-pie-chart fs-1 opacity-25 d-block mb-2"></i>
                                No data for this period
                            </div>
                        <?php else: ?>
                            <div class="chart-container">
                                <canvas id="expenseChart" 
                                    data-labels="<?= htmlspecialchars(json_encode(array_keys($cat_breakdown))) ?>" 
                                    data-values="<?= htmlspecialchars(json_encode(array_values($cat_breakdown))) ?>">
                                </canvas>
                            </div>
                            <div class="mt-4">
                                <ul class="list-group list-group-flush">
                                    <?php 
                                    arsort($cat_breakdown);
                                    $top_cats = array_slice($cat_breakdown, 0, 5);
                                    foreach($top_cats as $name => $val): ?>
                                        <li class="list-group-item px-0 border-light d-flex justify-content-between align-items-center">
                                            <span class="text-muted small fw-medium"><?= $name ?></span>
                                            <span class="fw-bold text-dark small">KES <?= number_format((float)$val) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
         <?php $layout->footer(); ?>
    </div>
</div>

<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Record Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_expense">
                <div class="modal-body p-4">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Expense Category</label>
                        <select name="category" class="form-select" required>
                            <option value="Maintenance">Maintenance & Repairs</option>
                            <option value="Fuel">Fuel & Lubricants</option>
                            <option value="Salaries">Salaries & Wages</option>
                            <option value="Rent">Rent & Rates</option>
                            <option value="Utilities">Utilities (Water/Power)</option>
                            <option value="Office">Office Supplies</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Link to Investment/Vehicle (Optional)</label>
                        <select name="investment_id" class="form-select">
                            <option value="">-- General Operational Expense --</option>
                            <?php 
                            mysqli_data_seek($investments_list, 0);
                            while($inv = $investments_list->fetch_assoc()): ?>
                                <option value="<?= $inv['investment_id'] ?>">
                                    [<?= strtoupper(str_replace('_',' ',$inv['category'])) ?>] 
                                    <?= esc($inv['title']) ?> <?= $inv['reg_no'] ? '('.$inv['reg_no'].')' : '' ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="small text-muted mt-1">Linking helps calculate Profit/Loss for specific assets.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Payee / Vendor</label>
                        <input type="text" name="payee" class="form-control" placeholder="e.g. KPLC, Landlord" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Amount (KES)</label>
                            <input type="number" name="amount" class="form-control" min="1" step="0.01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Date</label>
                            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Reference No</label>
                        <input type="text" name="ref_no" class="form-control" placeholder="Receipt / Invoice #">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief details..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Payment Source</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Cash at Hand</option>
                            <option value="mpesa">M-Pesa Float</option>
                            <option value="bank">Bank Account</option>
                        </select>
                    </div>

                    <div class="form-check p-3 border rounded-3 bg-light">
                        <input class="form-check-input" type="checkbox" name="is_pending" id="isPending">
                        <label class="form-check-label small fw-bold text-dark" for="isPending">
                            Mark as Pending Bill
                        </label>
                        <div class="small text-muted mt-1" style="font-size: 0.75rem;">Check this if the bill has been received but not yet paid from cash.</div>
                    </div>

                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lime rounded-pill px-4">Save Expense</button>
                </div>
            </form>
        </div>
       
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>/public/assets/js/main.js?v=<?= time() ?>"></script>
<script>
    function toggleDateInputs(val) {
        const range = document.getElementById('customDateRange');
        if(val === 'custom') {
            range.classList.remove('d-none');
        } else {
            range.classList.add('d-none');
            document.getElementById('filterForm').submit();
        }
    }
</script>
</body>
</html>
</body>
</html>




