<?php
/**
 * admin/loans_payouts.php
 * Enhanced Loan Management Console
 * Theme: Hope UI (Glassmorphism) + High Performance UX
 */

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

// --- 1. Dependencies & Security ---
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/ExportHelper.php';
require_once __DIR__ . '/../../inc/TransactionHelper.php';

// Initialize Layout & Auth
$layout = LayoutManager::create('admin');
Auth::requireAdmin(); 

// --- 2. Permission Logic (The Workflow Engine) ---
$permissions = $_SESSION['permissions'] ?? [];
$is_super = ($_SESSION['role_id'] ?? 0) == 1;

$can_approve  = $is_super || in_array('approve_loans', $permissions);
$can_disburse = $is_super || in_array('disburse_loans', $permissions);
$can_reject   = $can_approve;

$admin_id = $_SESSION['admin_id'];

// --- 3. Handle Actions (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';
    $loan_id = intval($_POST['loan_id'] ?? 0);
    
    if ($loan_id > 0) {
        $conn->begin_transaction();
        try {
            if ($action === 'approve' && $can_approve) {
                $stmt = $conn->prepare("UPDATE loans SET status='approved', approved_by=?, approval_date=NOW() WHERE loan_id=?");
                $stmt->bind_param("ii", $admin_id, $loan_id);
                $stmt->execute();

                // Trigger Loan Approved Notification
                require_once __DIR__ . '/../../inc/notification_helpers.php';
                $res_l = $conn->query("SELECT member_id, amount, reference_no FROM loans WHERE loan_id = $loan_id");
                if ($l_row = $res_l->fetch_assoc()) {
                    send_notification($conn, (int)$l_row['member_id'], 'loan_approved', ['amount' => $l_row['amount'], 'ref' => $l_row['reference_no']]);
                }

                flash_set("Loan #$loan_id Approved successfully.", "success");
            } 
            elseif ($action === 'reject' && $can_reject) {
                $reason = $conn->real_escape_string($_POST['rejection_reason'] ?? '');
                $stmt = $conn->prepare("UPDATE loans SET status='rejected', notes=CONCAT(IFNULL(notes,''), ' [Rejected: ?]') WHERE loan_id=?");
                $stmt->bind_param("si", $reason, $loan_id);
                $stmt->execute();
                
                $stmt = $conn->prepare("UPDATE loan_guarantors SET status='rejected' WHERE loan_id=?");
                $stmt->bind_param("i", $loan_id);
                $stmt->execute();

                // Trigger Loan Rejected Notification
                require_once __DIR__ . '/../../inc/notification_helpers.php';
                $res_l = $conn->query("SELECT member_id, amount, reference_no FROM loans WHERE loan_id = $loan_id");
                if ($l_row = $res_l->fetch_assoc()) {
                    send_notification($conn, (int)$l_row['member_id'], 'loan_rejected', ['amount' => $l_row['amount'], 'rejection_reason' => $reason, 'ref' => $l_row['reference_no']]);
                }

                flash_set("Loan #$loan_id Rejected.", "warning");
            }
            elseif ($action === 'disburse' && $can_disburse) {
                // Generate a fallback ref if empty somehow
                $fallback_ref = "DSB-" . date('Ymd') . "-" . rand(1000, 9999);
                $ref    = !empty($_POST['ref_no']) ? $_POST['ref_no'] : $fallback_ref;
                $method = $_POST['payment_method'] ?? 'cash';

                require_once __DIR__ . '/../../inc/FinancialEngine.php';
                $engine = new FinancialEngine($conn, $admin_id);
                
                $stmt = $conn->prepare("SELECT amount, member_id FROM loans WHERE loan_id=?");
                $stmt->bind_param("i", $loan_id);
                $stmt->execute();
                $l_chk = $stmt->get_result()->fetch_assoc();
                
                if (!$l_chk) throw new Exception("Loan data retrieval failed.");

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

                // Update loan balance
                $conn->query("UPDATE loans SET current_balance = amount, status='disbursed', disbursement_date=NOW() WHERE loan_id=$loan_id");

                // Trigger Loan Disbursed Notification
                require_once __DIR__ . '/../../inc/notification_helpers.php';
                send_notification($conn, (int)$l_chk['member_id'], 'loan_disbursed', ['amount' => (float)$l_chk['amount'], 'ref' => $ref]);

                flash_set("Funds Disbursed successfully. Reference: $ref", "success");
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            flash_set("Error: " . $e->getMessage(), "error");
        }
        header("Location: loans_payouts.php");
        exit;
    }
}

