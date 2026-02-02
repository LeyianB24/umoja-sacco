<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');
// admin/revenue.php
// V13 Dynamic Revenue Portal
// "No More Static Tables"


require_once __DIR__ . '/../../inc/TransactionHelper.php';
require_once __DIR__ . '/../../inc/ExportHelper.php';
Auth::requireAdmin();
require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');

$success = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_revenue'])) {
    $source = $_POST['source_type'];
    $amount = (float)$_POST['amount'];
    $method = $_POST['payment_method'] ?? 'cash';
    $desc   = trim($_POST['description']);
    
    $config = [
        'type'           => 'income',
        'category'       => 'revenue',
        'amount'         => $amount,
        'method'         => $method,
        'notes'          => $desc,
        'related_table'  => ($source === 'vehicle' ? 'vehicles' : 'investments'),
        'related_id'     => ($source === 'vehicle' ? (int)$_POST['vehicle_id'] : (int)$_POST['investment_id'])
    ];

    if (TransactionHelper::record($config)) {
        $success = "Revenue recorded successfully and synced to Golden Ledger.";
    } else {
        $error = "Failed to record revenue. Ensure Financial Engine is active.";
    }
}

// Fetch Data
$vehicles = $conn->query("SELECT * FROM vehicles WHERE status='active'");
$revenue_qry = "SELECT t.*, 
                CASE 
                    WHEN t.related_table = 'vehicle_income' THEN v.reg_no 
                    WHEN t.related_table = 'investment_income' THEN i.title
                    ELSE 'General / Other' 
                END as source_name 
                FROM transactions t 
                LEFT JOIN vehicles v ON t.related_id = v.vehicle_id AND t.related_table = 'vehicle_income'
                LEFT JOIN investments i ON t.related_id = i.investment_id AND t.related_table = 'investment_income'
                WHERE t.transaction_type = 'income' 
                ORDER BY t.transaction_date DESC, t.created_at DESC LIMIT 100";
$revenue = $conn->query($revenue_qry);

$total_rev = $conn->query("SELECT SUM(amount) FROM transactions WHERE transaction_type='income'")->fetch_row()[0] ?? 0;

$pageTitle = "Revenue Portal";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/public/assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill" style="margin-left: 280px; padding: 2rem;">
        <?php $layout->topbar($pageTitle ?? ''); ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Revenue Management</h2>
                <p class="text-muted">Track all inflows dynamically.</p>
            </div>
            <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#revenueModal">
                <i class="bi bi-plus-lg me-2"></i> Record New Revenue
            </button>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle-fill me-2"></i> <?= $success ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-3">
                    <p class="text-muted small fw-bold text-uppercase mb-1">Total Revenue (All Time)</p>
                    <h3 class="fw-bold text-success">KES <?= number_format((float)$total_rev, 2) ?></h3>
                </div>
            </div>
        </div>

        <!-- Dynamic Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold m-0">Recent Inflows</h6>
                <div class="btn-group">
                    <a href="export_revenue.php?type=pdf" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf"></i> PDF</a>
                    <a href="export_revenue.php?type=excel" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel"></i> Excel</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Ref #</th>
                            <th>Date</th>
                            <th>Source</th>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $revenue->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?= $row['reference_no'] ?></td>
                            <td><?= date('M d, Y', strtotime($row['transaction_date'])) ?></td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= ucfirst($row['related_table']) ?>
                                </span>
                            </td>
                            <td class="small"><?= htmlspecialchars($row['notes']) ?></td>
                            <td class="text-end fw-bold text-success">
                                + <?= number_format((float)$row['amount'], 2) ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="revenueModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Record Received Income</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="record_revenue" value="1">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Source Type</label>
                    <select name="source_type" class="form-select" id="sourceSelect" onchange="toggleSource()" required>
                        <option value="vehicle">Vehicle Fleet</option>
                        <option value="investment">Investment / Other</option>
                    </select>
                </div>

                <div class="mb-3" id="vehField">
                    <label class="form-label">Select Vehicle</label>
                    <select name="vehicle_id" class="form-select">
                        <?php while($v = $vehicles->fetch_assoc()): ?>
                            <option value="<?= $v['vehicle_id'] ?>"><?= $v['reg_no'] ?> - <?= $v['model'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-3 d-none" id="invField">
                    <label class="form-label">Investment ID (Optional)</label>
                    <input type="number" name="investment_id" class="form-control" value="0">
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Amount (KES)</label>
                        <input type="number" name="amount" class="form-control" step="0.01" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Cash Case</option>
                            <option value="mpesa">M-Pesa Float</option>
                            <option value="bank">Bank Account</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description / Notes</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="e.g. Daily Matatu Income"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success px-4">Save & Sync Ledger</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSource() {
        const type = document.getElementById('sourceSelect').value;
        if(type === 'vehicle') {
            document.getElementById('vehField').classList.remove('d-none');
            document.getElementById('invField').classList.add('d-none');
        } else {
            document.getElementById('vehField').classList.add('d-none');
            document.getElementById('invField').classList.remove('d-none');
        }
    }
</script>
</body>
</html>





