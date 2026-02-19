<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');

// manager/loans.php
// Operations Manager - Loan Review Console

// 1. SESSION & CONFIG
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../inc/LoanHelper.php';
require_once __DIR__ . '/../../inc/SettingsHelper.php';

// 2. SECURITY & AUTH
require_admin();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');
require_permission();

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$admin_id   = $_SESSION['admin_id'];
$db = $conn;

// 3. HANDLE ACTIONS (Post-Redirect-Get)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Validate CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Invalid security token.");
    }

    $loan_id = intval($_POST['loan_id']);
    $action  = $_POST['action'];
    $notes   = trim($_POST['notes'] ?? '');

    if ($loan_id > 0) {
        $new_status = null;
        $log_action = '';
        $details    = '';
        $notify_msg = '';

        if ($action === 'approve') {
            $new_status = 'approved';
            $log_action = 'Loan Approval';
            $details    = "Approved Loan #$loan_id. Queued for disbursement.";
            $notify_msg = "Great news! Your loan #$loan_id has been APPROVED and is processing.";
            
            // CHECK GUARANTOR COUNT
            $min_guarantors = (int)SettingsHelper::get('min_guarantor_count', 2);
            $resG = $db->query("SELECT COUNT(*) FROM loan_guarantors WHERE loan_id = $loan_id");
            $guarantor_count = (int)($resG->fetch_row()[0] ?? 0);
            
            if ($guarantor_count < $min_guarantors) {
                flash_set("Approval Failed: This loan requires at least $min_guarantors guarantors (Current: $guarantor_count).", "danger");
                header("Location: loans.php");
                exit;
            }

            // CHECK SAVINGS LIMIT (3X RULE)
            $q_loan = $db->query("SELECT member_id, amount FROM loans WHERE loan_id = $loan_id");
            $loan_info = $q_loan->fetch_assoc();
            if ($loan_info) {
                $m_id = $loan_info['member_id'];
                $app_amount = $loan_info['amount'];
                
                require_once __DIR__ . '/../../inc/FinancialEngine.php';
                $engine = new FinancialEngine($db);
                $balances = $engine->getBalances($m_id);
                $curr_savings = $balances['savings'];
                $limit = $curr_savings * 3;

                // Threshold check (allow small floating point diff if any)
                if ($app_amount > ($limit + 0.01)) {
                    flash_set("Approval Failed: Member's 3x Savings Limit is KES " . number_format((float)$limit) . " (Savings: KES " . number_format((float)$curr_savings) . "). The application for KES " . number_format((float)$app_amount) . " exceeds this.", "danger");
                    header("Location: loans.php");
                    exit;
                }
            }

        } elseif ($action === 'reject') {
            $new_status = 'rejected';
            $log_action = 'Loan Rejection';
            $details    = "Rejected Loan #$loan_id. Reason: $notes";
            $notify_msg = "Update on loan #$loan_id: Application rejected. Reason: $notes";
        }

        if ($new_status) {
            $db->begin_transaction();
            try {
                // A. Update Loan Status
                $stmt = $db->prepare("UPDATE loans SET status = ?, approved_by = ?, approval_date = NOW() WHERE loan_id = ?");
                $stmt->bind_param("sii", $new_status, $admin_id, $loan_id);
                $stmt->execute();

                if ($stmt->affected_rows === 0) {
                    throw new Exception("Loan not found or already processed.");
                }

                // E. Handle Guarantors
                if ($action === 'approve') {
                    // Lock them in
                    $db->query("UPDATE loan_guarantors SET status = 'approved' WHERE loan_id = $loan_id");
                } elseif ($action === 'reject') {
                    // Release them
                    $db->query("UPDATE loan_guarantors SET status = 'rejected' WHERE loan_id = $loan_id");
                }

                // B. Fetch Member and Loan details for Notification
                $res_data = $db->query("SELECT member_id, amount, reference_no FROM loans WHERE loan_id = $loan_id");
                if ($res_data && $res_data->num_rows > 0) {
                    $l_data = $res_data->fetch_assoc();
                    $member_id = (int)$l_data['member_id'];
                    $amount = (float)$l_data['amount'];
                    $ref = $l_data['reference_no'];
                    
                    // Unified Notification
                    require_once __DIR__ . '/../../inc/notification_helpers.php';
                    if ($action === 'approve') {
                        send_notification($db, $member_id, 'loan_approved', ['amount' => $amount, 'ref' => $ref]);
                    } else {
                        send_notification($db, $member_id, 'loan_rejected', ['amount' => $amount, 'rejection_reason' => $notes, 'ref' => $ref]);
                    }
                }

                // D. Audit Log
                $ip = $_SERVER['REMOTE_ADDR'];
                $stmt_log = $db->prepare("INSERT INTO audit_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt_log->bind_param("isss", $admin_id, $log_action, $details, $ip);
                $stmt_log->execute();

                $db->commit();
                
                flash_set("Loan #$loan_id has been " . strtoupper($new_status), $new_status === 'approved' ? 'success' : 'warning');

            } catch (Exception $e) {
                $db->rollback();
                flash_set("Error processing loan: " . $e->getMessage(), 'error');
            }
        }
    }
    
    // Redirect
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
    exit;
}

