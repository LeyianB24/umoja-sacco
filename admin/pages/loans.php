<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/FinancialEngine.php';
require_once __DIR__ . '/../../inc/notification_helpers.php';

$layout = LayoutManager::create('admin');
Auth::requireAdmin();
require_permission();

$admin_id = $_SESSION['admin_id'];
$loan_id  = intval($_GET['id'] ?? 0);

if ($loan_id <= 0) {
    // No loan ID provided – redirect to the loan list silently
    header("Location: loans_payouts.php");
    exit;
}

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';

    $permissions = $_SESSION['permissions'] ?? [];
    $is_super    = ($_SESSION['role_id'] ?? 0) == 1;
    $can_approve  = $is_super || in_array('approve_loans', $permissions);
    $can_disburse = $is_super || in_array('disburse_loans', $permissions);

    $conn->begin_transaction();
    try {
        if ($action === 'approve' && $can_approve) {
            $stmt = $conn->prepare("UPDATE loans SET status='approved', approved_by=?, approval_date=NOW() WHERE loan_id=?");
            $stmt->bind_param("ii", $admin_id, $loan_id);
            $stmt->execute();
            $res_l = $conn->query("SELECT member_id, amount, reference_no FROM loans WHERE loan_id = $loan_id");
            if ($l_row = $res_l->fetch_assoc()) {
                send_notification($conn, (int)$l_row['member_id'], 'loan_approved', ['amount' => $l_row['amount'], 'ref' => $l_row['reference_no']]);
            }
            flash_set("Loan #$loan_id approved successfully.", "success");

        } elseif ($action === 'reject' && $can_approve) {
            $reason = htmlspecialchars(trim($_POST['rejection_reason'] ?? ''));
            $stmt = $conn->prepare("UPDATE loans SET status='rejected', notes=CONCAT(IFNULL(notes,''), ' [Rejected: ?]') WHERE loan_id=?");
            $stmt->bind_param("si", $reason, $loan_id);
            $stmt->execute();
            $conn->query("UPDATE loan_guarantors SET status='rejected' WHERE loan_id=$loan_id");
            $res_l = $conn->query("SELECT member_id, amount, reference_no FROM loans WHERE loan_id = $loan_id");
            if ($l_row = $res_l->fetch_assoc()) {
                send_notification($conn, (int)$l_row['member_id'], 'loan_rejected', ['amount' => $l_row['amount'], 'rejection_reason' => $reason, 'ref' => $l_row['reference_no']]);
            }
            flash_set("Loan #$loan_id rejected.", "warning");

        } elseif ($action === 'disburse' && $can_disburse) {
            $ref    = !empty($_POST['ref_no']) ? $_POST['ref_no'] : ("DSB-" . date('Ymd') . "-" . rand(1000,9999));
            $method = $_POST['payment_method'] ?? 'cash';

            $stmt = $conn->prepare("SELECT amount, member_id FROM loans WHERE loan_id=?");
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();
            $l_chk = $stmt->get_result()->fetch_assoc();
            if (!$l_chk) throw new Exception("Loan data retrieval failed.");

            $engine = new FinancialEngine($conn, $admin_id);
            $engine->transact([
                'member_id'     => $l_chk['member_id'],
                'amount'        => (float)$l_chk['amount'],
                'action_type'   => 'loan_disbursement',
                'reference'     => $ref,
                'method'        => $method,
                'related_id'    => $loan_id,
                'related_table' => 'loans',
                'notes'         => "Loan Disbursement via $method. Ref: $ref"
            ]);
            $conn->query("UPDATE loans SET current_balance=amount, status='disbursed', disbursement_date=NOW() WHERE loan_id=$loan_id");
            send_notification($conn, (int)$l_chk['member_id'], 'loan_disbursed', ['amount' => (float)$l_chk['amount'], 'ref' => $ref]);
            flash_set("Loan #$loan_id disbursed. Ref: $ref", "success");

        } elseif ($action === 'record_repayment' && $can_disburse) {
            $rep_amount = (float)($_POST['repayment_amount'] ?? 0);
            $rep_method = $_POST['repayment_method'] ?? 'cash';
            $rep_ref    = !empty($_POST['repayment_ref']) ? $_POST['repayment_ref'] : ("REP-" . date('Ymd') . "-" . rand(1000,9999));

            if ($rep_amount <= 0) throw new Exception("Repayment amount must be greater than zero.");

            $stmt = $conn->prepare("SELECT amount, member_id, current_balance FROM loans WHERE loan_id=?");
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();
            $l_chk = $stmt->get_result()->fetch_assoc();
            if (!$l_chk) throw new Exception("Loan not found.");

            $engine = new FinancialEngine($conn, $admin_id);
            $engine->transact([
                'member_id'     => $l_chk['member_id'],
                'amount'        => $rep_amount,
                'action_type'   => 'loan_repayment',
                'reference'     => $rep_ref,
                'method'        => $rep_method,
                'related_id'    => $loan_id,
                'related_table' => 'loans',
                'notes'         => "Loan repayment via $rep_method"
            ]);
            flash_set("Repayment of KES " . number_format($rep_amount) . " recorded.", "success");
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        flash_set("Error: " . $e->getMessage(), "danger");
    }
    header("Location: loans.php?id=$loan_id");
    exit;
}

