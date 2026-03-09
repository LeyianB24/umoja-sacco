<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');
require_member();
$layout = LayoutManager::create('member');

$member_id = (int)$_SESSION['member_id'];

// ── Filters ────────────────────────────────────
$type_filter = filter_input(INPUT_GET, 'type',  FILTER_SANITIZE_SPECIAL_CHARS);
$date_filter = filter_input(INPUT_GET, 'date',  FILTER_SANITIZE_SPECIAL_CHARS);
$from_filter = filter_input(INPUT_GET, 'from',  FILTER_SANITIZE_SPECIAL_CHARS);
$to_filter   = filter_input(INPUT_GET, 'to',    FILTER_SANITIZE_SPECIAL_CHARS);

// ── Main query ─────────────────────────────────
$sql    = "SELECT transaction_id, transaction_type, amount, reference_no, created_at, payment_channel, notes FROM transactions WHERE member_id = ?";
$params = [$member_id];
$types  = "i";
if ($type_filter) { $sql .= " AND transaction_type = ?"; $params[] = $type_filter; $types .= "s"; }
if ($date_filter) { $sql .= " AND DATE(created_at) = ?"; $params[] = $date_filter; $types .= "s"; }
if ($from_filter) { $sql .= " AND DATE(created_at) >= ?"; $params[] = $from_filter; $types .= "s"; }
if ($to_filter)   { $sql .= " AND DATE(created_at) <= ?"; $params[] = $to_filter;   $types .= "s"; }
$sql .= " ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ── Export ─────────────────────────────────────
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf','export_excel','print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    $format = match($_GET['action']) { 'export_excel'=>'excel','print_report'=>'print', default=>'pdf' };
    $data = [];
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        $t = strtolower($row['transaction_type'] ?? '');
        $data[] = [
            'Date'      => date('d-M-Y H:i', strtotime($row['created_at'])),
            'Type'      => ucwords(str_replace('_',' ',$t)),
            'Reference' => $row['reference_no'],
            'Channel'   => strtoupper($row['payment_channel']),
            'Amount'    => ($t==='withdrawal'?'-':'+').' '.number_format((float)$row['amount'],2),
        ];
    }
    UniversalExportEngine::handle($format, $data, ['title'=>'Personal Transaction Ledger','module'=>'Member Portal','headers'=>['Date','Type','Reference','Channel','Amount']]);
    exit;
}

// ── KPIs ───────────────────────────────────────
require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine   = new FinancialEngine($conn);
$balances = $engine->getBalances($member_id);

$net_savings     = (float)$balances['savings'];
$total_loans     = (float)$balances['loans'];
$wallet_bal      = (float)$balances['wallet'];
$total_shares    = (float)$balances['shares'];