// 3. FETCH DATA FILTERS (Moved up for Export Logic)
// Filter Logic
$filter = $_GET['status'] ?? 'pending';
$search = trim($_GET['q'] ?? '');

$where_clauses = [];
$params = [];
$types = "";

if ($filter !== 'all') {
    $where_clauses[] = "l.status = ?";
    $params[] = $filter;
    $types .= "s";
}

if ($search) {
    $where_clauses[] = "(m.full_name LIKE ? OR m.national_id LIKE ? OR l.loan_id LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term;
    $types .= "sss";
}

// 3b. HANDLE EXPORT ACTIONS
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    
    // Re-fetch data for export
    $where_sql_export = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";
    $sql_export = "SELECT l.*, m.full_name FROM loans l JOIN members m ON l.member_id = m.member_id $where_sql_export ORDER BY l.created_at DESC";
    $stmt_e = $db->prepare($sql_export);
    if (!empty($params)) $stmt_e->bind_param($types, ...$params);
    $stmt_e->execute();
    $export_loans = $stmt_e->get_result()->fetch_all(MYSQLI_ASSOC);

    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $data = [];
    foreach ($export_loans as $l) {
        $data[] = [
            'Load ID' => $l['loan_id'],
            'Applicant' => $l['full_name'],
            'Amount' => number_format((float)$l['amount'], 2),
            'Type' => $l['loan_type'],
            'Duration' => $l['duration_months'] . ' Months',
            'Status' => ucfirst($l['status']),
            'Date' => date('d-M-Y', strtotime($l['created_at']))
        ];
    }

    UniversalExportEngine::handle($format, $data, [
        'title' => 'Loan Portfolio Report',
        'module' => 'Loan Management',
        'headers' => ['Load ID', 'Applicant', 'Amount', 'Type', 'Duration', 'Status', 'Date'],
        'total_value' => array_sum(array_column($export_loans, 'amount')),
        'currency' => 'KES'
    ]);
    exit;
}

// 4. FETCH DATA (Display)
// (Flash handling now handled by flash_render() below)

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$sql = "SELECT l.*, m.full_name, m.national_id, m.phone, m.profile_pic 
        FROM loans l 
        JOIN members m ON l.member_id = m.member_id 
        $where_sql 
        ORDER BY l.created_at DESC";

$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$loans = $stmt->get_result();