// --- FETCH DATA ---
$stmt = $conn->prepare("SELECT l.*, m.full_name, m.national_id, m.phone, m.email, m.profile_pic, m.member_reg_no,
        a.full_name as approver_name
        FROM loans l
        JOIN members m ON l.member_id = m.member_id
        LEFT JOIN admins a ON l.approved_by = a.admin_id
        WHERE l.loan_id = ?");
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();

if (!$loan) {
    flash_set("Loan #$loan_id not found.", "danger");
    header("Location: loans_payouts.php");
    exit;
}

// Guarantors
$guarantors = [];
$stmt = $conn->prepare("SELECT lg.*, m.full_name, m.phone, m.member_reg_no FROM loan_guarantors lg JOIN members m ON lg.member_id = m.member_id WHERE lg.loan_id = ?");
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$gres = $stmt->get_result();
while ($row = $gres->fetch_assoc()) $guarantors[] = $row;

// Repayment History
$repayments = [];
$stmt = $conn->prepare("SELECT * FROM loan_repayments WHERE loan_id = ? ORDER BY payment_date DESC");
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$rres = $stmt->get_result();
while ($row = $rres->fetch_assoc()) $repayments[] = $row;

// Compute progress
$principal      = (float)($loan['amount'] ?? 0);
$balance        = (float)($loan['current_balance'] ?? $principal);
$paid           = max(0, $principal - $balance);
$progress_pct   = $principal > 0 ? min(100, round(($paid / $principal) * 100)) : 0;
$total_payable  = (float)($loan['total_payable'] ?? 0);
$interest_total = $total_payable > 0 ? $total_payable - $principal : 0;

$status_cfg = [
    'pending'   => ['label' => 'Pending Review',    'class' => 'warning', 'icon' => 'hourglass-split'],
    'approved'  => ['label' => 'Approved',           'class' => 'info',    'icon' => 'check-circle'],
    'disbursed' => ['label' => 'Active / Disbursed', 'class' => 'success', 'icon' => 'graph-up-arrow'],
    'completed' => ['label' => 'Completed',          'class' => 'primary', 'icon' => 'patch-check-fill'],
    'rejected'  => ['label' => 'Rejected',           'class' => 'danger',  'icon' => 'x-circle'],
];
$scfg = $status_cfg[$loan['status']] ?? ['label' => ucfirst($loan['status']), 'class' => 'secondary', 'icon' => 'question-circle'];

$permissions = $_SESSION['permissions'] ?? [];
$is_super    = ($_SESSION['role_id'] ?? 0) == 1;
$can_approve  = $is_super || in_array('approve_loans', $permissions);
$can_disburse = $is_super || in_array('disburse_loans', $permissions);

$pageTitle = "Loan #$loan_id Detail";
?>
<?php $layout->header($pageTitle); ?>

<!-- ═══════════════════════════════════════════════════════════ PAGE STYLES -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

<style>
/* ── Base ───────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

body,
.main-content-wrapper,
.detail-card,
.modal-content,
.table,
select, input, textarea, button {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ── Design tokens ──────────────────────────────────────────── */
:root {
    --forest:       #1a3a2a;
    --forest-mid:   #234d38;
    --forest-light: #2e6347;
    --lime:         #a8e063;
    --lime-soft:    #d4f0a0;
    --lime-glow:    rgba(168,224,99,.18);
    --ink:          #111c14;
    --muted:        #6b7f72;
    --surface:      #ffffff;
    --surface-2:    #f5f8f5;
    --border:       #e3ebe5;
    --shadow-xs:    0 1px 3px rgba(26,58,42,.06);
    --shadow-sm:    0 4px 12px rgba(26,58,42,.08);
    --shadow-md:    0 8px 28px rgba(26,58,42,.12);
    --shadow-lg:    0 16px 48px rgba(26,58,42,.16);
    --radius-sm:    10px;
    --radius-md:    16px;
    --radius-lg:    22px;
    --radius-xl:    30px;
    --transition:   all .22s cubic-bezier(.4,0,.2,1);
}

/* ── Page scaffold ──────────────────────────────────────────── */
.page-canvas {
    background: var(--surface-2);
    min-height: 100vh;
    padding: 0 0 60px;
}

/* ── Breadcrumb ─────────────────────────────────────────────── */
.breadcrumb {
    background: none;
    padding: 0;
    margin: 0 0 28px;
    font-size: .8rem;
    font-weight: 500;
    gap: 2px;
}
.breadcrumb-item a {
    color: var(--muted);
    text-decoration: none;
    transition: var(--transition);
}
.breadcrumb-item a:hover { color: var(--forest); }
.breadcrumb-item.active { color: var(--ink); font-weight: 600; }
.breadcrumb-item + .breadcrumb-item::before { color: var(--border); }

/* ── Hero banner ────────────────────────────────────────────── */
.loan-hero {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 55%, var(--forest-light) 100%);
    border-radius: var(--radius-lg);
    padding: 36px 40px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}
