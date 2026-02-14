<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/FinancialEngine.php';
require_once __DIR__ . '/../../inc/PayrollCalculator.php';
require_once __DIR__ . '/../../inc/PayslipGenerator.php';
require_once __DIR__ . '/../../inc/Mailer.php';
require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';

// Permissions
require_permission('payroll.php'); // Ensure slug exists or use broader permission
$layout = LayoutManager::create('admin');
$engine = new FinancialEngine($db);
$calculator = new PayrollCalculator($db);

$pageTitle = "Payroll Management";
$current_month = date('Y-m');

// ---------------------------------------------------------
// 1. HANDLE POST ACTIONS
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // START NEW RUN
    if (isset($_POST['action']) && $_POST['action'] === 'start_run') {
        $month = $_POST['month_selector']; // YYYY-MM
        
        // Check if run exists
        $check = $db->query("SELECT id FROM payroll_runs WHERE month = '$month'");
        if ($check->num_rows > 0) {
            flash_set("Payroll run for $month already exists.", "warning");
        } else {
            $stmt = $db->prepare("INSERT INTO payroll_runs (month, status, created_by) VALUES (?, 'draft', ?)");
            $admin_id = $_SESSION['admin_id'] ?? 1;
            $stmt->bind_param("si", $month, $admin_id);
            if ($stmt->execute()) {
                flash_set("Payroll period $month started.", "success");
            } else {
                flash_set("Error: " . $stmt->error, "danger");
            }
        }
        header("Location: payroll.php");
        exit;
    }

    // CALCULATE / RE-CALCULATE BATCH
    if (isset($_POST['action']) && $_POST['action'] === 'calculate_batch') {
        $run_id = intval($_POST['run_id']);
        
        // Verify Run is Draft
        $run_q = $db->query("SELECT * FROM payroll_runs WHERE id = $run_id AND status = 'draft'");
        if ($run_q->num_rows === 0) {
            flash_set("Cannot calculate. Run not found or already approved.", "danger");
        } else {
            $run = $run_q->fetch_assoc();
            $month = $run['month'];
            list($year_num, $month_num) = explode('-', $month);

            // Fetch Active Employees
            $emps = $db->query("SELECT * FROM employees WHERE status = 'active'");
            $count = 0;
            $total_gross = 0;
            $total_net = 0;

            $db->begin_transaction();
            try {
                // Clear existing entries for this run to avoid dupes/stale data
                $db->query("DELETE FROM payroll WHERE payroll_run_id = $run_id");

                while ($emp = $emps->fetch_assoc()) {
                    // Do Calculation
                    $res = $calculator->calculate($emp);
                    
                    // Insert into Payroll Items
                    $stmt = $db->prepare(
                        "INSERT INTO payroll 
                        (payroll_run_id, employee_id, month, year, 
                        basic_salary, allowances, 
                        gross_pay, 
                        deductions, tax_paye, tax_nssf, tax_nhif, tax_housing, 
                        net_pay, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
                    );
                    
                    $deductions_sum = $res['total_deductions']; // Total deductions
                    $stmt->bind_param("iissddddddddd", 
                        $run_id, $emp['employee_id'], $month, $year_num,
                        $res['basic_salary'], 
                        // Allowances (Sum of house + transport for now stored in allowances col?) 
                        // Actually logic: Allowances col usually stores custom. 
                        // Calculator returns breakdown. Let's sum House+Transport for 'allowances' column
                        $allowances_total, 
                        $res['gross_pay'],
                        $deductions_sum, $res['tax_paye'], $res['tax_nssf'], $res['tax_nhif'], $res['tax_housing'],
                        $res['net_pay']
                    );
                    
                    $allowances_total = $res['house_allowance'] + $res['transport_allowance'];
                    $stmt->execute();
                    
                    $total_gross += $res['gross_pay'];
                    $total_net += $res['net_pay'];
                    $count++;
                }

                // Update Run Totals
                $db->query("UPDATE payroll_runs SET total_gross = $total_gross, total_net = $total_net WHERE id = $run_id");
                
                $db->commit();
                flash_set("Calculated payroll for $count employees.", "success");
            } catch (Exception $e) {
                $db->rollback();
                flash_set("Calculation failed: " . $e->getMessage(), "danger");
            }
        }
        header("Location: payroll.php?run_id=$run_id");
        exit;
    }

    // APPROVE & PAY
    if (isset($_POST['action']) && $_POST['action'] === 'approve_run') {
        $run_id = intval($_POST['run_id']);
        
        $run_q = $db->query("SELECT * FROM payroll_runs WHERE id = $run_id AND status = 'draft'");
        if ($run_q->num_rows === 0) {
            flash_set("Invalid run state.", "danger");
        } else {
            $db->begin_transaction();
            try {
                // 1. Update Run Status
                $db->query("UPDATE payroll_runs SET status = 'paid', processed_by = {$_SESSION['admin_id']} WHERE id = $run_id");
                
                // 2. Mark Items as Paid
                $pay_date = date('Y-m-d');
                $db->query("UPDATE payroll SET status = 'paid', payment_date = '$pay_date' WHERE payroll_run_id = $run_id");

                // 3. Post to Ledger (Aggregated or Individual?)
                // Individual is better for traceability
                $items = $db->query("SELECT * FROM payroll WHERE payroll_run_id = $run_id");
                while ($p = $items->fetch_assoc()) {
                    // Expense: Net Pay (Wallet/Bank Out)
                    // Liability: PAYE, NSSF, NHIF (To be paid to gov)
                    // Currently FinancialEngine handles single entry. We need multiple.
                    
                    // For now, let's record the NET PAY as the main expense transaction
                    // And potentially separate entries for taxes if desired, OR just one expense "Staff Costs".
                    // Let's stick to simplest correct approach: 
                    // Credit Bank/Cash, Debit Payroll Expense
                    
                    $engine->transact([
                        'action_type' => 'expense_outflow', // Mapping required in Engine? Or use generic expense
                        'amount' => $p['net_pay'],
                        'notes' => "Salary {$p['month']} - Emp #{$p['employee_id']}",
                        'related_table' => 'payroll',
                        'related_id' => $p['id'],
                        'method' => 'bank' // Default
                    ]);
                }

                $db->commit();
                flash_set("Payroll Approved & Posted to Ledger.", "success");
            } catch (Exception $e) {
                $db->rollback();
                flash_set("Approval failed: " . $e->getMessage(), "danger");
            }
        }
        header("Location: payroll.php?run_id=$run_id");
        exit;
    }

    // DOWNLOAD PAYSLIP
    if (isset($_POST['action']) && $_POST['action'] === 'download_payslip') {
        $pid = intval($_POST['payroll_id']);
        
        // Fetch Data
        $pq = $db->query("SELECT p.*, e.full_name, e.employee_no, e.organization_email, e.personal_email, 
                          e.job_title, e.kra_pin, e.nssf_no, e.nhif_no, e.bank_name, e.bank_account, sg.grade_name 
                          FROM payroll p 
                          JOIN employees e ON p.employee_id = e.employee_id 
                          LEFT JOIN salary_grades sg ON e.grade_id = sg.id
                          WHERE p.id = $pid");
                          
        if ($pq->num_rows > 0) {
            $row = $pq->fetch_assoc();
            $data = ['employee' => $row, 'payroll' => $row];
            
            UniversalExportEngine::handle('pdf', function($pdf) use ($data) {
                PayslipGenerator::render($pdf, $data);
            }, [
                'title' => "Payslip - " . date('M Y', strtotime($row['month'])), 
                'module' => 'Payroll', 
                'output_mode' => 'D'
            ]);
            exit;
        } else {
            flash_set("Payroll record not found.", "danger");
        }
    }

    // EMAIL INDIVIDUAL PAYSLIP
    if (isset($_POST['action']) && $_POST['action'] === 'email_payslip') {
        $pid = intval($_POST['payroll_id']);
        
        // Fetch Data
        $pq = $db->query("SELECT p.*, e.full_name, e.employee_no, e.company_email, e.personal_email, 
                          e.job_title, e.kra_pin, e.nssf_no, e.nhif_no, e.bank_name, e.bank_account, sg.grade_name 
                          FROM payroll p 
                          JOIN employees e ON p.employee_id = e.employee_id 
                          LEFT JOIN salary_grades sg ON e.grade_id = sg.id
                          WHERE p.id = $pid");

        if ($pq->num_rows > 0) {
            $row = $pq->fetch_assoc();
            $data = ['employee' => $row, 'payroll' => $row];
            
            // Generate PDF String
            $pdfContent = UniversalExportEngine::handle('pdf', function($pdf) use ($data) {
                PayslipGenerator::render($pdf, $data);
            }, [
                'title' => "Payslip", 
                'module' => 'Payroll', 
                'output_mode' => 'S' // Return String
            ]);
            
            // Send Email
            $email = !empty($row['company_email']) ? $row['company_email'] : $row['personal_email'];
            if ($email) {
                $monthName = date('F Y', strtotime($row['month']));
                $subject = "Payslip for $monthName - " . SITE_NAME;
                $body = "Dear {$row['full_name']},<br><br>Please find attached your payslip for <b>$monthName</b>.<br><br>Regards,<br>" . SITE_NAME . " HR Team";
                
                $sent = Mailer::send($email, $subject, $body, [
                    ['content' => $pdfContent, 'name' => "Payslip_$monthName.pdf"]
                ]);
                
                if ($sent) {
                    flash_set("Payslip sent to $email", "success");
                } else {
                    flash_set("Failed to send email to $email", "danger");
                }
            } else {
                flash_set("No email address found for employee.", "warning");
            }
        }
        header("Location: payroll.php?run_id=" . $row['payroll_run_id']);
        exit;
    }

    // BATCH EMAIL
    if (isset($_POST['action']) && $_POST['action'] === 'email_batch') {
        $run_id = intval($_POST['run_id']);
        $sent_count = 0;
        
        $pq = $db->query("SELECT p.*, e.full_name, e.employee_no, e.company_email, e.personal_email, 
                          e.job_title, e.kra_pin, e.nssf_no, e.nhif_no, e.bank_name, e.bank_account, sg.grade_name 
                          FROM payroll p 
                          JOIN employees e ON p.employee_id = e.employee_id 
                          LEFT JOIN salary_grades sg ON e.grade_id = sg.id
                          WHERE p.payroll_run_id = $run_id");

        while($row = $pq->fetch_assoc()) {
            $email = !empty($row['company_email']) ? $row['company_email'] : $row['personal_email'];
            if (!$email) continue;
            
            // Generate
            $data = ['employee' => $row, 'payroll' => $row];
            $pdfContent = UniversalExportEngine::handle('pdf', function($pdf) use ($data) {
                PayslipGenerator::render($pdf, $data);
            }, ['title' => "Payslip", 'module' => 'Payroll', 'output_mode' => 'S']);
            
            // Send
            $monthName = date('F Y', strtotime($row['month']));
            $subject = "Payslip for $monthName";
            $body = "Dear {$row['full_name']},<br><br>Please find attached your payslip for <b>$monthName</b>.";
            
            if (Mailer::send($email, $subject, $body, [['content' => $pdfContent, 'name' => "Payslip.pdf"]])) {
                $sent_count++;
            }
        }
        
        flash_set("Batch Complete. Sent $sent_count payslips.", "success");
        header("Location: payroll.php?run_id=$run_id");
        exit;
    }
}

// ---------------------------------------------------------
// 2. FETCH DATA
// ---------------------------------------------------------
// Fetch active run or requested run
$run_id = isset($_GET['run_id']) ? intval($_GET['run_id']) : null;
$active_run = null;

if ($run_id) {
    $active_run = $db->query("SELECT * FROM payroll_runs WHERE id = $run_id")->fetch_assoc();
} else {
    // Default to latest Draft, else latest Paid
    $active_run = $db->query("SELECT * FROM payroll_runs ORDER BY status='draft' DESC, month DESC LIMIT 1")->fetch_assoc();
}

$payroll_items = [];
if ($active_run) {
    if (!$run_id) $run_id = $active_run['id']; // sync if we auto-selected
    $pq = $db->query("SELECT p.*, e.full_name, e.employee_no, e.grade_id, sg.grade_name 
                      FROM payroll p 
                      JOIN employees e ON p.employee_id = e.employee_id 
                      LEFT JOIN salary_grades sg ON e.grade_id = sg.id
                      WHERE p.payroll_run_id = $run_id 
                      ORDER BY e.employee_no ASC");
    while($row = $pq->fetch_assoc()) $payroll_items[] = $row;
}

// Fetch all runs for sidebar/history
$history_runs = $db->query("SELECT * FROM payroll_runs ORDER BY month DESC LIMIT 12");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | USMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
</head>
<body class="bg-light">

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content p-4">
        <?php $layout->topbar($pageTitle); ?>
        <?php flash_render(); ?>

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-dark mb-1">Payroll Center</h1>
                <p class="text-muted mb-0">Manage salaries, statutory deductions, and disbursements.</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-white border shadow-sm" data-bs-toggle="modal" data-bs-target="#historyModal">
                    <i class="bi bi-clock-history me-2"></i> History
                </button>
                <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#newRunModal">
                    <i class="bi bi-plus-lg me-2"></i> New Pay Period
                </button>
            </div>
        </div>

        <?php if ($active_run): ?>
            <!-- ACTIVE RUN DASHBOARD -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                        <div>
                            <div class="small fw-bold text-uppercase text-muted letter-spacing-1">Current Period</div>
                            <div class="h2 fw-bold mb-0 text-primary">
                                <?= date('F Y', strtotime($active_run['month'])) ?>
                            </div>
                            <span class="badge rounded-pill px-3 py-2 mt-2 <?= $active_run['status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= strtoupper($active_run['status']) ?>
                            </span>
                        </div>
                        <div class="text-end">
                            <div class="small fw-bold text-muted">Total Gross Cost</div>
                            <div class="h3 mb-0 text-dark"><?= ksh((float)$active_run['total_gross']) ?></div>
                            <div class="small text-success fw-bold">Net Payable: <?= ksh((float)$active_run['total_net']) ?></div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <?php if ($active_run['status'] === 'draft'): ?>
                    <div class="d-flex gap-3 mb-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="calculate_batch">
                            <input type="hidden" name="run_id" value="<?= $active_run['id'] ?>">
                            <button type="submit" class="btn btn-dark">
                                <i class="bi bi-calculator me-2"></i> (Re)Calculate All
                            </button>
                        </form>
                        
                        <?php if (count($payroll_items) > 0): ?>
                        <form method="POST" onsubmit="return confirm('Are you sure? This will post to Ledger and cannot be undone.');">
                            <input type="hidden" name="action" value="approve_run">
                            <input type="hidden" name="run_id" value="<?= $active_run['id'] ?>">
                            <button type="submit" class="btn btn-success fw-bold">
                                <i class="bi bi-check-circle-fill me-2"></i> Approve & Disburse
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <?php if ($active_run['status'] === 'paid'): ?>
                            <form method="POST" onsubmit="return confirm('Send payslips to ALL employees in this run?');">
                                <input type="hidden" name="action" value="email_batch">
                                <input type="hidden" name="run_id" value="<?= $active_run['id'] ?>">
                                <button type="submit" class="btn btn-outline-primary fw-bold">
                                    <i class="bi bi-envelope-at-fill me-2"></i> Email All Payslips
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Data Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light small text-uppercase fw-bold text-muted">
                                <tr>
                                    <th>Employee</th>
                                    <th class="text-end">Basic</th>
                                    <th class="text-end">Benefits</th>
                                    <th class="text-end">Gross</th>
                                    <th class="text-end">PAYE</th>
                                    <th class="text-end">Housing</th>
                                    <th class="text-end">NSSF/NHIF</th>
                                    <th class="text-end">Net Pay</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payroll_items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($item['full_name']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($item['employee_no']) ?></div>
                                        <?php if($item['grade_name']): ?>
                                            <span class="badge bg-light text-muted border" style="font-size: 0.6rem;"><?= htmlspecialchars($item['grade_name']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end font-monospace"><?= number_format((float)$item['basic_salary']) ?></td>
                                    <td class="text-end font-monospace"><?= number_format((float)$item['allowances']) ?></td>
                                    <td class="text-end fw-bold font-monospace"><?= number_format((float)$item['gross_pay']) ?></td>
                                    
                                    <td class="text-end text-danger font-monospace" style="font-size: 0.85rem;"><?= number_format((float)$item['tax_paye']) ?></td>
                                    <td class="text-end text-danger font-monospace" style="font-size: 0.85rem;"><?= number_format((float)$item['tax_housing']) ?></td>
                                    <td class="text-end text-danger font-monospace" style="font-size: 0.85rem;">
                                        <?= number_format((float)$item['tax_nssf'] + (float)$item['tax_nhif']) ?>
                                    </td>
                                    
                                    <td class="text-end fw-bold text-success font-monospace bg-success bg-opacity-10">
                                        <?= number_format((float)$item['net_pay']) ?>
                                    </td>
                                    <td>
                                        <?php if($item['status'] === 'paid'): ?>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                    Action
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <form method="POST" target="_blank">
                                                            <input type="hidden" name="action" value="download_payslip">
                                                            <input type="hidden" name="payroll_id" value="<?= $item['id'] ?>">
                                                            <button class="dropdown-item"><i class="bi bi-download me-2"></i> Download PDF</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="email_payslip">
                                                            <input type="hidden" name="payroll_id" value="<?= $item['id'] ?>">
                                                            <button class="dropdown-item"><i class="bi bi-envelope me-2"></i> Email Payslip</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($payroll_items)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                            No calculations yet. Click "Calculate All" to generate payroll.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="display-1 text-muted opacity-25 mb-3"><i class="bi bi-receipt"></i></div>
                <h3 class="text-muted">No Active Payroll Run</h3>
                <p>Start a new pay period to begin.</p>
                <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#newRunModal">Start New Run</button>
            </div>
        <?php endif; ?>
        
        <?php $layout->footer(); ?>
    </div>
</div>

<!-- New Run Modal -->
<div class="modal fade" id="newRunModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="start_run">
                <div class="modal-header">
                    <h5 class="modal-title">Start Pay Period</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Select Month</label>
                    <input type="month" name="month_selector" value="<?= date('Y-m') ?>" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Create Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payroll History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="list-group list-group-flush">
                    <?php while($h = $history_runs->fetch_assoc()): ?>
                        <a href="payroll.php?run_id=<?= $h['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= date('F Y', strtotime($h['month'])) ?></div>
                                <div class="small text-muted"><?= strtoupper($h['status']) ?></div>
                            </div>
                            <span class="badge bg-light text-dark border"><?= ksh((float)$h['total_net']) ?></span>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