$total_repaid    = (float)($conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type='loan_repayment'")->fetch_row()[0]);
$total_withdrawn = (float)($conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type='withdrawal'")->fetch_row()[0]);
$total_deposited = (float)($conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type IN('deposit','contribution')")->fetch_row()[0]);
$total_txn_count = (int)  ($conn->query("SELECT COUNT(*) FROM transactions WHERE member_id=$member_id")->fetch_row()[0]);

// This month stats
$month_in  = (float)($conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type IN('deposit','contribution') AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetch_row()[0]);
$month_out = (float)($conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type IN('withdrawal','loan_repayment') AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetch_row()[0]);

// ── Chart data: 12-month income vs outflow ─────
$chart_labels = $chart_in = $chart_out = $chart_net = [];
for ($i = 11; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("-$i months"));
    $me = date('Y-m-t',  strtotime("-$i months"));
    $chart_labels[] = date('M', strtotime($ms));

    $in  = (float)($conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type IN('deposit','contribution') AND created_at BETWEEN '$ms' AND '$me 23:59:59'")->fetch_row()[0]);
    $out = (float)($conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type IN('withdrawal','loan_repayment') AND created_at BETWEEN '$ms' AND '$me 23:59:59'")->fetch_row()[0]);
    $chart_in[]  = $in;
    $chart_out[] = $out;
    $chart_net[] = $in - $out;
}

// ── Chart data: transaction type breakdown ─────
$type_labels = $type_counts = $type_amounts = [];
$tr = $conn->query("SELECT transaction_type, COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM transactions WHERE member_id=$member_id GROUP BY transaction_type ORDER BY total DESC LIMIT 7");
while ($row = $tr->fetch_assoc()) {
    $type_labels[]  = ucwords(str_replace('_',' ', $row['transaction_type']));
    $type_counts[]  = (int)$row['cnt'];
    $type_amounts[] = (float)$row['total'];
}

// ── Chart data: daily last 14 days ────────────
$daily_labels = $daily_in = $daily_out = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('d/m', strtotime($day));
    $daily_in[]  = (float)($conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type IN('deposit','contribution') AND DATE(created_at)='$day'")->fetch_row()[0]);
    $daily_out[] = (float)($conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type IN('withdrawal','loan_repayment') AND DATE(created_at)='$day'")->fetch_row()[0]);
}

// ── Chart data: running balance ────────────────
$run_labels = $run_data = [];
$running = 0;
for ($i = 11; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("-$i months"));
    $me = date('Y-m-t',  strtotime("-$i months"));
    $run_labels[] = date('M y', strtotime($ms));
    $delta = (float)($conn->query("SELECT COALESCE(SUM(CASE WHEN transaction_type IN('deposit','contribution') THEN amount ELSE -amount END),0) FROM transactions WHERE member_id=$member_id AND created_at BETWEEN '$ms' AND '$me 23:59:59'")->fetch_row()[0]);
    $running += $delta;
    $run_data[] = max(0, $running);
}

function ks(float $n): string {
    $n = (float)$n;
    if ($n >= 1000000) return 'KES '.number_format($n/1000000, 2).'M';
    if ($n >= 1000)    return 'KES '.number_format($n/1000, 1).'K';
    return 'KES '.number_format($n, 2);
}

$pageTitle = "Transaction Ledger";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" id="htmlTag">
<head>
<script>(function(){var s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $pageTitle ?> · <?= defined('SITE_NAME') ? SITE_NAME : 'Umoja SACCO' ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════════
   TOKENS
═══════════════════════════════════════════════ */
:root {
    --forest:    #1a3a2a; --forest-mid:#234d38; --forest-lt:#2e6347;
    --lime:      #a8e063; --lime-s:rgba(168,224,99,.13); --lime-ss:rgba(168,224,99,.07);
    --blue:      #4481eb; --blue-s:rgba(68,129,235,.12);
    --green:     #1aa053; --green-s:rgba(26,160,83,.12);
    --amber:     #f0a500; --amber-s:rgba(240,165,0,.11);
    --red:       #e63757; --red-s:rgba(230,55,87,.11);
    --teal:      #20c997; --teal-s:rgba(32,201,151,.11);
    --purple:    #7c4dff; --purple-s:rgba(124,77,255,.11);
    --bg:        #eef2f7; --surf:#fff; --surf2:#f7fafc;
    --bdr:       #e0e8f0; --ink:#1a2b4a; --muted:#6b7c93; --faint:#b0bec5;
    --r:         16px;    --r-sm:10px;
    --t:         all .22s cubic-bezier(.4,0,.2,1);
    --mono:      'JetBrains Mono',monospace;
    --font:      'Plus Jakarta Sans',sans-serif;
    --shd:       0 2px 16px rgba(26,42,74,.06);
    --shd-h:     0 8px 32px rgba(26,42,74,.13);
}
[data-bs-theme="dark"] {
    --bg:#0b1520; --surf:#0f1e2d; --surf2:#132233;
    --bdr:rgba(255,255,255,.07); --ink:#dce8f4;
    --muted:#5a7a96; --faint:rgba(255,255,255,.18);
    --shd:0 2px 16px rgba(0,0,0,.3); --shd-h:0 8px 32px rgba(0,0,0,.45);
}
*,*::before,*::after{box-sizing:border-box;}
body{font-family:var(--font);background:var(--bg);color:var(--ink);margin:0;}
.main-content-wrapper{margin-left:282px;min-height:100vh;transition:var(--t);}
body.sb-collapsed .main-content-wrapper{margin-left:70px;}
@media(max-width:991px){.main-content-wrapper{margin-left:0;}}
.page-wrap{padding:22px 22px 52px;}
@media(max-width:768px){.page-wrap{padding:14px 12px 40px;}}

/* ═══════════════════════════════════════════════
   HERO BANNER
═══════════════════════════════════════════════ */
.hero{
    background:linear-gradient(128deg,var(--forest) 0%,var(--forest-lt) 55%,#387a56 100%);
    border-radius:var(--r);padding:24px 30px;margin-bottom:20px;
    position:relative;overflow:hidden;color:#fff;
}
.hero::before{content:'';position:absolute;top:-70px;right:-70px;width:300px;height:300px;border-radius:50%;
    background:radial-gradient(circle,rgba(168,224,99,.18) 0%,transparent 65%);pointer-events:none;}
.hero-ring{position:absolute;top:-90px;right:-90px;width:380px;height:380px;border-radius:50%;
    border:1.5px solid rgba(168,224,99,.1);pointer-events:none;}
.hero-ring2{position:absolute;top:-130px;right:-130px;width:490px;height:490px;border-radius:50%;
    border:1px solid rgba(168,224,99,.06);pointer-events:none;}
.hero-inner{display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;position:relative;z-index:2;}
.hero-chip{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.1);
    border:1px solid rgba(255,255,255,.18);border-radius:100px;padding:3px 12px;
    font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--lime);margin-bottom:10px;}
.hero h1{font-size:1.5rem;font-weight:800;color:#fff;letter-spacing:-.4px;margin:0 0 5px;}
.hero-sub{font-size:.76rem;color:rgba(255,255,255,.55);margin:0 0 18px;}
.hero-sub strong{color:var(--lime);}
.hero-kpis{display:flex;gap:8px;flex-wrap:wrap;}
.hkpi{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);
    border-radius:var(--r-sm);padding:10px 16px;min-width:100px;transition:var(--t);}
.hkpi:hover{background:rgba(255,255,255,.18);transform:translateY(-2px);}
.hkpi-v{font-family:var(--mono);font-size:.9rem;font-weight:600;color:#fff;letter-spacing:-.4px;line-height:1.1;}
.hkpi-l{font-size:.55rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.45);margin-top:2px;}
.hero-actions{display:flex;gap:7px;flex-wrap:wrap;position:relative;z-index:2;}
.hbtn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--r-sm);
    font-family:var(--font);font-size:.79rem;font-weight:800;text-decoration:none;
    transition:var(--t);border:none;cursor:pointer;}
.hbtn-lime{background:var(--lime);color:var(--forest);box-shadow:0 4px 14px rgba(168,224,99,.4);}
.hbtn-lime:hover{background:#baea78;color:var(--forest);transform:translateY(-1px);}
.hbtn-ghost{background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.22);}
.hbtn-ghost:hover{background:rgba(255,255,255,.2);color:#fff;}

/* ═══════════════════════════════════════════════
   SPARKLINE ROW
═══════════════════════════════════════════════ */
.spk-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;}
@media(max-width:767px){.spk-row{grid-template-columns:repeat(2,1fr);}}
.spk{background:var(--surf);border:1px solid var(--bdr);border-radius:var(--r-sm);
    padding:14px 16px 10px;position:relative;overflow:hidden;box-shadow:var(--shd);transition:var(--t);}
.spk:hover{box-shadow:var(--shd-h);transform:translateY(-2px);}
.spk::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3.5px;border-radius:3px 0 0 3px;}
.spk-green::before{background:var(--green);}
.spk-blue::before{background:var(--blue);}
.spk-amber::before{background:var(--amber);}
.spk-red::before{background:var(--red);}
.spk-teal::before{background:var(--teal);}
.spk-lbl{font-size:.57rem;font-weight:800;text-transform:uppercase;letter-spacing:.9px;color:var(--muted);margin-bottom:3px;}
.spk-val{font-family:var(--mono);font-size:1.05rem;font-weight:600;color:var(--ink);letter-spacing:-.5px;line-height:1.1;}
.spk-chg{font-size:.62rem;font-weight:700;margin-top:3px;}
.spk-chg.up{color:var(--green);} .spk-chg.dn{color:var(--red);} .spk-chg.nt{color:var(--muted);}
.spk-canvas-wrap{height:38px;margin-top:8px;}
.spk-canvas-wrap canvas{width:100%!important;height:38px!important;}

/* ═══════════════════════════════════════════════
   CHART PANELS
═══════════════════════════════════════════════ */
.gp{background:var(--surf);border:1px solid var(--bdr);border-radius:var(--r);
    padding:20px 22px;height:100%;box-shadow:var(--shd);transition:var(--t);display:flex;flex-direction:column;}
.gp:hover{box-shadow:var(--shd-h);}
.gp-title{font-size:.86rem;font-weight:800;color:var(--ink);margin:0 0 2px;}
.gp-sub{font-size:.62rem;font-weight:600;color:var(--muted);}
.gp-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;gap:8px;flex-wrap:wrap;}
.gp-stats{display:flex;gap:14px;align-items:center;flex-wrap:wrap;}
.gp-stat-v{font-family:var(--mono);font-size:.86rem;font-weight:600;color:var(--ink);letter-spacing:-.4px;}
.gp-stat-l{font-size:.56rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);}
.gp-div{width:1px;height:28px;background:var(--bdr);}
.chart-box{position:relative;flex:1;min-height:0;}
.legend{display:flex;gap:12px;flex-wrap:wrap;margin-top:10px;}
.leg-i{display:flex;align-items:center;gap:5px;font-size:.66rem;font-weight:600;color:var(--muted);}
.leg-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}