.loan-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse 60% 80% at 90% -10%, rgba(168,224,99,.22) 0%, transparent 60%),
        radial-gradient(ellipse 40% 50% at -5% 100%, rgba(168,224,99,.1) 0%, transparent 55%);
    pointer-events: none;
}
.loan-hero::after {
    content: '';
    position: absolute;
    right: -60px;
    top: -60px;
    width: 260px;
    height: 260px;
    border-radius: 50%;
    border: 1px solid rgba(168,224,99,.12);
    pointer-events: none;
}

.hero-ref-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.15);
    color: rgba(255,255,255,.85);
    font-size: .72rem;
    font-weight: 600;
    letter-spacing: .5px;
    border-radius: 100px;
    padding: 5px 14px;
    margin-bottom: 14px;
    backdrop-filter: blur(4px);
}

.hero-amount {
    font-size: clamp(2rem, 4vw, 2.8rem);
    font-weight: 800;
    color: #fff;
    line-height: 1;
    margin-bottom: 10px;
    letter-spacing: -1px;
}

.hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 14px;
}
.hero-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
    color: rgba(255,255,255,.75);
    font-size: .82rem;
    font-weight: 500;
}
.hero-meta span i { opacity: .7; }

/* Status badge inside hero */
.st-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .4px;
    text-transform: uppercase;
    border-radius: 100px;
    padding: 4px 14px;
    margin-left: 10px;
    vertical-align: middle;
}
.st-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: .7; }
.st-pending   { background: rgba(255,193,7,.18);  color: #ffc107; border: 1px solid rgba(255,193,7,.3); }
.st-approved  { background: rgba(13,202,240,.18); color: #0dcaf0; border: 1px solid rgba(13,202,240,.3); }
.st-disbursed { background: rgba(168,224,99,.22); color: var(--lime); border: 1px solid rgba(168,224,99,.35); }
.st-completed { background: rgba(13,110,253,.18); color: #6ea8fe; border: 1px solid rgba(13,110,253,.3); }
.st-rejected  { background: rgba(220,53,69,.18);  color: #f87a86; border: 1px solid rgba(220,53,69,.3); }

/* Hero action buttons */
.btn-lime {
    background: var(--lime);
    color: var(--ink);
    border: none;
    font-weight: 700;
    font-size: .85rem;
    letter-spacing: .2px;
    transition: var(--transition);
    box-shadow: 0 4px 14px rgba(168,224,99,.4);
}
.btn-lime:hover {
    background: #baea78;
    color: var(--ink);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(168,224,99,.5);
}
.btn-lime:active { transform: translateY(0); }

.hero-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
.hero-actions .btn {
    font-size: .83rem;
    font-weight: 600;
    padding: 9px 22px;
    border-radius: 100px;
    white-space: nowrap;
    transition: var(--transition);
}
.hero-actions .btn-outline-light {
    border-color: rgba(255,255,255,.3);
    color: rgba(255,255,255,.9);
}
.hero-actions .btn-outline-light:hover {
    background: rgba(255,255,255,.12);
    border-color: rgba(255,255,255,.5);
    color: #fff;
    transform: translateY(-1px);
}

/* ── Detail cards ───────────────────────────────────────────── */
.detail-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 28px;
    margin-bottom: 20px;
    transition: var(--transition);
}
.detail-card:hover {
    box-shadow: var(--shadow-md);
    border-color: #d0ddd4;
}

.card-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--border);
}
.card-heading-title {
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: var(--forest);
    display: flex;
    align-items: center;
    gap: 8px;
}
.card-heading-title i {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: var(--lime-glow);
    color: var(--forest);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .9rem;
}

/* ── Info rows ──────────────────────────────────────────────── */
.info-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    transition: var(--transition);
}
.info-row:last-child { border-bottom: none; padding-bottom: 0; }
.info-row:first-child { padding-top: 0; }
.info-row:hover { background: var(--surface-2); margin: 0 -6px; padding-left: 6px; padding-right: 6px; border-radius: 8px; border-bottom-color: transparent; }

