<?php
/**
 * accountant/reports.php
 * Super Enhanced Executive Dashboard
 */

declare(strict_types=1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/ReportGenerator.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_permission();
Auth::requireAdmin();
$layout = LayoutManager::create('admin');

if (!function_exists('calc_growth')) {
    function calc_growth($current, $previous) {
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return (($current - $previous) / $previous) * 100;
    }
}

// --- Filter Logic ---
$duration   = $_GET['duration']   ?? 'monthly';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date']   ?? date('Y-m-d');

if ($duration !== 'custom') {
    switch ($duration) {
        case 'today':    $start_date = date('Y-m-d'); $end_date = date('Y-m-d'); break;
        case 'weekly':   $start_date = date('Y-m-d', strtotime('-7 days')); $end_date = date('Y-m-d'); break;
        case 'monthly':  $start_date = date('Y-m-01'); $end_date = date('Y-m-t'); break;
        case '3months':  $start_date = date('Y-m-d', strtotime('-3 months')); $end_date = date('Y-m-d'); break;
        case 'yearly':   $start_date = date('Y-01-01'); $end_date = date('Y-12-31'); break;
        case 'all':      $start_date = '2020-01-01'; $end_date = date('Y-m-d'); break;
    }
}

$days_diff       = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
$prev_end_date   = date('Y-m-d', strtotime($start_date . ' -1 day'));
$prev_start_date = date('Y-m-d', strtotime($prev_end_date . ' -' . $days_diff . ' days'));

$liquidity_names = "'Cash at Hand', 'M-Pesa Float', 'Bank Account', 'Paystack Clearing Account'";

function fetch_totals($conn, $start, $end, $liquidity_names) {
    $sql = "SELECT 
        SUM(CASE WHEN la.account_name IN ($liquidity_names) THEN le.debit ELSE 0 END) as total_inflow,
        SUM(CASE WHEN la.account_name IN ($liquidity_names) THEN le.credit ELSE 0 END) as total_outflow,
        SUM(CASE WHEN la.account_name = 'SACCO Expenses' THEN le.debit ELSE 0 END) as operational_expense
        FROM ledger_entries le
        JOIN ledger_accounts la ON le.account_id = la.account_id
        WHERE DATE(le.created_at) BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$totals       = fetch_totals($conn, $start_date, $end_date, $liquidity_names);
$net_cash_flow = $totals['total_inflow'] - $totals['total_outflow'];
$prev_totals  = fetch_totals($conn, $prev_start_date, $prev_end_date, $liquidity_names);
$prev_net_cash = $prev_totals['total_inflow'] - $prev_totals['total_outflow'];

$growth_inflow  = calc_growth($totals['total_inflow'],          $prev_totals['total_inflow']);
$growth_outflow = calc_growth($totals['total_outflow'],         $prev_totals['total_outflow']);
$growth_expense = calc_growth($totals['operational_expense'],   $prev_totals['operational_expense']);
$growth_net     = calc_growth($net_cash_flow, $prev_net_cash);

$trend_labels = []; $trend_in = []; $trend_out = []; $trend_net = []; $monthly_data = [];

$sql_trend = "SELECT DATE_FORMAT(le.created_at, '%Y-%m') as month_str,
    DATE_FORMAT(le.created_at, '%b %Y') as display_date,
    SUM(CASE WHEN la.account_name IN ($liquidity_names) THEN le.debit ELSE 0 END) as inflow,
    SUM(CASE WHEN la.account_name IN ($liquidity_names) THEN le.credit ELSE 0 END) as outflow
    FROM ledger_entries le JOIN ledger_accounts la ON le.account_id = la.account_id
    WHERE DATE(le.created_at) BETWEEN ? AND ?
    GROUP BY month_str ORDER BY month_str ASC";
$stmt = $conn->prepare($sql_trend);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res_trend = $stmt->get_result();
while($row = $res_trend->fetch_assoc()) {
    $trend_labels[] = $row['display_date'];
    $trend_in[]     = (float)$row['inflow'];
    $trend_out[]    = (float)$row['outflow'];
    $trend_net[]    = (float)$row['inflow'] - (float)$row['outflow'];
    $monthly_data[] = $row;
}

$inflow_dist = array_fill_keys(['Deposits','Repayments','Shares','Welfare','Revenue','Wallet','Investments','Other'], 0);
$sql_dist = "SELECT la.category, SUM(le.credit) as val FROM ledger_entries le
             JOIN ledger_accounts la ON le.account_id = la.account_id
             WHERE DATE(le.created_at) BETWEEN ? AND ?
             AND (la.account_type IN ('liability','equity','revenue') OR la.category IN ('loans','investments'))
             GROUP BY la.category";
$stmt = $conn->prepare($sql_dist);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res_dist = $stmt->get_result();
while($row = $res_dist->fetch_assoc()) {
    $cat = strtolower($row['category'] ?? ''); $val = (float)$row['val'];
    if($cat=='savings') $inflow_dist['Deposits'] += $val;
    elseif($cat=='loans') $inflow_dist['Repayments'] += $val;
    elseif($cat=='shares') $inflow_dist['Shares'] += $val;
    elseif($cat=='welfare') $inflow_dist['Welfare'] += $val;
    elseif(in_array($cat,['income','revenue'])) $inflow_dist['Revenue'] += $val;
    elseif($cat=='wallet') $inflow_dist['Wallet'] += $val;
    elseif($cat=='investments') $inflow_dist['Investments'] += $val;
    else $inflow_dist['Other'] += $val;
}

$reportGen   = new ReportGenerator($conn);
$balanceData = $reportGen->getBalanceSheetData($start_date, $end_date);

if (isset($_GET['action']) || isset($_POST['send_to_all'])) {
    set_time_limit(0); ignore_user_abort(true);
}

if (isset($_GET['action'])) {
    if (ob_get_length()) ob_clean();
    if ($_GET['action'] === 'export_pdf') {
        $reportGen->generatePDF("Financial Report (".date('d M', strtotime($start_date))." - ".date('d M', strtotime($end_date)).")", $balanceData);
        exit;
    } elseif ($_GET['action'] === 'export_excel') {
        $reportGen->generateExcel($balanceData); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_to_all'])) {
    $members = $conn->query("SELECT email, full_name FROM members WHERE status='active' AND email LIKE '%@%'");
    $sentCount = 0; $errCount = 0;
    $pdfContent = $reportGen->generatePDF("Performance Report", $balanceData, true);
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
        $mail->SMTPKeepAlive = true; $mail->Username = 'leyianbeza24@gmail.com';
        $mail->Password = 'duzb mbqt fnsz ipkg'; $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587; $mail->Timeout = 60;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        $mail->setFrom('leyianbeza24@gmail.com', 'Umoja Drivers Sacco');
        $mail->Subject = 'Executive Performance Report - ' . date('F Y');
    } catch (Exception $e) { $errCount++; }
    $errStrings = [];
    while ($m = $members->fetch_assoc()) {
        try {
            $mail->clearAllRecipients(); $mail->clearAttachments();
            $mail->addAddress($m['email'], $m['full_name']);
            $mail->Body = "Dear {$m['full_name']},\n\nAttached is the latest financial performance report.\n\nRegards,\nUmoja Sacco Admin";
            $mail->addStringAttachment($pdfContent, 'Financial_Report.pdf');
            $mail->send(); $sentCount++;
        } catch (Exception $e) {
            $errCount++;
            if (count($errStrings) < 3) $errStrings[] = "{$m['email']}: {$mail->ErrorInfo}";
        }
    }
    $mail->smtpClose();
    $flashMsg = "Report sent to $sentCount members. Failed: $errCount.";
    if ($errCount > 0 && !empty($errStrings)) $flashMsg .= " Errors: ".implode(" | ", $errStrings);
    flash_set($flashMsg, $errCount > 0 ? "warning" : "success");
    header("Location: reports.php"); exit;
}

$pageTitle = "Executive Reports";
?>
<?php $layout->header($pageTitle); ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">

<style>
/* ============================================================
   EXECUTIVE REPORTS — JAKARTA SANS + GLASSMORPHISM THEME
   ============================================================ */
*, *::before, *::after { box-sizing: border-box; }

:root {
    --forest:       #0d2b1f;
    --forest-mid:   #1a3d2b;
    --forest-light: #234d36;
    --lime:         #b5f43c;
    --lime-soft:    #d6fb8a;
    --lime-glow:    rgba(181,244,60,0.18);
    --lime-glow-sm: rgba(181,244,60,0.08);
    --surface:      #ffffff;
    --bg-muted:     #f5f8f6;
    --text-primary: #0d1f15;
    --text-muted:   #6b7c74;
    --border:       rgba(13,43,31,0.07);
    --radius-sm:    8px;
    --radius-md:    14px;
    --radius-lg:    20px;
    --radius-xl:    28px;
    --shadow-sm:    0 2px 8px rgba(13,43,31,0.07);
    --shadow-md:    0 8px 28px rgba(13,43,31,0.11);
    --shadow-lg:    0 20px 60px rgba(13,43,31,0.16);
    --shadow-glow:  0 0 0 3px var(--lime-glow), 0 6px 24px rgba(181,244,60,0.15);
    --transition:   all 0.22s cubic-bezier(0.4,0,0.2,1);
}

body, *, input, select, textarea, button, .btn, table, th, td,
h1,h2,h3,h4,h5,h6,p,span,div,label,a,.modal,.offcanvas {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ── Hero ── */
.hp-hero {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 55%, #0e3522 100%);
    border-radius: var(--radius-xl);
    padding: 2.6rem 3rem 5rem;
    position: relative; overflow: hidden; color: #fff; margin-bottom: 0;
}
.hp-hero::before {
    content:''; position:absolute; inset:0;
    background:
        radial-gradient(ellipse 55% 70% at 95% 5%,  rgba(181,244,60,0.13) 0%, transparent 60%),
        radial-gradient(ellipse 35% 45% at 5%  95%, rgba(181,244,60,0.06) 0%, transparent 60%);
    pointer-events:none;
}
.hp-hero .ring { position:absolute;border-radius:50%;border:1px solid rgba(181,244,60,0.1);pointer-events:none; }
.hp-hero .ring1 { width:320px;height:320px;top:-80px;right:-80px; }
.hp-hero .ring2 { width:500px;height:500px;top:-160px;right:-160px; }
.hero-badge {
    display:inline-flex;align-items:center;gap:0.45rem;
    background:rgba(181,244,60,0.12);border:1px solid rgba(181,244,60,0.25);
    color:var(--lime-soft);border-radius:100px;padding:0.28rem 0.85rem;
    font-size:0.68rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;
    margin-bottom:0.9rem;position:relative;
}
.hero-badge::before { content:'';width:6px;height:6px;border-radius:50%;background:var(--lime);animation:pulse-dot 2s ease-in-out infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.4)} }

.hero-period-box {
    display:inline-block;padding:0.65rem 1.1rem;border-radius:var(--radius-md);
    background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);
    position:relative;
}
.hero-period-label { font-size:0.67rem;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;color:rgba(255,255,255,0.5);margin-bottom:0.2rem; }
.hero-period-value { font-size:0.85rem;font-weight:700;color:var(--lime-soft); }

