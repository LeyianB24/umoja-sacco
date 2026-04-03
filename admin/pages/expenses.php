<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/TransactionHelper.php';

$layout = LayoutManager::create('admin');

require_admin();
require_permission();

$pageTitle = "Expenditure Portal";

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    verify_csrf_token();

    $amount     = floatval($_POST['amount']);
    $category   = $_POST['category'];
    $payee      = trim($_POST['payee']);
    $date       = $_POST['expense_date'];
    $ref_no     = trim($_POST['ref_no']);
    $desc       = trim($_POST['description']);
    $is_pending = isset($_POST['is_pending']);

    validate_not_future($date, "expenses.php");

    if ($amount <= 0) {
        $_SESSION['error'] = "Expense amount must be valid.";
    } else {
        $notes = "[$category] $payee";
        if (!empty($desc)) $notes .= " - $desc";
        if ($is_pending) $notes .= " [PENDING]";

        $conn->begin_transaction();
        try {
            $unified_id    = $_POST['unified_asset_id'] ?? '';
            $related_id    = 0;
            $related_table = null;

            if ($unified_id && $unified_id !== 'other_0') {
                list($source, $related_id) = explode('_', $unified_id);
                $related_id    = (int)$related_id;
                $related_table = 'investments';
            }

            $method = $_POST['payment_method'] ?? 'cash';

            $ok = TransactionHelper::record([
                'member_id'     => null,
                'amount'        => $amount,
                'type'          => $is_pending ? 'expense_incurred' : 'expense',
                'category'      => $category,
                'method'        => $method,
                'ref_no'        => $ref_no,
                'notes'         => $notes,
                'related_id'    => $related_id,
                'related_table' => $related_table,
            ]);

            if (!$ok) throw new Exception("Ledger recording failed.");

            $conn->commit();
            $_SESSION['success'] = $is_pending
                ? "Pending bill recorded. Cash will only deduct when you settle it."
                : "Expense recorded successfully!";
            header("Location: expenses.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }
}

// Handle Settle Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'settle_expense') {
    verify_csrf_token();
    $tx_id  = (int)$_POST['transaction_id'];
    $method = $_POST['settle_method'] ?? 'cash';

    $stmt = $conn->prepare("SELECT notes, amount FROM transactions WHERE ledger_transaction_id = ? AND transaction_type = 'expense_incurred'");
    $stmt->bind_param("i", $tx_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($ex_row = $res->fetch_assoc()) {
        $amount      = (float)$ex_row['amount'];
        $clean_notes = trim(str_replace([' [PENDING]', '[PENDING]'], '', $ex_row['notes']));
        $settle_ref  = 'SETTLE-' . $tx_id . '-' . date('ymdHis');

        try {
            $ok = TransactionHelper::record([
                'member_id'     => null,
                'amount'        => $amount,
                'type'          => 'expense_settlement',
                'method'        => $method,
                'ref_no'        => $settle_ref,
                'notes'         => $clean_notes . ' [SETTLED]',
                'related_id'    => $tx_id,
                'related_table' => 'transactions',
            ]);

            if (!$ok) throw new Exception("Settlement ledger entry failed.");

            // Remove [PENDING] marker from original record
            $upd = $conn->prepare("UPDATE transactions SET notes = ? WHERE ledger_transaction_id = ?");
            $upd->bind_param("si", $clean_notes, $tx_id);
            $upd->execute();

            $_SESSION['success'] = "Bill settled! KES " . number_format($amount, 2) . " deducted from " . ucfirst($method) . " account.";
        } catch (Exception $e) {
            $_SESSION['error'] = "Settlement failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Pending bill not found or already settled.";
    }
    header("Location: expenses.php");
    exit;
}

// 3. Data Fetching

$duration   = $_GET['duration'] ?? '3months';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-3 months'));
$end_date   = $_GET['end_date']   ?? date('Y-m-d');

$date_filter = "";
if ($duration !== 'all') {
    switch ($duration) {
        case 'today':   $start_date = $end_date = date('Y-m-d'); break;
        case 'weekly':  $start_date = date('Y-m-d', strtotime('-7 days')); break;
        case 'monthly': $start_date = date('Y-m-01'); $end_date = date('Y-m-t'); break;
        case '3months': $start_date = date('Y-m-d', strtotime('-3 months')); break;
    }
    $date_filter = " AND created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
}

$where  = "transaction_type IN ('expense', 'expense_outflow', 'expense_incurred') $date_filter";
$sql    = "SELECT * FROM transactions WHERE $where ORDER BY created_at DESC";
$result = $conn->query($sql);

$expenses             = [];
$total_period_expense = 0;
$pending_bills_count  = 0;
$cat_breakdown        = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
        $total_period_expense += $row['amount'];
        preg_match('/\[(.*?)\]/', $row['notes'], $matches);
        $cat = $matches[1] ?? 'Uncategorized';
        if (stripos($row['notes'], 'pending') !== false) $pending_bills_count++;
        $cat_breakdown[$cat] = ($cat_breakdown[$cat] ?? 0) + $row['amount'];
    }
}

// Handle Export
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    if ($_GET['action'] === 'export_pdf' || $_GET['action'] === 'export_excel') {
        require_once __DIR__ . '/../../inc/ExportHelper.php';
    } else {
        require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    }

    $format      = match($_GET['action']) { 'export_excel' => 'excel', 'print_report' => 'print', default => 'pdf' };
    $export_data = [];
    foreach ($expenses as $ex) {
        preg_match('/\[(.*?)\]/', $ex['notes'], $cat_match);
        $display_cat = $cat_match[1] ?? 'General';
        $export_data[] = [
            'Date'          => date('d-m-Y', strtotime($ex['created_at'])),
            'Reference'     => $ex['reference_no'],
            'Payee/Details' => trim(str_replace(['[PENDING]', $cat_match[0] ?? ''], '', $ex['notes'])),
            'Category'      => $display_cat,
            'Amount'        => number_format((float)$ex['amount'], 2),
            'Status'        => (stripos($ex['notes'], 'pending') !== false) ? 'Pending' : 'Paid'
        ];
    }

    $title   = 'Expense_Ledger_' . date('Ymd_His');
    $headers = ['Date', 'Reference', 'Payee/Details', 'Category', 'Amount', 'Status'];

    if ($format === 'pdf')       ExportHelper::pdf('Expense Ledger', $headers, $export_data, $title . '.pdf');
    elseif ($format === 'excel') ExportHelper::csv($title . '.csv', $headers, $export_data);
    else                         UniversalExportEngine::handle($format, $export_data, ['title' => 'Expense Ledger', 'module' => 'Expense Management', 'headers' => $headers, 'total_value' => $total_period_expense]);
    exit;
}