.info-label {
    font-size: .8rem;
    font-weight: 500;
    color: var(--muted);
    flex-shrink: 0;
}
.info-val {
    font-size: .85rem;
    font-weight: 600;
    color: var(--ink);
    text-align: right;
}

/* ── Summary stat pills (hero bottom) ──────────────────────── */
.stat-strip {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 22px;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,.1);
}
.stat-pill {
    flex: 1;
    min-width: 110px;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    backdrop-filter: blur(4px);
}
.stat-pill-label {
    font-size: .68rem;
    font-weight: 600;
    letter-spacing: .5px;
    text-transform: uppercase;
    color: rgba(255,255,255,.55);
    margin-bottom: 4px;
}
.stat-pill-value {
    font-size: 1rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.3px;
}
.stat-pill-value.lime { color: var(--lime); }

/* ── Progress bar ───────────────────────────────────────────── */
.progress-section { margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--border); }
.progress-label-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.progress-label-row span:first-child {
    font-size: .78rem;
    font-weight: 600;
    color: var(--muted);
}
.progress-pct-badge {
    font-size: .78rem;
    font-weight: 800;
    color: var(--forest);
    background: var(--lime-glow);
    padding: 3px 10px;
    border-radius: 100px;
}
.progress-track {
    height: 8px;
    background: var(--surface-2);
    border-radius: 100px;
    overflow: hidden;
    border: 1px solid var(--border);
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--forest-light), var(--lime));
    border-radius: 100px;
    transition: width .8s cubic-bezier(.4,0,.2,1);
    position: relative;
}
.progress-fill::after {
    content: '';
    position: absolute;
    top: 0; right: 0; bottom: 0;
    width: 20px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.35));
    border-radius: 100px;
}

/* ── Borrower block ─────────────────────────────────────────── */
.borrower-block {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 20px;
    padding-bottom: 18px;
    border-bottom: 1px solid var(--border);
}
.borrower-avatar {
    width: 58px;
    height: 58px;
    border-radius: 14px;
    object-fit: cover;
    border: 2px solid var(--border);
    flex-shrink: 0;
}
.borrower-avatar-fallback {
    width: 58px;
    height: 58px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--forest), var(--forest-light));
    color: var(--lime);
    font-weight: 800;
    font-size: 1.35rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 2px solid var(--border);
}
.borrower-name { font-size: 1.05rem; font-weight: 800; color: var(--ink); }
.borrower-reg  { font-size: .75rem; font-weight: 500; color: var(--muted); font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; }

/* ── Guarantor pills ────────────────────────────────────────── */
.guarantor-pill {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    transition: var(--transition);
    margin-bottom: 8px;
}
.guarantor-pill:last-child { margin-bottom: 0; }
.guarantor-pill:hover {
    background: var(--surface-2);
    border-color: #c8d9cc;
    transform: translateX(2px);
}
.guarantor-pill .g-name  { font-size: .85rem; font-weight: 700; color: var(--ink); }
.guarantor-pill .g-meta  { font-size: .73rem; color: var(--muted); margin-top: 2px; }
.guarantor-pill .g-amt   { font-size: .88rem; font-weight: 800; color: #1a7a3f; }

/* ── Repayment table ────────────────────────────────────────── */
.rep-table { width: 100%; border-collapse: collapse; }
.rep-table thead th {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .8px;
    text-transform: uppercase;
    color: var(--muted);
    padding: 0 10px 12px;
    border-bottom: 2px solid var(--border);
}
.rep-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: var(--transition);
}
.rep-table tbody tr:last-child { border-bottom: none; }
.rep-table tbody tr:hover { background: var(--surface-2); }
.rep-table td {
    padding: 13px 10px;
    font-size: .82rem;
    color: var(--ink);
    vertical-align: middle;
}
.rep-table td.date   { color: var(--muted); font-size: .78rem; }
.rep-table td.amount { font-weight: 800; color: #1a7a3f; }
.rep-table td.ref    { font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; font-size: .75rem; color: var(--muted); }

/* ── Timeline ───────────────────────────────────────────────── */
.timeline { display: flex; flex-direction: column; gap: 0; }
.tl-item {
    display: flex;
    gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}
.tl-item:last-child { border-bottom: none; padding-bottom: 0; }
.tl-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--lime);
    border: 2px solid var(--forest);
    margin-top: 5px;
    flex-shrink: 0;
}
.tl-label { font-size: .78rem; font-weight: 600; color: var(--muted); }
.tl-value { font-size: .85rem; font-weight: 700; color: var(--ink); margin-top: 2px; }

