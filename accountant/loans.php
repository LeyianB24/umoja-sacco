<?php
// accountant/loans.php
// Accountant Loan Processing Console - Enhanced "Hope" Theme

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../inc/functions.php';

// 1. Auth Check (Keep existing logic)
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['accountant', 'manager', 'superadmin'])) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$role = $_SESSION['role'];

// 2. Handle Actions (Keep existing logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';
    $loan_id = intval($_POST['loan_id']);
    
    if ($loan_id > 0) {
        $conn->begin_transaction();
        try {
            $msg_type = ''; $notify_msg = ''; $log_details = ''; $log_action = '';

            // --- APPROVE LOGIC ---
            if ($action === 'approve') {
                if (!in_array($role, ['manager', 'superadmin'])) throw new Exception("Only Managers can approve loans.");
                
                $stmt = $conn->prepare("UPDATE loans SET status = 'approved', approved_by = ?, approval_date = NOW() WHERE loan_id = ?");
                $stmt->bind_param("ii", $admin_id, $loan_id);
                $stmt->execute();
                
                flash_set("Loan approved. Sent to Accountant for disbursement.", "success");
                $notify_msg = "Your loan #$loan_id has been approved by the Manager. Pending disbursement.";
                $log_action = 'loan_approve';
                $log_details = "Approved Loan #$loan_id";
            
            // --- REJECT LOGIC ---
            } elseif ($action === 'reject') {
                if (!in_array($role, ['manager', 'superadmin'])) throw new Exception("Only Managers can reject loans.");
                
                $reason = trim($_POST['rejection_reason']);
                $stmt = $conn->prepare("UPDATE loans SET status = 'rejected', notes = CONCAT(IFNULL(notes,''), ' [Rejected: ', ?, ']'), approved_by = ? WHERE loan_id = ?");
                $stmt->bind_param("sii", $reason, $admin_id, $loan_id);
                $stmt->execute();
                
                flash_set("Loan rejected.", "success");
                $notify_msg = "Your loan #$loan_id was rejected. Reason: $reason";
                $log_action = 'loan_reject';
                $log_details = "Rejected Loan #$loan_id. Reason: $reason";

            // --- DISBURSE LOGIC ---
            } elseif ($action === 'disburse') {
                if (!in_array($role, ['accountant', 'superadmin'])) throw new Exception("Only Accountants can disburse funds.");

                $q = $conn->query("SELECT * FROM loans WHERE loan_id = $loan_id");
                $loan = $q->fetch_assoc();

                if ($loan && $loan['status'] === 'approved') {
                    $amount = $loan['amount']; 
                    $ref_no = trim($_POST['ref_no']);
                    $member_id = $loan['member_id'];
                    $notes = "Disbursement Ref: $ref_no";

                    // Update Loan: Set status, disbursed info, AND initialize current_balance to total_payable
                    $stmt = $conn->prepare("UPDATE loans SET status = 'disbursed', disbursed_date = NOW(), disbursed_amount = ?, current_balance = total_payable WHERE loan_id = ?");
                    $stmt->bind_param("di", $amount, $loan_id);
                    $stmt->execute();

                    // Credit Member Account (Funds sit in wallet until withdrawn)
                    $stmt = $conn->prepare("UPDATE members SET account_balance = account_balance + ? WHERE member_id = ?");
                    $stmt->bind_param("di", $amount, $member_id);
                    $stmt->execute();

                    // Record Transaction (Internal Transfer)
                    $stmt = $conn->prepare("INSERT INTO transactions (member_id, transaction_type, amount, reference_no, notes, created_at) VALUES (?, 'loan_disbursement', ?, ?, ?, NOW())");
                    $stmt->bind_param("idss", $member_id, $amount, $ref_no, $notes);
                    $stmt->execute();

                    flash_set("Funds disbursed successfully!", "success");
                    $notify_msg = "Your loan of KES " . number_format($amount) . " has been disbursed. Ref: $ref_no";
                    $log_action = 'loan_disburse';
                    $log_details = "Disbursed KES $amount for Loan #$loan_id";
                } else {
                    throw new Exception("Loan must be Approved by Manager first.");
                }
            }

            // --- NOTIFICATIONS & AUDIT ---
            if (!empty($notify_msg)) {
                if (!isset($member_id)) {
                    $m_res = $conn->query("SELECT member_id FROM loans WHERE loan_id = $loan_id");
                    $member_id = $m_res->fetch_assoc()['member_id'];
                }
                $chk = $conn->query("SHOW TABLES LIKE 'notifications'");
                if($chk->num_rows > 0) {
                    $stmt = $conn->prepare("INSERT INTO notifications (user_type, user_id, message, is_read, created_at) VALUES ('member', ?, ?, 0, NOW())");
                    $stmt->bind_param("is", $member_id, $notify_msg);
                    $stmt->execute();
                }
            }

            // Audit Logic (simplified)
            $chk_audit = $conn->query("SHOW TABLES LIKE 'audit_logs'");
            if($chk_audit->num_rows > 0 && !empty($log_action)) {
                $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                $ip = $_SERVER['REMOTE_ADDR'];
                $stmt->bind_param("isss", $admin_id, $log_action, $log_details, $ip);
                $stmt->execute();
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            flash_set("Error: " . $e->getMessage(), "error");
        }
    }
    header("Location: loans.php");
    exit;
}

