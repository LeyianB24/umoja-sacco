<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/SupportTicketWidget.php';

$layout = LayoutManager::create('admin');

require_once __DIR__ . '/../../inc/FinancialEngine.php';
require_once __DIR__ . '/../../inc/TransactionHelper.php';

\USMS\Middleware\AuthMiddleware::requireModulePermission('finance');

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_txn') {
    verify_csrf_token();

    $member_id = !empty($_POST['member_id']) ? intval($_POST['member_id']) : null;
    $type      = $_POST['transaction_type'] ?? '';
    $amount    = floatval($_POST['amount'] ?? 0);
    $notes     = trim($_POST['notes'] ?? '');
    $ref_no    = trim($_POST['reference_no'] ?? '');
    $date      = $_POST['txn_date'] ?? date('Y-m-d');

    if ($amount <= 0) {
        $_SESSION['error'] = "Amount must be greater than zero.";
    } elseif (in_array($type, ['deposit', 'withdrawal', 'loan_repayment', 'share_capital']) && empty($member_id)) {
        $_SESSION['error'] = "Member selection is required for this transaction type.";
    } else {
        $conn->begin_transaction();
        try {
            $method = $_POST['payment_method'] ?? 'cash';

            $category = match($type) {
                'deposit'        => 'savings',
                'withdrawal'     => 'wallet',
                'share_capital'  => 'shares',
                'loan_repayment' => 'loans',
                'expense'        => 'expense',
                'income'         => 'income',
                default          => 'general'
            };

            $related_id = null;
            $related_table = null;

            if ($type === 'loan_repayment' && $member_id) {
                $l_res = $conn->query("SELECT loan_id FROM loans WHERE member_id = $member_id AND status = 'disbursed' LIMIT 1");
                if ($l_res && $l_res->num_rows > 0) {
                    $related_id = $l_res->fetch_assoc()['loan_id'];
                    $related_table = 'loans';
                }
            } elseif (in_array($type, ['expense', 'income'])) {
                $unified_id = $_POST['unified_asset_id'] ?? 'other_0';
                if ($unified_id !== 'other_0') {
                    list($source, $related_id) = explode('_', $unified_id);
                    $related_id = (int)$related_id;
                    $related_table = 'investments';
                }
            }

            $ok = TransactionHelper::record([
                'member_id'     => $member_id,
                'amount'        => $amount,
                'type'          => $type,
                'category'      => $category,
                'ref_no'        => $ref_no,
                'notes'         => $notes,
                'method'        => $method,
                'related_id'    => $related_id,
                'related_table' => $related_table
            ]);

            if (!$ok) throw new Exception("Failed to record transaction in ledger.");

            $conn->commit();

            if ($member_id && in_array($type, ['deposit', 'share_capital'])) {
                require_once __DIR__ . '/../../inc/notification_helpers.php';
                send_notification($conn, (int)$member_id, 'deposit_success', ['amount' => $amount, 'ref' => $ref_no]);
            }

            $_SESSION['success'] = "Transaction recorded successfully!";
            header("Location: payments.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }
}

// A. Members List
$members = [];
$res = $conn->query("SELECT member_id, full_name, national_id FROM members ORDER BY full_name ASC");
while ($row = $res->fetch_assoc()) $members[] = $row;

// B. Active Investments
$investments = $conn->query("SELECT investment_id, title FROM investments WHERE status = 'active' ORDER BY title ASC");
$investments_all = $investments->fetch_all(MYSQLI_ASSOC);

// C. Filter / Search
$where = "1";
$params = [];
$types = "";

if (!empty($_GET['type'])) {
    $where .= " AND t.transaction_type = ?";
    $params[] = $_GET['type'];
    $types .= "s";
}
if (!empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where .= " AND (t.reference_no LIKE ? OR m.full_name LIKE ?)";
    $params[] = $search; $params[] = $search;
    $types .= "ss";
}

// HANDLE INDIVIDUAL RECEIPT DOWNLOAD
if (isset($_GET['action']) && $_GET['action'] === 'download_receipt') {
    $txn_id = intval($_GET['id'] ?? 0);
    if ($txn_id > 0) {
        require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
        
        $sql_receipt = "SELECT t.*, m.full_name, m.national_id, m.phone, m.member_reg_no
                        FROM transactions t 
                        LEFT JOIN members m ON t.member_id = m.member_id 
                        WHERE t.transaction_id = ?";
        $stmt_r = $conn->prepare($sql_receipt);
        $stmt_r->bind_param("i", $txn_id);
        $stmt_r->execute();
        $txn = $stmt_r->get_result()->fetch_assoc();
        
        if ($txn) {
            UniversalExportEngine::handle('pdf', function($pdf) use ($txn) {
                $pdf->SetFont('Arial', 'B', 14);
                $pdf->Cell(0, 10, 'OFFICIAL PAYMENT RECEIPT', 0, 1, 'C');
                $pdf->Ln(5);
                
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(40, 7, 'Receipt No:', 0, 0);
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(60, 7, $txn['reference_no'], 0, 1);
                
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(40, 7, 'Date:', 0, 0);
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(60, 7, date('d M Y h:i A', strtotime($txn['transaction_date'] ?? $txn['created_at'])), 0, 1);
                
                $pdf->Ln(5);
                $pdf->SetFillColor(240, 240, 240);
                $pdf->SetFont('Arial', 'B', 11);
                $pdf->Cell(0, 8, '  MEMBER DETAILS', 0, 1, 'L', true);
                $pdf->Ln(2);
                
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(40, 7, 'Name:', 0, 0);
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(0, 7, $txn['full_name'] ?? 'OFFICE / GENERAL', 0, 1);
                
                if (!empty($txn['national_id'])) {
                    $pdf->SetFont('Arial', 'B', 10);
                    $pdf->Cell(40, 7, 'ID Number:', 0, 0);
                    $pdf->SetFont('Arial', '', 10);
                    $pdf->Cell(0, 7, $txn['national_id'], 0, 1);
                }
                
                $pdf->Ln(5);
                $pdf->SetFillColor(240, 240, 240);
                $pdf->SetFont('Arial', 'B', 11);
                $pdf->Cell(0, 8, '  TRANSACTION DETAILS', 0, 1, 'L', true);
                $pdf->Ln(2);
                
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(40, 7, 'Type:', 0, 0);
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(0, 7, ucwords(str_replace('_', ' ', $txn['transaction_type'])), 0, 1);
                
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(40, 7, 'Method:', 0, 0);
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(0, 7, strtoupper($txn['payment_channel'] ?? 'CASH'), 0, 1);
                
                if (!empty($txn['notes'])) {
                    $pdf->SetFont('Arial', 'B', 10);
                    $pdf->Cell(40, 7, 'Notes:', 0, 0);
                    $pdf->SetFont('Arial', '', 10);
                    $pdf->MultiCell(0, 7, $txn['notes'], 0, 'L');
                }
                
                $pdf->Ln(10);
                $pdf->SetDrawColor(200, 200, 200);
                $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
                $pdf->Ln(5);
                
                $pdf->SetFont('Arial', 'B', 16);
                $pdf->Cell(120, 10, 'AMOUNT PAID:', 0, 0, 'R');
                $pdf->SetTextColor(27, 94, 32);
                $pdf->Cell(0, 10, 'KES ' . number_format((float)$txn['amount'], 2), 0, 1, 'R');
                
                $pdf->Ln(20);
                $pdf->SetTextColor(100, 100, 100);
                $pdf->SetFont('Arial', 'I', 9);
                $pdf->Cell(0, 5, 'This is a computer generated receipt and does not require a signature.', 0, 1, 'C');
                $pdf->Cell(0, 5, 'Thank you for choosing ' . SITE_NAME . '.', 0, 1, 'C');
                
            }, [
                'title' => 'Payment Receipt',
                'module' => 'Finance',
                'account_ref' => $txn['reference_no'],
                'total_value' => (float)$txn['amount']
            ]);
        }
    }
    header("Location: payments.php");
    exit;
}

// HANDLE EXPORT
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    if ($_GET['action'] === 'export_pdf' || $_GET['action'] === 'export_excel') {
        require_once __DIR__ . '/../../inc/ExportHelper.php';
    } else {
        require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    }

    $sql_export = "SELECT t.*, m.full_name, m.national_id FROM transactions t LEFT JOIN members m ON t.member_id = m.member_id WHERE $where ORDER BY t.created_at DESC";
    $stmt_e = $conn->prepare($sql_export);
    if (!empty($params)) $stmt_e->bind_param($types, ...$params);
    $stmt_e->execute();
    $export_data_raw = $stmt_e->get_result()->fetch_all(MYSQLI_ASSOC);

    $format = match($_GET['action']) { 'export_excel' => 'excel', 'print_report' => 'print', default => 'pdf' };

    $data = []; $total_val = 0;
    foreach ($export_data_raw as $row) {
        $total_val += (float)$row['amount'];
        $data[] = [
            'Date'          => date('d-M-Y', strtotime($row['created_at'])),
            'Reference'     => $row['reference_no'],
            'Member/Entity' => $row['full_name'] ?? 'Office',
            'Type'          => ucwords(str_replace('_', ' ', $row['transaction_type'])),
            'Amount'        => number_format((float)$row['amount'], 2),
            'Notes'         => $row['notes']
        ];
    }

    $title   = 'Payments_Ledger_' . date('Ymd_His');
    $headers = ['Date', 'Reference', 'Member/Entity', 'Type', 'Amount', 'Notes'];

    if ($format === 'pdf')         ExportHelper::pdf('Payments Ledger', $headers, $data, $title . '.pdf');
    elseif ($format === 'excel')   ExportHelper::csv($title . '.csv', $headers, $data);
    else                           UniversalExportEngine::handle($format, $data, ['title' => 'Payments Ledger', 'module' => 'Finance Management', 'headers' => $headers, 'total_value' => $total_val]);
    exit;
}

$sql = "SELECT t.*, m.full_name, m.national_id, i.title as asset_title
        FROM transactions t
        LEFT JOIN members m ON t.member_id = m.member_id
        LEFT JOIN investments i ON t.related_table = 'investments' AND t.related_id = i.investment_id
        WHERE $where ORDER BY t.created_at DESC LIMIT 50";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result();

// Summary stats
$stats_res = $conn->query("
    SELECT
        SUM(CASE WHEN transaction_type IN ('deposit','savings_deposit','share_purchase','revenue_inflow','loan_repayment','share_capital') THEN amount ELSE 0 END) as total_in,
        SUM(CASE WHEN transaction_type IN ('withdrawal','expense') THEN amount ELSE 0 END) as total_out,
        COUNT(*) as total_count
    FROM transactions
");
$stats = $stats_res->fetch_assoc();

$pageTitle = "Payments Ledger";
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
.detail-card, .modal-content,
.table, select, input, textarea, button {
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
    --rose-bg:      #fef0f5;
    --rose-border:  #f5c6d8;
    --rose-text:    #be185d;
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
    border-radius: var(--radius-lg); padding: 36px 40px;
    margin-bottom: 28px; position: relative; overflow: hidden;
    box-shadow: var(--shadow-lg);
    animation: fadeUp .35s ease both;
}
.page-header::before {
    content: ''; position: absolute; inset: 0;
    background:
        radial-gradient(ellipse 60% 80% at 90% -10%, rgba(168,224,99,.22) 0%, transparent 60%),
        radial-gradient(ellipse 40% 50% at -5% 100%, rgba(168,224,99,.08) 0%, transparent 55%);
    pointer-events: none;
}
.page-header::after {
    content: ''; position: absolute; right: -60px; top: -60px;
    width: 260px; height: 260px; border-radius: 50%;
    border: 1px solid rgba(168,224,99,.1); pointer-events: none;
}
.hero-inner { position: relative; z-index: 1; display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 24px; }
.hero-title  { font-size: clamp(1.5rem, 2.5vw, 2rem); font-weight: 800; color: #fff; letter-spacing: -.5px; margin: 0 0 6px; }
.hero-sub    { font-size: .85rem; color: rgba(255,255,255,.65); font-weight: 500; margin: 0 0 22px; }

/* Hero stat strip */
.hero-stats { display: flex; gap: 12px; flex-wrap: wrap; }
.hero-stat {
    background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12);
    border-radius: var(--radius-sm); padding: 10px 18px; backdrop-filter: blur(4px);
}
.hero-stat-label { font-size: .65rem; font-weight: 600; letter-spacing: .5px; text-transform: uppercase; color: rgba(255,255,255,.5); margin-bottom: 3px; }
.hero-stat-value { font-size: 1.1rem; font-weight: 800; color: #fff; }
.hero-stat-value.lime  { color: var(--lime); }
.hero-stat-value.rose  { color: #f9a8d4; }

/* Hero action buttons */
.hero-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
.btn-lime {
    background: var(--lime); color: var(--ink); border: none;
    font-weight: 700; font-size: .85rem; transition: var(--transition);
    box-shadow: 0 4px 14px rgba(168,224,99,.4);
}
.btn-lime:hover { background: #baea78; color: var(--ink); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(168,224,99,.5); }
.btn-outline-hero {
    background: rgba(255,255,255,.1); color: rgba(255,255,255,.9);
    border: 1px solid rgba(255,255,255,.25); font-weight: 600; font-size: .83rem;
    transition: var(--transition);
}
.btn-outline-hero:hover { background: rgba(255,255,255,.18); color: #fff; transform: translateY(-1px); }

/* ── Finance nav (pass-through) ─────────────────────────────── */

/* ── Alert banners ──────────────────────────────────────────── */
.alert-custom {
    border: 0; border-radius: var(--radius-sm); padding: 14px 18px;
    font-size: .84rem; font-weight: 600; display: flex; align-items: center; gap: 10px;
    margin-bottom: 18px;
}
.alert-custom.success { background: #f0fff6; color: #1a7a3f; border-left: 3px solid #2e6347; }
.alert-custom.danger  { background: #fef0f0; color: #c0392b; border-left: 3px solid #e74c3c; }

/* ── Filter card ────────────────────────────────────────────── */
.filter-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 22px 24px; margin-bottom: 20px;
    box-shadow: var(--shadow-sm);
    animation: fadeUp .4s ease both; animation-delay: .08s;
}
.filter-card .form-label {
    font-size: .68rem; font-weight: 800; letter-spacing: .8px;
    text-transform: uppercase; color: var(--muted); margin-bottom: 7px;
}
.search-group { display: flex; align-items: center; }
.search-group-icon {
    width: 42px; height: 42px;
    background: var(--surface-2); border: 1.5px solid var(--border); border-right: none;
    border-radius: 10px 0 0 10px;
    display: flex; align-items: center; justify-content: center;
    color: var(--muted); font-size: .85rem; flex-shrink: 0;
}
.search-group input {
    flex: 1; height: 42px;
    border: 1.5px solid var(--border); border-left: none;
    border-radius: 0 10px 10px 0;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .84rem; font-weight: 500; color: var(--ink); background: var(--surface-2);
    padding: 0 14px; transition: var(--transition);
}
.search-group input:focus {
    outline: none; border-color: var(--forest); background: #fff;
    box-shadow: 0 0 0 3px rgba(26,58,42,.07);
}
.form-select {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .84rem; font-weight: 600;
    border: 1.5px solid var(--border); border-radius: 10px;
    height: 42px; color: var(--ink); background: var(--surface-2);
    padding: 0 14px; transition: var(--transition); cursor: pointer;
}
.form-select:focus { outline: none; border-color: var(--forest); background: #fff; box-shadow: 0 0 0 3px rgba(26,58,42,.07); }

.btn-filter {
    height: 42px; border-radius: 10px; font-weight: 700; font-size: .84rem;
    background: var(--forest); color: #fff; border: none;
    padding: 0 22px; transition: var(--transition); cursor: pointer;
}
.btn-filter:hover { background: var(--forest-light); transform: translateY(-1px); }
.btn-clear { font-size: .82rem; font-weight: 600; color: var(--muted); text-decoration: none; transition: var(--transition); }
.btn-clear:hover { color: var(--ink); }

/* ── Transactions table card ────────────────────────────────── */
.detail-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); overflow: hidden;
    box-shadow: var(--shadow-sm); transition: var(--transition);
    animation: fadeUp .4s ease both; animation-delay: .14s;
}
.detail-card:hover { box-shadow: var(--shadow-md); border-color: #d0ddd4; }

.card-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 24px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px;
}
.card-toolbar-title {
    font-size: .7rem; font-weight: 800; letter-spacing: 1.2px;
    text-transform: uppercase; color: var(--forest);
    display: flex; align-items: center; gap: 8px;
}
.card-toolbar-title i {
    width: 28px; height: 28px; border-radius: 8px;
    background: var(--lime-glow); color: var(--forest);
    display: flex; align-items: center; justify-content: center; font-size: .9rem;
}
.record-count {
    font-size: .72rem; font-weight: 700;
    background: var(--lime-glow); color: var(--forest);
    border: 1px solid rgba(168,224,99,.35); border-radius: 100px; padding: 4px 12px;
}

/* ── Table ──────────────────────────────────────────────────── */
.txn-table { width: 100%; border-collapse: collapse; }
.txn-table thead th {
    font-size: .67rem; font-weight: 700; letter-spacing: .8px;
    text-transform: uppercase; color: var(--muted); background: var(--surface-2);
    padding: 13px 16px; border-bottom: 2px solid var(--border); white-space: nowrap;
}
.txn-table tbody tr { border-bottom: 1px solid var(--border); transition: var(--transition); }
.txn-table tbody tr:last-child { border-bottom: none; }
.txn-table tbody tr:hover { background: #f9fcf9; }
.txn-table td { padding: 15px 16px; vertical-align: middle; }

/* Avatar */
.txn-avatar {
    width: 42px; height: 42px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: .9rem; flex-shrink: 0;
    transition: var(--transition); border: 2px solid transparent;
}
.txn-avatar.in  { background: var(--lime-glow); color: var(--forest); }
.txn-avatar.out { background: var(--rose-bg); color: var(--rose-text); }
tr:hover .txn-avatar.in  { border-color: rgba(168,224,99,.5); box-shadow: 0 0 0 3px var(--lime-glow); }
tr:hover .txn-avatar.out { border-color: var(--rose-border); box-shadow: 0 0 0 3px var(--rose-bg); }

.member-cell { display: flex; align-items: center; gap: 12px; }
.member-name a { font-size: .88rem; font-weight: 700; color: var(--ink); text-decoration: none; transition: var(--transition); }
.member-name a:hover { color: var(--forest); }
.member-name .plain { font-size: .88rem; font-weight: 700; color: var(--ink); }
.member-id { font-size: .72rem; color: var(--muted); font-weight: 500; margin-top: 2px; }

.ref-cell .ref { font-size: .83rem; font-weight: 700; color: var(--ink); font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; }
.ref-cell .date { font-size: .73rem; color: var(--muted); font-weight: 500; margin-top: 2px; }

/* Type badge */
.type-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: .7rem; font-weight: 700; letter-spacing: .3px;
    text-transform: uppercase; border-radius: 8px; padding: 5px 12px;
    white-space: nowrap;
}
.type-badge.in  { background: var(--lime-glow); color: var(--forest); border: 1px solid rgba(168,224,99,.4); }
.type-badge.out { background: var(--rose-bg); color: var(--rose-text); border: 1px solid var(--rose-border); }

/* Amount */
.amount-cell { text-align: right; }
.amount-val { font-size: .95rem; font-weight: 800; line-height: 1; }
.amount-val.in  { color: #1a7a3f; }
.amount-val.out { color: var(--rose-text); }

/* Notes */
.notes-cell { max-width: 200px; }
.asset-chip {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: .65rem; font-weight: 700;
    background: var(--forest); color: var(--lime);
    border-radius: 100px; padding: 3px 9px; margin-bottom: 5px;
}
.notes-text { font-size: .78rem; color: var(--muted); font-weight: 500; }

/* Action col */
.action-col { text-align: right; padding-right: 20px !important; }
.btn-receipt {
    width: 34px; height: 34px; border-radius: 9px;
    border: 1.5px solid var(--border); background: var(--surface);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .82rem; color: var(--muted); cursor: pointer;
    transition: var(--transition);
}
.btn-receipt:hover { background: var(--lime-glow); border-color: var(--lime); color: var(--forest); transform: translateY(-1px); }

/* Empty state */
.empty-state { text-align: center; padding: 52px 24px; color: var(--muted); }
.empty-state i { font-size: 2.5rem; opacity: .2; display: block; margin-bottom: 12px; }
.empty-state p { font-size: .84rem; margin: 0; }

/* ── Modals ─────────────────────────────────────────────────── */
.modal-content { border: 0 !important; border-radius: var(--radius-lg) !important; overflow: hidden; box-shadow: var(--shadow-lg); }
.modal-header  { border-bottom: 0 !important; padding: 28px 28px 0 !important; }
.modal-body    { padding: 20px 28px 28px !important; }
.modal-footer  { border-top: 0 !important; padding: 0 28px 28px !important; }

.modal-header-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: var(--lime-glow); color: var(--forest);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; margin-bottom: 14px;
}
.form-label { font-size: .78rem; font-weight: 700; color: var(--ink); margin-bottom: 7px; }
.form-control, .modal .form-select {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .85rem; font-weight: 500;
    border: 1.5px solid var(--border); border-radius: 10px;
    padding: 10px 14px; color: var(--ink); background: var(--surface-2);
    transition: var(--transition);
}
.form-control:focus, .modal .form-select:focus {
    border-color: var(--forest); background: #fff;
    box-shadow: 0 0 0 3px rgba(26,58,42,.08); outline: none;
}
textarea.form-control { resize: vertical; min-height: 76px; }
.field-divider { height: 1px; background: var(--border); margin: 18px 0; }

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
                <li class="breadcrumb-item active">Payments Ledger</li>
            </ol>
        </nav>

        <!-- ═══ HERO ══════════════════════════════════════════════════════ -->
        <div class="page-header mb-4">
            <div class="hero-inner">
                <div>
                    <h1 class="hero-title">Transactions Ledger</h1>
                    <p class="hero-sub">Monitor all financial inflows and outflows across the Sacco.</p>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-label">Total Inflow</div>
                            <div class="hero-stat-value lime">KES <?= number_format((float)($stats['total_in'] ?? 0)) ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Total Outflow</div>
                            <div class="hero-stat-value rose">KES <?= number_format((float)($stats['total_out'] ?? 0)) ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Transactions</div>
                            <div class="hero-stat-value"><?= number_format((int)($stats['total_count'] ?? 0)) ?></div>
                        </div>
                    </div>
                </div>
                <div class="hero-actions">
                    <button class="btn btn-lime rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#recordTxnModal">
                        <i class="bi bi-plus-circle-fill me-2"></i>New Transaction
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-outline-hero rounded-pill px-4 fw-bold dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-2"></i>Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;overflow:hidden">
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf me-2 text-danger"></i>Export PDF</a></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-spreadsheet me-2 text-success"></i>Export Excel</a></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer me-2 text-muted"></i>Print Report</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Finance Nav -->

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert-custom success"><i class="bi bi-check-circle-fill"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-custom danger"><i class="bi bi-exclamation-triangle-fill"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php render_support_ticket_widget($conn, ['savings', 'withdrawals'], 'Savings & Withdrawals'); ?>

        <!-- ═══ FILTER BAR ════════════════════════════════════════════════ -->
        <div class="filter-card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <div class="search-group">
                        <div class="search-group-icon"><i class="bi bi-search"></i></div>
                        <input type="text" name="search" placeholder="Reference, member name…" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Transaction Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="deposit"        <?= ($_GET['type'] ?? '') === 'deposit'        ? 'selected' : '' ?>>Deposit (Savings)</option>
                        <option value="withdrawal"     <?= ($_GET['type'] ?? '') === 'withdrawal'     ? 'selected' : '' ?>>Withdrawal</option>
                        <option value="loan_repayment" <?= ($_GET['type'] ?? '') === 'loan_repayment' ? 'selected' : '' ?>>Loan Repayment</option>
                        <option value="share_capital"  <?= ($_GET['type'] ?? '') === 'share_capital'  ? 'selected' : '' ?>>Share Capital</option>
                        <option value="revenue_inflow" <?= ($_GET['type'] ?? '') === 'revenue_inflow' ? 'selected' : '' ?>>Revenue Inflow</option>
                        <option value="expense"        <?= ($_GET['type'] ?? '') === 'expense'        ? 'selected' : '' ?>>Expense</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn-filter w-100">
                        <i class="bi bi-funnel-fill me-2"></i>Filter
                    </button>
                </div>
                <?php if (!empty($_GET['search']) || !empty($_GET['type'])): ?>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="payments.php" class="btn-clear">
                        <i class="bi bi-x-circle me-1"></i>Clear Filters
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- ═══ TRANSACTIONS TABLE ════════════════════════════════════════ -->
        <div class="detail-card">
            <div class="card-toolbar">
                <div class="card-toolbar-title">
                    <i class="bi bi-arrow-left-right d-flex"></i>
                    Transaction Records
                </div>
                <span class="record-count">Showing last 50 entries</span>
            </div>

            <div class="table-responsive">
                <table class="txn-table">
                    <thead>
                        <tr>
                            <th style="padding-left:20px">Member / Entity</th>
                            <th>Reference & Date</th>
                            <th>Type</th>
                            <th style="text-align:right">Amount</th>
                            <th>Notes</th>
                            <th style="text-align:right;padding-right:20px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($transactions->num_rows > 0): ?>
                        <?php while ($row = $transactions->fetch_assoc()):
                            $in_types   = ['deposit', 'savings_deposit', 'loan_repayment', 'share_purchase', 'revenue_inflow', 'share_capital'];
                            $is_in      = in_array($row['transaction_type'], $in_types);
                            $dir        = $is_in ? 'in' : 'out';
                            $sign       = $is_in ? '+' : '−';
                            $name       = $row['full_name'] ?? 'Office';
                            $initials   = strtoupper(substr($name, 0, 2));
                        ?>
                        <tr>
                            <td style="padding-left:20px">
                                <div class="member-cell">
                                    <div class="txn-avatar <?= $dir ?>"><?= $initials ?></div>
                                    <div>
                                        <div class="member-name">
                                            <?php if ($row['member_id']): ?>
                                                <a href="member_profile.php?id=<?= $row['member_id'] ?>">
                                                    <?= htmlspecialchars($name) ?> <i class="bi bi-box-arrow-up-right ms-1" style="font-size:.65rem;opacity:.5"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="plain"><?= htmlspecialchars($name) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($row['national_id']): ?>
                                            <div class="member-id">ID: <?= htmlspecialchars($row['national_id']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="ref-cell">
                                <div class="ref"><?= htmlspecialchars($row['reference_no']) ?></div>
                                <div class="date"><?= date('d M Y', strtotime($row['created_at'])) ?></div>
                            </td>
                            <td>
                                <span class="type-badge <?= $dir ?>">
                                    <?= ucwords(str_replace('_', ' ', $row['transaction_type'])) ?>
                                </span>
                            </td>
                            <td class="amount-cell">
                                <div class="amount-val <?= $dir ?>"><?= $sign ?> KES <?= number_format((float)$row['amount'], 2) ?></div>
                            </td>
                            <td class="notes-cell">
                                <?php if ($row['asset_title']): ?>
                                    <div class="asset-chip"><i class="bi bi-tag-fill"></i><?= htmlspecialchars($row['asset_title']) ?></div>
                                <?php endif; ?>
                                <div class="notes-text"><?= htmlspecialchars($row['notes'] ?? '') ?></div>
                            </td>
                            <td class="action-col">
                                <a href="?action=download_receipt&id=<?= $row['transaction_id'] ?>" class="btn-receipt" title="Download Receipt"><i class="bi bi-download"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    <p>No transactions found<?= (!empty($_GET['search']) || !empty($_GET['type'])) ? ' matching your filters' : '' ?>.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ RECORD TRANSACTION MODAL ════════════════════════════════= -->
        <div class="modal fade" id="recordTxnModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <div class="modal-header-icon"><i class="bi bi-plus-circle-fill"></i></div>
                            <h5 class="fw-800 mb-1" style="color:var(--ink)">Record Transaction</h5>
                            <p class="text-muted mb-0" style="font-size:.82rem">Log a new financial entry in the ledger.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="record_txn">
                        <div class="modal-body">

                            <div class="mb-3">
                                <label class="form-label">Transaction Type <span class="text-danger">*</span></label>
                                <select name="transaction_type" id="txnType" class="form-control" required onchange="toggleMemberField()">
                                    <option value="" disabled selected>Select type…</option>
                                    <option value="deposit">Deposit (Savings)</option>
                                    <option value="withdrawal">Withdrawal</option>
                                    <option value="loan_repayment">Loan Repayment</option>
                                    <option value="share_capital">Share Capital</option>
                                    <option value="expense">Office Expense</option>
                                    <option value="income">Revenue Inflow</option>
                                </select>
                            </div>

                            <div class="mb-3" id="memberField">
                                <label class="form-label">Select Member</label>
                                <select name="member_id" class="form-control">
                                    <option value="">Search or select member…</option>
                                    <?php foreach ($members as $m): ?>
                                        <option value="<?= $m['member_id'] ?>"><?= htmlspecialchars($m['full_name']) ?> (ID: <?= $m['national_id'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3 d-none" id="assetField">
                                <label class="form-label">Attribute to Asset <span class="text-muted fw-normal">(optional)</span></label>
                                <select name="unified_asset_id" class="form-control">
                                    <option value="other_0">General / Unassigned</option>
                                    <?php foreach ($investments_all as $inv): ?>
                                        <option value="inv_<?= $inv['investment_id'] ?>"><?= htmlspecialchars($inv['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field-divider"></div>

                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">Amount (KES) <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Transaction Date</label>
                                    <input type="date" name="txn_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-control" required>
                                    <option value="cash">Cash at Hand</option>
                                    <option value="mpesa">M-Pesa Float</option>
                                    <option value="bank">Bank Account</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Reference No. <span class="text-danger">*</span></label>
                                <input type="text" name="reference_no" class="form-control" placeholder="e.g. MPESA-QWE12345" required>
                            </div>

                            <div class="mb-1">
                                <label class="form-label">Notes <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea name="notes" class="form-control" placeholder="Description or remarks…"></textarea>
                            </div>

                        </div>
                        <div class="modal-footer justify-content-end gap-2">
                            <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-lime rounded-pill fw-bold px-5">Save Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script>
function toggleMemberField() {
    const type     = document.getElementById('txnType').value;
    const memberDiv = document.getElementById('memberField');
    const assetDiv  = document.getElementById('assetField');

    if (type === 'expense' || type === 'income') {
        memberDiv.classList.add('d-none');
        assetDiv.classList.remove('d-none');
    } else {
        memberDiv.classList.remove('d-none');
        assetDiv.classList.add('d-none');
    }
}
</script>
</body>
</html>