$investments_list = $conn->query("SELECT investment_id, title FROM investments WHERE status = 'active' ORDER BY title ASC");
$investments_all  = $investments_list->fetch_all(MYSQLI_ASSOC);

// Top category
arsort($cat_breakdown);
$top_cat = !empty($cat_breakdown) ? array_key_first($cat_breakdown) : '—';


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
    --rose:         #c0392b;
    --rose-bg:      #fef0f0;
    --rose-border:  #f5c6c6;
    --amber-bg:     #fffbeb;
    --amber-border: #fde68a;
    --amber-text:   #92400e;
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
.hero-stat-value { font-size: 1.1rem; font-weight: 800; color: #fff; }
.hero-stat-value.rose { color: #fca5a5; }
.hero-stat-value.amber { color: #fde68a; }
.hero-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }

/* Buttons */
.btn-lime { background: var(--lime); color: var(--ink); border: none; font-weight: 700; font-size: .85rem; transition: var(--transition); box-shadow: 0 4px 14px rgba(168,224,99,.4); }
.btn-lime:hover { background: #baea78; color: var(--ink); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(168,224,99,.5); }
.btn-outline-hero { background: rgba(255,255,255,.1); color: rgba(255,255,255,.9); border: 1px solid rgba(255,255,255,.25); font-weight: 600; font-size: .83rem; transition: var(--transition); }
.btn-outline-hero:hover { background: rgba(255,255,255,.18); color: #fff; transform: translateY(-1px); }

/* ── Alert ──────────────────────────────────────────────────── */
.alert-custom { border: 0; border-radius: var(--radius-sm); padding: 14px 18px; font-size: .84rem; font-weight: 600; display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
.alert-custom.success { background: #f0fff6; color: #1a7a3f; border-left: 3px solid #2e6347; }
.alert-custom.danger  { background: var(--rose-bg); color: var(--rose); border-left: 3px solid var(--rose); }

/* ── Filter card ────────────────────────────────────────────── */
.filter-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 22px 24px; margin-bottom: 20px;
    box-shadow: var(--shadow-sm); animation: fadeUp .4s ease both; animation-delay: .06s;
}
.filter-label { font-size: .68rem; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); margin-bottom: 7px; }
.filter-select {
    width: 100%; height: 42px;
    border: 1.5px solid var(--border); border-radius: 10px;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .84rem; font-weight: 600; color: var(--ink); background: var(--surface-2);
    padding: 0 12px; transition: var(--transition); cursor: pointer;
}
.filter-select:focus { outline: none; border-color: var(--forest); background: #fff; box-shadow: 0 0 0 3px rgba(26,58,42,.07); }
.filter-date-row { display: flex; gap: 10px; flex-wrap: wrap; }
.filter-date-row > div { flex: 1; min-width: 140px; }
.date-input {
    width: 100%; height: 42px;
    border: 1.5px solid var(--border); border-radius: 10px;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .83rem; font-weight: 500; color: var(--ink); background: var(--surface-2);
    padding: 0 12px; transition: var(--transition);
}
.date-input:focus { outline: none; border-color: var(--forest); background: #fff; box-shadow: 0 0 0 3px rgba(26,58,42,.07); }
.btn-filter { height: 42px; border-radius: 10px; font-weight: 700; font-size: .84rem; background: var(--forest); color: #fff; border: none; padding: 0 22px; transition: var(--transition); cursor: pointer; }
.btn-filter:hover { background: var(--forest-light); transform: translateY(-1px); }
.btn-reset { width: 42px; height: 42px; border-radius: 10px; border: 1.5px solid var(--border); background: var(--surface); color: var(--muted); display: flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none; transition: var(--transition); flex-shrink: 0; font-size: .9rem; }
.btn-reset:hover { background: var(--surface-2); color: var(--ink); border-color: #c8d9cc; }

/* ── KPI cards ──────────────────────────────────────────────── */
.kpi-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 22px 24px;
    box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 16px;
    transition: var(--transition); animation: fadeUp .4s ease both;
}
.kpi-card:hover { box-shadow: var(--shadow-md); border-color: #d0ddd4; transform: translateY(-1px); }
.kpi-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
.kpi-icon.spend   { background: var(--lime-glow); color: var(--forest); }
.kpi-icon.pending { background: var(--amber-bg); color: var(--amber-text); border: 1px solid var(--amber-border); }
.kpi-icon.count   { background: var(--surface-2); color: var(--muted); border: 1px solid var(--border); }
.kpi-label { font-size: .67rem; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); margin-bottom: 5px; }
.kpi-value { font-size: 1.45rem; font-weight: 800; color: var(--ink); letter-spacing: -.3px; line-height: 1; }
.kpi-value .unit { font-size: .8rem; font-weight: 500; color: var(--muted); margin-left: 4px; }

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
.card-toolbar-title i { width: 28px; height: 28px; border-radius: 8px; background: var(--rose-bg); color: var(--rose); display: flex; align-items: center; justify-content: center; font-size: .9rem; }
.record-count { font-size: .72rem; font-weight: 700; background: var(--lime-glow); color: var(--forest); border: 1px solid rgba(168,224,99,.35); border-radius: 100px; padding: 4px 12px; }

/* Expense table */
.exp-table { width: 100%; border-collapse: collapse; }
.exp-table thead th { font-size: .67rem; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); background: var(--surface-2); padding: 13px 16px; border-bottom: 2px solid var(--border); white-space: nowrap; }
.exp-table tbody tr { border-bottom: 1px solid var(--border); transition: var(--transition); }
.exp-table tbody tr:last-child { border-bottom: none; }
.exp-table tbody tr:hover { background: #fdfaf8; }
.exp-table td { padding: 14px 16px; vertical-align: middle; }

.ref-val  { font-size: .85rem; font-weight: 700; color: var(--ink); font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; }
.ref-date { font-size: .73rem; color: var(--muted); font-weight: 500; margin-top: 2px; }
.payee-name { font-size: .86rem; font-weight: 700; color: var(--ink); }
.payee-sub  { font-size: .73rem; color: var(--muted); font-weight: 500; margin-top: 2px; }

.cat-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: .71rem; font-weight: 700; letter-spacing: .2px;
    border: 1.5px solid var(--border); border-radius: 8px; padding: 4px 12px;
    background: var(--surface-2); color: var(--ink); white-space: nowrap;
}

.amount-cell { text-align: right; }
.amount-val  { font-size: .95rem; font-weight: 800; color: var(--rose); }

.status-cell { text-align: right; padding-right: 20px !important; }
.status-pill { display: inline-flex; align-items: center; gap: 5px; font-size: .68rem; font-weight: 800; letter-spacing: .4px; text-transform: uppercase; border-radius: 100px; padding: 5px 13px; }
.status-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: .7; }
.status-pill.settled { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.status-pill.pending { background: var(--amber-bg); color: var(--amber-text); border: 1px solid var(--amber-border); }

/* Empty state */
.empty-state { text-align: center; padding: 52px 24px; color: var(--muted); }
.empty-state i { font-size: 2.5rem; opacity: .2; display: block; margin-bottom: 12px; }
.empty-state p { font-size: .84rem; margin: 0; }

/* ── Modal ──────────────────────────────────────────────────── */
.modal-content { border: 0 !important; border-radius: var(--radius-lg) !important; overflow: hidden; box-shadow: var(--shadow-lg); }
.modal-header-rose { background: linear-gradient(135deg, #7f1d1d, #991b1b); padding: 24px 28px; }
.modal-header-rose h5 { font-size: 1rem; font-weight: 800; color: #fff; margin: 0; display: flex; align-items: center; gap: 8px; }
.modal-body   { padding: 24px 28px !important; }
.modal-footer { border-top: 1px solid var(--border) !important; padding: 16px 28px !important; }
.form-label   { font-size: .78rem; font-weight: 700; color: var(--ink); margin-bottom: 7px; }
.form-control, .form-select {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .85rem; font-weight: 500;
    border: 1.5px solid var(--border); border-radius: 10px;
    padding: 10px 14px; color: var(--ink); background: var(--surface-2);
    transition: var(--transition);
}
.form-control:focus, .form-select:focus { border-color: var(--forest); background: #fff; box-shadow: 0 0 0 3px rgba(26,58,42,.08); outline: none; }
textarea.form-control { resize: vertical; min-height: 76px; }
.field-divider { height: 1px; background: var(--border); margin: 18px 0; }

.pending-check-wrap {
    display: flex; align-items: center; gap: 14px;
    background: var(--amber-bg); border: 1.5px solid var(--amber-border);
    border-radius: 10px; padding: 14px 16px; cursor: pointer;
    transition: var(--transition);
}
.pending-check-wrap:hover { background: #fef3c7; }
.pending-check-wrap input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--amber-text); cursor: pointer; flex-shrink: 0; }
.pending-check-label { font-size: .84rem; font-weight: 700; color: var(--amber-text); margin: 0; cursor: pointer; line-height: 1.3; }
.pending-check-sub   { font-size: .72rem; font-weight: 500; color: #a16207; margin-top: 2px; }

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
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Expenditure Portal</li>
            </ol>
        </nav>



        <!-- ═══ HERO ══════════════════════════════════════════════════════ -->
        <div class="page-header mb-4">
            <div class="hero-inner">
                <div>
                    <div class="hero-chip"><i class="bi bi-wallet2"></i>Finance · Expenditure</div>
                    <h1 class="hero-title">Expenditure Portal</h1>
                    <p class="hero-sub">Record and track office operational spending.</p>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-label">Total Spending</div>
                            <div class="hero-stat-value rose">KES <?= number_format($total_period_expense) ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Pending Bills</div>
                            <div class="hero-stat-value amber"><?= $pending_bills_count ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Entries</div>
                            <div class="hero-stat-value"><?= count($expenses) ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Top Category</div>
                            <div class="hero-stat-value" style="font-size:.85rem"><?= htmlspecialchars($top_cat) ?></div>
                        </div>
                    </div>
                </div>
                <div class="hero-actions">
                    <button class="btn btn-lime rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                        <i class="bi bi-plus-circle-fill me-2"></i>Record Expenditure
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-outline-hero rounded-pill px-4 fw-bold dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-2"></i>Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;overflow:hidden">
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf me-2 text-danger"></i>Export PDF</a></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-spreadsheet me-2 text-success"></i>Export Excel</a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer me-2 text-muted"></i>Print View</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert-custom success"><i class="bi bi-check-circle-fill"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-custom danger"><i class="bi bi-exclamation-triangle-fill"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- ═══ FILTER BAR ════════════════════════════════════════════════ -->
        <div class="filter-card">
            <form method="GET" id="filterForm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <div class="filter-label">Duration</div>
                        <select name="duration" class="filter-select" onchange="toggleDateInputs(this.value)">
                            <option value="all"      <?= $duration === 'all'      ? 'selected' : '' ?>>Historical Archive</option>
                            <option value="today"    <?= $duration === 'today'    ? 'selected' : '' ?>>Today</option>
                            <option value="weekly"   <?= $duration === 'weekly'   ? 'selected' : '' ?>>Past 7 Days</option>
                            <option value="monthly"  <?= $duration === 'monthly'  ? 'selected' : '' ?>>This Month</option>
                            <option value="3months"  <?= $duration === '3months'  ? 'selected' : '' ?>>Last Quarter</option>
                            <option value="custom"   <?= $duration === 'custom'   ? 'selected' : '' ?>>Custom Range</option>
                        </select>
                    </div>
                    <div id="customDateRange" class="col-md-5 <?= $duration !== 'custom' ? 'd-none' : '' ?>">
                        <div class="filter-label">Custom Range</div>
                        <div class="filter-date-row">
                            <div>
                                <input type="date" name="start_date" class="date-input" value="<?= htmlspecialchars($start_date) ?>" placeholder="Start">
                            </div>
                            <div>
                                <input type="date" name="end_date" class="date-input" value="<?= htmlspecialchars($end_date) ?>" placeholder="End">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-2 align-items-end">
                        <button type="submit" class="btn-filter flex-grow-1">
                            <i class="bi bi-funnel-fill me-2"></i>Filter View
                        </button>
                        <a href="expenses.php" class="btn-reset" title="Reset">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- ═══ KPIs ══════════════════════════════════════════════════════ -->
        <div class="row g-4 mb-4">
            <div class="col-md-4" style="animation-delay:.04s">
                <div class="kpi-card">
                    <div class="kpi-icon spend"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <div class="kpi-label">Total Spending</div>
                        <div class="kpi-value" style="color:var(--rose)">KES <?= number_format($total_period_expense) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4" style="animation-delay:.1s">
                <div class="kpi-card">
                    <div class="kpi-icon pending"><i class="bi bi-clock-history"></i></div>
                    <div>
                        <div class="kpi-label">Pending Bills</div>
                        <div class="kpi-value"><?= $pending_bills_count ?><span class="unit">Records</span></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4" style="animation-delay:.16s">
                <div class="kpi-card">
                    <div class="kpi-icon count"><i class="bi bi-journal-text"></i></div>
                    <div>
                        <div class="kpi-label">Entry Count</div>
                        <div class="kpi-value"><?= count($expenses) ?><span class="unit">Total</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ EXPENSE TABLE ════════════════════════════════════════════ -->
        <div class="detail-card">
            <div class="card-toolbar">
                <div class="card-toolbar-title">
                    <i class="bi bi-arrow-up-circle-fill d-flex"></i>
                    Expense Ledger
                </div>
                <?php if (!empty($expenses)): ?>
                <span class="record-count"><?= count($expenses) ?> entries</span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="exp-table">
                    <thead>
                        <tr>
                            <th style="padding-left:20px">Ref & Date</th>
                            <th>Payee / Details</th>
                            <th>Classification</th>
                            <th style="text-align:right">Amount</th>
                            <th style="text-align:right;padding-right:20px">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr><td colspan="5">
                            <div class="empty-state">
                                <i class="bi bi-journal-x"></i>
                                <p>No expense records found for the selected period.</p>
                            </div>
                        </td></tr>
                    <?php else: foreach ($expenses as $ex):
                        preg_match('/\[(.*?)\]/', $ex['notes'], $cat_match);
                        $display_cat = $cat_match[1] ?? 'General';
                        $is_pending  = $ex['transaction_type'] === 'expense_incurred';
                        $clean_notes = trim(str_replace(['[PENDING]', $cat_match[0] ?? ''], '', $ex['notes']));
                    ?>
                        <tr>
                            <td style="padding-left:20px">
                                <div class="ref-val"><?= htmlspecialchars($ex['reference_no'] ?: 'REF-' . $ex['ledger_transaction_id']) ?></div>
                                <div class="ref-date"><?= date('d M Y', strtotime($ex['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="payee-name"><?= htmlspecialchars($clean_notes) ?></div>
                                <div class="payee-sub"><?= $ex['related_id'] ? '<i class="bi bi-link-45deg me-1"></i>Linked to Asset' : '<i class="bi bi-building me-1"></i>Office Operation' ?></div>
                            </td>
                            <td><span class="cat-badge"><?= htmlspecialchars($display_cat) ?></span></td>
                            <td class="amount-cell">
                                <div class="amount-val">KES <?= number_format((float)$ex['amount'], 2) ?></div>
                            </td>
                            <td class="status-cell">
                                <span class="status-pill <?= $is_pending ? 'pending' : 'settled' ?>">
                                    <?= $is_pending ? 'Pending' : 'Settled' ?>
                                </span>
                                <?php if ($is_pending): ?>
                                <form method="POST" style="display:inline-flex; align-items:center; gap:5px; margin-left:8px;"
                                      onsubmit="return confirm('Settle this bill? This will deduct from the selected account.');"
                                >
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="settle_expense">
                                    <input type="hidden" name="transaction_id" value="<?= $ex['ledger_transaction_id'] ?>">
                                    <select name="settle_method" style="height:26px;border:1.5px solid var(--amber-border);border-radius:6px;font-size:0.65rem;font-weight:700;color:var(--amber-text);background:var(--amber-bg);padding:0 6px;cursor:pointer;">
                                        <option value="cash">Cash</option>
                                        <option value="bank">Bank</option>
                                        <option value="mpesa">M-Pesa</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-lime" style="padding:3px 10px;font-size:0.65rem;border-radius:6px;box-shadow:none;">Settle</button>
                                </form>
                                <?php endif; ?>
                            </td>

                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ RECORD EXPENSE MODAL ═════════════════════════════════════ -->
        <div class="modal fade" id="addExpenseModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width:560px">
                <div class="modal-content">
                    <div class="modal-header-rose">
                        <div style="display:flex;align-items:center;justify-content:space-between;width:100%">
                            <h5><i class="bi bi-wallet2"></i>Record Expenditure</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                    </div>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_expense">
                        <div class="modal-body">

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Expense Category <span class="text-danger">*</span></label>
                                    <select name="category" class="form-select" required>
                                        <option value="Maintenance">Vehicle Maintenance</option>
                                        <option value="Fuel">Fuel & Petroleum</option>
                                        <option value="Salaries">Staff Payroll</option>
                                        <option value="Rent">Office / Property Rent</option>
                                        <option value="Utilities">Utilities & Bills</option>
                                        <option value="Office">Admin & Sundries</option>
                                        <option value="Legal">Legal & Professional</option>
                                        <option value="Other">Miscellaneous</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Link to Asset <span class="text-muted fw-normal">(optional)</span></label>
                                    <select name="unified_asset_id" class="form-select">
                                        <option value="other_0">General Operational</option>
                                        <?php foreach ($investments_all as $inv): ?>
                                            <option value="inv_<?= $inv['investment_id'] ?>"><?= htmlspecialchars($inv['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Payee / Vendor Name <span class="text-danger">*</span></label>
                                <input type="text" name="payee" class="form-control" placeholder="e.g. Apex Mechanics" required>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Amount (KES) <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" class="form-control fw-bold" step="0.01" min="0.01" required placeholder="0.00">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Expense Date</label>
                                    <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Reference / Receipt No. <span class="text-danger">*</span></label>
                                    <input type="text" name="ref_no" class="form-control" placeholder="TXN-XXXX" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Payment Source</label>
                                    <select name="payment_method" class="form-select" required>
                                        <option value="cash">Cash Float</option>
                                        <option value="mpesa">M-Pesa Business</option>
                                        <option value="bank">Bank Wire</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Internal Notes <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea name="description" class="form-control" placeholder="Audit notes or additional context…"></textarea>
                            </div>

                            <div class="field-divider"></div>

                            <label class="pending-check-wrap" for="pendingCheck">
                                <input type="checkbox" name="is_pending" id="pendingCheck">
                                <div>
                                    <div class="pending-check-label">Mark as Outstanding Liability</div>
                                    <div class="pending-check-sub">This expense has not yet been paid / settled.</div>
                                </div>
                            </label>

                        </div>
                        <div class="modal-footer justify-content-end gap-2">
                            <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-lime rounded-pill fw-bold px-5 text-dark">Authorize & Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script>
function toggleDateInputs(val) {
    const range = document.getElementById('customDateRange');
    if (val === 'custom') {
        range.classList.remove('d-none');
    } else {
        range.classList.add('d-none');
        document.getElementById('filterForm').submit();
    }
}
</script>
</body>
</html>