// Stats
$stats = $db->query("SELECT 
    COUNT(CASE WHEN status='pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status='approved' THEN 1 END) as approved,
    COUNT(CASE WHEN status='disbursed' OR status='active' THEN 1 END) as active
    FROM loans")->fetch_assoc();

function ksh($v, $d = 2) { return number_format((float)($v ?? 0), $d); }
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <title>Loan Management - Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-app: #f0fdf4; /* Very light green background */
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: 1px solid rgba(16, 185, 129, 0.2); /* Emerald border */
            --text-main: #064e3b; /* Dark Emerald */
            --primary-green: #10b981;
            --dark-green: #047857;
        }
        
        body { background-color: var(--bg-app); color: var(--text-main); font-family: 'Inter', sans-serif; }
        
        .main-content-wrapper { margin-left: 260px; min-height: 100vh; padding: 20px; transition: margin-left 0.3s; }
        @media (max-width: 991.98px) { .main-content-wrapper { margin-left: 0; } }
        
        /* Glass Components */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: var(--glass-border);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.1);
        }

        /* Avatar */
        .avatar-circle {
            width: 42px; height: 42px; object-fit: cover;
            border: 2px solid #a7f3d0;
        }

        /* Custom Badges */
        .badge-status { padding: 6px 12px; font-weight: 500; border-radius: 6px; letter-spacing: 0.3px; }
        .st-pending { background: #fff7ed; color: #9a3412; border: 1px solid #ffedd5; }
        .st-approved { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .st-disbursed { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .st-rejected { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* Filter Buttons */
        .btn-filter { border: 1px solid transparent; color: #065f46; background: rgba(255,255,255,0.5); font-weight: 500; }
        .btn-filter.active { background-color: var(--primary-green); color: white; border-color: var(--primary-green); }
        .btn-filter:hover:not(.active) { background-color: #d1fae5; }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>

<div class="d-flex">
        <?php $layout->sidebar(); ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php $layout->topbar($pageTitle ?? ''); ?>
            
            <div class="container-fluid">
            
                <div>
                    <h4 class="fw-bold mb-1" style="color: var(--dark-green);">Loan Portfolio</h4>
                    <p class="text-muted small mb-0">Review applications and monitor disbursements.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank" class="btn btn-outline-success btn-sm rounded-pill fw-bold">
                        <i class="bi bi-printer me-1"></i> Print
                    </a>
                    <div class="btn-group">
                        <button type="button" class="btn btn-success btn-sm rounded-pill px-3 fw-bold dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg">
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>Export PDF</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-earmark-spreadsheet text-success me-2"></i>Export Excel</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php flash_render(); ?>

            <div class="glass-card p-3 mb-4">
                <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
                    <div class="btn-group shadow-sm rounded-pill overflow-hidden" role="group">
                        <a href="?status=pending" class="btn btn-sm btn-filter <?= $filter=='pending'?'active':'' ?>">
                            Pending <span class="badge bg-white  ms-1 rounded-pill"><?= $stats['pending'] ?></span>
                        </a>
                        <a href="?status=approved" class="btn btn-sm btn-filter <?= $filter=='approved'?'active':'' ?>">
                            Approved <span class="badge bg-white  ms-1 rounded-pill"><?= $stats['approved'] ?></span>
                        </a>
                        <a href="?status=disbursed" class="btn btn-sm btn-filter <?= $filter=='disbursed'?'active':'' ?>">
                            Active <span class="badge bg-white  ms-1 rounded-pill"><?= $stats['active'] ?></span>
                        </a>
                        <a href="?status=all" class="btn btn-sm btn-filter <?= $filter=='all'?'active':'' ?>">All History</a>
                    </div>
                    
                    <form class="d-flex" method="GET">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-success"><i class="bi bi-search"></i></span>
                            <input type="text" name="q" class="form-control border-start-0 ps-0 text-success" placeholder="Search loans..." value="<?= htmlspecialchars($search) ?>" style="min-width: 250px;">
                        </div>
                    </form>
                </div>
            </div>

            <div class="glass-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small text-uppercase" style="color: var(--dark-green);">
                            <tr>
                                <th class="ps-4 py-3">Applicant</th>
                                <th>Loan Request</th>
                                <th>Terms</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0">
                            <?php if($loans->num_rows === 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-folder2-open display-4 opacity-25"></i>
                                        <p class="mt-2 mb-0">No loan records found.</p>
                                    </td>
                                </tr>
                            <?php else: while($l = $loans->fetch_assoc()): 
                                $statusClass = match($l['status']) { 
                                    'pending'=>'st-pending', 
                                    'approved'=>'st-approved', 
                                    'disbursed'=>'st-disbursed', 
                                    'active'=>'st-disbursed',
                                    'rejected'=>'st-rejected', 
                                    default=>'bg-light text-dark' 
                                };
                                $img = !empty($l['profile_pic']) ? 'data:image/jpeg;base64,' . base64_encode($l['profile_pic']) : BASE_URL . '/public/assets/images/default_user.png';
                            ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= $img ?>" class="rounded-circle avatar-circle shadow-sm">
                                        <div>
                                            <div class="fw-bold "><?= htmlspecialchars($l['full_name']) ?></div>
                                            <div class="small text-muted font-monospace"><?= htmlspecialchars($l['national_id']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-success fs-6">KES <?= ksh($l['amount']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($l['loan_type']) ?></div>
                                </td>
                                <td>
                                    <span class="small  fw-bold"><?= $l['duration_months'] ?> Months</span>
                                    <div class="small text-muted"><?= $l['interest_rate'] ?>% Interest</div>
                                </td>
                                <td><span class="badge-status <?= $statusClass ?>"><?= ucfirst($l['status']) ?></span></td>
                                <td class="text-end pe-4">
                                    <?php if($l['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success px-3 rounded-pill fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#approveModal<?= $l['loan_id'] ?>">
                                            Review
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light border text-muted px-3 rounded-pill" disabled>
                                            <i class="bi bi-lock-fill me-1"></i> Closed
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php $layout->footer(); ?>
            </div>

        </div>
        
    </div>
    
</div>

<?php 
if($loans->num_rows > 0): 
    $loans->data_seek(0); 
    while($l = $loans->fetch_assoc()):
        if($l['status'] !== 'pending') continue;
?>

<div class="modal fade" id="approveModal<?= $l['loan_id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 bg-success bg-opacity-10">
                <h5 class="modal-title fw-bold text-success">Review Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="avatar-circle mx-auto mb-2 d-flex align-items-center justify-content-center bg-success text-white fs-4 rounded-circle" style="width:60px; height:60px;">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <h3 class="fw-bold text-success mb-0">KES <?= ksh($l['amount']) ?></h3>
                    <p class="text-muted small">Requested by <?= htmlspecialchars($l['full_name']) ?></p>
                </div>

                <div class="row g-2 mb-4">
                    <div class="col-6">
                        <div class="p-2 border rounded text-center bg-light">
                            <small class="text-muted d-block">Duration</small>
                            <span class="fw-bold "><?= $l['duration_months'] ?> Months</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded text-center bg-light">
                            <small class="text-muted d-block">Type</small>
                            <span class="fw-bold "><?= $l['loan_type'] ?></span>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-uppercase text-secondary mb-2">Guarantors</label>
                    <div class="border rounded-3 p-3 bg-light">
                        <?php 
                        $resG = $db->query("SELECT lg.*, m.full_name FROM loan_guarantors lg JOIN members m ON lg.member_id = m.member_id WHERE lg.loan_id = " . (int)$l['loan_id']);
                        if($resG && $resG->num_rows > 0): while($g = $resG->fetch_assoc()): ?>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-bold "><?= htmlspecialchars($g['full_name']) ?></span>
                                <span class="badge bg-white text-success border small ksh-font">KES <?= ksh($g['amount_locked']) ?></span>
                            </div>
                        <?php endwhile; else: ?>
                            <div class="text-center text-muted small py-2">No guarantors assigned</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger w-50 py-2 rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $l['loan_id'] ?>" data-bs-dismiss="modal">
                        Reject
                    </button>
                    
                    <form method="POST" class="w-50">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="loan_id" value="<?= $l['loan_id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-success w-100 py-2 rounded-pill fw-bold shadow-sm">
                            Approve <i class="bi bi-check-lg ms-1"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal<?= $l['loan_id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-danger bg-opacity-10">
                <h5 class="modal-title fw-bold text-danger">Reject Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted mb-3">Please provide a reason for rejecting the loan for <strong>KES <?= ksh($l['amount']) ?></strong>.</p>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="loan_id" value="<?= $l['loan_id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    
                    <div class="form-floating mb-4">
                        <textarea name="notes" class="form-control" placeholder="Reason" style="height: 100px" required></textarea>
                        <label>Reason for Rejection *</label>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light w-50 py-2 rounded-pill" data-bs-toggle="modal" data-bs-target="#approveModal<?= $l['loan_id'] ?>" data-bs-dismiss="modal">Back</button>
                        <button type="submit" class="btn btn-danger w-50 py-2 rounded-pill fw-bold shadow-sm">Confirm Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php endwhile; endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>






