<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/TransactionHelper.php';

// 1. Auth & Registry
require_admin();
require_permission('employees.php'); // Shared permission with HR

$layout = LayoutManager::create('admin');
$db = $conn;
$admin_id = $_SESSION['admin_id'];

// 2. Fetch Active Month
$active_month = $_GET['month'] ?? date('Y-m');
$year = intval(substr($active_month, 0, 4));

// 3. Handle Salary Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'process_payout') {
        $emp_id = intval($_POST['employee_id']);
        $month = $_POST['month'];
        $salary = floatval($_POST['base_salary']);
        $allowances = floatval($_POST['allowances'] ?? 0);
        $deductions = floatval($_POST['deductions'] ?? 0);
        $net_pay = $salary + $allowances - $deductions;
        $payment_date = date('Y-m-d');

        // Check if already paid for this month
        $check = $db->prepare("SELECT id FROM payroll WHERE employee_id = ? AND month = ?");
        $check->bind_param("is", $emp_id, $month);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            flash_set("Salary already processed for this employee for $month.", "warning");
        } else {
            // Start Transaction
            $db->begin_transaction();
            try {
                // 1. Record In Payroll Table
                $stmt = $db->prepare("INSERT INTO payroll (employee_id, month, year, basic_salary, allowances, deductions, net_pay, payment_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid')");
                $stmt->bind_param("isidddds", $emp_id, $month, $year, $salary, $allowances, $deductions, $net_pay, $payment_date);
                $stmt->execute();
                $payroll_id = $db->insert_id;

                // 2. Record in Golden Ledger
                $ref = TransactionHelper::record([
                    'type'           => 'expense',
                    'category'       => 'payroll',
                    'amount'         => $net_pay,
                    'notes'          => "Monthly Salary Payout ($month) for Employee #$emp_id",
                    'related_table'  => 'payroll',
                    'related_id'     => $payroll_id
                ]);

                $db->commit();
                flash_set("Payroll processed successfully for " . $_POST['emp_name'], "success");
            } catch (Exception $e) {
                $db->rollback();
                flash_set("Payroll processing failed: " . $e->getMessage(), "danger");
            }
        }
        header("Location: payroll.php?month=$month");
        exit;
    }
}

// 4. Fetch Employee Payroll Status for Month
$sql = "SELECT e.*, p.id as payroll_id, p.net_pay, p.payment_date, p.status as p_status
        FROM employees e
        LEFT JOIN payroll p ON e.employee_id = p.employee_id AND p.month = ?
        WHERE e.status = 'active'
        ORDER BY e.full_name ASC";
$stmt = $db->prepare($sql);
$stmt->bind_param("s", $active_month);
$stmt->execute();
$payroll_list = $stmt->get_result();

$stats = $db->query("SELECT SUM(net_pay) as total, COUNT(*) as count FROM payroll WHERE month = '$active_month'")->fetch_assoc();

$pageTitle = "Universal Payroll Hub";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> | USMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --forest: #0F2E25; --lime: #D0F35D; --glass: rgba(255, 255, 255, 0.95); }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0f4f3; }
        .main-content { margin-left: 280px; padding: 40px; min-height: 100vh; }
        .hp-banner {
            background: linear-gradient(135deg, var(--forest) 0%, #1a4d3e 100%);
            border-radius: 30px; padding: 40px; color: white; margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(15, 46, 37, 0.15);
        }
        .payroll-card {
            background: var(--glass); backdrop-filter: blur(10px);
            border-radius: 24px; border: 1px solid rgba(255,255,255,0.5);
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
        }
        .stat-box { background: rgba(255,255,255,0.1); border-radius: 15px; padding: 15px 25px; }
    </style>
</head>
<body>

<?php $layout->sidebar(); ?>

<div class="main-content">
    <?php $layout->topbar($pageTitle); ?>

    <div class="hp-banner fade-in">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3">Treasury Disbursment</span>
                <h1 class="display-5 fw-800 mb-2">Payroll Management</h1>
                <p class="opacity-75 fs-5">Processing monthly benefits and salaries for <?= SITE_NAME ?> staff.</p>
            </div>
            <div class="col-lg-5 text-lg-end">
                <div class="d-inline-flex gap-3">
                    <div class="stat-box text-start">
                        <div class="small fw-bold opacity-75">MONTHLY TOTAL</div>
                        <div class="h4 fw-800 mb-0 text-lime">KES <?= number_format((float)($stats['total'] ?? 0)) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php flash_render(); ?>

    <div class="payroll-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-800 mb-0">Payroll Registry: <?= date('F Y', strtotime($active_month)) ?></h5>
            <div class="d-flex gap-2">
                <input type="month" id="monthSelector" class="form-control rounded-pill px-3" value="<?= $active_month ?>" onchange="location.href='?month='+this.value">
                <a href="?action=export_pdf&month=<?= $active_month ?>" class="btn btn-outline-forest rounded-pill px-4 fw-bold"><i class="bi bi-download me-2"></i>Export Summary</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr class="text-muted small fw-bold text-uppercase">
                        <th>Employee Details</th>
                        <th>Job Title</th>
                        <th class="text-end">Base Salary</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($emp = $payroll_list->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= esc($emp['full_name']) ?></div>
                                <div class="small text-muted"><?= esc($emp['national_id']) ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border rounded-pill"><?= esc($emp['job_title']) ?></span></td>
                            <td class="text-end fw-bold text-forest">KES <?= number_format((float)$emp['salary']) ?></td>
                            <td class="text-center">
                                <?php if($emp['payroll_id']): ?>
                                    <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Paid</span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning px-3 py-2 rounded-pill"><i class="bi bi-clock-history me-1"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if(!$emp['payroll_id']): ?>
                                    <button class="btn btn-forest btn-sm rounded-pill px-3 fw-bold" onclick="showProcessModal(<?= htmlspecialchars(json_encode($emp)) ?>)">
                                        Process Pay
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-light btn-sm border rounded-pill px-3 disabled">
                                        Vault Sealed
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Process Pay Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <input type="hidden" name="action" value="process_payout">
                <input type="hidden" name="employee_id" id="pay_emp_id">
                <input type="hidden" name="emp_name" id="pay_emp_name_val">
                <input type="hidden" name="month" value="<?= $active_month ?>">
                <div class="modal-header border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-800">Process Salary Payout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert bg-forest-soft text-forest border-0 rounded-4 p-3 mb-4">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        Paying <strong id="pay_emp_name"></strong> for <strong><?= date('F Y', strtotime($active_month)) ?></strong>.
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted">Base Salary (KES)</label>
                            <input type="number" name="base_salary" id="pay_salary" class="form-control fw-bold border-0 bg-light" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Allowances (+)</label>
                            <input type="number" name="allowances" class="form-control" value="0" step="0.01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Deductions (-)</label>
                            <input type="number" name="deductions" class="form-control" value="0" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-forest rounded-pill px-4 fw-bold">Confirm Payout</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function showProcessModal(emp) {
        document.getElementById('pay_emp_id').value = emp.employee_id;
        document.getElementById('pay_emp_name').innerText = emp.full_name;
        document.getElementById('pay_emp_name_val').value = emp.full_name;
        document.getElementById('pay_salary').value = emp.salary;
        new bootstrap.Modal(document.getElementById('payModal')).show();
    }
</script>
</body>
</html>
