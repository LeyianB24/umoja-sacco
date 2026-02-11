<?php
/**
 * admin/loans_payouts.php
 * Enhanced Loan Management Console
 * Theme: Hope UI (Glassmorphism) + High Performance UX
 */

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

// DEBUG: Add debug info at the very top
error_log("=== LOANS PAGE DEBUG ===");
error_log("Session ID: " . session_id());
error_log("Admin ID: " . ($_SESSION['admin_id'] ?? 'NOT SET'));
error_log("Role ID: " . ($_SESSION['role_id'] ?? 'NOT SET'));
error_log("Request URI: " . $_SERVER['REQUEST_URI']);

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
error_log("Before Auth::requireAdmin()");
Auth::requireAdmin(); 
error_log("After Auth::requireAdmin() - passed auth check");

// --- 2. Permission Logic (The Workflow Engine) ---
// We determine exactly what this specific user can do
$permissions = $_SESSION['permissions'] ?? [];
$is_super = ($_SESSION['role_id'] ?? 0) == 1;

$can_approve  = $is_super || in_array('approve_loans', $permissions);
$can_disburse = $is_super || in_array('disburse_loans', $permissions);
$can_reject   = $can_approve; // Usually approvers can also reject

$admin_id = $_SESSION['admin_id'];

// --- 3. Handle Actions (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF check (implement strict token in production)
    $action = $_POST['action'] ?? '';
    $loan_id = intval($_POST['loan_id'] ?? 0);
    
    if ($loan_id > 0) {
        $conn->begin_transaction();
        try {
            if ($action === 'approve' && $can_approve) {
                $stmt = $conn->prepare("UPDATE loans SET status='approved', approved_by=?, approval_date=NOW() WHERE loan_id=?");
                $stmt->bind_param("ii", $admin_id, $loan_id);
                $stmt->execute();
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
                flash_set("Loan #$loan_id Rejected.", "warning");
            }
            elseif ($action === 'disburse' && $can_disburse) {
                // Fetch fresh data
                $stmt = $conn->prepare("SELECT amount, member_id, total_payable FROM loans WHERE loan_id=?");
                $stmt->bind_param("i", $loan_id);
                $stmt->execute();
                $l_chk = $stmt->get_result()->fetch_assoc();
                
                $amount = (float)$l_chk['amount'];
                $total = (float)$l_chk['total_payable'];
                $ref = $_POST['ref_no'];
                $method = $_POST['payment_method'];

                // Update Loan
                $stmt = $conn->prepare("UPDATE loans SET status='disbursed', disbursed_date=NOW(), disbursed_amount=?, current_balance=? WHERE loan_id=?");
                $stmt->bind_param("ddi", $amount, $total, $loan_id);
                $stmt->execute();
                
                // Activate Guarantors
                $stmt = $conn->prepare("UPDATE loan_guarantors SET status='active' WHERE loan_id=?");
                $stmt->bind_param("i", $loan_id);
                $stmt->execute();
                
                // Record Ledger
                TransactionHelper::record([
                    'member_id' => $l_chk['member_id'], 'amount' => $amount, 'type' => 'loan_disbursement',
                    'method' => $method, 'ref_no' => $ref, 'related_id' => $loan_id, 'related_table' => 'loans'
                ]);
                flash_set("Funds Disbursed. Ledger Updated.", "success");
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

// Filter Logic
if (!empty($_GET['status'])) {
    $where .= " AND l.status = ?";
    $params[] = $_GET['status']; $types .= "s";
}
if (!empty($_GET['search'])) {
    $s = "%" . $_GET['search'] . "%";
    $where .= " AND (m.full_name LIKE ? OR m.national_id LIKE ? OR l.loan_id = ?)";
    $params[] = $s; $params[] = $s; $params[] = $_GET['search']; $types .= "ssi";
}

// Main Query with Guarantor Counts
$sql = "SELECT l.*, m.full_name, m.national_id, m.phone,
        (SELECT COUNT(*) FROM loan_guarantors lg WHERE lg.loan_id = l.loan_id) as guarantor_count,
        a.full_name as approver_name
        FROM loans l 
        JOIN members m ON l.member_id = m.member_id 
        LEFT JOIN admins a ON l.approved_by = a.admin_id
        WHERE $where 
        ORDER BY l.created_at DESC";

// Execute Query Safe
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$loans = $stmt->get_result();

// Debug: Check if we have results
error_log("Loans query result count: " . $loans->num_rows);
error_log("SQL: " . $sql);
if (!empty($params)) {
    error_log("Params: " . print_r($params, true));
}

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection error: " . $conn->connect_error);
} else {
    error_log("Database connection: OK");
}

// Check if loans table exists
$table_check = $conn->query("SHOW TABLES LIKE 'loans'");
if ($table_check->num_rows == 0) {
    error_log("Loans table does not exist!");
} else {
    error_log("Loans table exists");
}

// Check if members table exists
$member_check = $conn->query("SHOW TABLES LIKE 'members'");
if ($member_check->num_rows == 0) {
    error_log("Members table does not exist!");
} else {
    error_log("Members table exists");
}

// Financial Stats
$stats_query = "SELECT 
    COUNT(CASE WHEN status='pending' THEN 1 END) as pending_count,
    SUM(CASE WHEN status='pending' THEN amount ELSE 0 END) as pending_val,
    COUNT(CASE WHEN status='approved' THEN 1 END) as approved_count,
    SUM(CASE WHEN status='approved' THEN amount ELSE 0 END) as approved_val,
    COUNT(CASE WHEN status='completed' THEN 1 END) as completed_count,
    SUM(CASE WHEN status='disbursed' THEN current_balance ELSE 0 END) as active_portfolio,
    COUNT(CASE WHEN status='rejected' THEN 1 END) as rejected_count
    FROM loans";
$stats_result = $conn->query($stats_query);
if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
} else {
    // Set default values if query fails
    $stats = [
        'pending_count' => 0,
        'pending_val' => 0,
        'approved_count' => 0,
        'approved_val' => 0,
        'active_portfolio' => 0
    ];
    error_log("Stats query failed: " . $conn->error);
}

// Debug: Check if loans table exists and has data
$table_check = $conn->query("SHOW TABLES LIKE 'loans'");
if ($table_check->num_rows == 0) {
    error_log("Loans table does not exist!");
} else {
    $total_loans = $conn->query("SELECT COUNT(*) as count FROM loans")->fetch_assoc()['count'];
    error_log("Total loans in database: " . $total_loans);
}

// --- 5. Export Handler ---
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    // Re-run query without limit for export
    $export_sql = "SELECT l.*, m.full_name, m.national_id, m.phone FROM loans l 
                   JOIN members m ON l.member_id = m.member_id 
                   WHERE $where 
                   ORDER BY l.created_at DESC";
    $stmt_e = $conn->prepare($export_sql);
    if (!empty($params)) $stmt_e->bind_param($types, ...$params);
    $stmt_e->execute();
    $export_result = $stmt_e->get_result();
    
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $data = [];
    $total_val = 0;
    while ($row = $export_result->fetch_assoc()) {
        $total_val += (float)$row['amount'];
        $data[] = [
            'ID' => $row['loan_id'],
            'Member' => $row['full_name'],
            'ID No' => $row['national_id'],
            'Amount' => number_format((float)$row['amount'], 2),
            'Status' => ucfirst($row['status']),
            'Applied' => date('d-M-Y', strtotime($row['created_at']))
        ];
    }
    
    UniversalExportEngine::handle($format, $data, [
        'title' => 'Loan Management Report',
        'module' => 'Loan Portfolio',
        'headers' => ['ID', 'Member', 'ID No', 'Amount', 'Status', 'Applied'],
        'total_value' => $total_val
    ]);
    exit;
}

