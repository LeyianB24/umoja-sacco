<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/LoanHelper.php';
require_once __DIR__ . '/../../inc/SettingsHelper.php';

require_admin();
$layout = LayoutManager::create('admin');
require_permission();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$admin_id = $_SESSION['admin_id'];
$db       = $conn;
$pageTitle = 'Loan Management';

// ---------------------------------------------------------
// 1. POST ACTIONS
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Invalid security token.");
    }

    $loan_id    = intval($_POST['loan_id']);
    $action     = $_POST['action'];
    $notes      = trim($_POST['notes'] ?? '');
    $new_status = null;
    $log_action = '';
    $details    = '';

    if ($loan_id > 0) {

        if ($action === 'approve') {
            $new_status = 'approved';
            $log_action = 'Loan Approval';
            $details    = "Approved Loan #$loan_id. Queued for disbursement.";

            $min_guarantors  = (int)SettingsHelper::get('min_guarantor_count', 2);
            $resG            = $db->query("SELECT COUNT(*) FROM loan_guarantors WHERE loan_id = $loan_id");
            $guarantor_count = (int)($resG->fetch_row()[0] ?? 0);

            if ($guarantor_count < $min_guarantors) {
                flash_set("Approval Failed: This loan requires at least $min_guarantors guarantors (Current: $guarantor_count).", "danger");
                header("Location: loans.php"); exit;
            }

            $q_loan    = $db->query("SELECT member_id, amount FROM loans WHERE loan_id = $loan_id");
            $loan_info = $q_loan->fetch_assoc();
            if ($loan_info) {
                require_once __DIR__ . '/../../inc/FinancialEngine.php';
                $engine       = new FinancialEngine($db);
                $balances     = $engine->getBalances($loan_info['member_id']);
                $curr_savings = $balances['savings'];
                $limit        = $curr_savings * 3;
                if ((float)$loan_info['amount'] > ($limit + 0.01)) {
                    flash_set("Approval Failed: Member's 3× Savings Limit is KES " . number_format($limit) . " (Savings: KES " . number_format($curr_savings) . "). Applied amount KES " . number_format($loan_info['amount']) . " exceeds this.", "danger");
                    header("Location: loans.php"); exit;
                }
            }

        } elseif ($action === 'reject') {
            $new_status = 'rejected';
            $log_action = 'Loan Rejection';
            $details    = "Rejected Loan #$loan_id. Reason: $notes";
        }

        if ($new_status) {
            $db->begin_transaction();
            try {
                $stmt = $db->prepare("UPDATE loans SET status = ?, approved_by = ?, approval_date = NOW() WHERE loan_id = ?");
                $stmt->bind_param("sii", $new_status, $admin_id, $loan_id);
                $stmt->execute();
                if ($stmt->affected_rows === 0) throw new Exception("Loan not found or already processed.");

                $db->query("UPDATE loan_guarantors SET status = '" . ($action === 'approve' ? 'approved' : 'rejected') . "' WHERE loan_id = $loan_id");

                $res_data = $db->query("SELECT member_id, amount FROM loans WHERE loan_id = $loan_id");
                if ($res_data && $res_data->num_rows > 0) {
                    $l_data   = $res_data->fetch_assoc();
                    $loan_ref = 'LOAN-' . str_pad((string)$loan_id, 5, '0', STR_PAD_LEFT);
                    require_once __DIR__ . '/../../inc/notification_helpers.php';
                    if ($action === 'approve') {
                        send_notification($db, (int)$l_data['member_id'], 'loan_approved', ['amount' => (float)$l_data['amount'], 'ref' => $loan_ref]);
                    } else {
                        send_notification($db, (int)$l_data['member_id'], 'loan_rejected', ['amount' => (float)$l_data['amount'], 'rejection_reason' => $notes, 'ref' => $loan_ref]);
                    }
                }

                $ip       = $_SERVER['REMOTE_ADDR'];
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

    header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']); exit;
}

// ---------------------------------------------------------
// 2. FILTER & EXPORT
// ---------------------------------------------------------
$filter = $_GET['status'] ?? 'pending';
$search = trim($_GET['q'] ?? '');

$where_clauses = [];
$params        = [];
$types         = "";

if ($filter !== 'all') { $where_clauses[] = "l.status = ?"; $params[] = $filter; $types .= "s"; }
if ($search) {
    $where_clauses[] = "(m.full_name LIKE ? OR m.national_id LIKE ? OR l.loan_id LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term;
    $types .= "sss";
}

if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    if ($_GET['action'] !== 'print_report') require_once __DIR__ . '/../../inc/ExportHelper.php';
    else require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';

    $where_sql_e  = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";
    $stmt_e       = $db->prepare("SELECT l.*, m.full_name FROM loans l JOIN members m ON l.member_id = m.member_id $where_sql_e ORDER BY l.created_at DESC");
    if (!empty($params)) $stmt_e->bind_param($types, ...$params);
    $stmt_e->execute();
    $export_loans = $stmt_e->get_result()->fetch_all(MYSQLI_ASSOC);

    $format = match($_GET['action']) { 'export_excel' => 'excel', 'print_report' => 'print', default => 'pdf' };
    $data   = array_map(fn($l) => ['Loan ID' => $l['loan_id'], 'Applicant' => $l['full_name'], 'Amount' => number_format((float)$l['amount'], 2), 'Type' => $l['loan_type'], 'Duration' => $l['duration_months'] . ' Months', 'Status' => ucfirst($l['status']), 'Date' => date('d-M-Y', strtotime($l['created_at']))], $export_loans);
    $headers = ['Loan ID', 'Applicant', 'Amount', 'Type', 'Duration', 'Status', 'Date'];
    $title   = 'Loan_Portfolio_Report_' . date('Ymd_His');

    if ($format === 'pdf')        ExportHelper::pdf('Loan Portfolio Report', $headers, $data, $title . '.pdf');
    elseif ($format === 'excel')  ExportHelper::csv($title . '.csv', $headers, $data);
    else                          UniversalExportEngine::handle($format, $data, ['title' => 'Loan Portfolio Report', 'module' => 'Loan Management', 'headers' => $headers, 'total_value' => array_sum(array_column($export_loans, 'amount')), 'currency' => 'KES']);
    exit;
}

// ---------------------------------------------------------
// 3. FETCH DATA
// ---------------------------------------------------------
$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";
$stmt      = $db->prepare("SELECT l.*, m.full_name, m.national_id, m.phone, m.profile_pic FROM loans l JOIN members m ON l.member_id = m.member_id $where_sql ORDER BY l.created_at DESC");
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$loans = $stmt->get_result();

$stats = $db->query("SELECT
    COUNT(CASE WHEN status='pending'  THEN 1 END) as pending,
    COUNT(CASE WHEN status='approved' THEN 1 END) as approved,
    COUNT(CASE WHEN status IN ('disbursed','active') THEN 1 END) as active
    FROM loans")->fetch_assoc();
?>
<?php $layout->header($pageTitle); ?>

<!-- ═══════════════════════════════════════════════════════════ PAGE STYLES -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

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
.hero-stat-value.lime  { color: var(--lime); }
.hero-stat-value.amber { color: #fde68a; }
.hero-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
.btn-lime { background: var(--lime); color: var(--ink); border: none; font-weight: 700; font-size: .85rem; transition: var(--transition); box-shadow: 0 4px 14px rgba(168,224,99,.4); }
.btn-lime:hover { background: #baea78; color: var(--ink); transform: translateY(-1px); }
.btn-outline-hero { background: rgba(255,255,255,.1); color: rgba(255,255,255,.9); border: 1px solid rgba(255,255,255,.25); font-weight: 600; font-size: .83rem; transition: var(--transition); }
.btn-outline-hero:hover { background: rgba(255,255,255,.18); color: #fff; transform: translateY(-1px); }

/* ── Filter toolbar ─────────────────────────────────────────── */
.filter-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 14px 20px;
    box-shadow: var(--shadow-sm); margin-bottom: 20px;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
    animation: fadeUp .4s ease both; animation-delay: .06s;
}

/* Status tabs */
.status-tabs { display: flex; gap: 4px; background: var(--surface-2); border: 1px solid var(--border); border-radius: 10px; padding: 4px; }
.status-tab {
    display: flex; align-items: center; gap: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .8rem; font-weight: 700; border-radius: 8px;
    padding: 7px 14px; border: none; cursor: pointer;
    background: transparent; color: var(--muted); text-decoration: none; transition: var(--transition);
    white-space: nowrap;
}
.status-tab:hover { background: var(--surface); color: var(--ink); }
.status-tab.active { background: var(--forest); color: #fff; box-shadow: 0 2px 8px rgba(26,58,42,.25); }
.status-tab .tab-badge {
    font-size: .63rem; font-weight: 800; border-radius: 100px; padding: 2px 7px; line-height: 1.4;
    background: rgba(255,255,255,.2); color: inherit;
}
.status-tab:not(.active) .tab-badge { background: var(--lime-glow); color: var(--forest); }

/* Search input */
.search-wrap { display: flex; align-items: center; gap: 0; border: 1.5px solid var(--border); border-radius: 10px; overflow: hidden; background: var(--surface); transition: var(--transition); }
.search-wrap:focus-within { border-color: var(--forest); box-shadow: 0 0 0 3px rgba(26,58,42,.07); }
.search-icon { width: 40px; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: .9rem; flex-shrink: 0; }
.search-input { border: none; background: none; outline: none; font-family: 'Plus Jakarta Sans', sans-serif !important; font-size: .84rem; font-weight: 500; color: var(--ink); padding: 9px 14px 9px 0; min-width: 220px; }
.search-input::placeholder { color: var(--muted); }
.btn-search { background: var(--forest); color: #fff; border: none; padding: 0 16px; height: 100%; font-size: .8rem; font-weight: 700; cursor: pointer; transition: var(--transition); }
.btn-search:hover { background: var(--forest-light); }
.btn-clear-search { font-size: .75rem; color: var(--muted); text-decoration: none; font-weight: 600; padding: 0 10px; display: flex; align-items: center; gap: 4px; transition: var(--transition); white-space: nowrap; }
.btn-clear-search:hover { color: #c0392b; }

/* ── Table card ─────────────────────────────────────────────── */
.detail-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); overflow: hidden;
    box-shadow: var(--shadow-sm); transition: var(--transition);
    animation: fadeUp .4s ease both; animation-delay: .12s;
}
.detail-card:hover { box-shadow: var(--shadow-md); border-color: #d0ddd4; }

.card-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; }
.card-toolbar-title { font-size: .7rem; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; color: var(--forest); display: flex; align-items: center; gap: 8px; }
.card-toolbar-title i { width: 28px; height: 28px; border-radius: 8px; background: var(--lime-glow); color: var(--forest); display: flex; align-items: center; justify-content: center; font-size: .9rem; }
.record-count { font-size: .72rem; font-weight: 700; background: var(--lime-glow); color: var(--forest); border: 1px solid rgba(168,224,99,.35); border-radius: 100px; padding: 4px 12px; }

/* ── Loans table ────────────────────────────────────────────── */
.loans-table { width: 100%; border-collapse: collapse; }
.loans-table thead th { font-size: .67rem; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); background: var(--surface-2); padding: 13px 16px; border-bottom: 2px solid var(--border); white-space: nowrap; }
.loans-table tbody tr { border-bottom: 1px solid var(--border); transition: var(--transition); }
.loans-table tbody tr:last-child { border-bottom: none; }
.loans-table tbody tr:hover { background: #f9fcf9; }
.loans-table td { padding: 14px 16px; vertical-align: middle; }

/* Avatar + member cell */
.member-cell { display: flex; align-items: center; gap: 12px; }
.member-avatar { width: 38px; height: 38px; border-radius: 10px; object-fit: cover; flex-shrink: 0; border: 2px solid var(--border); }
.member-avatar-fallback { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, var(--forest), var(--forest-light)); color: #fff; font-size: .78rem; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.member-name { font-size: .87rem; font-weight: 700; color: var(--ink); }
.member-id   { font-size: .72rem; color: var(--muted); font-weight: 500; font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; margin-top: 2px; }

/* Loan amount */
.loan-amount { font-size: .92rem; font-weight: 800; color: #1a7a3f; }
.loan-type   { font-size: .73rem; color: var(--muted); font-weight: 500; margin-top: 2px; }

/* Terms */
.terms-months { font-size: .83rem; font-weight: 700; color: var(--ink); }
.terms-rate   { font-size: .72rem; color: var(--muted); font-weight: 500; margin-top: 2px; }

/* Status pills */
.status-pill { display: inline-flex; align-items: center; gap: 5px; font-size: .68rem; font-weight: 800; letter-spacing: .3px; text-transform: uppercase; border-radius: 100px; padding: 5px 13px; white-space: nowrap; }
.status-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: .7; }
.sp-pending   { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.sp-approved  { background: #e0f2fe; color: #0c4a6e; border: 1px solid #7dd3fc; }
.sp-disbursed { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.sp-rejected  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.sp-default   { background: var(--surface-2); color: var(--muted); border: 1px solid var(--border); }

/* Action buttons in table */
.btn-review {
    display: inline-flex; align-items: center; gap: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .79rem; font-weight: 700; border: none; cursor: pointer;
    border-radius: 100px; padding: 7px 18px; transition: var(--transition);
    background: var(--forest); color: #fff;
}
.btn-review:hover { background: var(--forest-light); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(26,58,42,.25); }
.btn-closed {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: .77rem; font-weight: 600; color: var(--muted);
    background: var(--surface-2); border: 1.5px solid var(--border);
    border-radius: 100px; padding: 7px 16px; cursor: default;
}

/* Empty state */
.empty-state { text-align: center; padding: 60px 24px; color: var(--muted); }
.empty-state i { font-size: 2.8rem; opacity: .15; display: block; margin-bottom: 14px; }
.empty-state p { font-size: .84rem; margin: 0; }

/* ── Alert ──────────────────────────────────────────────────── */
.alert-custom { border: 0; border-radius: var(--radius-sm); padding: 14px 18px; font-size: .84rem; font-weight: 600; display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
.alert-custom.success { background: #f0fff6; color: #1a7a3f; border-left: 3px solid #2e6347; }
.alert-custom.danger  { background: #fef0f0; color: #c0392b; border-left: 3px solid #e74c3c; }
.alert-custom.warning { background: #fffbeb; color: #92400e; border-left: 3px solid #f59e0b; }

/* ── Modals ─────────────────────────────────────────────────── */
.modal-content { border: 0 !important; border-radius: var(--radius-lg) !important; overflow: hidden; box-shadow: var(--shadow-lg); }
.modal-header  { border-bottom: 0 !important; padding: 0 !important; }
.modal-body    { padding: 28px !important; }

/* Approve modal header gradient */
.modal-hdr-approve { background: linear-gradient(135deg, var(--forest), var(--forest-light)); padding: 24px 28px; position: relative; overflow: hidden; }
.modal-hdr-approve::after { content: ''; position: absolute; right: -30px; top: -30px; width: 160px; height: 160px; border-radius: 50%; border: 1px solid rgba(168,224,99,.15); pointer-events: none; }
.modal-hdr-reject  { background: linear-gradient(135deg, #7f1d1d, #991b1b); padding: 24px 28px; position: relative; overflow: hidden; }

.modal-hdr-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 12px; }
.mhi-approve { background: rgba(168,224,99,.2); color: var(--lime); }
.mhi-reject  { background: rgba(255,255,255,.1); color: #fca5a5; }
.modal-hdr-title { font-size: 1.1rem; font-weight: 800; color: #fff; margin: 0 0 3px; }
.modal-hdr-sub   { font-size: .78rem; color: rgba(255,255,255,.6); font-weight: 500; margin: 0; }

/* Amount display in modal */
.modal-amount { text-align: center; margin-bottom: 22px; padding-bottom: 22px; border-bottom: 1px solid var(--border); }
.modal-amount-val { font-size: 2rem; font-weight: 800; color: #1a7a3f; letter-spacing: -.5px; line-height: 1; }
.modal-amount-label { font-size: .75rem; color: var(--muted); font-weight: 600; margin-top: 4px; }

/* Detail chips */
.detail-chips { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
.detail-chip { flex: 1; min-width: 100px; background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px 14px; text-align: center; }
.detail-chip-label { font-size: .65rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: var(--muted); margin-bottom: 4px; }
.detail-chip-val   { font-size: .9rem; font-weight: 800; color: var(--ink); }

/* Guarantors section */
.guarantors-section { margin-bottom: 22px; }
.guarantors-label { font-size: .68rem; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
.guarantors-box { background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
.guarantor-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-bottom: 1px solid var(--border); }
.guarantor-row:last-child { border-bottom: none; }
.guarantor-name { font-size: .83rem; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: 7px; }
.guarantor-name::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--lime); flex-shrink: 0; }
.guarantor-amount { font-size: .78rem; font-weight: 800; color: var(--forest); font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; background: var(--lime-glow); border: 1px solid rgba(168,224,99,.3); border-radius: 6px; padding: 3px 10px; }
.guarantors-empty { text-align: center; padding: 16px; font-size: .8rem; color: var(--muted); display: flex; align-items: center; justify-content: center; gap: 6px; }

/* Modal action buttons */
.modal-actions { display: flex; gap: 10px; }
.btn-modal-approve {
    flex: 1; background: var(--forest); color: #fff; border: none;
    border-radius: 10px; padding: 12px; font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .85rem; font-weight: 800; cursor: pointer; transition: var(--transition);
    display: flex; align-items: center; justify-content: center; gap: 7px;
}
.btn-modal-approve:hover { background: var(--forest-light); box-shadow: 0 4px 14px rgba(26,58,42,.3); }
.btn-modal-reject {
    flex: 1; background: transparent; color: #c0392b; border: 1.5px solid #fca5a5;
    border-radius: 10px; padding: 12px; font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .85rem; font-weight: 800; cursor: pointer; transition: var(--transition);
    display: flex; align-items: center; justify-content: center; gap: 7px;
}
.btn-modal-reject:hover { background: #fef0f0; border-color: #e74c3c; }
.btn-modal-danger {
    flex: 1; background: #c0392b; color: #fff; border: none;
    border-radius: 10px; padding: 12px; font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .85rem; font-weight: 800; cursor: pointer; transition: var(--transition);
    display: flex; align-items: center; justify-content: center; gap: 7px;
}
.btn-modal-danger:hover { background: #a93226; box-shadow: 0 4px 14px rgba(192,57,43,.35); }
.btn-modal-back {
    background: var(--surface-2); color: var(--muted); border: 1.5px solid var(--border);
    border-radius: 10px; padding: 12px 20px; font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .83rem; font-weight: 700; cursor: pointer; transition: var(--transition);
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.btn-modal-back:hover { background: var(--surface); color: var(--ink); }

/* Reject textarea */
.reject-textarea {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .85rem; font-weight: 500; color: var(--ink);
    border: 1.5px solid var(--border); border-radius: 10px;
    padding: 12px 14px; width: 100%; resize: vertical; min-height: 100px;
    background: var(--surface-2); transition: var(--transition); outline: none;
    margin-bottom: 18px;
}
.reject-textarea:focus { border-color: #c0392b; background: #fff; box-shadow: 0 0 0 3px rgba(192,57,43,.08); }
.reject-context { font-size: .83rem; color: var(--muted); font-weight: 500; margin-bottom: 18px; padding: 12px 14px; background: var(--surface-2); border-radius: var(--radius-sm); border: 1px solid var(--border); }
.reject-context strong { color: var(--ink); }

/* ── Animate ────────────────────────────────────────────────── */
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4 page-canvas">

        <!-- Breadcrumb -->
        <nav class="mb-1" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
                <li class="breadcrumb-item active">Loan Portfolio</li>
            </ol>
        </nav>

        <?php flash_render(); ?>

        <!-- ═══ HERO ══════════════════════════════════════════════════════ -->
        <div class="page-header mb-4">
            <div class="hero-inner">
                <div>
                    <div class="hero-chip"><i class="bi bi-cash-stack"></i>Operations · Loans</div>
                    <h1 class="hero-title">Loan Portfolio</h1>
                    <p class="hero-sub">Review applications, enforce guarantor checks, and monitor disbursements.</p>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-label">Pending Review</div>
                            <div class="hero-stat-value amber"><?= $stats['pending'] ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Approved</div>
                            <div class="hero-stat-value lime"><?= $stats['approved'] ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Active Loans</div>
                            <div class="hero-stat-value"><?= $stats['active'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="hero-actions">
                    <div class="dropdown">
                        <button class="btn btn-lime rounded-pill px-4 fw-bold shadow-sm dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-2"></i>Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;overflow:hidden">
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf me-2 text-danger"></i>Export PDF</a></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-spreadsheet me-2 text-success"></i>Export Excel</a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer me-2 text-muted"></i>Print Report</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ FILTER TOOLBAR ═══════════════════════════════════════════ -->
        <div class="filter-card">
            <!-- Status tabs -->
            <div class="status-tabs">
                <a href="?status=pending" class="status-tab <?= $filter==='pending' ? 'active' : '' ?>">
                    Pending <span class="tab-badge"><?= $stats['pending'] ?></span>
                </a>
                <a href="?status=approved" class="status-tab <?= $filter==='approved' ? 'active' : '' ?>">
                    Approved <span class="tab-badge"><?= $stats['approved'] ?></span>
                </a>
                <a href="?status=disbursed" class="status-tab <?= $filter==='disbursed' ? 'active' : '' ?>">
                    Active <span class="tab-badge"><?= $stats['active'] ?></span>
                </a>
                <a href="?status=all" class="status-tab <?= $filter==='all' ? 'active' : '' ?>">
                    All
                </a>
            </div>

            <!-- Search -->
            <form method="GET" style="display:flex;align-items:center;gap:8px">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
                <div class="search-wrap">
                    <div class="search-icon"><i class="bi bi-search"></i></div>
                    <input type="text" name="q" class="search-input" placeholder="Search name, ID or loan…" value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-search"><i class="bi bi-arrow-right"></i></button>
                </div>
                <?php if ($search): ?>
                    <a href="?status=<?= urlencode($filter) ?>" class="btn-clear-search"><i class="bi bi-x-circle"></i>Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- ═══ LOANS TABLE ══════════════════════════════════════════════ -->
        <div class="detail-card">
            <div class="card-toolbar">
                <div class="card-toolbar-title">
                    <i class="bi bi-cash-stack d-flex"></i>
                    <?= ucfirst($filter === 'all' ? 'All Loans' : $filter . ' Loans') ?>
                </div>
                <?php if ($loans->num_rows > 0): ?>
                <span class="record-count"><?= $loans->num_rows ?> records</span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="loans-table">
                    <thead>
                        <tr>
                            <th style="padding-left:20px">Applicant</th>
                            <th>Loan Request</th>
                            <th>Terms</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th style="text-align:right;padding-right:20px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($loans->num_rows === 0): ?>
                        <tr><td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-folder2-open"></i>
                                <p>No loan records found<?= $search ? " for \"$search\"" : '' ?>.</p>
                            </div>
                        </td></tr>
                    <?php else:
                        $loans->data_seek(0);
                        while ($l = $loans->fetch_assoc()):
                            $sp = match($l['status']) {
                                'pending'   => 'sp-pending',
                                'approved'  => 'sp-approved',
                                'disbursed', 'active' => 'sp-disbursed',
                                'rejected'  => 'sp-rejected',
                                default     => 'sp-default',
                            };
                            $img = !empty($l['profile_pic'])
                                ? 'data:image/jpeg;base64,' . base64_encode($l['profile_pic'])
                                : null;
                            $initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(explode(' ', trim($l['full_name'])), 0, 2))));
                    ?>
                        <tr>
                            <td style="padding-left:20px">
                                <div class="member-cell">
                                    <?php if ($img): ?>
                                        <img src="<?= $img ?>" class="member-avatar" alt="">
                                    <?php else: ?>
                                        <div class="member-avatar-fallback"><?= $initials ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="member-name"><?= htmlspecialchars($l['full_name']) ?></div>
                                        <div class="member-id"><?= htmlspecialchars($l['national_id']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="loan-amount">KES <?= ksh($l['amount']) ?></div>
                                <div class="loan-type"><?= htmlspecialchars($l['loan_type']) ?></div>
                            </td>
                            <td>
                                <div class="terms-months"><?= $l['duration_months'] ?> Months</div>
                                <div class="terms-rate"><?= $l['interest_rate'] ?>% Interest</div>
                            </td>
                            <td>
                                <div style="font-size:.82rem;font-weight:600;color:var(--ink)"><?= date('d M Y', strtotime($l['created_at'])) ?></div>
                                <div style="font-size:.72rem;color:var(--muted)"><?= date('H:i', strtotime($l['created_at'])) ?></div>
                            </td>
                            <td><span class="status-pill <?= $sp ?>"><?= ucfirst($l['status']) ?></span></td>
                            <td style="text-align:right;padding-right:20px">
                                <?php if ($l['status'] === 'pending'): ?>
                                    <button class="btn-review" data-bs-toggle="modal" data-bs-target="#approveModal<?= $l['loan_id'] ?>">
                                        <i class="bi bi-eye-fill"></i>Review
                                    </button>
                                <?php else: ?>
                                    <span class="btn-closed"><i class="bi bi-lock-fill"></i>Closed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ MODALS ════════════════════════════════════════════════════ -->
        <?php
        if ($loans->num_rows > 0):
            $loans->data_seek(0);
            while ($l = $loans->fetch_assoc()):
                if ($l['status'] !== 'pending') continue;
                $resG = $db->query("SELECT lg.*, m.full_name FROM loan_guarantors lg JOIN members m ON lg.member_id = m.member_id WHERE lg.loan_id = " . (int)$l['loan_id']);
        ?>

        <!-- Approve Modal -->
        <div class="modal fade" id="approveModal<?= $l['loan_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
                <div class="modal-content">
                    <!-- Gradient header -->
                    <div class="modal-hdr-approve">
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                        <div class="modal-hdr-icon mhi-approve"><i class="bi bi-cash-stack"></i></div>
                        <div class="modal-hdr-title">Review Application</div>
                        <div class="modal-hdr-sub">Loan #<?= $l['loan_id'] ?> · <?= htmlspecialchars($l['full_name']) ?></div>
                    </div>
                    <div class="modal-body">
                        <!-- Amount -->
                        <div class="modal-amount">
                            <div class="modal-amount-val">KES <?= ksh($l['amount']) ?></div>
                            <div class="modal-amount-label">Requested by <?= htmlspecialchars($l['full_name']) ?></div>
                        </div>

                        <!-- Detail chips -->
                        <div class="detail-chips">
                            <div class="detail-chip">
                                <div class="detail-chip-label">Duration</div>
                                <div class="detail-chip-val"><?= $l['duration_months'] ?> mo</div>
                            </div>
                            <div class="detail-chip">
                                <div class="detail-chip-label">Interest</div>
                                <div class="detail-chip-val"><?= $l['interest_rate'] ?>%</div>
                            </div>
                            <div class="detail-chip">
                                <div class="detail-chip-label">Type</div>
                                <div class="detail-chip-val" style="font-size:.8rem"><?= htmlspecialchars($l['loan_type']) ?></div>
                            </div>
                        </div>

                        <!-- Guarantors -->
                        <div class="guarantors-section">
                            <div class="guarantors-label">Guarantors</div>
                            <div class="guarantors-box">
                                <?php if ($resG && $resG->num_rows > 0): while ($g = $resG->fetch_assoc()): ?>
                                    <div class="guarantor-row">
                                        <div class="guarantor-name"><?= htmlspecialchars($g['full_name']) ?></div>
                                        <span class="guarantor-amount">KES <?= ksh($g['amount_locked']) ?></span>
                                    </div>
                                <?php endwhile; else: ?>
                                    <div class="guarantors-empty"><i class="bi bi-exclamation-circle text-warning"></i>No guarantors assigned</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="modal-actions">
                            <button type="button" class="btn-modal-reject"
                                    data-bs-toggle="modal"
                                    data-bs-target="#rejectModal<?= $l['loan_id'] ?>"
                                    data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i>Reject
                            </button>
                            <form method="POST" style="flex:1">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="loan_id" value="<?= $l['loan_id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn-modal-approve w-100">
                                    <i class="bi bi-check-circle-fill"></i>Approve Loan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div class="modal fade" id="rejectModal<?= $l['loan_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
                <div class="modal-content">
                    <div class="modal-hdr-reject">
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                        <div class="modal-hdr-icon mhi-reject"><i class="bi bi-x-octagon-fill"></i></div>
                        <div class="modal-hdr-title">Reject Application</div>
                        <div class="modal-hdr-sub">Loan #<?= $l['loan_id'] ?> · This action will notify the member.</div>
                    </div>
                    <div class="modal-body">
                        <div class="reject-context">
                            Rejecting loan of <strong>KES <?= ksh($l['amount']) ?></strong> for <strong><?= htmlspecialchars($l['full_name']) ?></strong>. Please provide a clear reason.
                        </div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="loan_id" value="<?= $l['loan_id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <label style="font-size:.72rem;font-weight:800;letter-spacing:.6px;text-transform:uppercase;color:var(--muted);display:block;margin-bottom:6px">Rejection Reason <span style="color:#c0392b">*</span></label>
                            <textarea name="notes" class="reject-textarea" placeholder="e.g. Insufficient guarantor coverage, outstanding default…" required></textarea>
                            <div class="modal-actions">
                                <button type="button" class="btn-modal-back"
                                        data-bs-toggle="modal"
                                        data-bs-target="#approveModal<?= $l['loan_id'] ?>"
                                        data-bs-dismiss="modal">
                                    <i class="bi bi-arrow-left"></i>Back
                                </button>
                                <button type="submit" class="btn-modal-danger">
                                    <i class="bi bi-slash-circle-fill"></i>Confirm Reject
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php endwhile; endif; ?>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->
</body>
</html>