/* ═══════════════════════════════════════════════
   FILTER PANEL
═══════════════════════════════════════════════ */
.filter-panel{background:var(--surf);border:1px solid var(--bdr);border-radius:var(--r);
    padding:18px 22px;margin-bottom:18px;box-shadow:var(--shd);}
.filter-label{font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.9px;
    color:var(--muted);margin-bottom:5px;display:block;}
.filter-control{background:var(--surf2);border:1.5px solid var(--bdr);color:var(--ink);
    border-radius:var(--r-sm);padding:8px 12px;font-family:var(--font);font-size:.8rem;
    font-weight:600;width:100%;outline:none;transition:var(--t);}
.filter-control:focus{border-color:var(--lime);box-shadow:0 0 0 3px rgba(168,224,99,.15);}
.btn-filter{display:inline-flex;align-items:center;gap:6px;padding:9px 20px;
    border-radius:var(--r-sm);background:var(--forest);color:#fff;border:none;
    font-family:var(--font);font-size:.8rem;font-weight:800;cursor:pointer;transition:var(--t);}
.btn-filter:hover{background:var(--forest-lt);transform:translateY(-1px);}
.btn-clear{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;
    border-radius:var(--r-sm);background:var(--surf2);color:var(--muted);
    border:1.5px solid var(--bdr);font-family:var(--font);font-size:.8rem;font-weight:700;
    cursor:pointer;transition:var(--t);text-decoration:none;}
.btn-clear:hover{border-color:var(--red);color:var(--red);}

/* ═══════════════════════════════════════════════
   TABLE
═══════════════════════════════════════════════ */
.txn-table-wrap{background:var(--surf);border:1px solid var(--bdr);border-radius:var(--r);
    overflow:hidden;box-shadow:var(--shd);}
.txn-table-head{display:flex;align-items:center;justify-content:space-between;
    padding:16px 22px 14px;border-bottom:1px solid var(--bdr);flex-wrap:wrap;gap:10px;}
.txn-table-title{font-size:.9rem;font-weight:800;color:var(--ink);}
.table-count{display:inline-flex;align-items:center;gap:5px;background:var(--lime-s);
    border:1px solid rgba(168,224,99,.25);border-radius:100px;padding:3px 10px;
    font-size:.65rem;font-weight:800;color:var(--forest);}
[data-bs-theme="dark"] .table-count{color:var(--lime);}
.export-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;
    border-radius:var(--r-sm);background:var(--surf2);border:1.5px solid var(--bdr);
    font-family:var(--font);font-size:.75rem;font-weight:700;color:var(--muted);
    text-decoration:none;transition:var(--t);}
.export-btn:hover{border-color:var(--forest);color:var(--forest);}
.export-btn i{font-size:.85rem;}

/* actual table */
table.lt{width:100%;border-collapse:collapse;}
table.lt thead tr{border-bottom:1px solid var(--bdr);}
table.lt thead th{padding:10px 18px;font-size:.62rem;font-weight:800;text-transform:uppercase;
    letter-spacing:.8px;color:var(--muted);white-space:nowrap;}
table.lt tbody tr{border-bottom:1px solid var(--bdr);transition:var(--t);}
table.lt tbody tr:last-child{border-bottom:none;}
table.lt tbody tr:hover{background:var(--surf2);}
table.lt tbody td{padding:13px 18px;vertical-align:middle;}

/* txn icon */
.txn-ico{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;
    justify-content:center;font-size:.85rem;flex-shrink:0;}
.tio-in{background:var(--green-s);color:var(--green);}
.tio-out{background:var(--red-s);color:var(--red);}
.tio-loan{background:var(--blue-s);color:var(--blue);}
.tio-welfare{background:var(--teal-s);color:var(--teal);}

/* type pill */
.type-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:100px;
    font-size:.65rem;font-weight:700;white-space:nowrap;}
.pill-deposit{background:var(--green-s);color:var(--green);}
.pill-contribution{background:var(--lime-s);color:var(--forest);}
[data-bs-theme="dark"] .pill-contribution{color:var(--lime);}
.pill-loan_repayment{background:var(--blue-s);color:var(--blue);}
.pill-loan_disbursement{background:var(--amber-s);color:var(--amber);}
.pill-withdrawal{background:var(--red-s);color:var(--red);}
.pill-welfare{background:var(--teal-s);color:var(--teal);}
.pill-revenue_inflow{background:var(--purple-s);color:var(--purple);}
.pill-default{background:var(--surf2);color:var(--muted);border:1px solid var(--bdr);}