/* ── Empty states ───────────────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 28px 16px;
    color: var(--muted);
}
.empty-state i {
    font-size: 2rem;
    opacity: .2;
    display: block;
    margin-bottom: 10px;
}
.empty-state p { font-size: .82rem; margin: 0; }

/* ── Modals ─────────────────────────────────────────────────── */
.modal-content {
    border: 0;
    border-radius: var(--radius-lg) !important;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}
.modal-header { border-bottom: 0 !important; padding: 28px 28px 0 !important; }
.modal-body   { padding: 22px 28px !important; }
.modal-footer { border-top: 0 !important; padding: 0 28px 28px !important; gap: 10px; }

.modal-hero {
    padding: 28px;
    text-align: center;
}
.modal-hero.forest { background: linear-gradient(135deg, var(--forest), var(--forest-light)); color: #fff; }
.modal-hero-label  { font-size: .72rem; font-weight: 600; letter-spacing: .6px; text-transform: uppercase; opacity: .7; margin-bottom: 6px; }
.modal-hero-amount { font-size: 2rem; font-weight: 800; letter-spacing: -1px; }
.modal-hero-name   { font-size: .82rem; opacity: .7; margin-top: 4px; }

.form-label {
    font-size: .78rem;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 7px;
}
.form-control, .form-select {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .85rem;
    font-weight: 500;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 10px 14px;
    color: var(--ink);
    background: var(--surface-2);
    transition: var(--transition);
}
.form-control:focus, .form-select:focus {
    border-color: var(--forest);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(26,58,42,.08);
    outline: none;
}
.form-control.font-monospace { font-family: 'DM Mono', monospace, 'Plus Jakarta Sans' !important; font-size: .82rem; }

.modal-balance-bar {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.modal-balance-bar span:first-child { font-size: .78rem; color: var(--muted); font-weight: 500; }
.modal-balance-bar strong { font-weight: 800; color: #c0392b; font-size: .9rem; }

.btn-forest-modal {
    background: var(--forest);
    color: #fff;
    border: none;
    font-weight: 700;
    font-size: .85rem;
    border-radius: 100px;
    padding: 11px 28px;
    transition: var(--transition);
}
.btn-forest-modal:hover {
    background: var(--forest-light);
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(26,58,42,.3);
}

/* ── Animate in ─────────────────────────────────────────────── */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
.loan-hero          { animation: fadeUp .4s ease both; }
.detail-card        { animation: fadeUp .4s ease both; }
.col-lg-5 .detail-card:nth-child(1) { animation-delay: .08s; }
.col-lg-5 .detail-card:nth-child(2) { animation-delay: .14s; }
.col-lg-7 .detail-card:nth-child(1) { animation-delay: .1s; }
.col-lg-7 .detail-card:nth-child(2) { animation-delay: .16s; }
.col-lg-7 .detail-card:nth-child(3) { animation-delay: .22s; }

/* ── Utilities ──────────────────────────────────────────────── */
.bg-forest   { background: var(--forest) !important; }
.text-forest { color: var(--forest) !important; }
.fw-800      { font-weight: 800 !important; }
.x-small     { font-size: .72rem; }
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4 page-canvas">

        <!-- Breadcrumb -->
        <nav class="mb-1" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
                <li class="breadcrumb-item"><a href="loans_payouts.php">Loans</a></li>
                <li class="breadcrumb-item active">Loan #<?= $loan_id ?></li>
            </ol>
        </nav>

        <?php flash_render(); ?>

        <!-- ═══ HERO ═══════════════════════════════════════════════════════ -->
        <div class="loan-hero mb-4">
            <div class="row align-items-center g-4">
                <div class="col-md-7">
                    <div class="hero-ref-badge">
                        <i class="bi bi-hash"></i>
                        REF: <?= htmlspecialchars($loan['reference_no'] ?? "LOAN-$loan_id") ?>
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                        <h1 class="hero-amount mb-0">KES <?= number_format($principal) ?></h1>
                        <span class="st-badge st-<?= $loan['status'] ?>"><?= $scfg['label'] ?></span>
                    </div>
                    <div class="hero-meta">
                        <span><i class="bi bi-person-fill"></i><?= htmlspecialchars($loan['full_name']) ?></span>
                        <span><i class="bi bi-tag-fill"></i><?= htmlspecialchars($loan['loan_type']) ?></span>
                        <span><i class="bi bi-calendar3"></i>Applied <?= date('d M Y', strtotime($loan['created_at'])) ?></span>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="hero-actions">
                        <?php if ($loan['status'] === 'pending' && $can_approve): ?>
                            <button class="btn btn-lime px-4 fw-bold shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#approveModal">
                                <i class="bi bi-check-lg me-2"></i>Approve Loan
                            </button>
                            <button class="btn btn-outline-light rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="bi bi-x-lg me-2"></i>Reject
                            </button>
                        <?php elseif ($loan['status'] === 'approved' && $can_disburse): ?>
                            <button class="btn btn-lime px-4 fw-bold shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#disburseModal">
                                <i class="bi bi-send-fill me-2"></i>Process Disbursement
                            </button>
                        <?php elseif ($loan['status'] === 'disbursed' && $can_disburse): ?>
                            <button class="btn btn-lime px-4 fw-bold shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#repayModal">
                                <i class="bi bi-arrow-down-circle-fill me-2"></i>Record Repayment
                            </button>
                        <?php endif; ?>
                        <a href="member_profile.php?id=<?= $loan['member_id'] ?>" class="btn btn-outline-light rounded-pill px-4">
                            <i class="bi bi-person-circle me-2"></i>View Member
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stat strip -->
            <div class="stat-strip">
                <div class="stat-pill">
                    <div class="stat-pill-label">Principal</div>
                    <div class="stat-pill-value">KES <?= number_format($principal) ?></div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-label">Total Repayable</div>
                    <div class="stat-pill-value">KES <?= number_format($total_payable) ?></div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-label">Balance Due</div>
                    <div class="stat-pill-value <?= $balance > 0 ? 'lime' : '' ?>">KES <?= number_format($balance) ?></div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-label">Rate / Term</div>
                    <div class="stat-pill-value"><?= $loan['interest_rate'] ?>% · <?= $loan['duration_months'] ?>mo</div>
                </div>
            </div>
        </div>

        <!-- ═══ MAIN GRID ════════════════════════════════════════════════== -->
        <div class="row g-4">

            <!-- Left Column -->
            <div class="col-lg-5">

                <!-- Loan Details -->
                <div class="detail-card">
                    <div class="card-heading">
                        <div class="card-heading-title">
                            <i class="bi bi-receipt-cutoff d-flex"></i>
                            Loan Details
                        </div>
                    </div>
                    <div class="info-row"><span class="info-label">Principal</span><span class="info-val" style="color:#1a7a3f">KES <?= number_format($principal, 2) ?></span></div>
                    <div class="info-row"><span class="info-label">Interest Rate</span><span class="info-val"><?= $loan['interest_rate'] ?>% per annum</span></div>
                    <div class="info-row"><span class="info-label">Duration</span><span class="info-val"><?= $loan['duration_months'] ?> Months</span></div>
                    <div class="info-row"><span class="info-label">Total Repayable</span><span class="info-val">KES <?= number_format($total_payable, 2) ?></span></div>
                    <div class="info-row">
                        <span class="info-label">Monthly Instalment</span>
                        <span class="info-val">KES <?= ($loan['duration_months'] > 0 && $total_payable > 0) ? number_format($total_payable / (int)$loan['duration_months'], 2) : '–' ?></span>
                    </div>
                    <div class="info-row"><span class="info-label">Outstanding Balance</span><span class="info-val" style="color:#c0392b">KES <?= number_format($balance, 2) ?></span></div>
                    <div class="info-row"><span class="info-label">Amount Paid</span><span class="info-val" style="color:#1a7a3f">KES <?= number_format($paid, 2) ?></span></div>

                    <?php if ($loan['status'] === 'disbursed' || $loan['status'] === 'completed'): ?>
                    <div class="progress-section">
                        <div class="progress-label-row">
                            <span>Repayment Progress</span>
                            <span class="progress-pct-badge"><?= $progress_pct ?>% Paid</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width:<?= $progress_pct ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Timeline -->
                <div class="detail-card">
                    <div class="card-heading">
                        <div class="card-heading-title">
                            <i class="bi bi-clock-history d-flex"></i>
                            Timeline
                        </div>
                    </div>
                    <div class="timeline">
                        <div class="tl-item">
                            <div class="tl-dot"></div>
                            <div>
                                <div class="tl-label">Application Submitted</div>
                                <div class="tl-value"><?= date('d M Y, H:i', strtotime($loan['created_at'])) ?></div>
                            </div>
                        </div>
                        <?php if ($loan['approval_date']): ?>
                        <div class="tl-item">
                            <div class="tl-dot"></div>
                            <div>
                                <div class="tl-label">Approved by <?= htmlspecialchars($loan['approver_name'] ?? '—') ?></div>
                                <div class="tl-value"><?= date('d M Y', strtotime($loan['approval_date'])) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php
                        $ddate = $loan['disbursement_date'] ?? $loan['disbursed_date'] ?? null;
                        if ($ddate):
                        ?>
                        <div class="tl-item">
                            <div class="tl-dot"></div>
                            <div>
                                <div class="tl-label">Funds Disbursed</div>
                                <div class="tl-value"><?= date('d M Y', strtotime($ddate)) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($loan['notes']): ?>
                        <div class="tl-item">
                            <div class="tl-dot" style="background:var(--muted);border-color:var(--muted)"></div>
                            <div>
                                <div class="tl-label">Notes</div>
                                <div class="tl-value" style="color:var(--muted);font-weight:500"><?= htmlspecialchars($loan['notes']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-7">

                <!-- Borrower Information -->
                <div class="detail-card">
                    <div class="card-heading">
                        <div class="card-heading-title">
                            <i class="bi bi-person-vcard d-flex"></i>
                            Borrower Information
                        </div>
                    </div>
                    <div class="borrower-block">
                        <?php if (!empty($loan['profile_pic'])): ?>
                            <img src="data:image/jpeg;base64,<?= base64_encode($loan['profile_pic']) ?>" class="borrower-avatar" alt="Profile">
                        <?php else: ?>
                            <div class="borrower-avatar-fallback"><?= strtoupper(substr($loan['full_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <div>
                            <div class="borrower-name"><?= htmlspecialchars($loan['full_name']) ?></div>
                            <div class="borrower-reg">Reg: <?= htmlspecialchars($loan['member_reg_no'] ?? '–') ?></div>
                        </div>
                    </div>
                    <div class="info-row"><span class="info-label">National ID</span><span class="info-val" style="font-family:monospace"><?= htmlspecialchars($loan['national_id']) ?></span></div>
                    <div class="info-row"><span class="info-label">Phone</span><span class="info-val"><?= htmlspecialchars($loan['phone']) ?></span></div>
                    <div class="info-row"><span class="info-label">Email</span><span class="info-val"><?= htmlspecialchars($loan['email'] ?? '–') ?></span></div>
                </div>

                <!-- Guarantors -->
                <div class="detail-card">
                    <div class="card-heading">
                        <div class="card-heading-title">
                            <i class="bi bi-people-fill d-flex"></i>
                            Guarantors
                        </div>
                        <span class="badge rounded-pill px-3 py-2" style="background:var(--lime-glow);color:var(--forest);font-size:.72rem;font-weight:700"><?= count($guarantors) ?> assigned</span>
                    </div>
                    <?php if (empty($guarantors)): ?>
                        <div class="empty-state">
                            <i class="bi bi-people"></i>
                            <p>No guarantors assigned to this loan.</p>
                        </div>
                    <?php else: foreach ($guarantors as $g): ?>
                        <div class="guarantor-pill">
                            <div>
                                <div class="g-name"><?= htmlspecialchars($g['full_name']) ?></div>
                                <div class="g-meta"><?= htmlspecialchars($g['member_reg_no']) ?> &bull; <?= htmlspecialchars($g['phone']) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="g-amt">KES <?= number_format((float)($g['amount_locked'] ?? 0)) ?></div>
                                <?php
                                $gs = $g['status'] ?? 'pending';
                                $gc = $gs === 'approved' ? 'success' : ($gs === 'rejected' ? 'danger' : 'warning');
                                ?>
                                <span class="badge rounded-pill bg-<?= $gc ?> bg-opacity-10 text-<?= $gc ?>" style="font-size:.64rem;font-weight:700"><?= strtoupper($gs) ?></span>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <!-- Repayment History -->
                <div class="detail-card">
                    <div class="card-heading">
                        <div class="card-heading-title">
                            <i class="bi bi-arrow-repeat d-flex"></i>
                            Repayment History
                        </div>
                        <?php if (!empty($repayments)): ?>
                        <span class="badge rounded-pill px-3 py-2" style="background:var(--lime-glow);color:var(--forest);font-size:.72rem;font-weight:700"><?= count($repayments) ?> payments</span>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($repayments)): ?>
                        <div class="empty-state">
                            <i class="bi bi-receipt"></i>
                            <p>No repayments recorded yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="rep-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                        <th style="text-align:right">Balance After</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($repayments as $r): ?>
                                    <tr>
                                        <td class="date"><?= date('d M Y', strtotime($r['payment_date'])) ?></td>
                                        <td class="amount">KES <?= number_format((float)$r['amount_paid']) ?></td>
                                        <td><?= ucfirst($r['payment_method'] ?? '–') ?></td>
                                        <td class="ref"><?= htmlspecialchars($r['reference_no'] ?? '–') ?></td>
                                        <td style="text-align:right;font-size:.8rem;color:var(--muted)">KES <?= number_format((float)($r['remaining_balance'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /row -->
    </div><!-- /container-fluid -->

    <!-- ═══════════════════════════════════════════════════ MODALS ══════ -->

    <!-- APPROVE MODAL -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
            <div class="modal-content">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve">
                    <div class="modal-header">
                        <div>
                            <h5 class="fw-800 text-success mb-1"><i class="bi bi-check-circle-fill me-2"></i>Approve Loan</h5>
                            <p class="text-muted small mb-0">Loan #<?= $loan_id ?> · <?= htmlspecialchars($loan['full_name']) ?></p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-balance-bar" style="background:#f0fff4;border-color:#a7f3c1">
                            <span>Loan Amount</span>
                            <strong style="color:#1a7a3f">KES <?= number_format($principal) ?></strong>
                        </div>
                        <p class="text-muted" style="font-size:.85rem;line-height:1.6">
                            This will approve the application and notify the member, moving the loan into the disbursement queue.
                        </p>
                    </div>
                    <div class="modal-footer justify-content-end">
                        <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success rounded-pill fw-bold px-5">Confirm Approval</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- REJECT MODAL -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
            <div class="modal-content">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject">
                    <div class="modal-header">
                        <div>
                            <h5 class="fw-800 text-danger mb-1"><i class="bi bi-x-circle-fill me-2"></i>Reject Application</h5>
                            <p class="text-muted small mb-0">Loan #<?= $loan_id ?> · <?= htmlspecialchars($loan['full_name']) ?></p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" class="form-control" rows="4" required placeholder="e.g. Insufficient guarantor coverage, income not verified…"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-end">
                        <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger rounded-pill fw-bold px-5">Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- DISBURSE MODAL -->
    <div class="modal fade" id="disburseModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
            <div class="modal-content">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="disburse">
                    <div class="modal-hero forest">
                        <div class="modal-hero-label">Disbursement Amount</div>
                        <div class="modal-hero-amount">KES <?= number_format($principal) ?></div>
                        <div class="modal-hero-name"><?= htmlspecialchars($loan['full_name']) ?></div>
                    </div>
                    <div class="modal-body" style="padding-top:20px !important">
                        <div class="mb-3">
                            <label class="form-label">Payment Channel</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="bank">Bank Transfer</option>
                                <option value="cash">Petty Cash</option>
                                <option value="mpesa">M-Pesa</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Transaction Reference</label>
                            <input type="text" name="ref_no" class="form-control font-monospace fw-bold" value="DSB-<?= date('Ymd') ?>-<?= rand(1000,9999) ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-end">
                        <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-lime rounded-pill fw-bold px-5 text-dark">Process Payout</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- REPAYMENT MODAL -->
    <div class="modal fade" id="repayModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
            <div class="modal-content">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="record_repayment">
                    <div class="modal-header">
                        <div>
                            <h5 class="fw-800 mb-1" style="color:var(--forest)"><i class="bi bi-arrow-down-circle-fill me-2"></i>Record Repayment</h5>
                            <p class="text-muted small mb-0">Loan #<?= $loan_id ?> · <?= htmlspecialchars($loan['full_name']) ?></p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-balance-bar">
                            <span>Outstanding Balance</span>
                            <strong>KES <?= number_format($balance, 2) ?></strong>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount Paid <span class="text-danger">*</span></label>
                            <input type="number" name="repayment_amount" class="form-control" step="0.01" min="1" max="<?= $balance ?>" required placeholder="e.g. 5,000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select name="repayment_method" class="form-select" required>
                                <option value="cash">Cash</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="wallet">Wallet Deduction</option>
                            </select>
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Reference No. <span class="text-muted" style="font-weight:400">(optional)</span></label>
                            <input type="text" name="repayment_ref" class="form-control font-monospace" placeholder="Auto-generated if blank">
                        </div>
                    </div>
                    <div class="modal-footer justify-content-end">
                        <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success rounded-pill fw-bold px-5">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->
</body>
</html>