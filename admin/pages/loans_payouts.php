<?php
/**
 * admin/loans_payouts.php
 * Enhanced Loan Management Console
 * Theme: Hope UI (Glassmorphism) + High Performance UX
 */

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

// --- 1. Dependencies & Security ---
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/ExportHelper.php';
require_once __DIR__ . '/../../inc/TransactionHelper.php';
require_once __DIR__ . '/../../inc/SupportTicketWidget.php';

use USMS\Services\UniversalExportEngine;

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
            if ($action === 'send_reminder' && $loan_id > 0) {
                require_once __DIR__ . '/../../core/Services/CronService.php';
                $cron = new \USMS\Services\CronService();
                if ($cron->sendManualLateReminder($loan_id)) {
                    flash_set("Manual late payment reminder queued for Loan #$loan_id.", "success");
                } else {
                    flash_set("Failed to queue reminder. Member might not have an email address.", "warning");
                }
            }
            elseif ($action === 'send_bulk_reminders') {
                require_once __DIR__ . '/../../core/Services/CronService.php';
                require_once __DIR__ . '/../../core/Services/EmailQueueService.php';
                
                $cron = new \USMS\Services\CronService();
                $count = $cron->sendBulkLateReminders();
                
                if ($count > 0) {
                    // Trigger immediate processing for this batch
                    set_time_limit(300); // 5 minutes max
                    $emailService = new \USMS\Services\EmailQueueService();
                    $res = $emailService->processPendingEmails($count);
                    flash_set("Successfully sent {$res['sent']} late payment reminders immediately.", "success");
                } else {
                    flash_set("No overdue loans found to remind.", "info");
                }
            }
            elseif ($action === 'disburse' && $can_disburse) {
                $fallback_ref = "DSB-" . date('Ymd') . "-" . rand(1000, 9999);
                $ref    = !empty($_POST['ref_no']) ? $_POST['ref_no'] : $fallback_ref;
                $method = $_POST['payment_method'] ?? 'cash';

                require_once __DIR__ . '/../../inc/FinancialEngine.php';
                $engine = new FinancialEngine($conn);
                
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

                $conn->query("UPDATE loans SET current_balance = amount, status='disbursed', disbursed_date=NOW() WHERE loan_id=$loan_id");

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

// --- 4. Fetch Data ---
$where = "1";
$params = [];
$types = "";

if (!empty($_GET['status'])) {
    if ($_GET['status'] === 'overdue') {
        $where .= " AND l.status = 'disbursed' AND DATE(l.next_repayment_date) < CURDATE()";
    } else {
        $where .= " AND l.status = ?";
        $params[] = $_GET['status']; $types .= "s";
    }
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
        ORDER BY FIELD(l.status, 'approved', 'disbursed', 'pending', 'rejected'), l.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$loans = $stmt->get_result();

$stats_query = "SELECT 
    COUNT(CASE WHEN status='pending' THEN 1 END) as pending_count,
    SUM(CASE WHEN status='pending' THEN amount ELSE 0 END) as pending_val,
    COUNT(CASE WHEN status='approved' THEN 1 END) as approved_count,
    SUM(CASE WHEN status='approved' THEN amount ELSE 0 END) as approved_val,
    COUNT(CASE WHEN status='disbursed' THEN 1 END) as active_count,
    SUM(CASE WHEN status='disbursed' THEN current_balance ELSE 0 END) as active_portfolio,
    COUNT(CASE WHEN status='disbursed' AND DATE(next_repayment_date) < CURDATE() THEN 1 END) as overdue_count,
    SUM(CASE WHEN status='disbursed' AND DATE(next_repayment_date) < CURDATE() THEN current_balance ELSE 0 END) as overdue_val
    FROM loans";
$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [
    'pending_count' => 0, 'pending_val' => 0, 'approved_count' => 0, 'approved_val' => 0, 'active_count' => 0, 'active_portfolio' => 0, 'overdue_count' => 0, 'overdue_val' => 0
];

// --- 5. Export Handler ---
if (isset($_GET['export']) && in_array($_GET['export'], ['pdf', 'excel'])) {
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
    
    require_once __DIR__ . '/../../inc/ExportHelper.php';
    $title = 'Loan_Management_Report_' . date('Ymd_His');
    $headers = ['ID', 'Member', 'ID No', 'Amount', 'Status', 'Applied'];

    if ($_GET['export'] === 'pdf') {
        ExportHelper::pdf('Loan Management Report', $headers, $export_data, $title . '.pdf');
    } elseif ($_GET['export'] === 'excel') {
        ExportHelper::csv($title . '.csv', $headers, $export_data);
    }
    exit;
}

$pageTitle = "Loan Management";
?>
<?php $layout->header($pageTitle ?? 'Disbursement Console'); ?>

<!-- Jakarta Sans Font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jakarta+Sans:ital,wght@0,200..800;1,200..800&family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">

<style>
/* ============================================================
   JAKARTA SANS + GLASSMORPHISM ENHANCED THEME
   ============================================================ */

*, *::before, *::after { box-sizing: border-box; }

:root {
    --forest:        #0d2b1f;
    --forest-mid:    #1a3d2b;
    --forest-light:  #234d36;
    --lime:          #b5f43c;
    --lime-soft:     #d6fb8a;
    --lime-glow:     rgba(181,244,60,0.18);
    --lime-glow-sm:  rgba(181,244,60,0.08);
    --glass-bg:      rgba(255,255,255,0.07);
    --glass-border:  rgba(255,255,255,0.13);
    --glass-hover:   rgba(255,255,255,0.12);
    --surface:       rgba(255,255,255,0.95);
    --text-primary:  #0d1f15;
    --text-muted:    #6b7c74;
    --radius-sm:     8px;
    --radius-md:     14px;
    --radius-lg:     20px;
    --radius-xl:     28px;
    --shadow-sm:     0 2px 8px rgba(13,43,31,0.08);
    --shadow-md:     0 8px 28px rgba(13,43,31,0.12);
    --shadow-lg:     0 20px 60px rgba(13,43,31,0.18);
    --shadow-glow:   0 0 0 3px var(--lime-glow), 0 8px 30px rgba(181,244,60,0.15);
    --transition:    all 0.22s cubic-bezier(0.4,0,0.2,1);
}

/* Global Font Override */
body,
.main-content-wrapper,
.offcanvas,
.modal,
input, select, textarea, button, .btn, table, th, td,
h1, h2, h3, h4, h5, h6, p, span, div, label, a {
    font-family: 'Plus Jakarta Sans', 'Jakarta Sans', sans-serif !important;
}

/* ── Hero Banner ── */
.hp-hero {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 55%, #0f3320 100%);
    border-radius: var(--radius-xl);
    padding: 2.8rem 3rem 5rem;
    position: relative;
    overflow: hidden;
    color: #fff;
}
.hp-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(ellipse 60% 80% at 90% 10%, rgba(181,244,60,0.12) 0%, transparent 60%),
        radial-gradient(ellipse 40% 50% at 10% 90%, rgba(181,244,60,0.06) 0%, transparent 60%);
    pointer-events: none;
}
.hp-hero::after {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 340px; height: 340px;
    border-radius: 50%;
    border: 1px solid rgba(181,244,60,0.12);
    pointer-events: none;
}
.hp-hero .hero-ring2 {
    position: absolute;
    top: -120px; right: -120px;
    width: 500px; height: 500px;
    border-radius: 50%;
    border: 1px solid rgba(181,244,60,0.07);
    pointer-events: none;
}
.hp-hero h1 {
    font-weight: 800;
    letter-spacing: -0.03em;
    line-height: 1.15;
    font-size: 2.4rem !important;
}
.hp-hero .ops-label {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(181,244,60,0.12);
    border: 1px solid rgba(181,244,60,0.25);
    color: var(--lime-soft);
    border-radius: 100px;
    padding: 0.3rem 0.9rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    margin-bottom: 1rem;
}
.hp-hero .ops-label::before {
    content: '';
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--lime);
    animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot {
    0%,100% { opacity:1; transform:scale(1); }
    50% { opacity:0.5; transform:scale(1.4); }
}

/* Export Button */
.btn-lime {
    background: var(--lime);
    color: var(--forest) !important;
    border: none;
    font-weight: 700;
    transition: var(--transition);
    letter-spacing: -0.01em;
}
.btn-lime:hover {
    background: var(--lime-soft);
    box-shadow: var(--shadow-glow);
    transform: translateY(-1px);
}
.btn-outline-lime {
    border: 1.5px solid var(--lime);
    color: var(--lime) !important;
    background: transparent;
    font-weight: 700;
    transition: var(--transition);
}
.btn-outline-lime:hover {
    background: var(--lime-glow);
    border-color: var(--lime-soft);
}

/* ── KPI Cards ── */
.glass-stat {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.6rem 1.8rem;
    border: 1px solid rgba(13,43,31,0.07);
    box-shadow: var(--shadow-md);
    position: relative;
    overflow: hidden;
    transition: var(--transition);
}
.glass-stat::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    opacity: 0;
    transition: var(--transition);
}
.glass-stat:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
.glass-stat:hover::after { opacity: 1; }
.glass-stat:nth-child(1)::after { background: linear-gradient(90deg, #f59e0b, #fcd34d); }
.glass-stat:nth-child(2)::after { background: linear-gradient(90deg, #3b82f6, #93c5fd); }
.glass-stat:nth-child(3)::after { background: linear-gradient(90deg, var(--lime), var(--lime-soft)); }

.glass-stat-icon {
    width: 46px; height: 46px;
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    margin-bottom: 1.1rem;
    flex-shrink: 0;
}
.glass-stat-label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 0.3rem;
}
.glass-stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
    letter-spacing: -0.04em;
    margin-bottom: 0.4rem;
}
.glass-stat-trend {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
}

/* ── Main Table Card ── */
.glass-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    border: 1px solid rgba(13,43,31,0.07);
    box-shadow: var(--shadow-md);
    overflow: hidden;
}
.glass-card .card-header-zone {
    padding: 1.4rem 1.8rem;
    border-bottom: 1px solid rgba(13,43,31,0.06);
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    background: #fff;
}
.glass-card .card-header-zone h5 {
    font-weight: 800;
    font-size: 1rem;
    color: var(--text-primary);
    letter-spacing: -0.02em;
    margin: 0;
}

/* Search & Filter Bar */
.search-input-wrap {
    position: relative;
}
.search-input-wrap i {
    position: absolute;
    top: 50%; left: 14px;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 0.8rem;
    pointer-events: none;
}
.search-input-wrap input {
    padding-left: 2.4rem;
    border-radius: 100px;
    border: 1.5px solid rgba(13,43,31,0.1);
    background: #f8faf9;
    font-size: 0.85rem;
    font-weight: 500;
    height: 38px;
    color: var(--text-primary);
    transition: var(--transition);
    min-width: 220px;
}
.search-input-wrap input:focus {
    outline: none;
    border-color: var(--lime);
    background: #fff;
    box-shadow: var(--shadow-glow);
}
.filter-select {
    border-radius: 100px;
    border: 1.5px solid rgba(13,43,31,0.1);
    background: #f8faf9;
    font-size: 0.82rem;
    font-weight: 600;
    height: 38px;
    color: var(--text-primary);
    padding: 0 1.1rem;
    cursor: pointer;
    transition: var(--transition);
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b7c74' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    padding-right: 2.4rem;
}
.filter-select:focus {
    outline: none;
    border-color: var(--lime);
    box-shadow: var(--shadow-glow);
}

/* ── Table Styles ── */
.table-custom {
    margin: 0;
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}
.table-custom thead th {
    background: #f5f8f6;
    color: var(--text-muted);
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid rgba(13,43,31,0.07);
    white-space: nowrap;
}
.table-custom thead th:first-child { padding-left: 1.8rem; border-radius: var(--radius-sm) 0 0 0; }
.table-custom thead th:last-child  { padding-right: 1.8rem; border-radius: 0 var(--radius-sm) 0 0; }

.table-custom tbody tr {
    border-bottom: 1px solid rgba(13,43,31,0.04);
    transition: var(--transition);
    cursor: pointer;
}
.table-custom tbody tr:last-child { border-bottom: none; }
.table-custom tbody tr:hover {
    background: #f0faf4;
}
.table-custom tbody td {
    padding: 0.9rem 1rem;
    vertical-align: middle;
    font-size: 0.875rem;
    color: var(--text-primary);
}
.table-custom tbody td:first-child { padding-left: 1.8rem; }
.table-custom tbody td:last-child  { padding-right: 1.8rem; }

/* Member Cell */
.member-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.member-avatar {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: var(--forest);
    color: var(--lime);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    font-weight: 800;
    flex-shrink: 0;
    letter-spacing: -0.02em;
}
.member-name {
    font-weight: 700;
    color: var(--text-primary);
    font-size: 0.875rem;
    line-height: 1.3;
}
.member-id {
    font-size: 0.73rem;
    color: var(--text-muted);
    font-family: 'Courier New', monospace !important;
    font-weight: 600;
    margin-top: 1px;
}

/* Product cell */
.product-type {
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--text-primary);
}
.product-meta {
    font-size: 0.73rem;
    color: var(--text-muted);
    font-weight: 500;
    margin-top: 1px;
}

