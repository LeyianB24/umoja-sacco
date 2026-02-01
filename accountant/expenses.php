<?php
// accountant/expenses.php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../inc/functions.php';

// 1. Auth Check
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['accountant', 'superadmin'])) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

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

    if ($amount <= 0) {
        flash_set("Expense amount must be valid.", "warning");
    } else {
        // Construct Notes
        $notes = "[$category] $payee"; 
        if (!empty($desc)) $notes .= " - $desc";
        if ($is_pending) $notes .= " [PENDING]";

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO transactions (member_id, transaction_type, amount, reference_no, notes, created_at) VALUES (NULL, 'expense', ?, ?, ?, ?)");
            $timestamp = $date . ' ' . date('H:i:s');
            $stmt->bind_param("dsss", $amount, $ref_no, $notes, $timestamp);
            $stmt->execute();
            $stmt->close();
            
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

// 3. Fetch Data & Calculate Stats
$month_start = date('Y-m-01');
$where = "transaction_type = 'expense'";
$params = [];
$types = "";

// Filter: Month
if (!empty($_GET['month'])) {
    $sel_month = $_GET['month']; 
    $where .= " AND DATE_FORMAT(created_at, '%Y-%m') = ?";
    $params[] = $sel_month;
    $types .= "s";
}

$sql = "SELECT * FROM transactions WHERE $where ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

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
</head>
<body class="expenses-body">

<div class="d-flex">
        <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php require_once __DIR__ . '/../inc/topbar.php'; ?>
            
            <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1" style="color: var(--forest-dark);">Expenses</h2>
                    <p class="text-muted small mb-0">Track spending and manage operational costs.</p>
                </div>
                <button class="btn btn-lime shadow-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                    <i class="bi bi-plus-lg me-2"></i>Record Expense
                </button>
            </div>

            <?php flash_render(); ?>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card-custom p-3 d-flex flex-row align-items-center gap-3">
                        <div class="stat-icon icon-expense">
                            <i class="bi bi-graph-down-arrow"></i>
                        </div>
                        <div>
                            <div class="small text-muted fw-bold text-uppercase">Period Spend</div>
                            <div class="h4 fw-bold mb-0 text-dark">KES <?= number_format($total_period_expense) ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card-custom p-3 d-flex flex-row align-items-center gap-3">
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
                    <div class="card-custom p-3 h-100 d-flex align-items-center">
                        <form class="w-100 d-flex gap-3 align-items-center">
                            <div class="grow">
                                <label class="small text-muted fw-bold mb-1">Select Period</label>
                                <input type="month" name="month" class="form-control" value="<?= $_GET['month'] ?? date('Y-m') ?>">
                            </div>
                            <div class="mt-4">
                                <button class="btn btn-forest px-4">Filter</button>
                                <a href="expenses.php" class="btn btn-light ms-2 text-muted" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                
                <div class="col-lg-8">
                    <div class="card-custom p-0 overflow-hidden h-100">
                        <div class="p-4 border-bottom border-light d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-dark">Expense Ledger</h6>
                            <button class="btn btn-sm btn-light border text-muted"><i class="bi bi-download me-2"></i>Export</button>
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
                                        <tr><td colspan="5" class="text-center py-5 text-muted">No expenses recorded for this period.</td></tr>
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
                                                    <div class="cat-icon"><i class="bi <?= $icon ?>"></i></div>
                                                    <span class="small fw-medium text-muted"><?= $display_cat ?></span>
                                                </div>
                                            </td>
                                            <td class="text-end fw-bold" style="color: var(--expense-red);">
                                                -<?= number_format($ex['amount']) ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <?php if($is_pending): ?>
                                                    <span class="badge badge-status badge-pending">
                                                        <i class="bi bi-clock me-1"></i>Pending
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-status badge-paid">
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
                    <div class="card-custom p-4 h-100">
                        <h6 class="fw-bold mb-4 text-dark">Breakdown by Category</h6>
                        <?php if(empty($cat_breakdown)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-pie-chart fs-1 opacity-25 d-block mb-2"></i>
                                No data for this period
                            </div>
                        <?php else: ?>
                            <div style="height: 250px; position: relative;">
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
                                            <span class="fw-bold text-dark small">KES <?= number_format($val) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
         <?php require_once __DIR__ . '/../inc/footer.php'; ?>
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
                            <option value="Rent">Rent & Rates</option>
                            <option value="Utilities">Utilities (Water/Power)</option>
                            <option value="Salaries">Salaries & Wages</option>
                            <option value="Office">Office Supplies</option>
                            <option value="Maintenance">Repairs & Maintenance</option>
                            <option value="Transport">Transport & Travel</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Other">Other</option>
                        </select>
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
</body>
</html>
</body>
</html>