$pageTitle = "Loan Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> | USMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css?v=<?= time() ?>">
    <style>
        /* =============================
           HOPE UI PREMIUM THEME
           Forest & Lime Edition
        ============================= */
        :root {
            --forest-deep: #0f2e25;
            --forest-light: #1a4d3d;
            --lime-vibrant: #d0f35d;
            --lime-dark: #a8cf12;
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.2);
            --glass-shadow: 0 8px 32px 0 rgba(15, 46, 37, 0.1);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f0f2f5;
            color: var(--forest-deep);
        }

        /* --- Animations --- */
        @keyframes slideInUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        .fade-in { animation: fadeIn 0.6s ease-out forwards; }
        .slide-up { animation: slideInUp 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; }

        /* --- Stat Cards --- */
        .stat-card-enhanced {
            background: white;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 20px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        }

        .stat-card-enhanced:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(15, 46, 37, 0.08);
            border-color: var(--lime-vibrant);
        }

        .stat-icon-box {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .stat-card-enhanced:hover .stat-icon-box {
            transform: scale(1.1) rotate(5deg);
        }

        .stat-icon-box.warning { background: #fff8e1; color: #f59e0b; }
        .stat-icon-box.info { background: #e0f2fe; color: #0ea5e9; }
        .stat-icon-box.success { background: #dcfce7; color: #10b981; }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--forest-deep);
            letter-spacing: -0.5px;
        }

        /* --- Search & Filter --- */
        .search-enhanced {
            background: white;
            padding: 1rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            margin-bottom: 2rem;
        }

        .search-input-enhanced {
            border: 2px solid #f3f4f6;
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            font-size: 0.95rem;
            background: #f8fafc;
            transition: all 0.2s;
        }

        .search-input-enhanced:focus {
            background: white;
            border-color: var(--forest-deep);
            box-shadow: 0 0 0 4px rgba(15, 46, 37, 0.1);
        }

        /* --- Table --- */
        .table-enhanced {
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.04);
            overflow: hidden;
        }

        .table-custom thead {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        .table-custom th {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #64748b;
            padding: 1.2rem 1rem;
            border: none;
        }

        .table-custom td {
            vertical-align: middle;
            padding: 1.2rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 0.95rem;
        }

        .table-custom tr:last-child td { border-bottom: none; }
        
        .table-custom tbody tr { transition: all 0.2s; }
        .table-custom tbody tr:hover { background-color: #f8fafc; }

        /* --- Avatars --- */
        .user-avatar-enhanced {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--forest-deep), var(--forest-light));
            color: var(--lime-vibrant);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 3px 10px rgba(15, 46, 37, 0.2);
        }

        /* --- Badges --- */
        .status-badge-enhanced {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-badge-enhanced.status-pending { background: #fff7ed; color: #ea580c; border: 1px solid #ffedd5; }
        .status-badge-enhanced.status-approved { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
        .status-badge-enhanced.status-disbursed { background: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }
        .status-badge-enhanced.status-rejected { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }

        /* --- Buttons --- */
        .btn-lime {
            background: var(--lime-vibrant);
            color: var(--forest-deep);
            font-weight: 700;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            transition: all 0.2s;
        }

        .btn-lime:hover {
            background: var(--lime-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(208, 243, 93, 0.4);
        }

        .action-btn-enhanced {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }

        .action-btn-enhanced:hover { transform: scale(1.1); }
        .btn-view-enhanced { background: #e2e8f0; color: var(--forest-deep); }
        .btn-approve-enhanced { background: #dcfce7; color: #16a34a; }
        .btn-reject-enhanced { background: #fee2e2; color: #dc2626; }
        
        /* --- Offcanvas --- */
        .offcanvas { border-radius: 20px 0 0 20px; border: none; }
        .offcanvas-header { background: var(--forest-deep); color: white; }
        .offcanvas-body { background: #f8fafc; }
        
        .detail-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        /* --- Responsive --- */
        @media (max-width: 768px) {
            .table-enhanced { overflow-x: auto; }
            .stat-number { font-size: 1.5rem; }
        }

        @media print {
            .sidebar, .topbar, .btn, .no-print, .search-enhanced { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .stat-card-enhanced { border: 1px solid #ddd; box-shadow: none; }
            body { background: white; }
        }
    </style>
</head>

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content" style="padding: 40px; min-height: 100vh;">
    <?php $layout->topbar($pageTitle); ?>
    <?php flash_render(); ?>
    
    <div class="d-flex justify-content-between align-items-end mb-4 fade-in">
        <div>
            <h6 class="text-uppercase text-secondary fw-bold letter-spacing-1 mb-1">Financial Operations</h6>
            <h2 class="fw-bold text-forest">Loan Management</h2>
        </div>
        <div class="dropdown">
            <button class="btn btn-outline-forest dropdown-toggle rounded-pill shadow-sm" data-bs-toggle="dropdown">
                <i class="bi bi-download me-2"></i>Export
            </button>
            <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Export PDF</a></li>
                <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Export Excel</a></li>
                <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer text-primary me-2"></i>Print Report</a></li>
            </ul>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card-enhanced fade-in">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase small fw-bold mb-1">Awaiting Approval</p>
                        <div class="stat-number"><?= number_format((int)$stats['pending_count']) ?></div>
                        <p class="text-muted small mb-0">Applications</p>
                    </div>
                    <div class="stat-icon-box warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-top border-light">
                    <small class="text-muted">Total Value:</small>
                    <div class="fw-bold text-forest">KES <?= number_format((float)$stats['pending_val']) ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card-enhanced fade-in" style="animation-delay: 0.1s">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase small fw-bold mb-1">Ready for Disbursement</p>
                        <div class="stat-number"><?= number_format((int)$stats['approved_count']) ?></div>
                        <p class="text-muted small mb-0">Files</p>
                    </div>
                    <div class="stat-icon-box info">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-top border-light">
                    <small class="text-muted">Payout Required:</small>
                    <div class="fw-bold text-forest">KES <?= number_format((float)$stats['approved_val']) ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card-enhanced fade-in" style="animation-delay: 0.2s">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase small fw-bold mb-1">Active Portfolio</p>
                        <div class="stat-number">KES <?= number_format((float)$stats['active_portfolio']) ?></div>
                        <p class="text-muted small mb-0">Outstanding</p>
                    </div>
                    <div class="stat-icon-box success">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-top border-light">
                    <small class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Healthy</small>
                </div>
            </div>
        </div>
    </div>

    <div class="search-enhanced fade-in" style="animation-delay: 0.3s">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control search-input-enhanced border-start-0" placeholder="Search by Name, ID, or Loan Ref..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= ($_GET['status'] ?? '') == 'pending' ? 'selected' : '' ?>>Pending Review</option>
                    <option value="approved" <?= ($_GET['status'] ?? '') == 'approved' ? 'selected' : '' ?>>Pending Disbursement</option>
                    <option value="completed" <?= ($_GET['status'] ?? '') == 'completed' ? 'selected' : '' ?>>Completed / Active</option>
                    <option value="rejected" <?= ($_GET['status'] ?? '') == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-3 text-end">
                <button type="submit" class="btn btn-primary rounded-pill">Refresh</button>
            </div>
        </form>
    </div>

    <div class="table-enhanced fade-in" style="animation-delay: 0.4s">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Applicant</th>
                        <th>Loan Info</th>
                        <th>Guarantors</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($loans->num_rows == 0): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="opacity-50">
                                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                    <h6 class="fw-bold">No loans found</h6>
                                    <p class="small">Try adjusting your filters.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: 
                    // Reset pointer to beginning
                    $loans->data_seek(0);
                    $loan_count = 0;
                    while($row = $loans->fetch_assoc()): 
                        $loan_count++;
                        $pct = ($row['amount'] > 0) ? round(($row['amount'] - $row['current_balance']) / $row['amount'] * 100) : 0;
                    ?>
                    <tr class="clickable-row animate-slide-up" style="animation-delay: <?= $loan_count * 0.1 ?>s;">
                        <td class="ps-4" onclick="openLoanDrawer(<?= htmlspecialchars(json_encode($row)) ?>)">
                            <div class="d-flex align-items-center">
                                <div class="user-avatar-enhanced me-3">
                                    <?= substr($row['full_name'], 0, 1) ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?= $row['full_name'] ?></div>
                                    <div class="small text-muted font-monospace">#<?= $row['loan_id'] ?></div>
                                </div>
                            </div>
                        </td>

                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-medium"><?= ucfirst($row['loan_type'] ?? 'N/A') ?></span>
                                <span class="small text-muted"><?= $row['duration_months'] ?? 0 ?> Months @ <?= $row['interest_rate'] ?? 0 ?>%</span>
                            </div>
                        </td>

                        <td>
                            <?php if($row['guarantor_count'] > 0): ?>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-people-fill me-1"></i> <?= $row['guarantor_count'] ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger">None</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="fw-bold">KES <?= number_format((float)$row['amount']) ?></div>
                            <?php if($row['status'] == 'disbursed' || $row['status'] == 'completed'): ?>
                                <div class="progress mt-1" style="height: 4px; width: 80px;" title="<?= $pct ?>% Repaid">
                                    <div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php 
                                $statusClass = match($row['status']) {
                                    'pending' => 'status-pending',
                                    'approved' => 'status-approved',
                                    'disbursed' => 'status-disbursed',
                                    'completed' => 'status-disbursed',
                                    'rejected' => 'status-rejected',
                                    default => 'status-pending'
                                };
                                $statusIcon = match($row['status']) {
                                    'pending' => 'bi-hourglass-split',
                                    'approved' => 'bi-check-circle',
                                    'disbursed' => 'bi-cash-coin',
                                    'completed' => 'bi-check-circle-fill',
                                    'rejected' => 'bi-x-circle',
                                    default => 'bi-circle'
                                };
                            ?>
                            <span class="status-badge-enhanced <?= $statusClass ?>">
                                <i class="bi <?= $statusIcon ?>"></i> <?= ucfirst($row['status']) ?>
                            </span>
                        </td>

                        <td class="text-end pe-4">
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="action-btn-enhanced btn-view-enhanced" onclick="openLoanDrawer(<?= htmlspecialchars(json_encode($row)) ?>)" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </button>

                                <?php if($row['status'] == 'pending' && $can_approve): ?>
                                    <button class="action-btn-enhanced btn-approve-enhanced" onclick="confirmAction('approve', <?= $row['loan_id'] ?>)" title="Approve">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button class="action-btn-enhanced btn-reject-enhanced" onclick="openRejectModal(<?= $row['loan_id'] ?>)" title="Reject">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                <?php endif; ?>

                                <?php if($row['status'] == 'approved' && $can_disburse): ?>
                                    <button class="btn-disburse-enhanced" onclick="openDisburseModal(<?= $row['loan_id'] ?>, <?= $row['amount'] ?>)">
                                        <i class="bi bi-cash-coin me-2"></i> Disburse
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="loanDrawer">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold">Loan Application Details</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="text-center mb-4">
                <div id="drawer_avatar" class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center mx-auto mb-2 fs-3" style="width:70px; height:70px; box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);">
                </div>
                <h5 class="fw-bold mb-0 text-dark" id="drawer_name">Member Name</h5>
                <span class="badge bg-light text-muted border rounded-pill mt-2" id="drawer_id">ID: ---</span>
            </div>

            <div class="detail-card slide-up" style="animation-delay: 0.1s">
                <h6 class="text-uppercase small text-muted fw-bold mb-3 letter-spacing-1">Financial Snapshot</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Request Amount</span>
                    <span class="fw-bold text-dark" id="drawer_amount">KES 0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Interest Rate</span>
                    <span class="badge bg-blue-light text-primary fw-bold" id="drawer_rate">0%</span>
                </div>
                <div class="d-flex justify-content-between border-top pt-3 mt-2">
                    <span class="fw-bold text-dark">Total Repayable</span>
                    <span class="fw-bold text-primary fs-5" id="drawer_total">KES 0.00</span>
                </div>
            </div>

            <div class="detail-card slide-up" style="animation-delay: 0.2s">
                <h6 class="text-uppercase small text-muted fw-bold mb-3 letter-spacing-1">Contact Information</h6>
                <div class="d-flex align-items-center mb-3">
                    <div class="stat-icon-box info me-3" style="width:40px; height:40px; font-size: 1rem;">
                        <i class="bi bi-phone"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Phone Number</small>
                        <span class="fw-bold text-dark" id="drawer_phone">...</span>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <div class="stat-icon-box warning me-3" style="width:40px; height:40px; font-size: 1rem;">
                        <i class="bi bi-person-vcard"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">National ID</small>
                        <span class="fw-bold text-dark" id="drawer_nid">...</span>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning small border-0 bg-orange-light text-orange-dark d-flex align-items-center mt-4">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>Guarantor verification is mandatory before final approval.</div>
            </div>
        </div>
        <div class="p-3 bg-light border-top">
            <button class="btn btn-outline-dark w-100 rounded-pill fw-bold" data-bs-dismiss="offcanvas">Close Drawer</button>
        </div>
    </div>

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="loan_id" id="reject_loan_id">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Application</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <label class="form-label fw-bold">Reason for Rejection</label>
                    <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="e.g. Insufficient Guarantors"></textarea>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="disburseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="disburse">
                <input type="hidden" name="loan_id" id="disburse_loan_id">
                <div class="modal-body p-0">
                    <div class="bg-forest text-white p-4 text-center rounded-top">
                        <small class="text-uppercase opacity-75">Disbursing Amount</small>
                        <h2 class="fw-bold my-2">KES <span id="disburse_amount">0.00</span></h2>
                    </div>
                    <div class="p-4">
                        <div class="mb-3">
                            <label class="form-label small text-uppercase fw-bold text-secondary">Source Account</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="mpesa">M-Pesa Business</option>
                                <option value="bank">Equity Bank</option>
                                <option value="cash">Petty Cash</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-uppercase fw-bold text-secondary">Transaction Ref</label>
                            <input type="text" name="ref_no" class="form-control" required placeholder="e.g. QFH34...">
                        </div>
                        <button type="submit" class="btn btn-accent w-100 rounded-pill py-2 fw-bold">
                            Process Payment <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
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
<script>
    // Action Confirmation
    function confirmAction(action, id) {
        if(confirm('Are you sure you want to ' + action + ' this loan?')) {
            document.getElementById('form_action').value = action;
            document.getElementById('form_loan_id').value = id;
            document.getElementById('actionForm').submit();
        }
    }

    // Modal Triggers
    function openRejectModal(id) {
        document.getElementById('reject_loan_id').value = id;
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    }

    function openDisburseModal(id, amount) {
        document.getElementById('disburse_loan_id').value = id;
        document.getElementById('disburse_amount').innerText = new Intl.NumberFormat().format(amount);
        new bootstrap.Modal(document.getElementById('disburseModal')).show();
    }

    // The Drawer Logic (Enhanced UX)
    function openLoanDrawer(data) {
        document.getElementById('drawer_name').innerText = data.full_name;
        document.getElementById('drawer_avatar').innerText = data.full_name.charAt(0);
        document.getElementById('drawer_id').innerText = data.national_id;
        document.getElementById('drawer_amount').innerText = 'KES ' + new Intl.NumberFormat().format(data.amount);
        document.getElementById('drawer_rate').innerText = data.interest_rate + '%';
        document.getElementById('drawer_phone').innerText = data.phone || 'N/A';
        document.getElementById('drawer_nid').innerText = data.national_id;
        
        // Calculate Total
        let total = parseFloat(data.amount) + (parseFloat(data.amount) * (parseFloat(data.interest_rate)/100));
        document.getElementById('drawer_total').innerText = 'KES ' + new Intl.NumberFormat().format(total);

        new bootstrap.Offcanvas(document.getElementById('loanDrawer')).show();
    }
</script>
    <?php $layout->footer(); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>