/* ── Filter Bar ── */
.filter-bar {
    background:var(--surface);border-radius:var(--radius-lg);
    padding:1rem 1.4rem;border:1px solid var(--border);box-shadow:var(--shadow-sm);
    margin-bottom:1.5rem;
}
.filter-label { display:flex;align-items:center;gap:0.5rem;font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);white-space:nowrap; }
.form-select-enh, .form-control-enh {
    border-radius:var(--radius-md);border:1.5px solid rgba(13,43,31,0.1);
    font-size:0.82rem;font-weight:600;padding:0.5rem 0.9rem;
    color:var(--text-primary);background:#f8faf9;
    font-family:'Plus Jakarta Sans',sans-serif !important;transition:var(--transition);
    appearance:none;height:38px;
}
.form-select-enh {
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b7c74' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 0.9rem center;padding-right:2.2rem;
}
.form-select-enh:focus, .form-control-enh:focus { outline:none;border-color:var(--lime);background:#fff;box-shadow:var(--shadow-glow); }
.btn-filter-apply {
    background:var(--forest);color:#fff;border:none;border-radius:100px;
    padding:0.48rem 1.2rem;font-size:0.82rem;font-weight:700;cursor:pointer;transition:var(--transition);height:38px;
}
.btn-filter-apply:hover { background:var(--forest-light); }
.btn-filter-reset {
    background:#f5f8f6;color:var(--text-muted);border:1.5px solid var(--border);border-radius:100px;
    padding:0.48rem 1.1rem;font-size:0.82rem;font-weight:700;cursor:pointer;transition:var(--transition);height:38px;text-decoration:none;
    display:flex;align-items:center;
}
.btn-filter-reset:hover { background:#e8f0eb;color:var(--text-primary); }

/* ── KPI Cards ── */
.glass-stat {
    background:var(--surface);border-radius:var(--radius-lg);
    padding:1.5rem 1.6rem;border:1px solid var(--border);
    box-shadow:var(--shadow-md);position:relative;overflow:hidden;
    transition:var(--transition);
}
.glass-stat:hover { transform:translateY(-3px);box-shadow:var(--shadow-lg); }
.glass-stat::after { content:'';position:absolute;bottom:0;left:0;right:0;height:3px;border-radius:0 0 var(--radius-lg) var(--radius-lg);opacity:0;transition:var(--transition); }
.glass-stat:hover::after { opacity:1; }
.stat-card-dark { background:linear-gradient(135deg,var(--forest) 0%,var(--forest-mid) 100%) !important;border:none !important; }
.stat-card-dark::after { background:linear-gradient(90deg,var(--lime),var(--lime-soft)); }
.stat-card-accent { background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%) !important;border:1px solid rgba(22,163,74,0.12) !important; }
.stat-card-accent::after { background:linear-gradient(90deg,#22c55e,#86efac); }
.glass-stat.s-out::after { background:linear-gradient(90deg,#ef4444,#fca5a5); }
.glass-stat.s-exp::after { background:linear-gradient(90deg,#f59e0b,#fcd34d); }

.stat-top { display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem; }
.stat-icon-box { width:48px;height:48px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0; }
.hp-trend {
    display:inline-flex;align-items:center;gap:0.25rem;
    border-radius:100px;padding:0.2rem 0.65rem;font-size:0.68rem;font-weight:800;
}
.hp-trend-up   { background:rgba(34,197,94,0.12);color:#166534;border:1px solid rgba(34,197,94,0.18); }
.hp-trend-down { background:rgba(239,68,68,0.1); color:#b91c1c;border:1px solid rgba(239,68,68,0.18); }
.hp-badge { border-radius:100px;padding:0.2rem 0.65rem;font-size:0.68rem;font-weight:800; }

.stat-label { font-size:0.67rem;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;margin-top:0.4rem; }
.stat-value { font-size:1.65rem;font-weight:800;letter-spacing:-0.04em;line-height:1.1; }

/* ── Chart / Bottom Cards ── */
.glass-card {
    background:var(--surface);border-radius:var(--radius-lg);
    border:1px solid var(--border);box-shadow:var(--shadow-md);
    padding:1.6rem 1.8rem;
}
.chart-container { position:relative;height:260px;width:100%; }
.card-title-row { display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem; }
.card-title { font-weight:800;font-size:0.95rem;color:var(--text-primary);letter-spacing:-0.01em; }

/* Source legend */
.source-legend-item { display:flex;justify-content:space-between;align-items:center;padding:0.42rem 0;border-bottom:1px solid rgba(13,43,31,0.04); }
.source-legend-item:last-child { border-bottom:none; }
.source-dot { width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-right:0.55rem; }
.source-name { font-size:0.78rem;font-weight:600;color:var(--text-muted); }
.source-value { font-size:0.8rem;font-weight:800;color:var(--text-primary); }

/* ── Monthly Breakdown Table ── */
.breakdown-card {
    background:var(--surface);border-radius:var(--radius-lg);
    border:1px solid var(--border);box-shadow:var(--shadow-md);overflow:hidden;
}
.breakdown-card-header {
    padding:1rem 1.5rem;border-bottom:1px solid var(--border);
    display:flex;justify-content:space-between;align-items:center;background:#fff;
}
.breakdown-card-header h5 { font-weight:800;font-size:0.95rem;color:var(--text-primary);margin:0; }

.table-breakdown { width:100%;border-collapse:separate;border-spacing:0; }
.table-breakdown thead th {
    background:#f5f8f6;color:var(--text-muted);font-size:0.67rem;
    font-weight:800;text-transform:uppercase;letter-spacing:0.1em;
    padding:0.8rem 1rem;border-bottom:1px solid var(--border);white-space:nowrap;
}
.table-breakdown thead th:first-child { padding-left:1.5rem; }
.table-breakdown thead th:last-child  { padding-right:1.5rem; text-align:right; }
.table-breakdown tbody tr { border-bottom:1px solid rgba(13,43,31,0.04);transition:var(--transition); }
.table-breakdown tbody tr:last-child { border-bottom:none; }
.table-breakdown tbody tr:hover { background:#f0faf4; }
.table-breakdown tbody td { padding:0.85rem 1rem;vertical-align:middle;font-size:0.875rem;color:var(--text-primary); }
.table-breakdown tbody td:first-child { padding-left:1.5rem; }
.table-breakdown tbody td:last-child  { padding-right:1.5rem;text-align:right; }

.net-positive { color:#166534;font-weight:800; }
.net-negative { color:#b91c1c;font-weight:800; }
.status-pill {
    display:inline-flex;align-items:center;gap:0.3rem;border-radius:100px;
    padding:0.22rem 0.75rem;font-size:0.67rem;font-weight:800;text-transform:uppercase;
}
.status-pill::before { content:'';width:5px;height:5px;border-radius:50%;flex-shrink:0; }
.status-positive { background:#f0fdf4;color:#166534;border:1px solid rgba(22,163,74,0.18); }
.status-positive::before { background:#22c55e; }
.status-negative { background:#fef2f2;color:#b91c1c;border:1px solid rgba(239,68,68,0.18); }
.status-negative::before { background:#ef4444; }

/* Buttons */
.btn-lime   { background:var(--lime);color:var(--forest) !important;border:none;font-weight:700;transition:var(--transition); }
.btn-lime:hover  { background:var(--lime-soft);box-shadow:var(--shadow-glow);transform:translateY(-1px); }
.btn-forest { background:var(--forest);color:#fff !important;border:none;font-weight:700;transition:var(--transition); }
.btn-forest:hover { background:var(--forest-light); }

/* Animations */
@keyframes fadeIn  { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes slideUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }
.fade-in  { animation:fadeIn  0.5s ease-out both; }
.slide-up { animation:slideUp 0.5s cubic-bezier(0.4,0,0.2,1) both; }

/* Dropdown */
.dropdown-menu { border-radius:var(--radius-md) !important;border:1px solid var(--border) !important;box-shadow:var(--shadow-lg) !important;padding:0.4rem !important; }
.dropdown-item { border-radius:8px;font-size:0.84rem;font-weight:600;padding:0.58rem 0.9rem !important;color:var(--text-primary) !important;transition:var(--transition); }
.dropdown-item:hover { background:#f0faf4 !important; }

@media print {
    .hp-hero,.filter-bar,.no-print { display:none !important; }
    .breakdown-card { box-shadow:none; border:1px solid #ddd; }
}

@media (max-width:768px) {
    .hp-hero { padding:2rem 1.5rem 4rem; }
}
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

        <!-- Hero -->
        <div class="hp-hero fade-in">
            <div class="ring ring1"></div>
            <div class="ring ring2"></div>
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <div class="hero-badge">Executive Analytics</div>
                    <h1 style="font-weight:800;letter-spacing:-0.03em;font-size:2.2rem;line-height:1.15;position:relative;margin-bottom:0.5rem;">
                        Financial Insights.
                    </h1>
                    <p style="color:rgba(255,255,255,0.55);font-size:0.93rem;font-weight:500;position:relative;margin:0;">
                        Track liquidity, growth, and operational efficiency with real-time intelligence.
                    </p>
                </div>
                <div class="col-lg-5 text-lg-end mt-3 mt-lg-0 d-none d-lg-block" style="position:relative;">
                    <div class="d-flex align-items-center justify-content-end gap-2 mb-3">
                        <div class="dropdown">
                            <button class="btn btn-forest rounded-pill px-4 py-2 dropdown-toggle fw-bold" style="font-size:0.82rem;" data-bs-toggle="dropdown">
                                <i class="bi bi-file-earmark-arrow-down me-2"></i>Export
                            </button>
                            <ul class="dropdown-menu shadow-lg border-0 mt-2">
                                <li><a class="dropdown-item" href="?action=export_pdf&duration=<?= $duration ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                                    <i class="bi bi-file-pdf text-danger me-2"></i>PDF Document
                                </a></li>
                                <li><a class="dropdown-item" href="?action=export_excel&duration=<?= $duration ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                                    <i class="bi bi-file-excel text-success me-2"></i>Excel Sheet
                                </a></li>
                            </ul>
                        </div>
                        <form method="POST" onsubmit="return confirm('Email this report to all active members?');">
                            <button type="submit" name="send_to_all" class="btn btn-lime rounded-pill px-4 py-2 fw-bold" style="font-size:0.82rem;">
                                <i class="bi bi-send-fill me-2"></i>Email All
                            </button>
                        </form>
                    </div>
                    <div class="hero-period-box">
                        <div class="hero-period-label">Active Period</div>
                        <div class="hero-period-value">
                            <i class="bi bi-calendar3 me-1" style="font-size:0.75rem;"></i>
                            <?= date('M d, Y', strtotime($start_date)) ?> &mdash; <?= date('M d, Y', strtotime($end_date)) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:-36px;position:relative;z-index:10;">

            <?php flash_render(); ?>

            <!-- Filter Bar -->
            <div class="filter-bar d-flex flex-wrap align-items-center gap-2 no-print slide-up" style="animation-delay:0.04s;">
                <div class="filter-label"><i class="bi bi-funnel"></i>Filter</div>
                <form method="GET" class="d-flex flex-wrap flex-grow-1 gap-2 align-items-center" id="filterForm">
                    <select name="duration" class="form-select-enh" style="min-width:150px;" onchange="toggleDateInputs(this.value)">
                        <option value="today"   <?= $duration==='today'   ?'selected':''?>>Today</option>
                        <option value="weekly"  <?= $duration==='weekly'  ?'selected':''?>>This Week</option>
                        <option value="monthly" <?= $duration==='monthly' ?'selected':''?>>This Month</option>
                        <option value="3months" <?= $duration==='3months' ?'selected':''?>>Last 3 Months</option>
                        <option value="yearly"  <?= $duration==='yearly'  ?'selected':''?>>This Year</option>
                        <option value="all"     <?= $duration==='all'     ?'selected':''?>>All Time</option>
                        <option value="custom"  <?= $duration==='custom'  ?'selected':''?>>Custom Range</option>
                    </select>
                    <div id="customDateRange" class="d-flex gap-2 align-items-center <?= $duration !== 'custom' ? 'd-none' : '' ?>">
                        <input type="date" name="start_date" class="form-control-enh" value="<?= $start_date ?>">
                        <span style="color:var(--text-muted);font-weight:600;font-size:0.8rem;">to</span>
                        <input type="date" name="end_date" class="form-control-enh" value="<?= $end_date ?>">
                    </div>
                    <button type="submit" class="btn-filter-apply ms-auto">
                        <i class="bi bi-check2 me-1"></i>Apply
                    </button>
                    <a href="reports.php" class="btn-filter-reset">
                        <i class="bi bi-x-lg me-1"></i>Reset
                    </a>
                </form>
            </div>

            <!-- KPI Row -->
            <div class="row g-3 mb-4">
                <!-- Inflow -->
                <div class="col-xl-3 col-md-6">
                    <div class="glass-stat stat-card-dark slide-up" style="animation-delay:0.08s;">
                        <div class="stat-top">
                            <div class="stat-icon-box" style="background:rgba(255,255,255,0.1);color:var(--lime);">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                            <?php $g_in = $growth_inflow >= 0; ?>
                            <span class="hp-trend <?= $g_in ? 'hp-trend-up' : 'hp-trend-down' ?>" style="<?= $g_in ? 'background:rgba(181,244,60,0.15);color:var(--lime-soft);border-color:rgba(181,244,60,0.25);' : '' ?>">
                                <i class="bi <?= $g_in ? 'bi-arrow-up' : 'bi-arrow-down' ?>"></i>
                                <?= number_format(abs($growth_inflow), 1) ?>%
                            </span>
                        </div>
                        <div class="stat-value" style="color:#fff;">KES <?= number_format((float)($totals['total_inflow'] ?? 0)) ?></div>
                        <div class="stat-label" style="color:rgba(255,255,255,0.45);">Total Inflow</div>
                    </div>
                </div>

                <!-- Outflow -->
                <div class="col-xl-3 col-md-6">
                    <div class="glass-stat s-out slide-up" style="animation-delay:0.13s;">
                        <div class="stat-top">
                            <div class="stat-icon-box" style="background:#fef2f2;color:#dc2626;">
                                <i class="bi bi-graph-down-arrow"></i>
                            </div>
                            <?php $g_out_good = $growth_outflow <= 0; ?>
                            <span class="hp-trend <?= $g_out_good ? 'hp-trend-up' : 'hp-trend-down' ?>">
                                <i class="bi <?= $growth_outflow >= 0 ? 'bi-arrow-up' : 'bi-arrow-down' ?>"></i>
                                <?= number_format(abs($growth_outflow), 1) ?>%
                            </span>
                        </div>
                        <div class="stat-value" style="color:#dc2626;">KES <?= number_format((float)($totals['total_outflow'] ?? 0)) ?></div>
                        <div class="stat-label" style="color:var(--text-muted);">Total Outflow</div>
                    </div>
                </div>

                <!-- Net Cash Flow -->
                <div class="col-xl-3 col-md-6">
                    <div class="glass-stat stat-card-accent slide-up" style="animation-delay:0.18s;">
                        <div class="stat-top">
                            <div class="stat-icon-box" style="background:rgba(13,43,31,0.07);color:var(--forest);">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <span class="hp-badge" style="background:var(--forest);color:var(--lime);font-size:0.65rem;font-weight:800;letter-spacing:0.07em;text-transform:uppercase;">Net</span>
                        </div>
                        <div class="stat-value" style="color:var(--forest);">KES <?= number_format((float)($net_cash_flow ?? 0)) ?></div>
                        <div class="stat-label" style="color:rgba(13,43,31,0.5);">Net Cash Flow</div>
                    </div>
                </div>

                <!-- Op. Expenses -->
                <div class="col-xl-3 col-md-6">
                    <div class="glass-stat s-exp slide-up" style="animation-delay:0.23s;">
                        <div class="stat-top">
                            <div class="stat-icon-box" style="background:#fffbeb;color:#b45309;">
                                <i class="bi bi-receipt"></i>
                            </div>
                            <?php $g_exp_good = $growth_expense <= 0; ?>
                            <span class="hp-trend <?= $g_exp_good ? 'hp-trend-up' : 'hp-trend-down' ?>">
                                <i class="bi <?= $growth_expense >= 0 ? 'bi-arrow-up' : 'bi-arrow-down' ?>"></i>
                                <?= number_format(abs($growth_expense), 1) ?>%
                            </span>
                        </div>
                        <div class="stat-value" style="color:#b45309;">KES <?= number_format((float)($totals['operational_expense'] ?? 0)) ?></div>
                        <div class="stat-label" style="color:var(--text-muted);">Op. Expenses</div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-3 mb-4">
                <div class="col-lg-8">
                    <div class="glass-card slide-up" style="animation-delay:0.28s;">
                        <div class="card-title-row">
                            <span class="card-title">Cash Flow Trends</span>
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <span style="display:inline-flex;align-items:center;gap:0.3rem;font-size:0.72rem;font-weight:700;color:var(--text-muted);">
                                    <span style="width:10px;height:3px;border-radius:2px;background:var(--lime);display:inline-block;"></span>Net
                                </span>
                                <span style="display:inline-flex;align-items:center;gap:0.3rem;font-size:0.72rem;font-weight:700;color:var(--text-muted);">
                                    <span style="width:10px;height:10px;border-radius:3px;background:var(--lime-glow);border:1px solid var(--lime);display:inline-block;"></span>Inflow
                                </span>
                                <span style="display:inline-flex;align-items:center;gap:0.3rem;font-size:0.72rem;font-weight:700;color:var(--text-muted);">
                                    <span style="width:10px;height:10px;border-radius:3px;background:rgba(13,43,31,0.15);display:inline-block;"></span>Outflow
                                </span>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="glass-card slide-up" style="animation-delay:0.33s;">
                        <div class="card-title-row">
                            <span class="card-title">Inflow Sources</span>
                        </div>
                        <div style="height:180px;position:relative;margin-bottom:1rem;">
                            <canvas id="sourceChart"></canvas>
                        </div>
                        <div>
                            <?php
                            $top_sources = $inflow_dist;
                            arsort($top_sources);
                            $colors = ['#0d2b1f','#b5f43c','#f59e0b','#06b6d4','#3b82f6','#8b5cf6','#ec4899','#d1d5db'];
                            $ci = 0;
                            foreach(array_slice($top_sources, 0, 5, true) as $k => $v):
                                $color = $colors[$ci++ % count($colors)];
                            ?>
                            <div class="source-legend-item">
                                <div style="display:flex;align-items:center;">
                                    <span class="source-dot" style="background:<?= $color ?>;"></span>
                                    <span class="source-name"><?= $k ?></span>
                                </div>
                                <span class="source-value">KES <?= number_format($v) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Breakdown -->
            <div class="breakdown-card mb-5 slide-up" style="animation-delay:0.38s;">
                <div class="breakdown-card-header">
                    <h5>Monthly Breakdown</h5>
                    <button style="background:var(--bg-muted);border:1.5px solid var(--border);border-radius:100px;padding:0.35rem 1rem;font-size:0.78rem;font-weight:700;cursor:pointer;color:var(--text-muted);transition:var(--transition);"
                            onmouseover="this.style.background='#f0faf4';this.style.color='var(--forest)'"
                            onmouseout="this.style.background='var(--bg-muted)';this.style.color='var(--text-muted)'"
                            onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Print Report
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table-breakdown">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th style="text-align:right;">Total Inflow</th>
                                <th style="text-align:right;">Total Outflow</th>
                                <th style="text-align:right;">Net Change</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($monthly_data)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;padding:4rem 2rem;">
                                    <div style="width:56px;height:56px;border-radius:14px;background:#f5f8f6;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#c4d4cb;margin:0 auto 0.8rem;">
                                        <i class="bi bi-bar-chart"></i>
                                    </div>
                                    <div style="font-weight:800;font-size:0.9rem;color:var(--text-primary);margin-bottom:0.2rem;">No Data Available</div>
                                    <div style="font-size:0.8rem;color:var(--text-muted);">No entries found for the selected period.</div>
                                </td>
                            </tr>
                            <?php else: foreach($monthly_data as $m):
                                $net = $m['inflow'] - $m['outflow'];
                                $is_pos = $net >= 0;
                            ?>
                            <tr>
                                <td style="font-weight:700;"><?= $m['display_date'] ?></td>
                                <td style="text-align:right;">
                                    <span style="font-family:'Courier New',monospace !important;font-weight:700;color:#166534;">
                                        + <?= number_format((float)$m['inflow']) ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <span style="font-family:'Courier New',monospace !important;font-weight:700;color:#dc2626;">
                                        - <?= number_format((float)$m['outflow']) ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <span class="<?= $is_pos ? 'net-positive' : 'net-negative' ?>" style="font-family:'Courier New',monospace !important;">
                                        <?= ksh($net) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill <?= $is_pos ? 'status-positive' : 'status-negative' ?>">
                                        <?= $is_pos ? 'Positive' : 'Deficit' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="text-center mb-4 no-print" style="font-size:0.78rem;color:var(--text-muted);font-weight:600;">
                &copy; <?= date('Y') ?> Umoja Sacco Management System &mdash; All rights reserved.
            </div>

        </div><!-- /overlap -->

    </div><!-- /container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    function toggleDateInputs(val) {
        const el = document.getElementById('customDateRange');
        if (val === 'custom') {
            el.classList.remove('d-none');
        } else {
            el.classList.add('d-none');
            document.getElementById('filterForm').submit();
        }
    }

    // Trend Chart
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    let gradIn = ctxTrend.createLinearGradient(0, 0, 0, 280);
    gradIn.addColorStop(0, 'rgba(181,244,60,0.35)');
    gradIn.addColorStop(1, 'rgba(181,244,60,0.03)');
    let gradOut = ctxTrend.createLinearGradient(0, 0, 0, 280);
    gradOut.addColorStop(0, 'rgba(13,43,31,0.22)');
    gradOut.addColorStop(1, 'rgba(13,43,31,0.03)');

    new Chart(ctxTrend, {
        type: 'bar',
        data: {
            labels: <?= json_encode($trend_labels) ?>,
            datasets: [
                {
                    type: 'line',
                    label: 'Net Cash Flow',
                    data: <?= json_encode($trend_net) ?>,
                    borderColor: '#b5f43c',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#0d2b1f',
                    pointBorderColor: '#b5f43c',
                    pointRadius: 4,
                    tension: 0.45,
                    order: 0,
                    yAxisID: 'y'
                },
                {
                    label: 'Inflow',
                    data: <?= json_encode($trend_in) ?>,
                    backgroundColor: gradIn,
                    borderRadius: 6,
                    barPercentage: 0.5,
                    order: 1
                },
                {
                    label: 'Outflow',
                    data: <?= json_encode($trend_out) ?>,
                    backgroundColor: gradOut,
                    borderRadius: 6,
                    barPercentage: 0.5,
                    order: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0d2b1f',
                    borderColor: 'rgba(181,244,60,0.2)',
                    borderWidth: 1,
                    padding: 12,
                    titleFont: { family: 'Plus Jakarta Sans', size: 12, weight: '800' },
                    bodyFont:  { family: 'Plus Jakarta Sans', size: 12 },
                    callbacks: {
                        label: ctx => '  ' + ctx.dataset.label + ': KES ' + ctx.raw.toLocaleString('en-KE')
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(13,43,31,0.05)', borderDash: [4,4] },
                    ticks: { color: '#6b7c74', font: { size: 10, weight: '600' }, callback: v => 'KES ' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v) },
                    border: { display: false }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#6b7c74', font: { size: 10, weight: '600' } },
                    border: { display: false }
                }
            }
        }
    });

    // Source Donut Chart
    const ctxSource = document.getElementById('sourceChart').getContext('2d');
    new Chart(ctxSource, {
        type: 'doughnut',
        data: {
            labels: ['Deposits','Repayments','Shares','Welfare','Revenue','Wallet','Investments','Other'],
            datasets: [{
                data: [<?= $inflow_dist['Deposits']?>,<?= $inflow_dist['Repayments']?>,<?= $inflow_dist['Shares']?>,<?= $inflow_dist['Welfare']?>,<?= $inflow_dist['Revenue']?>,<?= $inflow_dist['Wallet']?>,<?= $inflow_dist['Investments']?>,<?= $inflow_dist['Other']?>],
                backgroundColor: ['#0d2b1f','#b5f43c','#f59e0b','#06b6d4','#3b82f6','#8b5cf6','#ec4899','#d1d5db'],
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0d2b1f',
                    padding: 10,
                    callbacks: { label: ctx => '  KES ' + ctx.raw.toLocaleString('en-KE') }
                }
            }
        }
    });
    </script>

    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->
</body>
</html>