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
require_once __DIR__ . '/../../inc/PayrollEngine.php'; // Updated Engine
require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';

// Permissions
require_permission('payroll.php'); 
$layout = LayoutManager::create('admin');
$engine = new PayrollEngine($conn);

$pageTitle = "Payroll Management";

// ---------------------------------------------------------
// 1. HANDLE POST ACTIONS
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // START NEW RUN
    if (isset($_POST['action']) && $_POST['action'] === 'start_run') {
        try {
            $month = $_POST['month_selector'];
            $engine->startRun($month, $_SESSION['admin_id']);
            flash_set("Payroll period $month started.", "success");
        } catch (Exception $e) {
            flash_set($e->getMessage(), "danger");
        }
        header("Location: payroll.php");
        exit;
    }

    // CALCULATE BATCH
    if (isset($_POST['action']) && $_POST['action'] === 'calculate_batch') {
        try {
            $count = $engine->calculateRun(intval($_POST['run_id']));
            flash_set("Calculated payroll for $count employees.", "success");
        } catch (Exception $e) {
            flash_set("Calculation failed: " . $e->getMessage(), "danger");
        }
        header("Location: payroll.php?run_id=" . $_POST['run_id']);
        exit;
    }

    // APPROVE RUN
    if (isset($_POST['action']) && $_POST['action'] === 'approve_run') {
        try {
            $engine->approveRun(intval($_POST['run_id']), $_SESSION['admin_id']);
            flash_set("Payroll Run Approved.", "success");
        } catch (Exception $e) {
            flash_set("Approval failed: " . $e->getMessage(), "danger");
        }
        header("Location: payroll.php?run_id=" . $_POST['run_id']);
        exit;
    }

    // DISBURSE RUN
    if (isset($_POST['action']) && $_POST['action'] === 'disburse_run') {
        try {
            $count = $engine->disburseRun(intval($_POST['run_id']));
            flash_set("Disbursed salaries to $count employees. Ledger updated.", "success");
        } catch (Exception $e) {
            flash_set("Disbursement failed: " . $e->getMessage(), "danger");
        }
        header("Location: payroll.php?run_id=" . $_POST['run_id']);
        exit;
    }

    // DOWNLOAD PAYSLIP
    if (isset($_POST['action']) && $_POST['action'] === 'download_payslip') {
        $pid = intval($_POST['payroll_id']);
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
            }, ['title' => "Payslip - " . $row['month'], 'module' => 'Payroll', 'output_mode' => 'D']);
            exit;
        }
        flash_set("Record not found.", "danger"); 
        header("Location: payroll.php"); exit;
    }

    // EMAIL INDIVIDUAL PAYSLIP
    if (isset($_POST['action']) && $_POST['action'] === 'email_payslip') {
        $pid = intval($_POST['payroll_id']);
        $pq = $db->query("SELECT p.*, e.full_name, e.employee_no, e.company_email, e.personal_email, 
                          e.job_title, e.kra_pin, e.nssf_no, e.nhif_no, e.bank_name, e.bank_account, sg.grade_name 
                          FROM payroll p 
                          JOIN employees e ON p.employee_id = e.employee_id 
                          LEFT JOIN salary_grades sg ON e.grade_id = sg.id
                          WHERE p.id = $pid");

        if ($pq->num_rows > 0) {
            $row = $pq->fetch_assoc();
            $email = !empty($row['company_email']) ? $row['company_email'] : $row['personal_email'];
            
            if ($email) {
                $data = ['employee' => $row, 'payroll' => $row];
                $pdfContent = UniversalExportEngine::handle('pdf', function($pdf) use ($data) {
                    PayslipGenerator::render($pdf, $data);
                }, ['title' => "Payslip", 'module' => 'Payroll', 'output_mode' => 'S']);
                
                $monthName = date('F Y', strtotime($row['month']));
                $subject = "Payslip for $monthName - " . SITE_NAME;
                $body = "Dear {$row['full_name']},<br><br>Please find attached your payslip for <b>$monthName</b>.";
                
                if (Mailer::send($email, $subject, $body, [['content' => $pdfContent, 'name' => "Payslip_$monthName.pdf"]])) {
                    flash_set("Payslip sent to $email", "success");
                } else {
                    flash_set("Failed to send email.", "danger");
                }
            } else {
                flash_set("No email address found for employee.", "warning");
            }
        }
        header("Location: payroll.php"); exit;
    }

    // EMAIL BATCH
    if (isset($_POST['action']) && $_POST['action'] === 'email_batch') {
        $run_id = intval($_POST['run_id']);
        $sent_count = 0;
        $pq = $db->query("SELECT p.*, e.full_name, e.company_email, e.personal_email, e.job_title, e.kra_pin, e.nssf_no, e.nhif_no, e.bank_name, e.bank_account, sg.grade_name 
                          FROM payroll p 
                          JOIN employees e ON p.employee_id = e.employee_id 
                          LEFT JOIN salary_grades sg ON e.grade_id = sg.id
                          WHERE p.payroll_run_id = $run_id");

        while($row = $pq->fetch_assoc()) {
            $email = !empty($row['company_email']) ? $row['company_email'] : $row['personal_email'];
            if (!$email) continue;
            
            $data = ['employee' => $row, 'payroll' => $row];
            $pdfContent = UniversalExportEngine::handle('pdf', function($pdf) use ($data) {
                PayslipGenerator::render($pdf, $data);
            }, ['title' => "Payslip", 'module' => 'Payroll', 'output_mode' => 'S']);
            
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
$run_id = isset($_GET['run_id']) ? intval($_GET['run_id']) : null;
$active_run = null;

if ($run_id) {
    $active_run = $db->query("SELECT * FROM payroll_runs WHERE id = $run_id")->fetch_assoc();
} else {
    $active_run = $db->query("SELECT * FROM payroll_runs ORDER BY status='draft' DESC, month DESC LIMIT 1")->fetch_assoc();
}

$payroll_items = [];
if ($active_run) {
    if (!$run_id) $run_id = $active_run['id'];
    $pq = $db->query("SELECT p.*, e.full_name, e.employee_no, e.grade_id, sg.grade_name 
                      FROM payroll p 
                      JOIN employees e ON p.employee_id = e.employee_id 
                      LEFT JOIN salary_grades sg ON e.grade_id = sg.id
                      WHERE p.payroll_run_id = $run_id 
                      ORDER BY e.employee_no ASC");
    while($row = $pq->fetch_assoc()) $payroll_items[] = $row;
}

$history_runs = $db->query("SELECT * FROM payroll_runs ORDER BY month DESC LIMIT 12");
?>
<?php $layout->header($pageTitle); ?>
    <style>
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }

        /* Page-specific overrides */
        .hd-glass { 
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
            transition: 0.3s;
        }
        
        .text-gradient { background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
    </style>

<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content-wrapper p-0">
        <?php $layout->topbar($pageTitle ?? 'Payroll Command'); ?>
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-gradient">Payroll Management</h3>
                    <p class="text-muted small mb-0">Disburse salaries, manage tax deductions, and generate payslips.</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-light border shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#historyModal">
                        <i class="bi bi-clock-history me-2"></i> History
                    </button>
                    <button class="btn btn-primary fw-bold px-4" data-bs-toggle="modal" data-bs-target="#newRunModal">
                        <i class="bi bi-plus-lg me-2"></i> New Pay Period
                    </button>
                </div>
            </div>

            <?php if ($active_run): ?>
                <!-- STATS & CONTROLS -->
                <div class="row g-4 mb-4">
                    <!-- Period Info -->
                    <div class="col-md-5">
                        <div class="hd-glass p-4 h-100 d-flex flex-column justify-content-center">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge text-uppercase fw-bold rounded-pill border px-3 py-2 <?= $active_run['status'] === 'paid' ? 'bg-success text-white border-success' : ($active_run['status'] === 'approved' ? 'bg-info text-white border-info' : 'bg-warning bg-opacity-10 text-warning border-warning') ?>">
                                    <?= strtoupper($active_run['status']) ?>
                                </span>
                                <div class="text-end">
                                    <div class="small text-muted fw-bold">EMPLOYEES</div>
                                    <div class="h5 mb-0"><?= count($payroll_items) ?></div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="small fw-bold text-muted text-uppercase letter-spacing-1">Current Period</div>
                                <div class="h1 fw-bold mb-0 "><?= date('F Y', strtotime($active_run['month'])) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Financials -->
                    <div class="col-md-7">
                        <div class="hd-glass p-4 h-100">
                            <div class="row g-0 h-100 align-items-center">
                                <div class="col-6 border-end pe-4">
                                    <div class="text-muted small fw-bold text-uppercase">Total Gross</div>
                                    <div class="h2 fw-bold  mb-0"><?= ksh((float)$active_run['total_gross']) ?></div>
                                </div>
                                <div class="col-6 ps-4">
                                    <div class="text-muted small fw-bold text-uppercase">Net Payable</div>
                                    <div class="h2 fw-bold text-success mb-0"><?= ksh((float)$active_run['total_net']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACTIONS TOOLBAR -->
                <div class="hd-glass p-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Actions for <strong><?= date('F Y', strtotime($active_run['month'])) ?></strong>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($active_run['status'] === 'draft'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="calculate_batch">
                                    <input type="hidden" name="run_id" value="<?= $active_run['id'] ?>">
                                    <button type="submit" class="btn btn-dark rounded-pill">
                                        <i class="bi bi-calculator me-2"></i> (Re)Calculate
                                    </button>
                                </form>
                                <?php if (count($payroll_items) > 0): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Lock and Approve this run? No further calculations will be possible.');">
                                        <input type="hidden" name="action" value="approve_run">
                                        <input type="hidden" name="run_id" value="<?= $active_run['id'] ?>">
                                        <button type="submit" class="btn btn-primary rounded-pill">
                                            <i class="bi bi-check-circle me-2"></i> Approve
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif ($active_run['status'] === 'approved'): ?>
                                <button class="btn btn-secondary rounded-pill" disabled><i class="bi bi-lock me-2"></i> Approved</button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Disburse funds? This will post transactions to the General Ledger.');">
                                    <input type="hidden" name="action" value="disburse_run">
                                    <input type="hidden" name="run_id" value="<?= $active_run['id'] ?>">
                                    <button type="submit" class="btn btn-success fw-bold rounded-pill">
                                        <i class="bi bi-wallet2 me-2"></i> Disburse Payments
                                    </button>
                                </form>
                            <?php elseif ($active_run['status'] === 'paid'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 rounded-pill me-2 d-flex align-items-center">
                                    <i class="bi bi-check-all me-2"></i> Paid
                                </span>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Send payslips to ALL employees?');">
                                    <input type="hidden" name="action" value="email_batch">
                                    <input type="hidden" name="run_id" value="<?= $active_run['id'] ?>">
                                    <button type="submit" class="btn btn-outline-primary rounded-pill">
                                        <i class="bi bi-envelope-at-fill me-2"></i> Email Payslips
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="hd-glass overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0" style="background: transparent;">
                            <thead class="bg-light bg-opacity-50 small text-uppercase text-muted fw-bold">
                                <tr>
                                    <th class="ps-4 py-3">Employee</th>
                                    <th class="text-end py-3">Basic</th>
                                    <th class="text-end py-3">Allowances</th>
                                    <th class="text-end py-3">Gross</th>
                                    <th class="text-end py-3">Deductions</th>
                                    <th class="text-end py-3">Net Pay</th>
                                    <th class="text-end pe-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="border-top-0">
                                <?php foreach ($payroll_items as $item): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold "><?= htmlspecialchars($item['full_name']) ?></div>
                                        <div class="small text-muted font-monospace"><?= htmlspecialchars($item['employee_no']) ?></div>
                                    </td>
                                    <td class="text-end font-monospace text-muted"><?= ksh((float)$item['basic_salary']) ?></td>
                                    <td class="text-end font-monospace text-muted"><?= ksh((float)$item['allowances']) ?></td>
                                    <td class="text-end font-monospace fw-medium"><?= ksh((float)$item['gross_pay']) ?></td>
                                    
                                    <td class="text-end font-monospace text-danger" style="font-size: 0.9em;">
                                        <div data-bs-toggle="tooltip" title="PAYE: <?= ksh($item['tax_paye']) ?>, HSG: <?= ksh($item['tax_housing']) ?>, NSSF/NHIF: <?= ksh($item['tax_nssf'] + $item['tax_nhif']) ?>">
                                            <?php 
                                            $total_ded = $item['tax_paye'] + $item['tax_housing'] + $item['tax_nssf'] + $item['tax_nhif'];
                                            echo ksh($total_ded); 
                                            ?>
                                        </div>
                                    </td>
                                    
                                    <td class="text-end font-monospace fw-bold text-success">
                                        <?= ksh((float)$item['net_pay']) ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if($item['status'] === 'paid'): ?>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-light bg-transparent border-0 text-muted" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-glass border-0 shadow-lg">
                                                    <li>
                                                        <form method="POST" target="_blank">
                                                            <input type="hidden" name="action" value="download_payslip">
                                                            <input type="hidden" name="payroll_id" value="<?= $item['id'] ?>">
                                                            <button class="dropdown-item"><i class="bi bi-download me-2 text-primary"></i> Download PDF</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="email_payslip">
                                                            <input type="hidden" name="payroll_id" value="<?= $item['id'] ?>">
                                                            <button class="dropdown-item"><i class="bi bi-envelope me-2 text-success"></i> Email Payslip</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary opacity-50 fw-normal">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($payroll_items)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="bi bi-calculator fs-1 d-block mb-3 opacity-25"></i>
                                            <p class="mb-0">No data found.</p>
                                            <p class="small">Click <strong>(Re)Calculate</strong> to generate payroll.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- EMPTY STATE -->
                <div class="text-center py-5 mt-5">
                    <div class="display-1 text-muted opacity-25 mb-4"><i class="bi bi-calendar2-range"></i></div>
                    <h3 class="text-muted">No Active Payroll Run</h3>
                    <p class="text-muted mb-4">Start a new pay period to begin processing salaries.</p>
                    <button class="btn btn-primary fw-bold px-4 rounded-pill py-3 shadow" data-bs-toggle="modal" data-bs-target="#newRunModal">
                        Start New Pay Period
                    </button>
                </div>
            <?php endif; ?>
            
        </div>
        <?php $layout->footer(); ?>
    </div>
</div>

<!-- New Run Modal -->
<div class="modal fade" id="newRunModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hd-glass border-0 shadow-lg">
            <form method="POST">
                <input type="hidden" name="action" value="start_run">
                <div class="modal-header border-bottom-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold">Start Pay Period</h5>
                        <div class="small text-muted">Select the month to process.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Billing Month</label>
                        <input type="month" name="month_selector" value="<?= date('Y-m') ?>" class="form-control bg-light border-0 py-3" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold">Create Draft Run</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hd-glass border-0 shadow-lg">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold">Payroll History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="list-group list-group-flush">
                    <?php while($h = $history_runs->fetch_assoc()): ?>
                        <a href="payroll.php?run_id=<?= $h['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-4 py-3 bg-transparent border-bottom">
                            <div>
                                <div class="fw-bold "><?= date('F Y', strtotime($h['month'])) ?></div>
                                <div class="small text-muted text-uppercase"><?= $h['status'] ?></div>
                            </div>
                            <span class="badge bg-light  border fw-normal font-monospace"><?= ksh((float)$h['total_net']) ?></span>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