/* channel badge */
.chan-badge{background:var(--surf2);border:1px solid var(--bdr);border-radius:6px;
    padding:2px 8px;font-size:.62rem;font-weight:700;color:var(--muted);font-family:var(--mono);}

/* amount */
.amt-in{font-family:var(--mono);font-size:.86rem;font-weight:600;color:var(--green);}
.amt-out{font-family:var(--mono);font-size:.86rem;font-weight:600;color:var(--red);}
.amt-loan{font-family:var(--mono);font-size:.86rem;font-weight:600;color:var(--blue);}

/* ref */
.ref-code{font-family:var(--mono);font-size:.62rem;color:var(--muted);
    background:var(--surf2);border:1px solid var(--bdr);padding:2px 7px;border-radius:5px;}

/* empty state */
.empty-state{padding:60px 20px;text-align:center;}
.empty-state i{font-size:2.5rem;color:var(--faint);display:block;margin-bottom:12px;}
.empty-state p{font-size:.82rem;font-weight:600;color:var(--muted);margin:0;}

/* ═══════════════════════════════════════════════
   THEME TOGGLE
═══════════════════════════════════════════════ */
.theme-toggle{position:fixed;bottom:24px;right:24px;z-index:1000;width:46px;height:46px;
    border-radius:50%;background:var(--surf);border:1.5px solid var(--bdr);
    color:var(--ink);display:flex;align-items:center;justify-content:center;
    box-shadow:var(--shd-h);cursor:pointer;transition:var(--t);}
.theme-toggle:hover{transform:scale(1.1);border-color:var(--lime);}

/* ═══════════════════════════════════════════════
   ANIMATIONS
═══════════════════════════════════════════════ */
@keyframes fadeUp{from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:none;}}
.a1{animation:fadeUp .38s .04s both;} .a2{animation:fadeUp .38s .10s both;}
.a3{animation:fadeUp .38s .16s both;} .a4{animation:fadeUp .38s .22s both;}
.a5{animation:fadeUp .38s .28s both;} .a6{animation:fadeUp .38s .34s both;}
.a7{animation:fadeUp .38s .40s both;}
</style>
<?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>
<?php $layout->sidebar(); ?>

<div class="main-content-wrapper">
<?php $layout->topbar($pageTitle ?? ''); ?>
<div class="page-wrap">

<!-- ════════════════════════════════════════
     HERO BANNER
════════════════════════════════════════ -->
<div class="hero a1">
    <div class="hero-ring"></div><div class="hero-ring2"></div>
    <div class="hero-inner">
        <div>
            <div class="hero-chip"><i class="bi bi-receipt"></i> Personal Ledger</div>
            <h1>Transaction History</h1>
            <p class="hero-sub">Full financial activity for your account &nbsp;·&nbsp; <strong><?= $total_txn_count ?> transactions</strong> on record</p>
            <div class="hero-kpis">
                <div class="hkpi"><div class="hkpi-v"><?= ks($net_savings) ?></div><div class="hkpi-l">Net Savings</div></div>
                <div class="hkpi"><div class="hkpi-v"><?= ks($total_loans) ?></div><div class="hkpi-l">Active Loans</div></div>
                <div class="hkpi"><div class="hkpi-v"><?= ks($total_repaid) ?></div><div class="hkpi-l">Total Repaid</div></div>
                <div class="hkpi"><div class="hkpi-v"><?= ks($total_withdrawn) ?></div><div class="hkpi-l">Withdrawn</div></div>
                <div class="hkpi"><div class="hkpi-v"><?= ks($wallet_bal) ?></div><div class="hkpi-l">Wallet</div></div>
            </div>
        </div>
        <div class="hero-actions" style="margin-top:0;">
            <div class="dropdown">
                <button class="hbtn hbtn-lime dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export
                </button>
                <ul class="dropdown-menu shadow-lg" style="border:1px solid var(--bdr);background:var(--surf);border-radius:var(--r-sm);">
                    <li><a class="dropdown-item" style="font-size:.82rem;font-weight:600;" href="?<?= http_build_query(array_merge($_GET,['action'=>'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Export PDF</a></li>
                    <li><a class="dropdown-item" style="font-size:.82rem;font-weight:600;" href="?<?= http_build_query(array_merge($_GET,['action'=>'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Export Excel</a></li>
                    <li><a class="dropdown-item" style="font-size:.82rem;font-weight:600;" href="?<?= http_build_query(array_merge($_GET,['action'=>'print_report'])) ?>" target="_blank"><i class="bi bi-printer text-primary me-2"></i>Print Ledger</a></li>
                </ul>
            </div>
            <button class="hbtn hbtn-ghost" onclick="document.getElementById('filtersSection').scrollIntoView({behavior:'smooth'})">
                <i class="bi bi-funnel"></i> Filters
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════
     SPARKLINE ROW  (8 key metrics)
════════════════════════════════════════ -->
<div class="spk-row a2" style="grid-template-columns:repeat(4,1fr);">
    <div class="spk spk-green">
        <div class="spk-lbl">Total Deposited</div>
        <div class="spk-val"><?= ks($total_deposited) ?></div>
        <div class="spk-chg up"><i class="bi bi-arrow-up-short"></i> All time inflows</div>
        <div class="spk-canvas-wrap"><canvas id="spk1"></canvas></div>
    </div>
    <div class="spk spk-red">
        <div class="spk-lbl">Total Withdrawn</div>
        <div class="spk-val"><?= ks($total_withdrawn) ?></div>
        <div class="spk-chg dn"><i class="bi bi-arrow-down-short"></i> All time outflows</div>
        <div class="spk-canvas-wrap"><canvas id="spk2"></canvas></div>
    </div>
    <div class="spk spk-blue">
        <div class="spk-lbl">Total Repaid</div>
        <div class="spk-val"><?= ks($total_repaid) ?></div>
        <div class="spk-chg up"><i class="bi bi-check2"></i> Loan repayments</div>
        <div class="spk-canvas-wrap"><canvas id="spk3"></canvas></div>
    </div>
    <div class="spk spk-amber">
        <div class="spk-lbl">This Month Net</div>
        <div class="spk-val"><?= ks($month_in - $month_out) ?></div>
        <div class="spk-chg <?= ($month_in-$month_out)>=0?'up':'dn' ?>">
            <i class="bi bi-calendar-month"></i> <?= date('F') ?>
        </div>
        <div class="spk-canvas-wrap"><canvas id="spk4"></canvas></div>
    </div>
