<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/TransactionHelper.php';

$layout = LayoutManager::create('admin');

require_admin();
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
        $_SESSION['error'] = "Expense amount must be valid.";
    } else {
        $notes = "[$category] $payee"; 
        if (!empty($desc)) $notes .= " - $desc";
        if ($is_pending) $notes .= " [PENDING]";

        $conn->begin_transaction();
        try {
            $unified_id = $_POST['unified_asset_id'] ?? '';
            $related_id = 0;
            $related_table = null;

            if ($unified_id && $unified_id !== 'other_0') {
                list($source, $related_id) = explode('_', $unified_id);
                $related_id = (int)$related_id;
                $related_table = 'investments';
            }

            $method = $_POST['payment_method'] ?? 'cash';
            
            $ok = TransactionHelper::record([
                'member_id'     => null,
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
            $_SESSION['success'] = "Expense recorded successfully!";
            header("Location: expenses.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }
}

// 3. Data Fetching
$duration = $_GET['duration'] ?? '3months';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-3 months'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$date_filter = "";
if ($duration !== 'all') {
    switch ($duration) {
        case 'today': $start_date = $end_date = date('Y-m-d'); break;
        case 'weekly': $start_date = date('Y-m-d', strtotime('-7 days')); break;
        case 'monthly': $start_date = date('Y-m-01'); $end_date = date('Y-m-t'); break;
        case '3months': $start_date = date('Y-m-d', strtotime('-3 months')); break;
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
        preg_match('/\[(.*?)\]/', $row['notes'], $matches);
        $cat = $matches[1] ?? 'Uncategorized';
        if (stripos($row['notes'], 'pending') !== false) $pending_bills_count++;
        if (!isset($cat_breakdown[$cat])) $cat_breakdown[$cat] = 0;
        $cat_breakdown[$cat] += $row['amount'];
    }
}

// Handle Export
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    $format = match($_GET['action']) { 'export_excel' => 'excel', 'print_report' => 'print', default => 'pdf' };
    $export_data = [];
    foreach ($expenses as $ex) {
        preg_match('/\[(.*?)\]/', $ex['notes'], $cat_match);
        $display_cat = $cat_match[1] ?? 'General';
        $export_data[] = [
            'Date' => date('d-m-Y', strtotime($ex['created_at'])),
            'Reference' => $ex['reference_no'],
            'Payee/Details' => trim(str_replace(['[PENDING]', $cat_match[0] ?? ''], '', $ex['notes'])),
            'Category' => $display_cat,
            'Amount' => number_format((float)$ex['amount'], 2),
            'Status' => (stripos($ex['notes'], 'pending') !== false) ? 'Pending' : 'Paid'
        ];
    }
    UniversalExportEngine::handle($format, $export_data, [
        'title' => 'Expense Ledger',
        'module' => 'Expense Management',
        'headers' => ['Date', 'Reference', 'Payee/Details', 'Category', 'Amount', 'Status'],
        'total_value' => $total_period_expense
    ]);
    exit;
}

$investments_list = $conn->query("SELECT investment_id, title FROM investments WHERE status = 'active' ORDER BY title ASC");
$investments_all = $investments_list->fetch_all(MYSQLI_ASSOC);
$pageTitle = "Expenses Portal";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= SITE_NAME ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .main-content { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding-bottom: 2rem; }
        @media (max-width: 991px) { .main-content { margin-left: 0; } }

        .card-custom { border: 1px solid var(--border-color); border-radius: 20px; }
        .btn-lime { background-color: var(--lime); color: #000000; font-weight: 600; border: none; border-radius: 50px; padding: 0.5rem 1.5rem; transition: all 0.2s; }
        .btn-lime:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-forest { background-color: var(--lime); color: #000000; border-radius: 50px; padding: 0.5rem 1.5rem; }
        .btn-forest:hover { opacity: 0.9; }

        .form-control, .form-select { border-radius: 12px; padding: 0.6rem 1rem; }
        .table-custom th { font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-color); padding: 1rem; }
        .table-custom td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid var(--border-color); font-size: 0.95rem; }

        .modal-header { background-color: #000000; color: white; border-top-left-radius: 20px; border-top-right-radius: 20px; border-bottom: 1px solid var(--border-color); }
        .modal-content { border-radius: 20px; }
        .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
        
        .stat-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; background: rgba(255,255,255,0.05); }
        .bg-lime-subtle { color: var(--lime); }
        .bg-red-subtle { color: #dc2626; }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>
<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content">
        <?php $layout->topbar($pageTitle); ?>

        <div class="container-fluid">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
                <div>
                    <h2 class="fw-bold mb-1" style="color: var(--forest-dark);">Expenditure Portal</h2>
                    <p class="text-muted mb-0">Record and track office operational spending.</p>
                </div>
                <div class="d-flex gap-2">
                    <div class="dropdown">
                        <button class="btn btn-outline-dark dropdown-toggle shadow-sm" data-bs-toggle="dropdown" style="border-radius: 50px;">
                            <i class="bi bi-download me-2"></i>Export
                        </button>
                        <ul class="dropdown-menu shadow-lg">
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>">Export PDF</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>">Export Excel</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank">Print View</a></li>
                        </ul>
                    </div>
                    <button class="btn btn-lime shadow-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                        <i class="bi bi-plus-lg me-2"></i>Record Expenditure
                    </button>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success rounded-3 mb-4">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Filter Controls -->
            <div class="card-custom p-4 mb-4">
                <form method="GET" class="row g-3 align-items-end" id="filterForm">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Duration</label>
                        <select name="duration" class="form-select" onchange="toggleDateInputs(this.value)">
                            <option value="all" <?= $duration === 'all' ? 'selected' : '' ?>>Historical Archive</option>
                            <option value="today" <?= $duration === 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="weekly" <?= $duration === 'weekly' ? 'selected' : '' ?>>Past 7 Days</option>
                            <option value="monthly" <?= $duration === 'monthly' ? 'selected' : '' ?>>This Month</option>
                            <option value="3months" <?= $duration === '3months' ? 'selected' : '' ?>>Last Quarter</option>
                            <option value="custom" <?= $duration === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                        </select>
                    </div>
                    <div id="customDateRange" class="col-md-6 <?= $duration !== 'custom' ? 'd-none' : '' ?>">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">End Date</label>
                                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-forest w-100">Filter View</button>
                        <a href="expenses.php" class="btn btn-light rounded-3 px-3 border"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>
            </div>

            <?php include __DIR__ . '/../../inc/finance_nav.php'; ?>

            <!-- KPIs -->
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="card-custom p-4 d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background: rgba(190, 242, 100, 0.2); color: var(--forest-dark);"><i class="bi bi-wallet2"></i></div>
                        <div>
                            <div class="small text-muted fw-bold">TOTAL SPENDING</div>
                            <div class="h4 fw-bold mb-0">KES <?= number_format((float)$total_period_expense) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-custom p-4 d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background: #fee2e2; color: #991b1b;"><i class="bi bi-clock-history"></i></div>
                        <div>
                            <div class="small text-muted fw-bold">PENDING BILLS</div>
                            <div class="h4 fw-bold mb-0"><?= $pending_bills_count ?> <small class="text-muted fw-normal" style="font-size: 0.8rem;">Records</small></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-custom p-4 d-flex align-items-center gap-3">
                        <div class="stat-icon" style="background: #f1f5f9; color: #64748b;"><i class="bi bi-journal-text"></i></div>
                        <div>
                            <div class="small text-muted fw-bold">ENTRY COUNT</div>
                            <div class="h4 fw-bold mb-0"><?= count($expenses) ?> <small class="text-muted fw-normal" style="font-size: 0.8rem;">Total</small></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="card-custom p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Ref & Date</th>
                                <th>Payee / Details</th>
                                <th>Classification</th>
                                <th class="text-end">Amount (KES)</th>
                                <th class="text-end pe-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($expenses)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No records found.</td></tr>
                            <?php else: foreach($expenses as $ex): 
                                preg_match('/\[(.*?)\]/', $ex['notes'], $cat_match);
                                $display_cat = $cat_match[1] ?? 'General';
                                $is_pending = stripos($ex['notes'], 'pending') !== false;
                            ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold "><?= esc($ex['reference_no'] ?: 'REF-'.$ex['ledger_transaction_id']) ?></div>
                                        <div class="small text-muted"><?= date('d M, Y', strtotime($ex['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-500 "><?= esc(trim(str_replace(['[PENDING]', $cat_match[0] ?? ''], '', $ex['notes']))) ?></div>
                                        <div class="small text-muted" style="font-size: 0.75rem;"><?= $ex['related_id'] ? 'Linked to Asset' : 'Office Operation' ?></div>
                                    </td>
                                    <td><span class="badge bg-light  border rounded-pill fw-normal px-3 py-2"><?= $display_cat ?></span></td>
                                    <td class="text-end fw-bold text-danger">
                                        KES <?= number_format((float)$ex['amount'], 2) ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <span class="badge rounded-pill <?= $is_pending ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success' ?> px-3 py-2 fw-bold" style="font-size: 0.7rem;">
                                            <?= $is_pending ? 'PENDING' : 'SETTLED' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php $layout->footer(); ?>
            </div>
        </div>
    </div>
</div>

<!-- Classic Record Expenditure Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Record Expenditure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_expense">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Expense Category</label>
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
                            <label class="form-label small fw-bold text-uppercase text-muted">Link to Asset (Optional)</label>
                            <select name="unified_asset_id" class="form-select">
                                <option value="other_0">-- General Operational --</option>
                                <?php foreach($investments_all as $inv): ?>
                                    <option value="inv_<?= $inv['investment_id'] ?>"><?= esc($inv['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase text-muted">Payee / Vendor Name</label>
                            <input type="text" name="payee" class="form-control" placeholder="e.g. Apex Mechanics" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Amount (KES)</label>
                            <input type="number" name="amount" class="form-control fw-bold" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Expense Date</label>
                            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase text-muted">Reference / Receipt No.</label>
                            <input type="text" name="ref_no" class="form-control" placeholder="TXN-XXXX" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase text-muted">Payment Source</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="cash">Cash Float</option>
                                <option value="mpesa">M-Pesa Business</option>
                                <option value="bank">Bank Wire</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase text-muted">Internal Notes</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Audit notes..."></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check p-3 border rounded-3 bg-light d-flex align-items-center gap-3">
                                <input class="form-check-input ms-0" type="checkbox" name="is_pending" id="pendingCheck">
                                <label class="form-check-label fw-bold mb-0" for="pendingCheck">Mark as Outstanding Liability (Unpaid)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 justify-content-center text-center">
                    <button type="button" class="btn btn-light rounded-pill px-4 me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lime rounded-pill px-5 shadow-sm">Authorize & Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleDateInputs(val) {
        if(val === 'custom') document.getElementById('customDateRange').classList.remove('d-none');
        else {
            document.getElementById('customDateRange').classList.add('d-none');
            document.getElementById('filterForm').submit();
        }
    }
</script>
</body>
</html>