// --- 4. Fetch Data (Enhanced Query) ---
$where = "1";
$params = [];
$types = "";

if (!empty($_GET['status'])) {
    $where .= " AND l.status = ?";
    $params[] = $_GET['status']; $types .= "s";
}
if (!empty($_GET['search'])) {
    $s = "%" . $_GET['search'] . "%";
    $where .= " AND (m.full_name LIKE ? OR m.national_id LIKE ? OR l.loan_id = ?)";
    $params[] = $s; $params[] = $s; $params[] = $_GET['search']; $types .= "ssi";
}

$sql = "SELECT l.*, m.full_name, m.national_id, m.phone,
        (SELECT COUNT(*) FROM loan_guarantors lg WHERE lg.loan_id = l.loan_id) as guarantor_count,
        a.full_name as approver_name
        FROM loans l 
        JOIN members m ON l.member_id = m.member_id 
        LEFT JOIN admins a ON l.approved_by = a.admin_id
        WHERE $where 
        ORDER BY FIELD(l.status, 'approved', 'pending', 'disbursed', 'rejected'), l.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$loans = $stmt->get_result();

// Financial Stats
$stats_query = "SELECT 
    COUNT(CASE WHEN status='pending' THEN 1 END) as pending_count,
    SUM(CASE WHEN status='pending' THEN amount ELSE 0 END) as pending_val,
    COUNT(CASE WHEN status='approved' THEN 1 END) as approved_count,
    SUM(CASE WHEN status='approved' THEN amount ELSE 0 END) as approved_val,
    COUNT(CASE WHEN status='disbursed' THEN 1 END) as active_count,
    SUM(CASE WHEN status='disbursed' THEN current_balance ELSE 0 END) as active_portfolio
    FROM loans";
$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [
    'pending_count' => 0, 'pending_val' => 0, 'approved_count' => 0, 'approved_val' => 0, 'active_count' => 0, 'active_portfolio' => 0
];