</div>

<!-- ════════════════════════════════════════
     ROW 1: Area line + Grouped Bar
════════════════════════════════════════ -->
<div class="row g-3 mb-3 a3">
    <div class="col-xl-7">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">12-Month Cash Flow</div>
                    <div class="gp-sub">Monthly inflows vs outflows — dual filled area chart</div>
                </div>
                <div class="gp-stats">
                    <div>
                        <div class="gp-stat-v" style="color:var(--green)"><?= ks(array_sum($chart_in)) ?></div>
                        <div class="gp-stat-l">Total In</div>
                    </div>
                    <div class="gp-div"></div>
                    <div>
                        <div class="gp-stat-v" style="color:var(--red)"><?= ks(array_sum($chart_out)) ?></div>
                        <div class="gp-stat-l">Total Out</div>
                    </div>
                </div>
            </div>
            <div class="legend">
                <div class="leg-i"><span class="leg-dot" style="background:var(--green)"></span>Inflows</div>
                <div class="leg-i"><span class="leg-dot" style="background:var(--red)"></span>Outflows</div>
                <div class="leg-i"><span class="leg-dot" style="background:var(--blue)"></span>Net</div>
            </div>
            <div class="chart-box" style="height:260px;margin-top:10px;">
                <canvas id="chartFlow"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Running Balance</div>
                    <div class="gp-sub">Cumulative account balance — smooth area</div>
                </div>
            </div>
            <div class="gp-stats" style="margin-bottom:10px;">
                <div>
                    <div class="gp-stat-v"><?= ks($wallet_bal) ?></div>
                    <div class="gp-stat-l">Current</div>
                </div>
                <div class="gp-div"></div>
                <div>
                    <div class="gp-stat-v"><?= ks(max($run_data ?: [0])) ?></div>
                    <div class="gp-stat-l">Peak</div>
                </div>
            </div>
            <div class="chart-box" style="height:240px;">
                <canvas id="chartRunning"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════
     ROW 2: Stacked bar + Doughnut + Daily Line
════════════════════════════════════════ -->
<div class="row g-3 mb-3 a4">
    <div class="col-xl-5">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Monthly Comparison</div>
                    <div class="gp-sub">Income vs outflow — grouped bar chart</div>
                </div>
            </div>
            <div class="legend" style="margin-bottom:8px;">
                <div class="leg-i"><span class="leg-dot" style="background:var(--green)"></span>Income</div>
                <div class="leg-i"><span class="leg-dot" style="background:var(--red)"></span>Outflow</div>
            </div>
            <div class="chart-box" style="height:240px;">
                <canvas id="chartGrouped"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Transaction Mix</div>
                    <div class="gp-sub">By amount — doughnut chart</div>
                </div>
            </div>
            <div style="position:relative;width:150px;height:150px;margin:8px auto 12px;">
                <canvas id="chartDonut" width="150" height="150"></canvas>
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;pointer-events:none;">
                    <div style="font-family:var(--mono);font-size:.82rem;font-weight:600;color:var(--ink);letter-spacing:-.4px;line-height:1.1;"><?= count($type_labels) ?> types</div>
                    <div style="font-size:.52rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">categories</div>
                </div>
            </div>
            <?php
            $donut_colors = ['#1a3a2a','#a8e063','#4481eb','#e63757','#20c997','#f0a500','#7c4dff'];
            foreach ($type_labels as $i => $lbl): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--bdr);font-size:.7rem;">
                <span style="display:flex;align-items:center;gap:6px;font-weight:600;color:var(--muted);">
                    <span style="width:7px;height:7px;border-radius:50%;background:<?= $donut_colors[$i % count($donut_colors)] ?>;flex-shrink:0;"></span>
                    <?= $lbl ?>
                </span>
                <span style="font-family:var(--mono);font-weight:600;color:var(--ink);font-size:.68rem;"><?= ks($type_amounts[$i]) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Daily Activity — 14 Days</div>
                    <div class="gp-sub">Inflows vs outflows per day</div>
                </div>
            </div>
            <div class="legend" style="margin-bottom:8px;">
                <div class="leg-i"><span class="leg-dot" style="background:var(--green)"></span>In</div>
                <div class="leg-i"><span class="leg-dot" style="background:var(--red)"></span>Out</div>
            </div>
            <div class="chart-box" style="height:220px;">
                <canvas id="chartDaily"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════
     ROW 3: Horizontal bar + Polar + Net bar
════════════════════════════════════════ -->
<div class="row g-3 mb-3 a5">
    <div class="col-xl-5">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Transaction Volume by Type</div>
                    <div class="gp-sub">Cumulative KES per category — horizontal bar</div>
                </div>
            </div>
            <div class="chart-box" style="height:260px;">
                <canvas id="chartHBar"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Transaction Count</div>
                    <div class="gp-sub">Number of txns per type — polar area</div>
                </div>
            </div>
            <div class="chart-box" style="height:200px;">
                <canvas id="chartPolar"></canvas>
            </div>
            <div class="legend" style="margin-top:8px;flex-wrap:wrap;">
                <?php foreach ($type_labels as $i => $lbl): ?>
                <div class="leg-i"><span class="leg-dot" style="background:<?= $donut_colors[$i % count($donut_colors)] ?>"></span><?= $lbl ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Monthly Net Position</div>
                    <div class="gp-sub">Net cash flow per month — positive/negative bar</div>
                </div>
            </div>
            <div class="chart-box" style="height:260px;">
                <canvas id="chartNet"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════
     FILTER PANEL