/* Amount */
.amount-value {
    font-weight: 800;
    font-size: 0.9rem;
    color: var(--forest);
    letter-spacing: -0.02em;
}

/* Guarantor badge */
.guarantor-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #f0f4f2;
    color: var(--text-muted);
    border: 1px solid rgba(13,43,31,0.08);
    border-radius: 100px;
    padding: 0.28rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 700;
}
.guarantor-badge.full { background: #e8faf0; color: #16a34a; border-color: rgba(22,163,74,0.15); }

/* Status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 100px;
    padding: 0.3rem 0.85rem;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    white-space: nowrap;
}
.status-badge::before {
    content: '';
    width: 5px; height: 5px;
    border-radius: 50%;
    flex-shrink: 0;
}
.status-pending  { background: #fffbeb; color: #b45309; border: 1px solid rgba(245,158,11,0.2); }
.status-pending::before  { background: #f59e0b; }
.status-approved { background: #eff6ff; color: #1d4ed8; border: 1px solid rgba(59,130,246,0.2); }
.status-approved::before { background: #3b82f6; }
.status-disbursed{ background: #f0fdf4; color: #166534; border: 1px solid rgba(22,163,74,0.2); }
.status-disbursed::before{ background: #22c55e; }
.status-rejected { background: #fef2f2; color: #b91c1c; border: 1px solid rgba(239,68,68,0.2); }
.status-rejected::before { background: #ef4444; }

/* Action buttons */
.action-zone {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
}
.btn-action-approve {
    padding: 0.35rem 1rem;
    border-radius: 100px;
    font-size: 0.75rem;
    font-weight: 700;
    border: 1.5px solid var(--lime);
    color: var(--forest);
    background: var(--lime-glow-sm);
    cursor: pointer;
    transition: var(--transition);
    white-space: nowrap;
}
.btn-action-approve:hover {
    background: var(--lime);
    box-shadow: 0 4px 14px rgba(181,244,60,0.3);
}
.btn-action-reject {
    padding: 0.35rem 0.8rem;
    border-radius: 100px;
    font-size: 0.75rem;
    font-weight: 700;
    border: none;
    color: #dc2626;
    background: transparent;
    cursor: pointer;
    transition: var(--transition);
}
.btn-action-reject:hover { background: #fef2f2; }
.btn-action-disburse {
    padding: 0.42rem 1.2rem;
    border-radius: 100px;
    font-size: 0.78rem;
    font-weight: 700;
    border: none;
    background: var(--lime);
    color: var(--forest);
    cursor: pointer;
    transition: var(--transition);
    display: flex; align-items: center; gap: 0.4rem;
    white-space: nowrap;
    box-shadow: 0 3px 12px rgba(181,244,60,0.25);
}
.btn-action-disburse:hover {
    background: var(--lime-soft);
    box-shadow: 0 6px 20px rgba(181,244,60,0.4);
    transform: translateY(-1px);
}
.btn-finalized {
    padding: 0.35rem 1rem;
    border-radius: 100px;
    font-size: 0.72rem;
    font-weight: 700;
    border: 1px solid rgba(13,43,31,0.08);
    color: var(--text-muted);
    background: #f8faf9;
    cursor: not-allowed;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4.5rem 2rem;
}
.empty-state-icon {
    width: 72px; height: 72px;
    border-radius: 18px;
    background: #f5f8f6;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem;
    color: #c4d4cb;
    margin: 0 auto 1.2rem;
}
.empty-state h5 {
    font-weight: 800;
    font-size: 1rem;
    color: var(--text-primary);
    margin-bottom: 0.4rem;
}
.empty-state p {
    font-size: 0.83rem;
    color: var(--text-muted);
    margin: 0;
}

/* ── Offcanvas Drawer ── */
#loanDrawer {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    background: #ffffff !important;
    backdrop-filter: none !important;
    width: 400px !important;
    border-left: 1px solid rgba(13,43,31,0.08) !important;
    box-shadow: -20px 0 60px rgba(13,43,31,0.12) !important;
}
.drawer-header {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%);
    padding: 1.6rem 1.8rem;
    position: relative;
    overflow: hidden;
}
.drawer-header::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 90% 20%, rgba(181,244,60,0.15), transparent 60%);
}
.drawer-header h5 {
    color: #fff;
    font-weight: 800;
    font-size: 1rem;
    margin: 0;
    position: relative;
}
.drawer-avatar-wrap {
    text-align: center;
    padding: 2rem 0 1.5rem;
}
.drawer-avatar {
    width: 76px; height: 76px;
    border-radius: 20px;
    background: var(--forest);
    color: var(--lime);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem;
    font-weight: 800;
    margin: 0 auto 1rem;
    box-shadow: 0 8px 24px rgba(13,43,31,0.2);
}
.drawer-name {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-primary);
    letter-spacing: -0.02em;
    margin-bottom: 0.4rem;
}
.drawer-id-badge {
    display: inline-block;
    background: #f0faf4;
    color: var(--forest);
    border: 1px solid rgba(13,43,31,0.1);
    border-radius: 100px;
    padding: 0.25rem 0.9rem;
    font-size: 0.75rem;
    font-weight: 700;
}
.drawer-section {
    background: #f8faf9;
    border-radius: var(--radius-md);
    padding: 1.2rem 1.4rem;
    margin: 0 1.4rem 1rem;
    border: 1px solid rgba(13,43,31,0.05);
}
.drawer-section-label {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--text-muted);
    margin-bottom: 1rem;
}
.drawer-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.55rem 0;
    border-bottom: 1px solid rgba(13,43,31,0.05);
}
.drawer-row:last-child { border-bottom: none; }
.drawer-row-label { font-size: 0.8rem; color: var(--text-muted); font-weight: 500; }
.drawer-row-value { font-size: 0.88rem; font-weight: 700; color: var(--text-primary); }
.drawer-total-box {
    background: var(--forest);
    border-radius: var(--radius-md);
    padding: 1.2rem 1.4rem;
    margin: 0 1.4rem 1.4rem;
    display: flex; justify-content: space-between; align-items: center;
}
.drawer-total-label { color: rgba(255,255,255,0.6); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; }
.drawer-total-value { color: var(--lime); font-size: 1.35rem; font-weight: 800; letter-spacing: -0.03em; }
.drawer-close-btn {
    margin: 0 1.4rem 1.4rem;
    display: flex;
    padding: 0.7rem;
    border-radius: var(--radius-md);
    border: 1.5px solid rgba(13,43,31,0.1);
    background: #fff;
    color: var(--text-primary);
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    align-items: center; justify-content: center; gap: 0.5rem;
    width: calc(100% - 2.8rem);
}
.drawer-close-btn:hover { background: #f0faf4; border-color: var(--lime); }

/* ── Modals ── */
.modal-content {
    border-radius: var(--radius-xl) !important;
    border: none !important;
    overflow: hidden;
}
.modal-reject-header {
    background: linear-gradient(135deg, #991b1b, #dc2626);
    padding: 1.4rem 1.8rem;
    color: #fff;
}
.modal-reject-header h5 {
    font-weight: 800;
    font-size: 1rem;
    margin: 0;
}
.modal-body-pad {
    padding: 1.6rem 1.8rem;
    background: #fff;
}
.modal-body-pad label {
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    margin-bottom: 0.6rem;
}
.modal-body-pad textarea {
    border-radius: var(--radius-md);
    border: 1.5px solid rgba(13,43,31,0.1);
    font-size: 0.875rem;
    font-weight: 500;
    padding: 0.85rem 1rem;
    resize: vertical;
    transition: var(--transition);
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    width: 100%;
}
.modal-body-pad textarea:focus {
    outline: none;
    border-color: #dc2626;
    box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
}
.modal-footer-pad {
    padding: 0 1.8rem 1.6rem;
    background: #fff;
    display: flex; gap: 0.75rem; justify-content: flex-end;
}

/* Disburse Modal */
.disburse-hero {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%);
    padding: 2.5rem 2rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.disburse-hero::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 80% 0%, rgba(181,244,60,0.2), transparent 60%);
}
.disburse-hero .amount-label {
    color: rgba(255,255,255,0.55);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    margin-bottom: 0.4rem;
    position: relative;
}
.disburse-hero .amount-display {
    font-size: 2.6rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.04em;
    line-height: 1;
    position: relative;
}
.disburse-hero .amount-display span {
    color: var(--lime);
}
.disburse-body {
    padding: 1.6rem 1.8rem;
    background: #fff;
}
.field-label-enhanced {
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-muted);
    margin-bottom: 0.55rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.ref-autobadge {
    background: #e8faf0;
    color: #16a34a;
    border-radius: 100px;
    padding: 0.15rem 0.6rem;
    font-size: 0.67rem;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: none;
}
.form-control-enhanced {
    border-radius: var(--radius-md);
    border: 1.5px solid rgba(13,43,31,0.1);
    font-size: 0.9rem;
    font-weight: 600;
    padding: 0.7rem 1rem;
    width: 100%;
    color: var(--text-primary);
    background: #f8faf9;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    transition: var(--transition);
}
.form-control-enhanced:focus {
    outline: none;
    border-color: var(--lime);
    background: #fff;
    box-shadow: var(--shadow-glow);
}
.form-control-enhanced.monospace {
    font-family: 'Courier New', monospace !important;
    letter-spacing: 0.04em;
}
.disburse-footer {
    padding: 0 1.8rem 1.6rem;
    background: #fff;
    display: flex; gap: 0.75rem;
}

