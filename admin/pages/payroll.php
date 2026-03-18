<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/FinancialEngine.php';
require_once __DIR__ . '/../../inc/PayrollCalculator.php';
require_once __DIR__ . '/../../inc/PayslipGenerator.php';
require_once __DIR__ . '/../../inc/Mailer.php';
require_once __DIR__ . '/../../inc/PayrollEngine.php';
require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';

require_permission('payroll.php');
$layout = LayoutManager::create('admin');
$engine = new PayrollEngine($conn);
$db     = $conn;

$pageTitle = "Payroll Management";

// ---------------------------------------------------------
// 1. HANDLE POST ACTIONS
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['action']) && $_POST['action'] === 'start_run') {
        try {
            $month = $_POST['month_selector'];
            $engine->startRun($month, $_SESSION['admin_id']);
            flash_set("Payroll period $month started.", "success");
        } catch (Exception $e) { flash_set($e->getMessage(), "danger"); }
        header("Location: payroll.php"); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'calculate_batch') {
        try {
            $count = $engine->calculateRun(intval($_POST['run_id']));
            flash_set("Calculated payroll for $count employees.", "success");
        } catch (Exception $e) { flash_set("Calculation failed: " . $e->getMessage(), "danger"); }
        header("Location: payroll.php?run_id=" . $_POST['run_id']); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'approve_run') {
        try {
            $engine->approveRun(intval($_POST['run_id']), $_SESSION['admin_id']);
            flash_set("Payroll Run Approved.", "success");
        } catch (Exception $e) { flash_set("Approval failed: " . $e->getMessage(), "danger"); }
        header("Location: payroll.php?run_id=" . $_POST['run_id']); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'disburse_run') {
        try {
            $count = $engine->disburseRun(intval($_POST['run_id']));
            flash_set("Disbursed salaries to $count employees. Ledger updated.", "success");
        } catch (Exception $e) { flash_set("Disbursement failed: " . $e->getMessage(), "danger"); }
        header("Location: payroll.php?run_id=" . $_POST['run_id']); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'download_payslip') {
        $pid = intval($_POST['payroll_id']);
        $pq  = $db->query("SELECT p.*, e.full_name, e.employee_no, e.company_email, e.personal_email, e.email,
                            e.job_title, e.kra_pin, e.nssf_no, e.sha_no, e.bank_name, e.bank_account, sg.grade_name,
                            a.email as admin_email
                            FROM payroll p JOIN employees e ON p.employee_id = e.employee_id
                            LEFT JOIN salary_grades sg ON e.grade_id = sg.id
                            LEFT JOIN admins a ON e.admin_id = a.admin_id
                            WHERE p.id = $pid");
        if ($pq->num_rows > 0) {
            $row  = $pq->fetch_assoc();
            $data = ['employee' => $row, 'payroll' => $row];
            require_once __DIR__ . '/../../inc/ExportHelper.php';
            ExportHelper::pdf("Payslip - " . $row['month'], [], function($pdf) use ($data) {
                PayslipGenerator::render($pdf, $data);
            }, "payslip.pdf", 'D');
            exit;
        }
        flash_set("Record not found.", "danger");
        header("Location: payroll.php"); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'email_payslip') {
        $pid = intval($_POST['payroll_id']);
        $pq  = $db->query("SELECT p.*, e.full_name, e.employee_no, e.company_email, e.personal_email, e.email,
                            e.job_title, e.kra_pin, e.nssf_no, e.sha_no, e.bank_name, e.bank_account, sg.grade_name
                            FROM payroll p JOIN employees e ON p.employee_id = e.employee_id
                            LEFT JOIN salary_grades sg ON e.grade_id = sg.id WHERE p.id = $pid");
        if ($pq->num_rows > 0) {
            $row   = $pq->fetch_assoc();
            $email = $row['company_email'] ?: ($row['personal_email'] ?: ($row['email'] ?: ($row['admin_email'] ?? null)));
            if ($email) {
                $data       = ['employee' => $row, 'payroll' => $row];
                require_once __DIR__ . '/../../inc/ExportHelper.php';
                $pdfContent = ExportHelper::pdf("Payslip", [], function($pdf) use ($data) { PayslipGenerator::render($pdf, $data); }, "payslip.pdf", 'S');
                $monthName  = $row['month'] ? date('F Y', strtotime($row['month'])) : 'Unknown';
                $subject    = "Payslip for $monthName - " . SITE_NAME;
                $body       = "Dear {$row['full_name']},<br><br>Please find attached your payslip for <b>$monthName</b>.";
                flash_set(Mailer::send($email, $subject, $body, [['content' => $pdfContent, 'name' => "Payslip_$monthName.pdf"]])
                    ? "Payslip sent to $email" : "Failed to send email.", Mailer::send($email, $subject, $body) ? "success" : "danger");
            } else {
                flash_set("No email address found for employee.", "warning");
            }
        }
        header("Location: payroll.php"); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'email_batch') {
        $run_id = intval($_POST['run_id']);
        $sent_count = $failed_count = $missing_email_count = 0;
        $pq = $db->query("SELECT p.*, e.full_name, e.employee_no, e.company_email, e.personal_email, e.email,
                          e.job_title, e.kra_pin, e.nssf_no, e.sha_no, e.bank_name, e.bank_account, sg.grade_name, a.email as admin_email
                          FROM payroll p JOIN employees e ON p.employee_id = e.employee_id
                          LEFT JOIN salary_grades sg ON e.grade_id = sg.id
                          LEFT JOIN admins a ON e.admin_id = a.admin_id
                          WHERE p.payroll_run_id = $run_id");
        while ($row = $pq->fetch_assoc()) {
            $email = $row['company_email'] ?: ($row['personal_email'] ?: ($row['email'] ?: ($row['admin_email'] ?? null)));
            if (!$email) { $missing_email_count++; continue; }
            $data       = ['employee' => $row, 'payroll' => $row];
            require_once __DIR__ . '/../../inc/ExportHelper.php';
            $pdfContent = ExportHelper::pdf("Payslip", [], function($pdf) use ($data) { PayslipGenerator::render($pdf, $data); }, "payslip.pdf", 'S');
            $monthName  = $row['month'] ? date('F Y', strtotime($row['month'])) : 'Unknown';
            Mailer::send($email, "Payslip for $monthName", "Dear {$row['full_name']},<br><br>Payslip for <b>$monthName</b> attached.", [['content' => $pdfContent, 'name' => 'Payslip.pdf']]) ? $sent_count++ : $failed_count++;
        }
        $msg  = "Batch Complete. Sent: $sent_count";
        $type = "success";
        if ($failed_count > 0 || $missing_email_count > 0) { $msg .= ", Failed: $failed_count, Missing Email: $missing_email_count"; $type = "warning"; }
        flash_set($msg, $type);
        header("Location: payroll.php?run_id=$run_id"); exit;
    }
}

// ---------------------------------------------------------
// 2. FETCH DATA
// ---------------------------------------------------------
$run_id      = isset($_GET['run_id']) ? intval($_GET['run_id']) : null;
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;
$active_run  = null;
$employee    = null;
$view_mode   = $employee_id ? 'employee' : 'run';

$payroll_items = [];

if ($view_mode === 'employee') {
    $employee = $db->query("SELECT * FROM employees WHERE employee_id = $employee_id")->fetch_assoc();
    if ($employee) {
        $pq = $db->query("SELECT p.*, r.status as run_status, r.month as run_month, e.full_name, e.employee_no
                          FROM payroll p 
                          JOIN payroll_runs r ON p.payroll_run_id = r.id 
                          JOIN employees e ON p.employee_id = e.employee_id
                          WHERE p.employee_id = $employee_id 
                          ORDER BY r.month DESC");
        while ($row = $pq->fetch_assoc()) $payroll_items[] = $row;
    }
} else {
    if ($run_id) {
        $active_run = $db->query("SELECT * FROM payroll_runs WHERE id = $run_id")->fetch_assoc();
    } else {
        $active_run = $db->query("SELECT * FROM payroll_runs ORDER BY status='draft' DESC, month DESC LIMIT 1")->fetch_assoc();
    }

    if ($active_run) {
        if (!$run_id) $run_id = $active_run['id'];
        $pq = $db->query("SELECT p.*, e.full_name, e.employee_no, e.grade_id, sg.grade_name
                          FROM payroll p JOIN employees e ON p.employee_id = e.employee_id
                          LEFT JOIN salary_grades sg ON e.grade_id = sg.id
                          WHERE p.payroll_run_id = $run_id ORDER BY e.employee_no ASC");
        while ($row = $pq->fetch_assoc()) $payroll_items[] = $row;
    }
}

$history_runs = $db->query("SELECT * FROM payroll_runs ORDER BY month DESC LIMIT 12");

// Aggregates
$total_gross_sum = array_sum(array_column($payroll_items, 'gross_pay'));
$total_net_sum   = array_sum(array_column($payroll_items, 'net_pay'));
?>
<?php $layout->header($pageTitle); ?>

<!-- ═══════════════════════════════════════════════════════════ PAGE STYLES -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

<style>
/* ── Base ───────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }
body, .main-content-wrapper, .modal-content,
select, input, textarea, button, table {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ── Tokens ─────────────────────────────────────────────────── */
:root {
    --forest:       #1a3a2a;
    --forest-mid:   #234d38;
    --forest-light: #2e6347;
    --lime:         #a8e063;
    --lime-glow:    rgba(168,224,99,.18);
    --ink:          #111c14;
    --muted:        #6b7f72;
    --surface:      #ffffff;
    --surface-2:    #f5f8f5;
    --border:       #e3ebe5;
    --shadow-sm:    0 4px 12px rgba(26,58,42,.08);
    --shadow-md:    0 8px 28px rgba(26,58,42,.12);
    --shadow-lg:    0 16px 48px rgba(26,58,42,.16);
    --radius-sm:    10px;
    --radius-md:    16px;
    --radius-lg:    22px;
    --transition:   all .22s cubic-bezier(.4,0,.2,1);
}

/* ── Scaffold ───────────────────────────────────────────────── */
.page-canvas { background: var(--surface-2); min-height: 100vh; padding: 0 0 60px; }

/* ── Breadcrumb ─────────────────────────────────────────────── */
.breadcrumb { background: none; padding: 0; margin: 0 0 28px; font-size: .8rem; font-weight: 500; }
.breadcrumb-item a { color: var(--muted); text-decoration: none; transition: var(--transition); }
.breadcrumb-item a:hover { color: var(--forest); }
.breadcrumb-item.active { color: var(--ink); font-weight: 600; }
.breadcrumb-item + .breadcrumb-item::before { color: var(--border); }

/* ── Hero ───────────────────────────────────────────────────── */
.page-header {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 55%, var(--forest-light) 100%);
    border-radius: var(--radius-lg); padding: 36px 40px; margin-bottom: 28px;
    position: relative; overflow: hidden; box-shadow: var(--shadow-lg);
    animation: fadeUp .35s ease both;
}
.page-header::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse 60% 80% at 90% -10%, rgba(168,224,99,.22) 0%, transparent 60%),
                radial-gradient(ellipse 40% 50% at -5% 100%, rgba(168,224,99,.08) 0%, transparent 55%);
    pointer-events: none;
}
.page-header::after {
    content: ''; position: absolute; right: -60px; top: -60px;
    width: 260px; height: 260px; border-radius: 50%;
    border: 1px solid rgba(168,224,99,.1); pointer-events: none;
}
.hero-inner   { position: relative; z-index: 1; display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 24px; }
.hero-chip    { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.15); color: rgba(255,255,255,.8); font-size: .72rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; border-radius: 100px; padding: 5px 14px; margin-bottom: 14px; }
.hero-title   { font-size: clamp(1.5rem, 2.5vw, 2rem); font-weight: 800; color: #fff; letter-spacing: -.5px; margin: 0 0 6px; }
.hero-sub     { font-size: .85rem; color: rgba(255,255,255,.65); font-weight: 500; margin: 0 0 22px; }
.hero-stats   { display: flex; gap: 12px; flex-wrap: wrap; }
.hero-stat    { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); border-radius: var(--radius-sm); padding: 10px 18px; backdrop-filter: blur(4px); }
.hero-stat-label { font-size: .65rem; font-weight: 600; letter-spacing: .5px; text-transform: uppercase; color: rgba(255,255,255,.5); margin-bottom: 3px; }
.hero-stat-value { font-size: 1.05rem; font-weight: 800; color: #fff; }
.hero-stat-value.lime { color: var(--lime); }
.hero-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }

/* Buttons */
.btn-lime { background: var(--lime); color: var(--ink); border: none; font-weight: 700; font-size: .85rem; transition: var(--transition); box-shadow: 0 4px 14px rgba(168,224,99,.4); }
.btn-lime:hover { background: #baea78; color: var(--ink); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(168,224,99,.5); }
.btn-outline-hero { background: rgba(255,255,255,.1); color: rgba(255,255,255,.9); border: 1px solid rgba(255,255,255,.25); font-weight: 600; font-size: .83rem; transition: var(--transition); }
.btn-outline-hero:hover { background: rgba(255,255,255,.18); color: #fff; transform: translateY(-1px); }

/* ── Period + Financials strip ──────────────────────────────── */
.period-strip {
    display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;
    animation: fadeUp .4s ease both; animation-delay: .08s;
}
.period-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 24px 28px;
    box-shadow: var(--shadow-sm); flex: 1; min-width: 200px;
    transition: var(--transition);
}
.period-card:hover { box-shadow: var(--shadow-md); border-color: #d0ddd4; }

.period-label { font-size: .68rem; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
.period-month { font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 800; color: var(--ink); letter-spacing: -.5px; line-height: 1; margin-bottom: 10px; }
.period-meta  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* Run status pill */
.run-status-pill { display: inline-flex; align-items: center; gap: 5px; font-size: .7rem; font-weight: 800; letter-spacing: .4px; text-transform: uppercase; border-radius: 100px; padding: 5px 14px; }
.run-status-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: .7; }
.rs-draft    { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.rs-approved { background: #e0f2fe; color: #0c4a6e; border: 1px solid #7dd3fc; }
.rs-paid     { background: #dcfce7; color: #166534; border: 1px solid #86efac; }

.emp-count-chip { font-size: .75rem; font-weight: 700; color: var(--muted); background: var(--surface-2); border: 1px solid var(--border); border-radius: 100px; padding: 4px 12px; display: flex; align-items: center; gap: 5px; }

/* Financials card */
.financials-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 24px 28px;
    box-shadow: var(--shadow-sm); flex: 2; min-width: 260px;
    display: flex; align-items: center; gap: 0;
    transition: var(--transition);
}
.financials-card:hover { box-shadow: var(--shadow-md); }
.fin-block { flex: 1; padding: 0 20px; }
.fin-block:first-child { padding-left: 0; border-right: 1px solid var(--border); }
.fin-block:last-child  { padding-right: 0; }
.fin-label { font-size: .68rem; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
.fin-value { font-size: 1.55rem; font-weight: 800; color: var(--ink); letter-spacing: -.4px; line-height: 1; }
.fin-value.net { color: #1a7a3f; }

/* ── Actions toolbar ────────────────────────────────────────── */
.action-toolbar {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 16px 24px;
    margin-bottom: 20px; box-shadow: var(--shadow-sm);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
    animation: fadeUp .4s ease both; animation-delay: .12s;
}
.toolbar-context { font-size: .82rem; font-weight: 600; color: var(--muted); display: flex; align-items: center; gap: 6px; }
.toolbar-context strong { color: var(--ink); }
.toolbar-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* Toolbar buttons */
.btn-tb {
    display: inline-flex; align-items: center; gap: 7px;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .83rem; font-weight: 700; border-radius: 100px;
    padding: 9px 20px; border: none; cursor: pointer; transition: var(--transition);
    text-decoration: none;
}
.btn-tb.calc   { background: var(--forest); color: #fff; }
.btn-tb.calc:hover   { background: var(--forest-light); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(26,58,42,.25); }
.btn-tb.approve { background: #1d4ed8; color: #fff; }
.btn-tb.approve:hover { background: #1e40af; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(29,78,216,.3); }
.btn-tb.disburse { background: #1a7a3f; color: #fff; box-shadow: 0 4px 14px rgba(26,122,63,.3); }
.btn-tb.disburse:hover { background: #166534; transform: translateY(-1px); }
.btn-tb.email  { background: var(--surface-2); color: var(--forest); border: 1.5px solid var(--border); }
.btn-tb.email:hover  { background: var(--lime-glow); border-color: rgba(168,224,99,.5); transform: translateY(-1px); }
.btn-tb.lock-badge { background: #f0fdf4; color: #166534; border: 1.5px solid #86efac; cursor: default; font-size: .75rem; }

/* ── Table card ─────────────────────────────────────────────── */
.detail-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); overflow: hidden;
    box-shadow: var(--shadow-sm); transition: var(--transition);
    animation: fadeUp .4s ease both; animation-delay: .18s;
}
.detail-card:hover { box-shadow: var(--shadow-md); border-color: #d0ddd4; }

.card-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; }
.card-toolbar-title { font-size: .7rem; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; color: var(--forest); display: flex; align-items: center; gap: 8px; }
.card-toolbar-title i { width: 28px; height: 28px; border-radius: 8px; background: var(--lime-glow); color: var(--forest); display: flex; align-items: center; justify-content: center; font-size: .9rem; }
.record-count { font-size: .72rem; font-weight: 700; background: var(--lime-glow); color: var(--forest); border: 1px solid rgba(168,224,99,.35); border-radius: 100px; padding: 4px 12px; }

/* Payroll table */
.pay-table { width: 100%; border-collapse: collapse; }
.pay-table thead th { font-size: .67rem; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); background: var(--surface-2); padding: 13px 16px; border-bottom: 2px solid var(--border); white-space: nowrap; }
.pay-table tbody tr { border-bottom: 1px solid var(--border); transition: var(--transition); }
.pay-table tbody tr:last-child { border-bottom: none; }
.pay-table tbody tr:hover { background: #f9fcf9; }
.pay-table td { padding: 14px 16px; vertical-align: middle; }

.emp-name { font-size: .88rem; font-weight: 700; color: var(--ink); }
.emp-no   { font-size: .72rem; color: var(--muted); font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; margin-top: 2px; }
.mono-val { font-size: .84rem; font-weight: 600; font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; text-align: right; }
.mono-val.dim    { color: var(--muted); }
.mono-val.ded    { color: #c0392b; }
.mono-val.net    { color: #1a7a3f; font-weight: 800; font-size: .9rem; }

/* Action dropdown in table */
.btn-row-action {
    width: 32px; height: 32px; border-radius: 8px;
    border: 1.5px solid var(--border); background: var(--surface);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .82rem; color: var(--muted); cursor: pointer; transition: var(--transition);
}
.btn-row-action:hover { background: var(--surface-2); border-color: #c8d9cc; color: var(--ink); }
.pending-row-badge { font-size: .68rem; font-weight: 700; color: var(--muted); background: var(--surface-2); border: 1px solid var(--border); border-radius: 100px; padding: 4px 10px; }

/* Empty state */
.empty-state { text-align: center; padding: 60px 24px; color: var(--muted); }
.empty-state i { font-size: 3rem; opacity: .15; display: block; margin-bottom: 16px; }
.empty-state h5 { font-size: 1rem; font-weight: 700; color: var(--ink); margin-bottom: 6px; }
.empty-state p  { font-size: .83rem; margin: 0; }

/* No run state */
.no-run-state { text-align: center; padding: 80px 24px; }
.no-run-icon  { font-size: 4rem; color: var(--muted); opacity: .15; margin-bottom: 24px; }
.no-run-title { font-size: 1.3rem; font-weight: 800; color: var(--ink); margin-bottom: 8px; }
.no-run-sub   { font-size: .85rem; color: var(--muted); margin-bottom: 28px; }

/* ── Alert ──────────────────────────────────────────────────── */
.alert-custom { border: 0; border-radius: var(--radius-sm); padding: 14px 18px; font-size: .84rem; font-weight: 600; display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
.alert-custom.success { background: #f0fff6; color: #1a7a3f; border-left: 3px solid #2e6347; }
.alert-custom.danger  { background: #fef0f0; color: #c0392b; border-left: 3px solid #e74c3c; }
.alert-custom.warning { background: #fffbeb; color: #92400e; border-left: 3px solid #f59e0b; }

/* ── Modal ──────────────────────────────────────────────────── */
.modal-content { border: 0 !important; border-radius: var(--radius-lg) !important; overflow: hidden; box-shadow: var(--shadow-lg); }
.modal-header  { border-bottom: 0 !important; padding: 28px 28px 0 !important; }
.modal-body    { padding: 20px 28px 28px !important; }
.modal-footer  { border-top: 1px solid var(--border) !important; padding: 16px 28px !important; }
.modal-header-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--lime-glow); color: var(--forest); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 14px; }
.form-label { font-size: .78rem; font-weight: 700; color: var(--ink); margin-bottom: 7px; }
.form-control, .form-select {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .85rem; font-weight: 500;
    border: 1.5px solid var(--border); border-radius: 10px;
    padding: 10px 14px; color: var(--ink); background: var(--surface-2); transition: var(--transition);
}
.form-control:focus, .form-select:focus { border-color: var(--forest); background: #fff; box-shadow: 0 0 0 3px rgba(26,58,42,.08); outline: none; }

/* History list in modal */
.history-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 20px; border-bottom: 1px solid var(--border);
    text-decoration: none; transition: var(--transition);
}
.history-item:last-child { border-bottom: none; }
.history-item:hover { background: var(--surface-2); }
.history-month { font-size: .88rem; font-weight: 700; color: var(--ink); }
.history-status { font-size: .7rem; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; color: var(--muted); margin-top: 2px; }
.history-amt { font-size: .85rem; font-weight: 800; color: var(--forest); font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; }

/* ── Animate ────────────────────────────────────────────────── */
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

/* ── Utils ──────────────────────────────────────────────────── */
.fw-800 { font-weight: 800 !important; }
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4 page-canvas">

        <!-- Breadcrumb -->
        <nav class="mb-1" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
                <li class="breadcrumb-item active">Payroll Management</li>
            </ol>
        </nav>

        <?php flash_render(); ?>

        <!-- ═══ HERO ══════════════════════════════════════════════════════ -->
        <div class="page-header mb-4">
            <div class="hero-inner">
                <div>
                    <div class="hero-chip"><i class="bi bi-people-fill"></i>HR · Payroll</div>
                    <h1 class="hero-title"><?= $view_mode === 'employee' ? 'Employee Payroll History' : 'Payroll Management' ?></h1>
                    <p class="hero-sub"><?= $view_mode === 'employee' ? 'Viewing payroll records for <strong>' . htmlspecialchars($employee['full_name']??'') . '</strong>' : 'Disburse salaries, manage tax deductions, and generate payslips.' ?></p>
                    
                    <div class="hero-stats">
                        <?php if ($view_mode === 'employee'): ?>
                            <div class="hero-stat">
                                <div class="hero-stat-label">Records Found</div>
                                <div class="hero-stat-value"><?= count($payroll_items) ?></div>
                            </div>
                            <div class="hero-stat">
                                <div class="hero-stat-label">Total Net Received</div>
                                <div class="hero-stat-value lime"><?= ksh($total_net_sum) ?></div>
                            </div>
                        <?php elseif ($active_run): ?>
                            <div class="hero-stat">
                                <div class="hero-stat-label">Period</div>
                                <div class="hero-stat-value"><?= date('M Y', strtotime($active_run['month'] ?? 'now')) ?></div>
                            </div>
                            <div class="hero-stat">
                                <div class="hero-stat-label">Employees</div>
                                <div class="hero-stat-value"><?= count($payroll_items) ?></div>
                            </div>
                            <div class="hero-stat">
                                <div class="hero-stat-label">Total Gross</div>
                                <div class="hero-stat-value"><?= ksh((float)($active_run['total_gross'] ?? $total_gross_sum)) ?></div>
                            </div>
                            <div class="hero-stat">
                                <div class="hero-stat-label">Net Payable</div>
                                <div class="hero-stat-value lime"><?= ksh((float)($active_run['total_net'] ?? $total_net_sum)) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="hero-actions">
                    <?php if ($view_mode === 'employee'): ?>
                        <a href="payroll.php" class="btn btn-outline-hero rounded-pill px-4 fw-bold">
                            <i class="bi bi-arrow-left me-2"></i>Back to Overview
                        </a>
                    <?php else: ?>
                        <button class="btn btn-lime rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#newRunModal">
                            <i class="bi bi-plus-circle-fill me-2"></i>New Pay Period
                        </button>
                        <button class="btn btn-outline-hero rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#historyModal">
                            <i class="bi bi-clock-history me-2"></i>Pay History
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($view_mode === 'run' && $active_run): ?>

        <!-- ═══ PERIOD + FINANCIALS STRIP ════════════════════════════════ -->
        <div class="period-strip">
            <!-- Period card -->
            <div class="period-card">
                <div class="period-label">Active Pay Period</div>
                <div class="period-month"><?= date('F Y', strtotime($active_run['month'] ?? 'now')) ?></div>
                <div class="period-meta">
                    <?php
                    $rs = $active_run['status'];
                    $rs_class = $rs === 'paid' ? 'rs-paid' : ($rs === 'approved' ? 'rs-approved' : 'rs-draft');
                    $rs_label = $rs === 'paid' ? 'Paid' : ($rs === 'approved' ? 'Approved' : 'Draft');
                    ?>
                    <span class="run-status-pill <?= $rs_class ?>"><?= $rs_label ?></span>
                    <span class="emp-count-chip"><i class="bi bi-people"></i><?= count($payroll_items) ?> Employees</span>
                </div>
            </div>

            <!-- Financials card -->
            <div class="financials-card">
                <div class="fin-block">
                    <div class="fin-label">Total Gross</div>
                    <div class="fin-value"><?= ksh((float)($active_run['total_gross'] ?? $total_gross_sum)) ?></div>
                </div>
                <div class="fin-block">
                    <div class="fin-label">Net Payable</div>
                    <div class="fin-value net"><?= ksh((float)($active_run['total_net'] ?? $total_net_sum)) ?></div>
                </div>
            </div>
        </div>

        <!-- ═══ ACTIONS TOOLBAR ═══════════════════════════════════════════ -->
        <div class="action-toolbar">
            <div class="toolbar-context">
                <i class="bi bi-sliders"></i>
                Actions for <strong><?= date('F Y', strtotime($active_run['month'] ?? 'now')) ?></strong>
            </div>
            <div class="toolbar-actions">
                <?php if ($active_run['status'] === 'draft'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="calculate_batch">
                        <input type="hidden" name="run_id" value="<?= $active_run['id'] ?>">
                        <button type="submit" class="btn-tb calc">
                            <i class="bi bi-calculator-fill"></i>(Re)Calculate
                        </button>
                    </form>
                    <?php if (count($payroll_items) > 0): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Lock and Approve this run? No further calculations will be possible.');">
                        <input type="hidden" name="action" value="approve_run">
                        <input type="hidden" name="run_id" value="<?= $active_run['id'] ?>">
                        <button type="submit" class="btn-tb approve">
                            <i class="bi bi-check-circle-fill"></i>Approve Run
                        </button>
                    </form>
                    <?php endif; ?>

                <?php elseif ($active_run['status'] === 'approved'): ?>
                    <span class="btn-tb lock-badge"><i class="bi bi-lock-fill"></i>Approved & Locked</span>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Disburse funds? This will post transactions to the General Ledger.');">
                        <input type="hidden" name="action" value="disburse_run">
                        <input type="hidden" name="run_id" value="<?= $active_run['id'] ?>">
                        <button type="submit" class="btn-tb disburse">
                            <i class="bi bi-send-fill"></i>Disburse Payments
                        </button>
                    </form>

                <?php elseif ($active_run['status'] === 'paid'): ?>
                    <span class="btn-tb lock-badge"><i class="bi bi-check-all"></i>Fully Paid</span>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Send payslips to ALL employees?');">
                        <input type="hidden" name="action" value="email_batch">
                        <input type="hidden" name="run_id" value="<?= $active_run['id'] ?>">
                        <button type="submit" class="btn-tb email">
                            <i class="bi bi-envelope-at-fill"></i>Email All Payslips
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══ PAYROLL TABLE ════════════════════════════════════════════ -->
        <div class="detail-card">
            <div class="card-toolbar">
                <div class="card-toolbar-title">
                    <i class="bi bi-receipt-cutoff d-flex"></i>
                    Payroll Register
                </div>
                <?php if (!empty($payroll_items)): ?>
                <span class="record-count"><?= count($payroll_items) ?> employees</span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th style="padding-left:20px"><?= $view_mode === 'employee' ? 'Month' : 'Employee' ?></th>
                            <th style="text-align:right">Basic</th>
                            <th style="text-align:right">Allowances</th>
                            <th style="text-align:right">Gross</th>
                            <th style="text-align:right">Deductions</th>
                            <th style="text-align:right">Net Pay</th>
                            <th style="text-align:right;padding-right:20px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($payroll_items)): ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-calculator"></i>
                                <h5>No Payroll Data</h5>
                                <p><?= $view_mode === 'employee' ? 'This employee has no payroll history recorded.' : 'Click <strong>(Re)Calculate</strong> to generate payroll entries.' ?></p>
                            </div>
                        </td></tr>
                    <?php else: foreach ($payroll_items as $item):
                        $total_ded = ($item['tax_paye'] ?? 0) + ($item['tax_housing'] ?? 0) + ($item['tax_nssf'] ?? 0) + ($item['tax_nhif'] ?? $item['tax_sha'] ?? 0);
                        $ded_tip   = "PAYE: " . ksh($item['tax_paye'] ?? 0) . " | Housing: " . ksh($item['tax_housing'] ?? 0) . " | NSSF/SHA: " . ksh(($item['tax_nssf'] ?? 0) + ($item['tax_nhif'] ?? $item['tax_sha'] ?? 0));
                    ?>
                        <tr>
                            <td style="padding-left:20px">
                                <?php if ($view_mode === 'employee'): ?>
                                    <div class="emp-name"><a href="payroll.php?run_id=<?= $item['payroll_run_id'] ?>" style="text-decoration:none;color:inherit;"><?= date('F Y', strtotime($item['run_month']??'now')) ?></a></div>
                                    <div class="emp-no"><span class="run-status-pill <?= $item['run_status']==='paid'?'rs-paid':($item['run_status']==='approved'?'rs-approved':'rs-draft') ?>" style="font-size:0.6rem;padding:2px 8px;"><?= strtoupper($item['run_status']??'draft') ?></span></div>
                                <?php else: ?>
                                    <div class="emp-name"><?= htmlspecialchars($item['full_name']) ?></div>
                                    <div class="emp-no"><?= htmlspecialchars($item['employee_no']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><div class="mono-val dim"><?= ksh((float)$item['basic_salary']) ?></div></td>
                            <td><div class="mono-val dim"><?= ksh((float)$item['allowances']) ?></div></td>
                            <td><div class="mono-val"><?= ksh((float)$item['gross_pay']) ?></div></td>
                            <td>
                                <div class="mono-val ded" data-bs-toggle="tooltip" title="<?= htmlspecialchars($ded_tip) ?>">
                                    <?= ksh($total_ded) ?>
                                </div>
                            </td>
                            <td><div class="mono-val net"><?= ksh((float)$item['net_pay']) ?></div></td>
                            <td style="text-align:right;padding-right:20px">
                                <?php if ($item['status'] === 'paid'): ?>
                                <div class="dropdown">
                                    <button class="btn-row-action" data-bs-toggle="dropdown" title="Options">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;overflow:hidden;min-width:180px">
                                        <li>
                                            <form method="POST" target="_blank">
                                                <input type="hidden" name="action" value="download_payslip">
                                                <input type="hidden" name="payroll_id" value="<?= $item['id'] ?>">
                                                <button class="dropdown-item py-2"><i class="bi bi-download me-2 text-primary"></i>Download PDF</button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="email_payslip">
                                                <input type="hidden" name="payroll_id" value="<?= $item['id'] ?>">
                                                <button class="dropdown-item py-2"><i class="bi bi-envelope me-2 text-success"></i>Email Payslip</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                                <?php else: ?>
                                    <span class="pending-row-badge">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <!-- ═══ NO ACTIVE RUN ════════════════════════════════════════════ -->
        <div class="detail-card">
            <div class="no-run-state">
                <div class="no-run-icon"><i class="bi bi-calendar2-range"></i></div>
                <div class="no-run-title">No Active Payroll Run</div>
                <div class="no-run-sub">Start a new pay period to begin processing employee salaries.</div>
                <button class="btn btn-lime rounded-pill px-5 fw-bold shadow-sm" style="padding:12px 36px" data-bs-toggle="modal" data-bs-target="#newRunModal">
                    <i class="bi bi-plus-circle-fill me-2"></i>Start New Pay Period
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══ NEW RUN MODAL ════════════════════════════════════════════ -->
        <div class="modal fade" id="newRunModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="start_run">
                        <div class="modal-header">
                            <div>
                                <div class="modal-header-icon"><i class="bi bi-calendar-plus-fill"></i></div>
                                <h5 class="fw-800 mb-1" style="color:var(--ink)">Start Pay Period</h5>
                                <p class="text-muted mb-0" style="font-size:.82rem">Select the billing month to process.</p>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Billing Month <span class="text-danger">*</span></label>
                                <input type="month" name="month_selector" value="<?= date('Y-m') ?>" class="form-control" required>
                            </div>
                            <button type="submit" class="btn-tb calc w-100" style="border-radius:10px;justify-content:center;padding:12px">
                                <i class="bi bi-play-circle-fill"></i>Create Draft Run
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ═══ HISTORY MODAL ════════════════════════════════════════════ -->
        <div class="modal fade" id="historyModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <div class="modal-header-icon" style="background:#e8f0fe;color:#1a6fc4"><i class="bi bi-clock-history"></i></div>
                            <h5 class="fw-800 mb-1" style="color:var(--ink)">Payroll History</h5>
                            <p class="text-muted mb-0" style="font-size:.82rem">Last 12 pay periods.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding:0 !important;max-height:420px;overflow-y:auto">
                        <?php
                        $has_history = false;
                        while ($h = $history_runs->fetch_assoc()):
                            $has_history = true;
                            $hs = $h['status'];
                            $hs_class = $hs === 'paid' ? 'rs-paid' : ($hs === 'approved' ? 'rs-approved' : 'rs-draft');
                        ?>
                        <a href="payroll.php?run_id=<?= $h['id'] ?>" class="history-item">
                            <div>
                                <div class="history-month"><?= $h['month'] ? date('F Y', strtotime($h['month'])) : 'Unknown Period' ?></div>
                                <div><span class="run-status-pill <?= $hs_class ?>" style="font-size:.6rem;padding:3px 10px"><?= strtoupper($hs) ?></span></div>
                            </div>
                            <div class="history-amt"><?= ksh((float)$h['total_net']) ?></div>
                        </a>
                        <?php endwhile; ?>
                        <?php if (!$has_history): ?>
                        <div class="empty-state" style="padding:32px">
                            <i class="bi bi-clock-history"></i>
                            <p>No payroll history found.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script>
// Activate Bootstrap tooltips
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el, { placement: 'top' });
    });
});
</script>
</body>
</html>