════════════════════════════════════════ -->
<div class="filter-panel a6" id="filtersSection">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
        <div style="width:26px;height:26px;border-radius:7px;background:var(--lime-s);color:var(--forest);display:flex;align-items:center;justify-content:center;font-size:.8rem;"><i class="bi bi-funnel-fill"></i></div>
        <span style="font-size:.84rem;font-weight:800;color:var(--ink);">Filter Transactions</span>
    </div>
    <form method="GET">
        <div class="row g-3 align-items-end">
            <div class="col-xl-3 col-md-6">
                <label class="filter-label">Transaction Type</label>
                <select name="type" class="filter-control">
                    <option value="">All Types</option>
                    <option value="deposit"           <?= $type_filter==='deposit'           ?'selected':'' ?>>Savings Deposit</option>
                    <option value="contribution"      <?= $type_filter==='contribution'      ?'selected':'' ?>>Contribution</option>
                    <option value="revenue_inflow"    <?= $type_filter==='revenue_inflow'    ?'selected':'' ?>>Registration Fee</option>
                    <option value="loan_disbursement" <?= $type_filter==='loan_disbursement' ?'selected':'' ?>>Loan Received</option>
                    <option value="loan_repayment"    <?= $type_filter==='loan_repayment'    ?'selected':'' ?>>Loan Repayment</option>
                    <option value="withdrawal"        <?= $type_filter==='withdrawal'        ?'selected':'' ?>>Withdrawal</option>
                    <option value="welfare"           <?= $type_filter==='welfare'           ?'selected':'' ?>>Welfare</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-6">
                <label class="filter-label">Specific Date</label>
                <input type="date" name="date" class="filter-control" value="<?= htmlspecialchars($date_filter ?? '') ?>">
            </div>
            <div class="col-xl-2 col-md-6">
                <label class="filter-label">From Date</label>
                <input type="date" name="from" class="filter-control" value="<?= htmlspecialchars($from_filter ?? '') ?>">
            </div>
            <div class="col-xl-2 col-md-6">
                <label class="filter-label">To Date</label>
                <input type="date" name="to" class="filter-control" value="<?= htmlspecialchars($to_filter ?? '') ?>">
            </div>
            <div class="col-xl-3 col-md-12">
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn-filter flex-fill"><i class="bi bi-funnel"></i> Apply</button>
                    <a href="transactions.php" class="btn-clear"><i class="bi bi-x"></i> Clear</a>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- ════════════════════════════════════════
     TRANSACTIONS TABLE
════════════════════════════════════════ -->
<div class="txn-table-wrap a7">
    <div class="txn-table-head">
        <div style="display:flex;align-items:center;gap:10px;">
            <span class="txn-table-title">All Transactions</span>
            <span class="table-count"><i class="bi bi-list-ul"></i> <?= $result->num_rows ?> records</span>
            <?php if ($type_filter || $date_filter || $from_filter || $to_filter): ?>
            <span style="background:var(--amber-s);border:1px solid rgba(240,165,0,.25);border-radius:100px;padding:3px 10px;font-size:.62rem;font-weight:800;color:var(--amber);">
                <i class="bi bi-funnel-fill"></i> Filtered
            </span>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="?<?= http_build_query(array_merge($_GET,['action'=>'export_pdf'])) ?>" class="export-btn"><i class="bi bi-file-pdf"></i> PDF</a>
            <a href="?<?= http_build_query(array_merge($_GET,['action'=>'export_excel'])) ?>" class="export-btn"><i class="bi bi-file-excel"></i> Excel</a>
            <a href="?<?= http_build_query(array_merge($_GET,['action'=>'print_report'])) ?>" class="export-btn" target="_blank"><i class="bi bi-printer"></i> Print</a>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="lt">
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th>Channel</th>
                    <th style="text-align:right;">Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $result->data_seek(0);
            if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                    $type = strtolower($row['transaction_type'] ?? '');
                    $is_in    = in_array($type, ['deposit','contribution','welfare','revenue_inflow']);
                    $is_loan  = ($type === 'loan_disbursement');
                    $is_out   = ($type === 'withdrawal' || $type === 'loan_repayment');
                    $dt = new DateTime($row['created_at']);

                    // Icon class
                    if ($type === 'welfare' || $type === 'contribution') $ico_class = 'tio-welfare';
                    elseif ($is_loan)  $ico_class = 'tio-loan';
                    elseif ($is_in)    $ico_class = 'tio-in';
                    else               $ico_class = 'tio-out';

                    $icon_map = [
                        'deposit'=>'bi-arrow-down-left', 'contribution'=>'bi-calendar-check',
                        'withdrawal'=>'bi-arrow-up-right', 'loan_repayment'=>'bi-cash-stack',
                        'loan_disbursement'=>'bi-bank', 'welfare'=>'bi-heart-pulse',
                        'revenue_inflow'=>'bi-receipt',
                    ];
                    $icon = $icon_map[$type] ?? 'bi-arrow-left-right';

                    // Amount style
                    if ($is_loan)        { $amt_class = 'amt-loan'; $sign = '+'; }
                    elseif ($is_in)      { $amt_class = 'amt-in';   $sign = '+'; }
                    else                 { $amt_class = 'amt-out';  $sign = '-'; }

                    // Pill class
                    $pill_class = 'pill-'.($type ? str_replace(' ','_',$type) : 'default');
                    if (!in_array($pill_class, ['pill-deposit','pill-contribution','pill-loan_repayment','pill-loan_disbursement','pill-withdrawal','pill-welfare','pill-revenue_inflow'])) $pill_class = 'pill-default';

                    $display = ucwords(str_replace('_',' ',$type));
            ?>
            <tr>
                <td style="white-space:nowrap;min-width:120px;">
                    <div style="font-size:.82rem;font-weight:700;color:var(--ink);"><?= $dt->format('d M Y') ?></div>
                    <div style="font-size:.65rem;font-weight:600;color:var(--faint);"><?= $dt->format('h:i A') ?></div>
                </td>
                <td>
                    <span class="type-pill <?= $pill_class ?>">
                        <i class="bi <?= $icon ?>"></i> <?= $display ?>
                    </span>
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="txn-ico <?= $ico_class ?>"><i class="bi <?= $icon ?>"></i></div>
                        <div>
                            <div style="font-size:.78rem;font-weight:700;color:var(--ink);"><?= $display ?></div>
                            <div style="font-size:.64rem;color:var(--muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?= htmlspecialchars($row['notes'] ?? '—') ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td><span class="ref-code"><?= htmlspecialchars($row['reference_no'] ?? '—') ?></span></td>
                <td><span class="chan-badge"><?= strtoupper(htmlspecialchars($row['payment_channel'] ?? 'SYS')) ?></span></td>
                <td style="text-align:right;white-space:nowrap;">
                    <span class="<?= $amt_class ?>"><?= $sign ?> KES <?= number_format((float)$row['amount'], 2) ?></span>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="6"><div class="empty-state"><i class="bi bi-inbox"></i><p>No transactions match your filters</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div><!-- /page-wrap -->
