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
$sql = "SELECT l.*, m.full_name, m.national_id, m.profile_pic, m.phone,
        (SELECT COUNT(*) FROM loan_guarantors lg WHERE lg.loan_id = l.loan_id) as guarantor_count,
        a.full_name as approver_name
        FROM loans l 
        JOIN members m ON l.member_id = m.member_id 
        LEFT JOIN admins a ON l.approved_by = a.admin_id
        WHERE $where 
        ORDER BY l.created_at DESC";

// Execute query directly for debugging
$loans = $conn->query($sql);

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
    SUM(CASE WHEN status='completed' THEN current_balance ELSE 0 END) as active_portfolio,
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

// --- 5. Export CSV Handler ---
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="loans_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, ['Loan ID', 'Member Name', 'National ID', 'Phone', 'Amount', 'Status', 'Applied Date', 'Approval Date']);
    
    // Re-run query without pagination for export
    $export_sql = "SELECT l.*, m.full_name, m.national_id, m.phone FROM loans l 
                   JOIN members m ON l.member_id = m.member_id 
                   ORDER BY l.created_at DESC";
    $export_result = $conn->query($export_sql);
    
    while ($row = $export_result->fetch_assoc()) {
        fputcsv($output, [
            $row['loan_id'],
            $row['full_name'],
            $row['national_id'],
            $row['phone'],
            $row['amount'],
            $row['status'],
            $row['created_at'],
            $row['approval_date'] ?? 'N/A'
        ]);
    }
    
    fclose($output);
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
           LOANS PAGE - HOPE UI ENHANCEMENTS
           Using existing design system
        ============================= */
        
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
        }
        
        .main-content-wrapper {
            background: var(--bg-body);
        }
        
        /* =============================
           ENHANCED STAT CARDS
        ============================= */
        .stat-card-enhanced {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--glass-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card-enhanced:hover {
            transform: translateY(-4px);
            box-shadow: var(--glass-shadow-hover);
        }
        
        .stat-card-enhanced::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(208, 243, 93, 0.1), transparent);
            transition: left 0.6s;
        }
        
        .stat-card-enhanced:hover::before {
            left: 100%;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--forest-deep);
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        
        .stat-icon-box {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: var(--lime-vibrant);
            color: var(--forest-deep);
            box-shadow: 0 4px 12px rgba(208, 243, 93, 0.3);
        }
        
        .stat-icon-box.warning {
            background: #f59e0b;
            color: white;
        }
        
        .stat-icon-box.info {
            background: #3b82f6;
            color: white;
        }
        
        .stat-icon-box.success {
            background: #10b981;
            color: white;
        }
        
        /* =============================
           ENHANCED SEARCH CONTAINER
        ============================= */
        .search-enhanced {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--glass-shadow);
            margin-bottom: 1.5rem;
        }
        
        .search-input-enhanced {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        
        .search-input-enhanced:focus {
            border-color: var(--forest-deep);
            box-shadow: 0 0 0 3px rgba(15, 46, 37, 0.1);
        }
        
        /* =============================
           ENHANCED TABLE
        ============================= */
        .table-enhanced {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--glass-shadow);
        }
        
        .table-enhanced thead {
            background: var(--forest-deep);
            color: white;
        }
        
        .table-enhanced thead th {
            padding: 1rem 1.25rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            border: none;
        }
        
        .table-enhanced tbody td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        
        .table-enhanced tbody tr:last-child td {
            border-bottom: none;
        }
        
        .table-enhanced tbody tr:hover {
            background-color: #f8fafc;
        }
        
        /* =============================
           ENHANCED USER AVATARS
        ============================= */
        .user-avatar-enhanced {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            background: var(--forest-deep);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 2px 8px rgba(15, 46, 37, 0.2);
            transition: all 0.2s ease;
        }
        
        .user-avatar-enhanced:hover {
            transform: scale(1.05);
        }
        
        /* =============================
           ENHANCED STATUS BADGES
        ============================= */
        .status-badge-enhanced {
            padding: 0.5rem 0.875rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: all 0.2s ease;
        }
        
        .status-badge-enhanced:hover {
            transform: scale(1.02);
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-approved {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-disbursed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* =============================
           ENHANCED ACTION BUTTONS
        ============================= */
        .action-btn-enhanced {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            margin: 0 0.125rem;
        }
        
        .action-btn-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-view-enhanced {
            background: var(--forest-deep);
            color: white;
        }
        
        .btn-approve-enhanced {
            background: #10b981;
            color: white;
        }
        
        .btn-reject-enhanced {
            background: #ef4444;
            color: white;
        }
        
        .btn-disburse-enhanced {
            background: var(--lime-vibrant);
            color: var(--forest-deep);
            padding: 0.5rem 1rem;
            width: auto;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        /* =============================
           ANIMATIONS
        ============================= */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }
            100% {
                background-position: 1000px 0;
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        .animate-slide-up {
            animation: slideInUp 0.6s ease-out;
        }
        
        .animate-slide-left {
            animation: slideInLeft 0.6s ease-out;
        }
        
        .animate-fade-scale {
            animation: fadeInScale 0.5s ease-out;
        }
        
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        
        /* Enhanced stat cards */
        .stat-card-enhanced {
            position: relative;
            overflow: hidden;
        }
        
        .stat-card-enhanced::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shimmer 3s infinite;
        }
        
        .stat-card-enhanced:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(15, 46, 37, 0.15);
        }
        
        .stat-icon-box {
            transition: all 0.3s ease;
        }
        
        .stat-card-enhanced:hover .stat-icon-box {
            transform: scale(1.1) rotate(5deg);
        }
        
        .stat-number {
            background: linear-gradient(135deg, var(--forest-deep), var(--hope-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
        }
        
        /* Enhanced table */
        .table-enhanced {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        
        .table-custom thead th {
            background: linear-gradient(135deg, var(--forest-deep), var(--hope-green));
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
            border: none;
            padding: 1rem;
        }
        
        .table-custom tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .table-custom tbody tr:hover {
            background: rgba(15, 46, 37, 0.03);
            transform: scale(1.01);
            box-shadow: 0 5px 20px rgba(15, 46, 37, 0.1);
        }
        
        .clickable-row {
            cursor: pointer;
        }
        
        .clickable-row:hover td {
            color: var(--forest-deep);
        }
        
        /* Enhanced search */
        .search-enhanced {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .search-enhanced:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .search-input-enhanced {
            border-radius: 12px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .search-input-enhanced:focus {
            border-color: var(--hope-green);
            box-shadow: 0 0 0 0.2rem rgba(15, 46, 37, 0.1);
        }
        
        /* Enhanced buttons */
        .btn-outline-primary:hover {
            background: var(--forest-deep);
            border-color: var(--forest-deep);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(15, 46, 37, 0.3);
        }
        
        .btn-accent:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(15, 46, 37, 0.3);
        }
        
        /* Enhanced status badges */
        .status-badge-enhanced {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .status-badge-enhanced::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .status-badge-enhanced:hover::before {
            left: 100%;
        }
        
        /* Enhanced user avatars */
        .user-avatar-enhanced {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--forest-deep), var(--hope-green));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .user-avatar-enhanced::after {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(135deg, var(--forest-deep), var(--hope-green));
            border-radius: 50%;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .clickable-row:hover .user-avatar-enhanced::after {
            opacity: 0.3;
        }
        
        .clickable-row:hover .user-avatar-enhanced {
            transform: scale(1.1);
        }
        
        /* =============================
           RESPONSIVE DESIGN
        ============================= */
        @media (max-width: 768px) {
            .stat-number {
                font-size: 2rem;
            }
            
            .table-enhanced thead th,
            .table-enhanced tbody td {
                padding: 0.75rem 0.5rem;
                font-size: 0.875rem;
            }
        }
    </style>
</head>

<?php $layout->sidebar(); ?>

<div class="main-content" style="margin-left: 280px; padding: 40px; min-height: 100vh;">
    <?php $layout->topbar($pageTitle); ?>
    <?php flash_render(); ?>
    
    <div class="d-flex justify-content-between align-items-end mb-4 fade-in">
        <div>
            <h6 class="text-uppercase text-secondary fw-bold letter-spacing-1 mb-1">Financial Operations</h6>
            <h2 class="fw-bold text-forest">Loan Management</h2>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary rounded-pill" onclick="window.print()">
                <i class="bi bi-printer me-2"></i> Print List
            </button>
            <a href="?action=export_csv" class="btn btn-accent rounded-pill">
                <i class="bi bi-cloud-download me-2"></i> Export CSV
            </a>
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
                    while($row = $loans->fetch_assoc()): 
                        $pct = ($row['amount'] > 0) ? round(($row['amount'] - $row['current_balance']) / $row['amount'] * 100) : 0;
                    ?>
                    <tr class="clickable-row">
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
                                <span class="fw-medium"><?= ucfirst($row['loan_type']) ?></span>
                                <span class="small text-muted"><?= $row['duration_months'] ?> Months @ <?= $row['interest_rate'] ?>%</span>
                            </div>
                        </td>

                        <td>
                            <?php if($row['guarantor_count'] > 0): ?>
                                <span class="badge bg-light text-dark border"><i class="bi bi-people-fill me-1"></i> <?= $row['guarantor_count'] ?></span>
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
        </div>
        </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="loanDrawer" style="width: 450px;">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Loan Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="text-center mb-4">
            <div id="drawer_avatar" class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center mx-auto mb-2 fs-3" style="width:60px; height:60px;">
                </div>
            <h5 class="fw-bold mb-0" id="drawer_name">Member Name</h5>
            <span class="badge bg-secondary rounded-pill" id="drawer_id">ID: ---</span>
        </div>

        <div class="detail-card">
            <h6 class="text-uppercase small text-muted fw-bold mb-3">Financials</h6>
            <div class="d-flex justify-content-between mb-2">
                <span>Request Amount:</span>
                <span class="fw-bold" id="drawer_amount">KES 0.00</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>Interest Rate:</span>
                <span id="drawer_rate">0%</span>
            </div>
            <div class="d-flex justify-content-between border-top pt-2 mt-2">
                <span>Total Repayable:</span>
                <span class="fw-bold text-primary" id="drawer_total">KES 0.00</span>
            </div>
        </div>

        <div class="detail-card">
            <h6 class="text-uppercase small text-muted fw-bold mb-2">Contact Info</h6>
            <p class="mb-1"><i class="bi bi-phone me-2"></i> <span id="drawer_phone">...</span></p>
            <p class="mb-0"><i class="bi bi-card-heading me-2"></i> <span id="drawer_nid">...</span></p>
        </div>

        <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1"></i> Guarantor data verification required before approval.
        </div>
    </div>
    <div class="p-3 bg-white border-top">
        <button class="btn btn-outline-dark w-100 rounded-pill" data-bs-dismiss="offcanvas">Close</button>
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
</body>
</html>