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
    flash_set("Invalid Loan ID.", "danger");
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
<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">
<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content-wrapper">
        <?php $layout->topbar($pageTitle ?? ""); ?>
        <div class="container-fluid px-4 py-4">




            <!-- Breadcrumb -->
            <nav class="mb-4"><ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="loans_payouts.php" class="text-decoration-none">Loans</a></li>
                <li class="breadcrumb-item active">Loan #<?= $loan_id ?></li>
            </ol></nav>

            <?php flash_render(); ?>

            <!-- Hero -->
            <div class="loan-hero shadow-lg">
                <div class="row align-items-center g-4">
                    <div class="col-md-7">
                        <span class="badge bg-white bg-opacity-15 text-white rounded-pill mb-3 px-3 py-2 small">
                            <i class="bi bi-hash me-1"></i>REF: <?= htmlspecialchars($loan['reference_no'] ?? "LOAN-$loan_id") ?>
                        </span>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <h1 class="fw-800 mb-0">KES <?= number_format($principal) ?></h1>
                            <span class="st-badge st-<?= $loan['status'] ?>"><?= $scfg['label'] ?></span>
                        </div>
                        <div class="d-flex flex-wrap gap-4 opacity-75 small">
                            <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($loan['full_name']) ?></span>
                            <span><i class="bi bi-tag me-1"></i><?= htmlspecialchars($loan['loan_type']) ?></span>
                            <span><i class="bi bi-calendar3 me-1"></i>Applied: <?= date('d M Y', strtotime($loan['created_at'])) ?></span>
                        </div>
                    </div>
                    <div class="col-md-5 text-md-end d-flex flex-column align-items-md-end gap-2">
                        <?php if ($loan['status'] === 'pending' && $can_approve): ?>
                            <button class="btn btn-lime px-4 fw-bold shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#approveModal">
                                <i class="bi bi-check-lg me-2"></i>Approve
                            </button>
                            <button class="btn btn-outline-light rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="bi bi-x-lg me-2"></i>Reject
                            </button>
                        <?php elseif ($loan['status'] === 'approved' && $can_disburse): ?>
                            <button class="btn btn-lime px-4 fw-bold shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#disburseModal">
                                <i class="bi bi-send-fill me-2"></i>Process Disbursement
                            </button>
                        <?php elseif ($loan['status'] === 'disbursed' && $can_disburse): ?>
                            <button class="btn btn-lime px-4 fw-bold shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#repayModal">
                                <i class="bi bi-arrow-down-circle me-2"></i>Record Repayment
                            </button>
                        <?php endif; ?>
                        <a href="member_profile.php?id=<?= $loan['member_id'] ?>" class="btn btn-outline-light rounded-pill px-4 fw-bold">
                            <i class="bi bi-person-circle me-2"></i>View Member
                        </a>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Left Column -->
                <div class="col-lg-5">
                    <!-- Loan Details -->
                    <div class="detail-card shadow-sm">
                        <h6 class="fw-800 mb-4 text-uppercase small" style="letter-spacing:1px; color:var(--forest)">Loan Details</h6>
                        <div class="info-row"><span class="info-label">Principal</span><span class="info-val text-success">KES <?= number_format($principal, 2) ?></span></div>
                        <div class="info-row"><span class="info-label">Interest Rate</span><span class="info-val"><?= $loan['interest_rate'] ?>%</span></div>
                        <div class="info-row"><span class="info-label">Duration</span><span class="info-val"><?= $loan['duration_months'] ?> Months</span></div>
                        <div class="info-row"><span class="info-label">Total Repayable</span><span class="info-val">KES <?= number_format($total_payable, 2) ?></span></div>
                        <div class="info-row"><span class="info-label">Monthly Installment</span><span class="info-val">
                            KES <?= ($loan['duration_months'] > 0 && $total_payable > 0) ? number_format($total_payable / (int)$loan['duration_months'], 2) : '–' ?>
                        </span></div>
                        <div class="info-row"><span class="info-label">Outstanding Balance</span><span class="info-val text-danger">KES <?= number_format($balance, 2) ?></span></div>
                        <div class="info-row"><span class="info-label">Amount Paid</span><span class="info-val text-success">KES <?= number_format($paid, 2) ?></span></div>
                        <?php if ($loan['status'] === 'disbursed' || $loan['status'] === 'completed'): ?>
                        <div class="mt-4">
                            <div class="d-flex justify-content-between mb-1 small fw-bold"><span>Repayment Progress</span><span><?= $progress_pct ?>%</span></div>
                            <div class="progress-track"><div class="progress-fill" style="width:<?= $progress_pct ?>%"></div></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Timeline -->
                    <div class="detail-card shadow-sm">
                        <h6 class="fw-800 mb-4 text-uppercase small" style="letter-spacing:1px; color:var(--forest)">Timeline</h6>
                        <div class="info-row"><span class="info-label">Applied</span><span class="info-val small"><?= date('d M Y, H:i', strtotime($loan['created_at'])) ?></span></div>
                        <?php if ($loan['approval_date']): ?>
                        <div class="info-row"><span class="info-label">Approved</span><span class="info-val small"><?= date('d M Y', strtotime($loan['approval_date'])) ?></span></div>
                        <div class="info-row"><span class="info-label">Approved By</span><span class="info-val small"><?= htmlspecialchars($loan['approver_name'] ?? '–') ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($loan['disbursement_date']) || !empty($loan['disbursed_date'])): 
                            $ddate = $loan['disbursement_date'] ?? $loan['disbursed_date'];
                        ?>
                        <div class="info-row"><span class="info-label">Disbursed</span><span class="info-val small"><?= date('d M Y', strtotime($ddate)) ?></span></div>
                        <?php endif; ?>
                        <?php if ($loan['notes']): ?>
                        <div class="info-row"><span class="info-label">Notes</span><span class="info-val small text-muted"><?= htmlspecialchars($loan['notes']) ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-7">
                    <!-- Borrower -->
                    <div class="detail-card shadow-sm">
                        <h6 class="fw-800 mb-4 text-uppercase small" style="letter-spacing:1px; color:var(--forest)">Borrower Information</h6>
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <?php if (!empty($loan['profile_pic'])): ?>
                                <img src="data:image/jpeg;base64,<?= base64_encode($loan['profile_pic']) ?>" class="rounded-3 shadow-sm" style="width:64px;height:64px;object-fit:cover;">
                            <?php else: ?>
                                <div class="rounded-3 shadow-sm d-flex align-items-center justify-content-center fw-800 fs-4" style="width:64px;height:64px;background:var(--forest);color:var(--lime);"><?= strtoupper(substr($loan['full_name'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-800 fs-5"><?= htmlspecialchars($loan['full_name']) ?></div>
                                <div class="text-muted small font-monospace">Reg: <?= htmlspecialchars($loan['member_reg_no'] ?? '–') ?></div>
                            </div>
                        </div>
                        <div class="info-row"><span class="info-label">National ID</span><span class="info-val font-monospace"><?= htmlspecialchars($loan['national_id']) ?></span></div>
                        <div class="info-row"><span class="info-label">Phone</span><span class="info-val"><?= htmlspecialchars($loan['phone']) ?></span></div>
                        <div class="info-row"><span class="info-label">Email</span><span class="info-val"><?= htmlspecialchars($loan['email'] ?? '–') ?></span></div>
                    </div>

                    <!-- Guarantors -->
                    <div class="detail-card shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h6 class="fw-800 mb-0 text-uppercase small" style="letter-spacing:1px; color:var(--forest)">Guarantors</h6>
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3"><?= count($guarantors) ?> assigned</span>
                        </div>
                        <?php if (empty($guarantors)): ?>
                            <div class="text-center text-muted py-3 small"><i class="bi bi-people fs-2 opacity-25 d-block mb-2"></i>No guarantors assigned.</div>
                        <?php else: foreach ($guarantors as $g): ?>
                            <div class="guarantor-pill mb-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold small"><?= htmlspecialchars($g['full_name']) ?></div>
                                    <div class="text-muted x-small"><?= htmlspecialchars($g['member_reg_no']) ?> &bull; <?= htmlspecialchars($g['phone']) ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-800 small text-success">KES <?= number_format((float)($g['amount_locked'] ?? 0)) ?></div>
                                    <span class="badge bg-<?= $g['status']==='approved'?'success':($g['status']==='rejected'?'danger':'warning') ?> bg-opacity-10 text-<?= $g['status']==='approved'?'success':($g['status']==='rejected'?'danger':'warning') ?> rounded-pill" style="font-size:.65rem"><?= strtoupper($g['status'] ?? 'pending') ?></span>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>

                    <!-- Repayment History -->
                    <div class="detail-card shadow-sm">
                        <h6 class="fw-800 mb-4 text-uppercase small" style="letter-spacing:1px; color:var(--forest)">Repayment History</h6>
                        <?php if (empty($repayments)): ?>
                            <div class="text-center text-muted py-3 small"><i class="bi bi-receipt fs-2 opacity-25 d-block mb-2"></i>No repayments recorded yet.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light small text-uppercase text-muted">
                                        <tr>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Reference</th>
                                            <th>Balance After</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($repayments as $r): ?>
                                        <tr>
                                            <td class="small"><?= date('d M Y', strtotime($r['payment_date'])) ?></td>
                                            <td class="fw-bold text-success">KES <?= number_format((float)$r['amount_paid']) ?></td>
                                            <td class="small text-muted"><?= ucfirst($r['payment_method'] ?? '–') ?></td>
                                            <td class="small font-monospace"><?= htmlspecialchars($r['reference_no'] ?? '–') ?></td>
                                            <td class="small">KES <?= number_format((float)($r['remaining_balance'] ?? 0)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve">
                <div class="modal-header bg-success bg-opacity-10 border-0 p-4">
                    <h5 class="modal-title fw-bold text-success"><i class="bi bi-check-circle me-2"></i>Approve Loan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted mb-3">You are about to approve <strong>Loan #<?= $loan_id ?></strong> of <strong>KES <?= number_format($principal) ?></strong> for <strong><?= htmlspecialchars($loan['full_name']) ?></strong>.</p>
                    <p class="text-muted small">This will notify the member and move the loan to the disbursement queue.</p>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 gap-2">
                    <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill fw-bold px-5 shadow-sm">Confirm Approval</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- REJECT MODAL -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <div class="modal-header bg-danger bg-opacity-10 border-0 p-4">
                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-x-circle me-2"></i>Reject Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="4" required placeholder="e.g. Insufficient guarantor coverage..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 gap-2">
                    <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill fw-bold px-5 shadow-sm">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DISBURSE MODAL -->
<div class="modal fade" id="disburseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="disburse">
                <div class="bg-forest text-white p-4 text-center">
                    <div class="small opacity-75 mb-1">Disbursement Amount</div>
                    <h2 class="fw-800 text-white">KES <?= number_format($principal) ?></h2>
                    <div class="small opacity-75"><?= htmlspecialchars($loan['full_name']) ?></div>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Payment Channel</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="bank">Bank Transfer</option>
                            <option value="cash">Petty Cash</option>
                            <option value="mpesa">M-Pesa</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Transaction Reference</label>
                        <input type="text" name="ref_no" class="form-control font-monospace fw-bold" value="DSB-<?= date('Ymd') ?>-<?= rand(1000,9999) ?>" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 gap-2">
                    <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lime rounded-pill fw-bold px-5 shadow-sm text-dark">Process Payout</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- REPAYMENT MODAL -->
<div class="modal fade" id="repayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="record_repayment">
                <div class="modal-header border-0 p-4 bg-success bg-opacity-10">
                    <h5 class="modal-title fw-bold text-success"><i class="bi bi-arrow-down-circle me-2"></i>Record Repayment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">Outstanding Balance: <strong class="text-danger">KES <?= number_format($balance, 2) ?></strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Amount Paid <span class="text-danger">*</span></label>
                        <input type="number" name="repayment_amount" class="form-control" step="0.01" min="1" max="<?= $balance ?>" required placeholder="e.g. 5000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Payment Method</label>
                        <select name="repayment_method" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="wallet">Wallet Deduction</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Reference No.</label>
                        <input type="text" name="repayment_ref" class="form-control font-monospace" placeholder="Auto-generated if blank">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 gap-2">
                    <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill fw-bold px-5 shadow-sm">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