// 3. Fetch Data
$where = "1";
$params = [];
$types = "";

if (!empty($_GET['status'])) {
    $where .= " AND l.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

if (!empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where .= " AND (m.full_name LIKE ? OR m.national_id LIKE ?)";
    $params[] = $search; $params[] = $search;
    $types .= "ss";
}

$sql = "SELECT l.*, m.full_name, m.national_id, m.profile_pic, a.full_name as approver_name
        FROM loans l 
        JOIN members m ON l.member_id = m.member_id 
        LEFT JOIN admins a ON l.approved_by = a.admin_id
        WHERE $where 
        ORDER BY FIELD(l.status, 'approved', 'pending', 'disbursed', 'rejected'), l.created_at DESC";

$stmt = $conn->prepare($sql);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$loans = $stmt->get_result();

// Stats for Cards
$stats = $conn->query("SELECT 
    COUNT(CASE WHEN status='pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status='approved' THEN 1 END) as approved,
    COUNT(CASE WHEN status='disbursed' OR status='active' THEN 1 END) as active
    FROM loans")->fetch_assoc();

$pageTitle = "Loan Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom Assets -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css?v=<?= time() ?>">
</head>
<body class="accountant-body">

<div class="d-flex">
    <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

    <div class="flex-fill main-content-wrapper">
        <?php require_once __DIR__ . '/../inc/topbar.php'; ?>

        <div class="d-flex justify-content-between align-items-end mb-5">
            <div>
                <h2 class="mb-1">Loans Management</h2>
                <p class="text-secondary mb-0">Overview of applications and disbursements</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-light rounded-pill border px-3"><i class="bi bi-download me-2"></i>Export</button>
            </div>
        </div>

        <?php flash_render(); ?>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="hope-card card-forest h-100 p-4 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="mb-1 opacity-75">Pending Review</p>
                            <h2 class="display-5 fw-bold mb-0"><?= $stats['pending'] ?></h2>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="badge bg-white bg-opacity-10 text-white fw-normal rounded-pill px-3 py-2">
                            <i class="bi bi-arrow-right me-1"></i> Awaiting Action
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="hope-card card-lime h-100 p-4 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="mb-1 opacity-75 fw-bold">Ready to Pay</p>
                            <h2 class="display-5 fw-bold mb-0"><?= $stats['approved'] ?></h2>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="badge bg-black bg-opacity-10 text-dark fw-normal rounded-pill px-3 py-2">
                            Needs Disbursement
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="hope-card h-100 p-4 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="mb-1 text-secondary">Active Loans</p>
                            <h2 class="display-5 fw-bold mb-0 text-dark"><?= $stats['active'] ?></h2>
                        </div>
                        <div class="stat-icon bg-light text-dark">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-success" style="width: 70%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4 align-items-center">
            <div class="col-md-8">
                <form method="GET" class="d-flex gap-3">
                    <div class="flex-grow-1 position-relative">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                        <input type="text" name="search" class="form-control search-input" placeholder="Search members by name or ID..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    <select name="status" class="form-select search-input w-auto" style="border-radius: 50px;">
                        <option value="">All Status</option>
                        <option value="pending" <?= ($_GET['status'] ?? '') == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= ($_GET['status'] ?? '') == 'approved' ? 'selected' : '' ?>>Approved</option>
                    </select>
                    <button type="submit" class="btn btn-filter d-flex align-items-center gap-2">
                        Filter
                    </button>
                </form>
            </div>
        </div>

        <div class="hope-card p-0">
            <div class="table-responsive p-4">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Applicant</th>
                            <th>Loan Details</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Approver</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($loans->num_rows == 0): ?>
                            <tr><td colspan="6" class="text-center py-5 text-secondary">No applications found.</td></tr>
                        <?php else: while($row = $loans->fetch_assoc()): 
                            $badgeClass = match($row['status']) { 
                                'pending'=>'bg-pending-soft', 
                                'approved'=>'bg-approved-soft', 
                                'disbursed'=>'bg-disbursed-soft', 
                                'rejected'=>'bg-rejected-soft', 
                                default=>'bg-secondary' 
                            };
                            $img = !empty($row['profile_pic']) ? 'data:image/jpeg;base64,'.base64_encode($row['profile_pic']) : BASE_URL.'/public/assets/images/default_user.png';
                        ?>
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= $img ?>" class="avatar">
                                    <div>
                                        <div class="fw-bold text-dark"><?= esc($row['full_name']) ?></div>
                                        <div class="small text-secondary"><?= $row['national_id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-medium text-dark"><?= $row['duration_months'] ?> Months</div>
                                <div class="small text-muted"><?= $row['interest_rate'] ?>% Interest</div>
                            </td>
                            <td>
                                <span class="fw-bold text-dark fs-6">KES <?= number_format($row['amount']) ?></span>
                            </td>
                            <td>
                                <span class="badge badge-pill <?= $badgeClass ?>"><?= ucfirst($row['status']) ?></span>
                            </td>
                            <td class="small text-secondary">
                                <?= $row['approver_name'] ?? '<span class="opacity-25">--</span>' ?>
                            </td>
                            <td class="text-end pe-3">
                                <?php if($row['status'] == 'pending' && in_array($role, ['manager', 'superadmin'])): ?>
                                    <button class="btn-action approve me-1" onclick="confirmAction('approve', <?= $row['loan_id'] ?>)" title="Approve">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button class="btn-action reject" onclick="openRejectModal(<?= $row['loan_id'] ?>)" title="Reject">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                <?php elseif($row['status'] == 'approved' && in_array($role, ['accountant', 'superadmin'])): ?>
                                    <button class="btn btn-primary-custom" onclick="openDisburseModal(<?= $row['loan_id'] ?>, <?= $row['amount'] ?>)">
                                        Disburse <i class="bi bi-arrow-right ms-1"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted opacity-25"><i class="bi bi-three-dots"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
         <?php require_once __DIR__ . '/../inc/footer.php'; ?>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="loan_id" id="reject_loan_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Reject Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary mb-2">Please provide a reason for the client.</p>
                    <textarea name="rejection_reason" class="form-control" rows="3" required style="border-radius: 12px; background: #f8f9fa; border: none; padding: 1rem;"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">Reject Loan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="disburseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="disburse">
                <input type="hidden" name="loan_id" id="disburse_loan_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Disburse Funds</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="p-4 rounded-4 mb-4 text-center" style="background-color: var(--forest-dark); color: white;">
                        <small class="text-uppercase opacity-75 letter-spacing-2">Amount to Transfer</small>
                        <h1 class="fw-bold mb-0 mt-2 text-white">KES <span id="disburse_amount"></span></h1>
                    </div>
                    <label class="form-label fw-bold small text-secondary text-uppercase">Transaction Reference</label>
                    <input type="text" name="ref_no" class="form-control form-control-lg" required placeholder="e.g. MPESA Ref" style="border-radius: 12px; font-size: 1rem;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom px-5">Confirm Disbursement</button>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/public/assets/js/main.js?v=<?= time() ?>"></script>