/* Animations */
@keyframes fadeIn { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
@keyframes slideUp { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }
.fade-in  { animation: fadeIn 0.5s ease-out both; }
.slide-up { animation: slideUp 0.5s cubic-bezier(0.4,0,0.2,1) both; }

/* Dropdown */
.dropdown-menu {
    border-radius: var(--radius-md) !important;
    border: 1px solid rgba(13,43,31,0.08) !important;
    box-shadow: var(--shadow-lg) !important;
    padding: 0.4rem !important;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}
.dropdown-item {
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.6rem 0.9rem !important;
    color: var(--text-primary) !important;
    transition: var(--transition);
}
.dropdown-item:hover { background: #f0faf4 !important; }

/* Flash Messages */
.alert {
    border-radius: var(--radius-md) !important;
    font-weight: 600 !important;
    font-size: 0.875rem !important;
    border: none !important;
}

/* Responsive tweaks */
@media (max-width: 768px) {
    .hp-hero { padding: 2rem 1.5rem 4rem; }
    .hp-hero h1 { font-size: 1.7rem !important; }
    .glass-stat-value { font-size: 1.6rem; }
    #loanDrawer { width: 100% !important; }
    .table-responsive { font-size: 0.82rem; }
    .disburse-hero .amount-display { font-size: 2rem; }
}
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

        <!-- Hero Banner -->
        <div class="hp-hero fade-in mb-0">
            <div class="hero-ring2"></div>
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="ops-label">
                        Operations Engine
                    </div>
                    <h1 class="mb-2">Loan Disbursement Center</h1>
                    <p class="mb-0" style="color: rgba(255,255,255,0.6); font-size: 0.95rem; font-weight: 500;">
                        Processing approved credit lines into active liquidity.
                    </p>
                </div>
                <div class="col-lg-4 text-end mt-3 mt-lg-0">
                    <div class="dropdown">
                        <button class="btn btn-lime px-4 py-2 rounded-pill dropdown-toggle fw-bold" data-bs-toggle="dropdown" style="font-size: 0.875rem;">
                            <i class="bi bi-cloud-download me-2"></i>Export Logs
                        </button>
                        <ul class="dropdown-menu shadow-lg border-0 mt-2">
                            <li><a class="dropdown-item py-2" href="?export=pdf">
                                <i class="bi bi-file-pdf text-danger me-2"></i>Disbursement PDF
                            </a></li>
                            <li><a class="dropdown-item py-2" href="?export=excel">
                                <i class="bi bi-file-excel text-success me-2"></i>Excel Sheet
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Area (overlapping hero) -->
        <div style="margin-top: -36px; position: relative; z-index: 10;">

            <?php flash_render(); ?>

            <?php render_support_ticket_widget($conn, ['loans'], 'Loans & Repayments'); ?>

            <!-- KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="glass-stat slide-up" style="animation-delay: 0.05s">
                        <div class="d-flex align-items-start gap-3">
                            <div class="glass-stat-icon" style="background:#fffbeb; color:#b45309;">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div>
                                <div class="glass-stat-label">Pending Review</div>
                                <div class="glass-stat-value"><?= number_format((int)$stats['pending_count']) ?></div>
                                <div class="glass-stat-trend">
                                    <span style="color:#b45309; font-weight:700;">KES <?= number_format((float)$stats['pending_val']) ?></span> in queue
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-stat slide-up" style="animation-delay: 0.12s">
                        <div class="d-flex align-items-start gap-3">
                            <div class="glass-stat-icon" style="background:#eff6ff; color:#1d4ed8;">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <div>
                                <div class="glass-stat-label">Awaiting Payout</div>
                                <div class="glass-stat-value"><?= number_format((int)$stats['approved_count']) ?></div>
                                <div class="glass-stat-trend">
                                    <span style="color:#1d4ed8; font-weight:700;">KES <?= number_format((float)$stats['approved_val']) ?></span> required
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-stat slide-up" style="animation-delay: 0.19s">
                        <div class="d-flex align-items-start gap-3">
                            <div class="glass-stat-icon" style="background:#f0fdf4; color:#166534;">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                            <div>
                                <div class="glass-stat-label">Active Portfolio</div>
                                <div class="glass-stat-value"><?= number_format((int)$stats['active_count']) ?></div>
                                <div class="glass-stat-trend">
                                    <span style="color:#166534; font-weight:700;">KES <?= number_format((float)$stats['active_portfolio']) ?></span> outstanding
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Overdue Portfolio Card -->
                <div class="col-md-3">
                    <div class="glass-stat slide-up" style="animation-delay: 0.23s; border-bottom: 3px solid #ef4444;">
                        <div class="d-flex align-items-start gap-3">
                            <div class="glass-stat-icon" style="background:#fef2f2; color:#ef4444;">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div>
                                <div class="glass-stat-label">Overdue Items</div>
                                <div class="glass-stat-value"><?= number_format((int)($stats['overdue_count'] ?? 0)) ?></div>
                                <div class="glass-stat-trend">
                                    <span style="color:#ef4444; font-weight:700;">KES <?= number_format((float)($stats['overdue_val'] ?? 0)) ?></span> late
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="glass-card slide-up" style="animation-delay: 0.26s">
                <!-- Card Header -->
                <div class="card-header-zone">
                    <div class="d-flex align-items-center gap-2">
                        <h5>Payment Queue</h5>
                        <span style="background:#f0faf4; color:#166534; border:1px solid rgba(22,163,74,0.15); border-radius:100px; padding:0.2rem 0.7rem; font-size:0.7rem; font-weight:800;">
                            <?= $loans->num_rows ?> records
                        </span>
                    </div>

                    <form class="d-flex gap-2 align-items-center" method="GET">
                        <div class="search-input-wrap">
                            <i class="bi bi-search"></i>
                            <input type="text" name="search" placeholder="Search members, ID..."
                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        </div>
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Queues</option>
                            <option value="pending"   <?= ($_GET['status'] ?? '') === 'pending'   ? 'selected' : '' ?>>Review Queue</option>
                            <option value="approved"  <?= ($_GET['status'] ?? '') === 'approved'  ? 'selected' : '' ?>>Payout Queue</option>
                            <option value="disbursed" <?= ($_GET['status'] ?? '') === 'disbursed' ? 'selected' : '' ?>>Disbursed</option>
                            <option value="overdue"   <?= ($_GET['status'] ?? '') === 'overdue'   ? 'selected' : '' ?>>Overdue</option>
                        </select>
                        <?php if (($_GET['status'] ?? '') === 'overdue' && ($stats['overdue_count'] ?? 0) > 0): ?>
                            <button type="button" onclick="sendBulkReminders()" class="btn btn-warning fw-bold px-3 border-0 shadow-sm" style="background:#f59e0b; color:white;">
                                <i class="bi bi-send-check-fill me-1"></i>Remind All
                            </button>
                        <?php endif; ?>
                        <?php if (!empty($_GET['search']) || !empty($_GET['status'])): ?>
                            <a href="loans_payouts.php" class="btn btn-finalized" style="cursor:pointer; text-decoration:none; white-space:nowrap;">
                                <i class="bi bi-x-lg me-1"></i>Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Beneficiary</th>
                                <th>Product</th>
                                <th>Amount</th>
                                <th>Collateral</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $loans->data_seek(0);
                            if ($loans->num_rows > 0):
                                while ($row = $loans->fetch_assoc()):
                                    $g_count = (int)$row['guarantor_count'];
                                    $g_full  = $g_count >= 2;
                                    $is_overdue = ($row['status'] === 'disbursed' && !empty($row['next_repayment_date']) && strtotime($row['next_repayment_date']) < time());
                            ?>
                            <tr onclick="openLoanDrawer(<?= htmlspecialchars(json_encode($row)) ?>)" style="<?= $is_overdue ? 'background-color: #fff5f5;' : '' ?>">
                                <td>
                                    <div class="member-cell">
                                        <div class="member-avatar">
                                            <?= strtoupper(substr($row['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="member-name"><?= htmlspecialchars($row['full_name']) ?></div>
                                            <div class="member-id">ID: <?= htmlspecialchars($row['national_id']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="product-type"><?= ucfirst(htmlspecialchars($row['loan_type'])) ?></div>
                                    <div class="product-meta"><?= $row['duration_months'] ?> mo &bull; <?= $row['interest_rate'] ?>% p.a.</div>
                                </td>
                                <td>
                                    <div class="amount-value">KES <?= number_format((float)$row['amount'], 2) ?></div>
                                </td>
                                <td>
                                    <span class="guarantor-badge <?= $g_full ? 'full' : '' ?>">
                                        <i class="bi bi-people-fill" style="font-size:0.7rem;"></i>
                                        <?= $g_count ?> / 2
                                        <?php if ($g_full): ?><i class="bi bi-check-circle-fill" style="font-size:0.65rem;"></i><?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                        $sc = match($row['status']) {
                                            'pending'   => 'status-pending',
                                            'approved'  => 'status-approved',
                                            'disbursed' => 'status-disbursed',
                                            'rejected'  => 'status-rejected',
                                            default     => ''
                                        };
                                    ?>
                                    <span class="status-badge <?= $sc ?>">
                                        <?= strtoupper($row['status']) ?>
                                    </span>
                                    <?php if ($is_overdue): ?>
                                        <span class="status-badge bg-danger text-white border-0 ms-1" style="font-size: 0.6rem; padding: 0.2rem 0.5rem;">
                                            LATE
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td onclick="event.stopPropagation()">
                                    <div class="action-zone">
                                        <?php if ($row['status'] === 'approved' && $can_disburse): ?>
                                            <button onclick="openDisburseModal(<?= $row['loan_id'] ?>, <?= $row['amount'] ?>)" class="btn-action-disburse">
                                                Process Payout <i class="bi bi-send-fill" style="font-size:0.7rem;"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="btn-finalized"><?= ucfirst($row['status']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="bi bi-inbox"></i>
                                        </div>
                                        <h5>Pipeline Empty</h5>
                                        <p>No loan records match the current queue filter.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /content overlap -->

    </div><!-- /container-fluid -->


    <!-- ═══════════════════════════════════════════
         LOAN DETAILS DRAWER
    ═══════════════════════════════════════════ -->
    <div class="offcanvas offcanvas-end border-0" tabindex="-1" id="loanDrawer">
        <div class="drawer-header offcanvas-header">
            <h5 class="offcanvas-title">Loan Analytics</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0" style="overflow-y: auto;">

            <div class="drawer-avatar-wrap">
                <div class="drawer-avatar" id="drawer_avatar"></div>
                <div class="drawer-name" id="drawer_name">—</div>
                <span class="drawer-id-badge" id="drawer_id">ID: —</span>
            </div>

            <div class="drawer-section">
                <div class="drawer-section-label">Loan Details</div>
                <div class="drawer-row">
                    <span class="drawer-row-label">Principal</span>
                    <span class="drawer-row-value" id="drawer_amount">KES 0</span>
                </div>
                <div class="drawer-row">
                    <span class="drawer-row-label">Interest Rate</span>
                    <span class="drawer-row-value">
                        <span style="background:var(--lime-glow); color:var(--forest); border-radius:100px; padding:0.15rem 0.6rem; font-size:0.8rem; font-weight:800;" id="drawer_rate">0%</span>
                    </span>
                </div>
                <div class="drawer-row">
                    <span class="drawer-row-label">Duration</span>
                    <span class="drawer-row-value" id="drawer_duration">— months</span>
                </div>
                <div class="drawer-row">
                    <span class="drawer-row-label">Loan Type</span>
                    <span class="drawer-row-value" id="drawer_type">—</span>
                </div>
                <div class="drawer-row">
                    <span class="drawer-row-label">Phone</span>
                    <span class="drawer-row-value" id="drawer_phone">—</span>
                </div>
            </div>

            <div class="drawer-total-box">
                <div>
                    <div class="drawer-total-label">Total Repayable</div>
                    <div style="color:rgba(255,255,255,0.45); font-size:0.72rem; font-weight:500;">Principal + Interest</div>
                </div>
                <div class="drawer-total-value" id="drawer_total">KES 0</div>
            </div>

            <button class="drawer-close-btn" data-bs-dismiss="offcanvas">
                <i class="bi bi-x-circle"></i> Close Analytics
            </button>

        </div>
    </div>


    <!-- (Reject Modal Removed per Requirement: Disbursement only) -->


    <!-- ═══════════════════════════════════════════
         DISBURSE MODAL
    ═══════════════════════════════════════════ -->
    <div class="modal fade" id="disburseModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
            <div class="modal-content">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="disburse">
                    <input type="hidden" name="loan_id" id="disburse_loan_id">

                    <div class="disburse-hero">
                        <div class="amount-label">Disbursement Amount</div>
                        <div class="amount-display">
                            <span>KES</span> <span id="disburse_amount_val">0.00</span>
                        </div>
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="disburse-body">
                        <div class="mb-4">
                            <div class="field-label-enhanced">Disbursement Channel</div>
                            <select name="payment_method" id="payment_method" class="form-control-enhanced" required onchange="handleRefGeneration()">
                                <option value="bank">🏦 Bank Transfer (Internal)</option>
                                <option value="cash">💵 Petty Cash (Internal)</option>
                                <option value="mpesa">📱 M-Pesa (External)</option>
                            </select>
                        </div>
                        <div>
                            <div class="field-label-enhanced">
                                Transaction Reference
                                <span class="ref-autobadge" id="ref_badge">Auto-Generated</span>
                            </div>
                            <input type="text" name="ref_no" id="ref_no_input"
                                   class="form-control-enhanced monospace" required
                                   placeholder="DSB-...">
                        </div>
                    </div>

                    <div class="disburse-footer">
                        <button type="button" style="flex:1; background:#f5f8f6; color:var(--text-muted); border:1.5px solid rgba(13,43,31,0.1); border-radius:100px; padding:0.65rem; font-weight:700; font-size:0.85rem; cursor:pointer;" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" style="flex:2; background:var(--lime); color:var(--forest); border:none; border-radius:100px; padding:0.65rem 1.6rem; font-weight:800; font-size:0.875rem; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:0.5rem; box-shadow:0 4px 16px rgba(181,244,60,0.35);">
                            Process Payout <i class="bi bi-check-circle-fill" style="font-size:0.85rem;"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- Hidden action form -->
    <form id="actionForm" method="POST" style="display:none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="form_action">
        <input type="hidden" name="loan_id" id="form_loan_id">
    </form>

    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmAction(action, id) {
    if (confirm('Are you absolutely sure you want to ' + action + ' this loan?')) {
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
    document.getElementById('disburse_amount_val').innerText = new Intl.NumberFormat('en-KE', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    }).format(amount);
    document.getElementById('ref_no_input').value = 'DSB-' + Date.now();
    new bootstrap.Modal(document.getElementById('disburseModal')).show();
}

function handleRefGeneration() {
    document.getElementById('ref_no_input').value = 'DSB-' + Date.now();
}

function sendBulkReminders() {
    if (!confirm('This will queue late payment reminders for ALL overdue loans. Proceed?')) return;
    
    document.getElementById('form_action').value = 'send_bulk_reminders';
    document.getElementById('form_loan_id').value = '0';
    document.getElementById('actionForm').submit();
}

function openLoanDrawer(data) {
    const fmt = v => new Intl.NumberFormat('en-KE', {minimumFractionDigits: 2}).format(v);
    const amt   = parseFloat(data.amount) || 0;
    const rate  = parseFloat(data.interest_rate) || 0;
    const total = amt + (amt * (rate / 100));

    document.getElementById('drawer_avatar').innerText  = data.full_name.charAt(0).toUpperCase();
    document.getElementById('drawer_name').innerText    = data.full_name;
    document.getElementById('drawer_id').innerText      = 'ID: ' + data.national_id;
    document.getElementById('drawer_amount').innerText  = 'KES ' + fmt(amt);
    document.getElementById('drawer_rate').innerText    = rate + '%';
    document.getElementById('drawer_total').innerText   = 'KES ' + fmt(total);
    document.getElementById('drawer_duration').innerText = (data.duration_months || '—') + ' months';
    document.getElementById('drawer_type').innerText    = data.loan_type ? data.loan_type.charAt(0).toUpperCase() + data.loan_type.slice(1) : '—';
    document.getElementById('drawer_phone').innerText   = data.phone || '—';

    new bootstrap.Offcanvas(document.getElementById('loanDrawer')).show();
}
</script>
</body>
</html>