<?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<!-- Theme toggle -->
<button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
    <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
/* ── PHP → JS DATA ──────────────────────────── */
const MO_LABELS   = <?= json_encode($chart_labels) ?>;
const CHART_IN    = <?= json_encode($chart_in) ?>;
const CHART_OUT   = <?= json_encode($chart_out) ?>;
const CHART_NET   = <?= json_encode($chart_net) ?>;
const RUN_LABELS  = <?= json_encode($run_labels) ?>;
const RUN_DATA    = <?= json_encode($run_data) ?>;
const DAY_LABELS  = <?= json_encode($daily_labels) ?>;
const DAY_IN      = <?= json_encode($daily_in) ?>;
const DAY_OUT     = <?= json_encode($daily_out) ?>;
const TYPE_LABELS = <?= json_encode($type_labels) ?>;
const TYPE_COUNTS = <?= json_encode($type_counts) ?>;
const TYPE_AMOUNTS= <?= json_encode($type_amounts) ?>;

/* ── THEME ──────────────────────────────────── */
const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
const GRID = dark ? 'rgba(255,255,255,.05)' : 'rgba(26,42,74,.05)';
const TICK = dark ? '#3a5a76' : '#8a9fb0';
const SURF = dark ? '#0f1e2d' : '#ffffff';

const TT = {
    backgroundColor: dark ? '#0f1e2d' : '#1a3a2a',
    titleColor:'#a8e063', bodyColor:'#fff',
    padding:12, cornerRadius:10,
    borderColor:'rgba(168,224,99,.2)', borderWidth:1,
    titleFont:{family:"'Plus Jakarta Sans',sans-serif",weight:'800',size:12},
    bodyFont:{family:"'JetBrains Mono',monospace",size:11},
};
const XS = {grid:{display:false}, ticks:{color:TICK,font:{family:"'Plus Jakarta Sans',sans-serif",size:10}}};
const YS = {grid:{color:GRID},    ticks:{color:TICK,font:{family:"'Plus Jakarta Sans',sans-serif",size:10}}};

const COLORS = ['#1a3a2a','#a8e063','#4481eb','#e63757','#20c997','#f0a500','#7c4dff'];

/* ── SPARKLINES ─────────────────────────────── */
function sparkline(id, data, color) {
    const el = document.getElementById(id);
    if (!el) return;
    new Chart(el, {
        type:'line',
        data:{labels:MO_LABELS.slice(-data.length),
            datasets:[{data,borderColor:color,borderWidth:2,fill:true,backgroundColor:color+'28',tension:.5,pointRadius:0}]},
        options:{responsive:true,maintainAspectRatio:false,animation:{duration:900},
            plugins:{legend:{display:false},tooltip:{enabled:false}},
            scales:{x:{display:false},y:{display:false}}}
    });
}
sparkline('spk1', CHART_IN,                  '#1aa053');
sparkline('spk2', CHART_OUT,                 '#e63757');
sparkline('spk3', [...CHART_OUT],            '#4481eb');
sparkline('spk4', CHART_NET,                 '#f0a500');

/* ── 1. DUAL AREA — 12mo Cash Flow ─────────── */
(()=>{
    const ctx = document.getElementById('chartFlow').getContext('2d');
    const gi = ctx.createLinearGradient(0,0,0,260);
    gi.addColorStop(0,'#1aa05340'); gi.addColorStop(1,'#1aa05300');
    const go = ctx.createLinearGradient(0,0,0,260);
    go.addColorStop(0,'#e6375740'); go.addColorStop(1,'#e6375700');
    new Chart(ctx, {
        type:'line',
        data:{labels:MO_LABELS, datasets:[
            {label:'Inflows', data:CHART_IN,  borderColor:'#1aa053',borderWidth:2.5,
             backgroundColor:gi,fill:true,tension:.45,
             pointRadius:4,pointBackgroundColor:'#1aa053',pointBorderColor:SURF,pointBorderWidth:2},
            {label:'Outflows',data:CHART_OUT, borderColor:'#e63757',borderWidth:2.5,
             backgroundColor:go,fill:true,tension:.45,
             pointRadius:4,pointBackgroundColor:'#e63757',pointBorderColor:SURF,pointBorderWidth:2},
            {label:'Net',     data:CHART_NET, borderColor:'#4481eb',borderWidth:2,
             backgroundColor:'transparent',fill:false,tension:.45,borderDash:[5,3],
             pointRadius:3,pointBackgroundColor:'#4481eb',pointBorderColor:SURF,pointBorderWidth:2},
        ]},
        options:{responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:{...TT,callbacks:{label:c=>' '+c.dataset.label+': KES '+c.parsed.y.toLocaleString()}}},
            scales:{x:XS,y:YS}}
    });
})();