// --- 5. Export Handler ---
if (isset($_GET['export']) && in_array($_GET['export'], ['pdf', 'excel'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $export_data = [];
    $total_val = 0;
    while ($row_ex = $loans->fetch_assoc()) {
        $total_val += (float)$row_ex['amount'];
        $export_data[] = [
            'ID' => $row_ex['loan_id'],
            'Member' => $row_ex['full_name'],
            'ID No' => $row_ex['national_id'],
            'Amount' => number_format((float)$row_ex['amount'], 2),
            'Status' => ucfirst($row_ex['status']),
            'Applied' => date('d-M-Y', strtotime($row_ex['created_at']))
        ];
    }
    
    UniversalExportEngine::handle($_GET['export'], $export_data, [
        'title' => 'Loan Management Report',
        'module' => 'Loan Portfolio',
        'headers' => ['ID', 'Member', 'ID No', 'Amount', 'Status', 'Applied'],
        'total_value' => $total_val
    ]);
    exit;
}

$pageTitle = "Loan Management";
?>
<?php $layout->header($pageTitle); ?>
<body>
<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content">
        <?php $layout->topbar($pageTitle); ?>
        
        <div class="portal-header mt-4">
            <h2 class="fw-bold mb-2">Loan Operations Center</h2>
            <p class="mb-0 opacity-75">Review, approve, and seamlessly disburse member loans.</p>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="icon-circle bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
                    <div class="text-muted small fw-bold text-uppercase">Pending Review</div>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format((int)$stats['pending_count']) ?></h3>
                    <div class="small text-muted mt-2 fw-medium">Value: KES <?= number_format((float)$stats['pending_val']) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="border-bottom: 4px solid #1d4ed8;">
                    <div class="icon-circle bg-primary bg-opacity-10 text-primary"><i class="bi bi-wallet2"></i></div>
                    <div class="text-muted small fw-bold text-uppercase">Awaiting Payout</div>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format((int)$stats['approved_count']) ?></h3>
                    <div class="small text-muted mt-2 fw-medium">Required: KES <?= number_format((float)$stats['approved_val']) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="icon-circle bg-success bg-opacity-10 text-success"><i class="bi bi-graph-up-arrow"></i></div>
                    <div class="text-muted small fw-bold text-uppercase">Active Portfolio</div>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format((int)$stats['active_count']) ?></h3>
                    <div class="small text-success mt-2 fw-bold">KES <?= number_format((float)$stats['active_portfolio']) ?> Out</div>
                </div>
            </div>
        </div>

        <div class="ledger-container">
            <div class="ledger-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <h5 class="fw-bold mb-0">Loan Ledger</h5>
                <div class="d-flex gap-2">
                    <form class="d-flex gap-2" method="GET">
                        <input type="text" name="search" class="form-control form-control-sm border-0 bg-light rounded-pill px-3" placeholder="Search members..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <select name="status" class="form-select form-select-sm border-0 bg-light rounded-pill px-3" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                            <option value="approved" <?= ($_GET['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Awaiting Payout</option>
                            <option value="disbursed" <?= ($_GET['status'] ?? '') === 'disbursed' ? 'selected' : '' ?>>Disbursed</option>
                        </select>
                    </form>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-light border rounded-pill px-3 dropdown-toggle fw-bold" data-bs-toggle="dropdown"><i class="bi bi-cloud-download me-1"></i> Export</button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                            <li><a class="dropdown-item py-2" href="?export=pdf"><i class="bi bi-file-pdf text-danger me-2"></i> PDF Report</a></li>
                            <li><a class="dropdown-item py-2" href="?export=excel"><i class="bi bi-file-excel text-success me-2"></i> Excel Sheet</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <?php flash_render(); ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Member Info</th>
                                <th>Loan Info</th>
                                <th>Amount</th>
                                <th>Guarantors</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $loans->data_seek(0);
                            if ($loans->num_rows > 0): ?>
                                <?php while ($row = $loans->fetch_assoc()): ?>
                                <tr onclick="openLoanDrawer(<?= htmlspecialchars(json_encode($row)) ?>)">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="member-avatar-sm me-3" style="width: 40px; height: 40px; border-radius: 12px; background: var(--forest); color: var(--lime); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem;">
                                                <?= substr($row['full_name'], 0, 1) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold "><?= htmlspecialchars($row['full_name']) ?></div>
                                                <div class="text-secondary small font-monospace">ID: <?= htmlspecialchars($row['national_id']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold "><?= ucfirst($row['loan_type']) ?></div>
                                        <div class="text-secondary small"><?= $row['duration_months'] ?> Mo @ <?= $row['interest_rate'] ?>%</div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-forest">KES <?= number_format((float)$row['amount'], 2) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?= $row['guarantor_count'] >= 2 ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning' ?> rounded-pill px-3 py-2">
                                            <i class="bi bi-people-fill me-1"></i> <?= $row['guarantor_count'] ?> / 2
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            $statusClass = match($row['status']) {
                                                'pending' => 'st-pending', 'approved' => 'st-approved',
                                                'disbursed' => 'st-disbursed', 'rejected' => 'st-rejected',
                                                default => 'badge bg-secondary'
                                            };
                                        ?>
                                        <span class="status-badge <?= $statusClass ?> shadow-sm">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td class="pe-4 text-end" onclick="event.stopPropagation()">
                                        <div class="d-flex justify-content-end gap-2">
                                            <?php if ($row['status'] === 'pending' && $can_approve): ?>
                                                <button onclick="confirmAction('approve', <?= $row['loan_id'] ?>)" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold">Approve</button>
                                                <button onclick="openRejectModal(<?= $row['loan_id'] ?>)" class="btn btn-sm btn-link text-danger text-decoration-none fw-bold">Reject</button>
                                            <?php elseif ($row['status'] === 'approved' && $can_disburse): ?>
                                                <button onclick="openDisburseModal(<?= $row['loan_id'] ?>, <?= $row['amount'] ?>)" class="btn btn-lime btn-sm px-4 rounded-pill fw-bold shadow-sm">
                                                    Disburse <i class="bi bi-send-fill ms-1"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-light rounded-pill btn-sm px-3 border-0 fw-bold text-muted" disabled>Locked</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="bi bi-inbox fs-1 text-muted opacity-50 mb-3 d-block"></i>
                                        <h6 class="text-muted fw-bold">No loans found.</h6>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php $layout->footer(); ?>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="loanDrawer">
    <div class="offcanvas-header bg-forest text-white">
        <h5 class="offcanvas-title fw-bold">Loan Overview</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="text-center mb-4 mt-3">
            <div class="mx-auto mb-3" style="width: 70px; height: 70px; font-size: 2rem; border-radius: 12px; background: var(--forest); color: var(--lime); display: flex; align-items: center; justify-content: center; font-weight: 800;" id="drawer_avatar"></div>
            <h4 class="fw-bold mb-0 " id="drawer_name">Member Name</h4>
            <span class="badge bg-light text-muted border rounded-pill mt-2 px-3 py-2" id="drawer_id">ID: ---</span>
        </div>

        <div class="card border-0 bg-light rounded-4 p-4 mb-4">
            <h6 class="text-uppercase small text-muted fw-bold mb-3 letter-spacing-1">Financial Snapshot</h6>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-secondary fw-medium">Request Amount</span>
                <span class="fw-bold  fs-5" id="drawer_amount">KES 0.00</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-secondary fw-medium">Interest Rate</span>
                <span class="badge bg-info text-white fw-bold px-3 py-2" id="drawer_rate">0%</span>
            </div>
            <div class="d-flex justify-content-between mt-3 pt-3 border-top">
                <span class="fw-bold ">Total Repayable</span>
                <span class="fw-bold text-success fs-4" id="drawer_total">KES 0.00</span>
            </div>
        </div>
        
        <div class="mt-4 px-2">
            <button class="btn btn-outline-dark w-100 rounded-pill fw-bold py-2" data-bs-dismiss="offcanvas">Close Drawer</button>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="loan_id" id="reject_loan_id">
                <div class="modal-header bg-danger text-white border-0 p-4">
                    <h5 class="modal-title fw-bold">Reject Application</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <label class="form-label fw-bold  mb-2">Reason for Rejection (Visible to Member)</label>
                    <textarea name="rejection_reason" class="form-control bg-white" rows="4" required placeholder="e.g. Insufficient Guarantor coverage..."></textarea>
                </div>
                <div class="modal-footer border-0 bg-light p-4 pt-0">
                    <button type="button" class="btn btn-link text-muted text-decoration-none fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-5 fw-bold shadow-sm">Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="disburseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="disburse">
                <input type="hidden" name="loan_id" id="disburse_loan_id">
                
                <div class="bg-forest text-white p-5 text-center position-relative">
                    <h1 class="fw-bold my-2 display-5 text-white">KES <span id="disburse_amount_val">0.00</span></h1>
                </div>

                <div class="modal-body p-4 bg-white">
                    <div class="mb-4">
                        <label class="form-label small text-uppercase fw-bold text-secondary mb-2">Disbursement Channel</label>
                        <select name="payment_method" id="payment_method" class="form-select form-select-lg fw-medium " required onchange="handleRefGeneration()">
                            <option value="bank">Bank Transfer (Internal)</option>
                            <option value="cash">Petty Cash (Internal)</option>
                            <option value="mpesa">M-Pesa (External)</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small text-uppercase fw-bold text-secondary mb-2 d-flex justify-content-between">
                            <span>Transaction Reference</span>
                            <span id="ref_badge" class="badge bg-success bg-opacity-10 text-success rounded-pill px-2">Auto-Generated</span>
                        </label>
                        <input type="text" name="ref_no" id="ref_no_input" class="form-control form-control-lg fw-bold font-monospace " required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 bg-white">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lime rounded-pill px-5 fw-bold shadow-sm flex-fill">Process Payout <i class="bi bi-check-circle-fill ms-2"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="actionForm" method="POST" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" id="form_action">
    <input type="hidden" name="loan_id" id="form_loan_id">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmAction(action, id) {
        if(confirm('Are you absolutely sure?')) {
            document.getElementById('form_action').value = action;
            document.getElementById('form_loan_id').value = id;
            document.getElementById('actionForm').submit();
        }
    }
    function openRejectModal(id) {
        document.getElementById('reject_loan_id').value = id;
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    }
    function openDisburseModal(id, amount) {
        document.getElementById('disburse_loan_id').value = id;
        document.getElementById('disburse_amount_val').innerText = new Intl.NumberFormat().format(amount);
        document.getElementById('ref_no_input').value = 'DSB-' + Date.now();
        new bootstrap.Modal(document.getElementById('disburseModal')).show();
    }
    function handleRefGeneration() {
        document.getElementById('ref_no_input').value = 'DSB-' + Date.now();
    }
    function openLoanDrawer(data) {
        document.getElementById('drawer_name').innerText = data.full_name;
        document.getElementById('drawer_avatar').innerText = data.full_name.charAt(0);
        document.getElementById('drawer_id').innerText = 'ID: ' + data.national_id;
        document.getElementById('drawer_amount').innerText = 'KES ' + new Intl.NumberFormat().format(data.amount);
        document.getElementById('drawer_rate').innerText = data.interest_rate + '%';
        let total = parseFloat(data.amount) + (parseFloat(data.amount) * (parseFloat(data.interest_rate)/100));
        document.getElementById('drawer_total').innerText = 'KES ' + new Intl.NumberFormat().format(total);
        new bootstrap.Offcanvas(document.getElementById('loanDrawer')).show();
    }
</script>
<?php $layout->footer(); ?>
</body>
</html>