/* ── 2. AREA LINE — Running Balance ─────────── */
(()=>{
    const ctx = document.getElementById('chartRunning').getContext('2d');
    const g = ctx.createLinearGradient(0,0,0,240);
    g.addColorStop(0,'#1a3a2a55'); g.addColorStop(1,'#1a3a2a00');
    new Chart(ctx, {
        type:'line',
        data:{labels:RUN_LABELS, datasets:[{label:'Balance',data:RUN_DATA,
            borderColor:'#1a3a2a',borderWidth:2.5,backgroundColor:g,fill:true,tension:.45,
            pointRadius:4,pointBackgroundColor:'#1a3a2a',pointBorderColor:SURF,pointBorderWidth:2}]},
        options:{responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:{...TT,callbacks:{label:c=>' KES '+c.parsed.y.toLocaleString()}}},
            scales:{x:XS,y:YS}}
    });
})();

/* ── 3. GROUPED BAR ─────────────────────────── */
new Chart(document.getElementById('chartGrouped'),{
    type:'bar',
    data:{labels:MO_LABELS, datasets:[
        {label:'Income', data:CHART_IN,  backgroundColor:'#1aa053cc',borderRadius:6,barPercentage:.7},
        {label:'Outflow',data:CHART_OUT, backgroundColor:'#e63757cc',borderRadius:6,barPercentage:.7},
    ]},
    options:{responsive:true,maintainAspectRatio:false,
        plugins:{legend:{display:false},tooltip:TT},
        scales:{x:XS,y:YS}}
});

/* ── 4. DOUGHNUT ────────────────────────────── */
new Chart(document.getElementById('chartDonut'),{
    type:'doughnut',
    data:{labels:TYPE_LABELS,
        datasets:[{data:TYPE_AMOUNTS,backgroundColor:COLORS.map(c=>c+'cc'),borderWidth:0,hoverOffset:7}]},
    options:{cutout:'70%',responsive:false,
        plugins:{legend:{display:false},tooltip:{...TT,callbacks:{label:c=>' KES '+c.parsed.toLocaleString()}}}}
});

/* ── 5. DAILY DUAL LINE ─────────────────────── */
(()=>{
    const ctx = document.getElementById('chartDaily').getContext('2d');
    const gi  = ctx.createLinearGradient(0,0,0,220);
    gi.addColorStop(0,'#1aa05330'); gi.addColorStop(1,'#1aa05300');
    const go  = ctx.createLinearGradient(0,0,0,220);
    go.addColorStop(0,'#e6375730'); go.addColorStop(1,'#e6375700');
    new Chart(ctx,{
        type:'line',
        data:{labels:DAY_LABELS, datasets:[
            {label:'In', data:DAY_IN,  borderColor:'#1aa053',borderWidth:2,backgroundColor:gi,fill:true,tension:.4,pointRadius:3,pointBackgroundColor:'#1aa053',pointBorderColor:SURF,pointBorderWidth:1.5},
            {label:'Out',data:DAY_OUT, borderColor:'#e63757',borderWidth:2,backgroundColor:go,fill:true,tension:.4,pointRadius:3,pointBackgroundColor:'#e63757',pointBorderColor:SURF,pointBorderWidth:1.5},
        ]},
        options:{responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:{...TT,callbacks:{label:c=>' '+c.dataset.label+': KES '+c.parsed.y.toLocaleString()}}},
            scales:{x:XS,y:YS}}
    });
})();

/* ── 6. HORIZONTAL BAR ──────────────────────── */
new Chart(document.getElementById('chartHBar'),{
    type:'bar',
    data:{labels:TYPE_LABELS,
        datasets:[{label:'KES Total',data:TYPE_AMOUNTS,backgroundColor:COLORS.map(c=>c+'cc'),borderRadius:8,barPercentage:.65}]},
    options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,
        plugins:{legend:{display:false},tooltip:{...TT,callbacks:{label:c=>' KES '+c.parsed.x.toLocaleString()}}},
        scales:{x:YS, y:{grid:{display:false},ticks:{color:TICK,font:{family:"'Plus Jakarta Sans',sans-serif",size:10}}}}}
});

/* ── 7. POLAR AREA ──────────────────────────── */
new Chart(document.getElementById('chartPolar'),{
    type:'polarArea',
    data:{labels:TYPE_LABELS,
        datasets:[{data:TYPE_COUNTS,backgroundColor:COLORS.map(c=>c+'cc'),borderWidth:0}]},
    options:{responsive:true,maintainAspectRatio:false,
        plugins:{legend:{display:false},tooltip:{...TT,callbacks:{label:c=>' '+c.parsed.r+' transactions'}}},
        scales:{r:{grid:{color:dark?'rgba(255,255,255,.06)':'rgba(26,42,74,.06)'},ticks:{display:false}}}}
});

/* ── 8. NET POSITION BAR ─────────────────────── */
new Chart(document.getElementById('chartNet'),{
    type:'bar',
    data:{labels:MO_LABELS,
        datasets:[{label:'Net',data:CHART_NET,
            backgroundColor:CHART_NET.map(v=>v>=0?'#1aa053cc':'#e63757cc'),
            borderRadius:7,barPercentage:.7}]},
    options:{responsive:true,maintainAspectRatio:false,
        plugins:{legend:{display:false},tooltip:{...TT,callbacks:{label:c=>' Net: KES '+c.parsed.y.toLocaleString()}}},
        scales:{x:XS, y:{...YS, ticks:{...YS.ticks,callback:v=>v>=0?'+'+v.toLocaleString():v.toLocaleString()}}}}
});

/* ── THEME TOGGLE ───────────────────────────── */
document.getElementById('themeToggle').addEventListener('click',()=>{
    const html = document.getElementById('htmlTag');
    const icon = document.getElementById('themeIcon');
    const next = html.getAttribute('data-bs-theme')==='dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme',next);
    icon.className = next==='dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    localStorage.setItem('theme',next);
    // reload charts for correct theme colors
    setTimeout(()=>location.reload(), 300);
});
document.addEventListener('DOMContentLoaded',()=>{
    const saved = localStorage.getItem('theme')||'light';
    document.getElementById('htmlTag').setAttribute('data-bs-theme',saved);
    document.getElementById('themeIcon').className = saved==='dark'?'bi bi-sun-fill':'bi bi-moon-stars-fill';
});
</script>
</body>
</html>