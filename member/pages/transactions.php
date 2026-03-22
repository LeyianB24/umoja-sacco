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

$member_id = (int)$_SESSION['member_id'];

// Filters
$type_filter = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_SPECIAL_CHARS);
$date_filter = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS);
$from_filter = filter_input(INPUT_GET, 'from', FILTER_SANITIZE_SPECIAL_CHARS);
$to_filter   = filter_input(INPUT_GET, 'to',   FILTER_SANITIZE_SPECIAL_CHARS);

// Main query
$sql    = "SELECT transaction_id, transaction_type, amount, reference_no, created_at, payment_channel, notes FROM transactions WHERE member_id = ?";
$params = [$member_id]; $types = "i";
if ($type_filter) { $sql .= " AND transaction_type = ?";  $params[] = $type_filter; $types .= "s"; }
if ($date_filter) { $sql .= " AND DATE(created_at) = ?";  $params[] = $date_filter; $types .= "s"; }
if ($from_filter) { $sql .= " AND DATE(created_at) >= ?"; $params[] = $from_filter; $types .= "s"; }
if ($to_filter)   { $sql .= " AND DATE(created_at) <= ?"; $params[] = $to_filter;   $types .= "s"; }
$sql .= " ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Export
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf','export_excel','print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    $format = match($_GET['action']) { 'export_excel'=>'excel','print_report'=>'print', default=>'pdf' };
    $data = [];
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        $t = strtolower($row['transaction_type'] ?? '');
        $data[] = ['Date'=>date('d-M-Y H:i',strtotime($row['created_at'])),'Type'=>ucwords(str_replace('_',' ',$t)),'Reference'=>$row['reference_no'],'Channel'=>strtoupper($row['payment_channel']??''),'Amount'=>($t==='withdrawal'?'-':'+').' '.number_format((float)$row['amount'],2)];
    }
    UniversalExportEngine::handle($format,$data,['title'=>'Personal Transaction Ledger','module'=>'Member Portal','headers'=>['Date','Type','Reference','Channel','Amount']]);
    exit;
}

// KPIs
require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine   = new FinancialEngine($conn);
$balances = $engine->getBalances($member_id);
$net_savings     = (float)$balances['savings'];
$total_loans     = (float)$balances['loans'];
$wallet_bal      = (float)$balances['wallet'];
$total_shares    = (float)$balances['shares'];
$total_repaid    = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type='loan_repayment'")->fetch_row()[0];
$total_withdrawn = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type='withdrawal'")->fetch_row()[0];
$total_deposited = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type IN('deposit','contribution')")->fetch_row()[0];
$total_txn_count = (int)  $conn->query("SELECT COUNT(*) FROM transactions WHERE member_id=$member_id")->fetch_row()[0];
$month_in  = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type IN('deposit','contribution') AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetch_row()[0];
$month_out = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type IN('withdrawal','loan_repayment') AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetch_row()[0];

// 12-month cash flow chart data
$chart_labels = $chart_in = $chart_out = $chart_net = [];
for ($i = 11; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("-$i months"));
    $me = date('Y-m-t',  strtotime("-$i months"));
    $chart_labels[] = date('M', strtotime($ms));
    $in  = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type IN('deposit','contribution') AND created_at BETWEEN '$ms' AND '$me 23:59:59'")->fetch_row()[0];
    $out = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=$member_id AND transaction_type IN('withdrawal','loan_repayment') AND created_at BETWEEN '$ms' AND '$me 23:59:59'")->fetch_row()[0];
    $chart_in[]  = $in; $chart_out[] = $out; $chart_net[] = $in - $out;
}

// Transaction type breakdown (doughnut)
$type_labels = $type_amounts = [];
$tr = $conn->query("SELECT transaction_type, COALESCE(SUM(amount),0) as total FROM transactions WHERE member_id=$member_id GROUP BY transaction_type ORDER BY total DESC LIMIT 7");
while ($row = $tr->fetch_assoc()) {
    $type_labels[]  = ucwords(str_replace('_',' ',$row['transaction_type']));
    $type_amounts[] = (float)$row['total'];
}

function ks(float $n): string {
    if ($n >= 1_000_000) return 'KES '.number_format($n/1_000_000,2).'M';
    if ($n >= 1_000)     return 'KES '.number_format($n/1_000,1).'K';
    return 'KES '.number_format($n,2);
}

$pageTitle = "Transaction Ledger";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<script>(function(){var s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $pageTitle ?> &middot; <?= defined('SITE_NAME') ? SITE_NAME : 'Umoja SACCO' ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════
   TRANSACTION LEDGER · HD · Forest & Lime · Plus Jakarta Sans
═══════════════════════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --f:#0b2419;--fm:#154330;--fs:#1d6044;
    --lime:#a3e635;--lt:#6a9a1a;--lg:rgba(163,230,53,.14);
    --bg:#eff5f1;--bg2:#e8f1ec;--surf:#fff;--surf2:#f7fbf8;
    --bdr:rgba(11,36,25,.07);--bdr2:rgba(11,36,25,.04);
    --t1:#0b2419;--t2:#456859;--t3:#8fada0;
    --grn:#16a34a;--red:#dc2626;--amb:#d97706;--blu:#2563eb;
    --grn-bg:rgba(22,163,74,.08);--red-bg:rgba(220,38,38,.08);
    --amb-bg:rgba(217,119,6,.08);--blu-bg:rgba(37,99,235,.08);
    --r:20px;--rsm:12px;
    --ease:cubic-bezier(.16,1,.3,1);--spring:cubic-bezier(.34,1.56,.64,1);
    --sh:0 1px 3px rgba(11,36,25,.05),0 6px 20px rgba(11,36,25,.08);
    --sh-lg:0 4px 8px rgba(11,36,25,.07),0 20px 56px rgba(11,36,25,.13);
}
[data-bs-theme="dark"]{
    --bg:#070e0b;--bg2:#0a1510;--surf:#0d1d14;--surf2:#0a1810;
    --bdr:rgba(255,255,255,.07);--bdr2:rgba(255,255,255,.04);
    --t1:#d8eee2;--t2:#4d7a60;--t3:#2a4d38;
}
body,*{font-family:'Plus Jakarta Sans',sans-serif!important;-webkit-font-smoothing:antialiased}
body{background:var(--bg);color:var(--t1)}
.main-content-wrapper{margin-left:272px;min-height:100vh;transition:margin-left .3s var(--ease)}
body.sb-collapsed .main-content-wrapper{margin-left:72px}
@media(max-width:991px){.main-content-wrapper{margin-left:0}}
.dash{padding:28px 28px 72px}
@media(max-width:768px){.dash{padding:16px 14px 48px}}

/* ── HERO ── */
.hero{background:linear-gradient(135deg,var(--f) 0%,var(--fm) 55%,var(--fs) 100%);border-radius:var(--r);padding:40px 48px 96px;position:relative;overflow:hidden;color:#fff;animation:fadeUp .7s var(--ease) both}
.hero-mesh{position:absolute;inset:0;pointer-events:none;background:radial-gradient(ellipse 60% 80% at 105% -5%,rgba(163,230,53,.11) 0%,transparent 55%),radial-gradient(ellipse 35% 45% at -8% 105%,rgba(163,230,53,.07) 0%,transparent 55%)}
.hero-dots{position:absolute;inset:0;pointer-events:none;background-image:radial-gradient(rgba(255,255,255,.05) 1px,transparent 1px);background-size:20px 20px}
.hero-ring{position:absolute;border-radius:50%;pointer-events:none;border:1px solid rgba(163,230,53,.07)}
.hero-ring.r1{width:420px;height:420px;top:-140px;right:-100px}
.hero-ring.r2{width:620px;height:620px;top:-220px;right:-200px}
.hero-inner{position:relative;z-index:2}
.hero-eyebrow{display:inline-flex;align-items:center;gap:7px;background:rgba(163,230,53,.12);border:1px solid rgba(163,230,53,.2);border-radius:50px;padding:4px 14px;margin-bottom:14px;font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#bff060}
.eyebrow-dot{width:5px;height:5px;border-radius:50%;background:var(--lime);animation:pulse 1.8s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(1.8)}}
.hero h1{font-size:clamp(1.8rem,4vw,2.6rem);font-weight:800;color:#fff;letter-spacing:-.6px;line-height:1.1;margin-bottom:8px}
.hero-sub{font-size:.8rem;color:rgba(255,255,255,.45);margin-bottom:22px;font-weight:500}
.hero-sub strong{color:rgba(255,255,255,.75);font-weight:700}
.hero-bubbles{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:26px}
.hbub{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:11px 16px;min-width:100px;transition:all .22s var(--spring)}
.hbub:hover{background:rgba(255,255,255,.18);transform:translateY(-2px)}
.hbub-val{font-size:.9rem;font-weight:800;color:#fff;letter-spacing:-.3px;line-height:1.1}
.hbub-lbl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.4);margin-top:3px}
.hero-ctas{display:flex;gap:9px;flex-wrap:wrap}
.btn-lime{display:inline-flex;align-items:center;gap:8px;background:var(--lime);color:var(--f);font-size:.875rem;font-weight:800;padding:11px 24px;border-radius:50px;border:none;cursor:pointer;text-decoration:none;box-shadow:0 2px 14px rgba(163,230,53,.28);transition:all .25s var(--spring)}
.btn-lime:hover{transform:translateY(-2px) scale(1.03);box-shadow:0 10px 28px rgba(163,230,53,.4);color:var(--f)}
.btn-ghost{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);font-size:.875rem;font-weight:700;padding:11px 20px;border-radius:50px;cursor:pointer;text-decoration:none;transition:all .22s ease}
.btn-ghost:hover{background:rgba(255,255,255,.17);color:#fff;transform:translateY(-2px)}

/* ── STAT CARDS ── */
.stats-float{margin-top:-56px;position:relative;z-index:10;padding:0 28px;animation:floatUp .8s var(--ease) .4s both}
@media(max-width:767px){.stats-float{padding:0 14px}}
.sc{background:var(--surf);border-radius:var(--r);padding:22px 24px;border:1px solid var(--bdr);box-shadow:var(--sh-lg);height:100%;position:relative;overflow:hidden;transition:transform .28s var(--ease),box-shadow .28s ease}
.sc:hover{transform:translateY(-4px);box-shadow:0 8px 20px rgba(11,36,25,.09),0 32px 64px rgba(11,36,25,.14)}
.sc::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2.5px;border-radius:0 0 var(--r) var(--r);transform:scaleX(0);transform-origin:left;transition:transform .38s var(--ease)}
.sc:hover::after{transform:scaleX(1)}
.sc-g::after{background:linear-gradient(90deg,#16a34a,#4ade80)}
.sc-r::after{background:linear-gradient(90deg,#dc2626,#f87171)}
.sc-a::after{background:linear-gradient(90deg,#d97706,#fbbf24)}
.sc-b::after{background:linear-gradient(90deg,#2563eb,#60a5fa)}
.sc-ico{width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:16px;transition:transform .3s var(--spring)}
.sc:hover .sc-ico{transform:scale(1.12) rotate(7deg)}
.sc-lbl{font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--t3);margin-bottom:5px}
.sc-val{font-size:1.5rem;font-weight:800;color:var(--t1);letter-spacing:-.8px;line-height:1.1;margin-bottom:14px}
.sc-bar{height:4px;border-radius:99px;background:var(--bg);overflow:hidden;margin-bottom:9px}
.sc-bar-fill{height:100%;border-radius:99px;width:0;transition:width 1.4s var(--ease)}
.sc-meta{font-size:.72rem;font-weight:600;color:var(--t3)}
.sa1{animation:floatUp .7s var(--ease) .45s both}
.sa2{animation:floatUp .7s var(--ease) .53s both}
.sa3{animation:floatUp .7s var(--ease) .61s both}
.sa4{animation:floatUp .7s var(--ease) .69s both}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes floatUp{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}

/* ── PAGE BODY ── */
.pg-body{padding:32px 28px 0}
@media(max-width:767px){.pg-body{padding:24px 14px 0}}

/* ── CHART CARDS ── */
.chart-card{background:var(--surf);border-radius:var(--r);padding:24px 26px;border:1px solid var(--bdr);box-shadow:var(--sh);height:100%;display:flex;flex-direction:column;animation:floatUp .7s var(--ease) both}
.cc-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;gap:10px}
.cc-title{font-size:.9rem;font-weight:800;color:var(--t1);letter-spacing:-.2px}
.cc-sub{font-size:.7rem;font-weight:500;color:var(--t3);margin-top:2px}
.cc-stats{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px}
.cc-stat-val{font-size:.88rem;font-weight:800;color:var(--t1);letter-spacing:-.3px;line-height:1.1}
.cc-stat-lbl{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--t3);margin-top:2px}
.cc-stat-div{width:1px;height:28px;background:var(--bdr);align-self:center}
.chart-box{position:relative;flex:1}
.leg{display:flex;gap:12px;flex-wrap:wrap;margin-top:10px}
.leg-i{display:flex;align-items:center;gap:5px;font-size:.68rem;font-weight:700;color:var(--t3)}
.leg-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}

/* ── FILTER CARD ── */
.filter-card{background:var(--surf);border-radius:var(--r);padding:22px 26px;border:1px solid var(--bdr);box-shadow:var(--sh);margin-bottom:20px;animation:floatUp .7s var(--ease) .88s both}
.filter-card-head{display:flex;align-items:center;gap:10px;margin-bottom:18px}
.filter-card-title{font-size:.875rem;font-weight:800;color:var(--t1)}
.filter-lbl{font-size:10px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:var(--t3);margin-bottom:6px;display:block}
.filter-ctrl{background:var(--surf2);border:1px solid var(--bdr);color:var(--t1);border-radius:var(--rsm);padding:9px 13px;width:100%;outline:none;font-size:.82rem;font-weight:600;transition:border-color .18s ease,box-shadow .18s ease}
.filter-ctrl:focus{border-color:rgba(11,36,25,.3);box-shadow:0 0 0 3px rgba(11,36,25,.06)}
.btn-apply{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;border-radius:50px;background:var(--f);color:#fff;border:none;font-size:.83rem;font-weight:800;cursor:pointer;transition:all .22s var(--ease)}
.btn-apply:hover{background:var(--fm);transform:translateY(-1px);box-shadow:0 6px 18px rgba(11,36,25,.2)}
.btn-clear-filter{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:50px;background:var(--bg);color:var(--t2);border:1px solid var(--bdr);font-size:.83rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .18s ease}
.btn-clear-filter:hover{border-color:rgba(220,38,38,.3);color:var(--red)}

/* ── LEDGER TABLE CARD ── */
.txn-card{background:var(--surf);border-radius:22px;border:1px solid var(--bdr);box-shadow:var(--sh);overflow:hidden;animation:floatUp .7s var(--ease) .94s both}
.txn-card-head{display:flex;align-items:center;justify-content:space-between;padding:18px 26px;border-bottom:1px solid var(--bdr2);flex-wrap:wrap;gap:12px;background:var(--surf2)}
.txn-card-title{font-size:.88rem;font-weight:800;color:var(--t1)}
.txn-ct{display:inline-flex;align-items:center;gap:5px;background:var(--lg);border:1px solid rgba(163,230,53,.25);border-radius:50px;padding:3px 10px;font-size:9.5px;font-weight:800;color:var(--lt)}
[data-bs-theme="dark"] .txn-ct{color:var(--lime)}
.filter-badge{background:var(--amb-bg);border:1px solid rgba(217,119,6,.25);border-radius:50px;padding:3px 10px;font-size:9.5px;font-weight:800;color:var(--amb)}
.btn-exp{display:inline-flex;align-items:center;gap:6px;background:var(--bg);border:1px solid var(--bdr);color:var(--t2);font-size:.76rem;font-weight:700;padding:7px 14px;border-radius:50px;text-decoration:none;transition:all .18s ease}
.btn-exp:hover{border-color:rgba(11,36,25,.18);color:var(--t1);background:var(--surf)}

/* ── TABLE ── */
.lt{width:100%;border-collapse:collapse}
.lt thead th{background:var(--surf2);font-size:10px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:var(--t3);padding:11px 18px;border:none;border-bottom:1px solid var(--bdr2);white-space:nowrap}
.lt tbody tr{border-bottom:1px solid var(--bdr2);transition:background .13s ease}
.lt tbody tr:last-child{border-bottom:none}
.lt tbody tr:hover{background:rgba(11,36,25,.018)}
.lt tbody td{padding:13px 18px;vertical-align:middle}

/* icon */
.txn-ico{width:36px;height:36px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;transition:transform .25s var(--spring)}
.lt tbody tr:hover .txn-ico{transform:scale(1.1) rotate(5deg)}
.ico-in{background:var(--grn-bg);color:var(--grn)}
.ico-out{background:var(--red-bg);color:var(--red)}
.ico-loan{background:var(--blu-bg);color:var(--blu)}
.ico-welfare{background:rgba(13,148,136,.08);color:#0d9488}

/* type pill */
.type-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:7px;font-size:9.5px;font-weight:800;letter-spacing:.3px;white-space:nowrap}
.pill-deposit{background:var(--grn-bg);color:var(--grn)}
.pill-contribution{background:var(--lg);color:var(--lt)}
[data-bs-theme="dark"] .pill-contribution{color:var(--lime)}
.pill-loan_repayment{background:var(--blu-bg);color:var(--blu)}
.pill-loan_disbursement{background:var(--amb-bg);color:var(--amb)}
.pill-withdrawal{background:var(--red-bg);color:var(--red)}
.pill-welfare{background:rgba(13,148,136,.08);color:#0d9488}
.pill-revenue_inflow{background:rgba(124,77,255,.08);color:#7c4dff}
.pill-default{background:var(--bg);color:var(--t3);border:1px solid var(--bdr)}

/* misc cell elements */
.chan-badge{background:var(--bg);border:1px solid var(--bdr);border-radius:6px;padding:2px 8px;font-size:.68rem;font-weight:700;color:var(--t3);font-family:monospace}
.ref-code{background:var(--bg);border:1px solid var(--bdr);border-radius:6px;padding:2px 8px;font-size:.68rem;font-weight:700;color:var(--t3);font-family:monospace}
.amt-in{font-size:.85rem;font-weight:800;color:var(--grn)}
.amt-out{font-size:.85rem;font-weight:800;color:var(--red)}
.amt-loan{font-size:.85rem;font-weight:800;color:var(--blu)}
.cell-date{font-size:.85rem;font-weight:700;color:var(--t1)}
.cell-time{font-size:.65rem;font-weight:500;color:var(--t3);margin-top:2px}

/* empty state */
.empty-well{display:flex;flex-direction:column;align-items:center;padding:72px 24px;text-align:center}
.ew-ico{width:72px;height:72px;border-radius:20px;background:var(--bg);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:var(--t3);margin-bottom:18px}
.ew-title{font-size:.9rem;font-weight:800;color:var(--t1);margin-bottom:5px}
.ew-sub{font-size:.78rem;font-weight:500;color:var(--t3)}

/* export dropdown */
.exp-dd{border-radius:16px!important;padding:7px!important;border-color:var(--bdr)!important;box-shadow:var(--sh-lg)!important;background:var(--surf)!important;min-width:185px}
.dd-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;text-decoration:none;font-size:.82rem;font-weight:600;color:var(--t1);transition:background .14s ease}
.dd-item:hover{background:var(--bg);color:var(--t1)}
.dd-ic{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.88rem;flex-shrink:0}
</style>
</head>
<body>
<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
<?php $layout->topbar($pageTitle ?? ''); ?>
<div class="dash">

<!-- HERO -->
<div class="hero">
    <div class="hero-mesh"></div><div class="hero-dots"></div>
    <div class="hero-ring r1"></div><div class="hero-ring r2"></div>
    <div class="hero-inner">
        <div class="row align-items-end gy-4">
            <div class="col-lg-9">
                <div class="hero-eyebrow"><span class="eyebrow-dot"></span> Personal Ledger</div>
                <h1>Transaction History</h1>
                <p class="hero-sub">Full financial activity for your account &nbsp;&middot;&nbsp; <strong><?= number_format($total_txn_count) ?> transactions</strong> on record</p>
                <div class="hero-bubbles">
                    <div class="hbub"><div class="hbub-val"><?= ks($net_savings) ?></div><div class="hbub-lbl">Net Savings</div></div>
                    <div class="hbub"><div class="hbub-val"><?= ks($total_loans) ?></div><div class="hbub-lbl">Active Loans</div></div>
                    <div class="hbub"><div class="hbub-val"><?= ks($total_repaid) ?></div><div class="hbub-lbl">Total Repaid</div></div>
                    <div class="hbub"><div class="hbub-val"><?= ks($total_withdrawn) ?></div><div class="hbub-lbl">Withdrawn</div></div>
                    <div class="hbub"><div class="hbub-val"><?= ks($wallet_bal) ?></div><div class="hbub-lbl">Wallet</div></div>
                </div>
                <div class="hero-ctas">
                    <div class="dropdown">
                        <button class="btn-lime dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-cloud-download-fill"></i> Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-start exp-dd mt-2">
                            <li><a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'export_pdf'])) ?>"><div class="dd-ic" style="background:rgba(220,38,38,.09);color:#dc2626"><i class="bi bi-file-pdf"></i></div> PDF Report</a></li>
                            <li><a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'export_excel'])) ?>"><div class="dd-ic" style="background:rgba(5,150,105,.09);color:#059669"><i class="bi bi-file-earmark-excel"></i></div> Excel Sheet</a></li>
                            <li><a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'print_report'])) ?>" target="_blank"><div class="dd-ic" style="background:rgba(79,70,229,.09);color:#4f46e5"><i class="bi bi-printer"></i></div> Print Ledger</a></li>
                        </ul>
                    </div>
                    <button class="btn-ghost" onclick="document.getElementById('filterSection').scrollIntoView({behavior:'smooth'})">
                        <i class="bi bi-funnel"></i> Filters
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- STAT CARDS -->
<div class="stats-float">
    <div class="row g-3">
        <div class="col-md-3 sa1">
            <div class="sc sc-g">
                <div class="sc-ico" style="background:var(--grn-bg);color:var(--grn)"><i class="bi bi-arrow-down-circle-fill"></i></div>
                <div class="sc-lbl">Total Deposited</div>
                <div class="sc-val"><?= ks($total_deposited) ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--grn)" data-w="100"></div></div>
                <div class="sc-meta">All-time inflows</div>
            </div>
        </div>
        <div class="col-md-3 sa2">
            <div class="sc sc-r">
                <div class="sc-ico" style="background:var(--red-bg);color:var(--red)"><i class="bi bi-arrow-up-circle-fill"></i></div>
                <div class="sc-lbl">Total Withdrawn</div>
                <div class="sc-val"><?= ks($total_withdrawn) ?></div>
                <?php $wPct = $total_deposited>0 ? min(100,($total_withdrawn/$total_deposited)*100) : 0; ?>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--red)" data-w="<?= round($wPct) ?>"></div></div>
                <div class="sc-meta"><?= round($wPct) ?>% of deposits</div>
            </div>
        </div>
        <div class="col-md-3 sa3">
            <div class="sc sc-b">
                <div class="sc-ico" style="background:var(--blu-bg);color:var(--blu)"><i class="bi bi-bank2"></i></div>
                <div class="sc-lbl">Total Repaid</div>
                <div class="sc-val"><?= ks($total_repaid) ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--blu)" data-w="100"></div></div>
                <div class="sc-meta">Loan repayments</div>
            </div>
        </div>
        <div class="col-md-3 sa4">
            <div class="sc sc-<?= ($month_in-$month_out)>=0?'g':'r' ?>">
                <div class="sc-ico" style="background:<?= ($month_in-$month_out)>=0?'var(--grn-bg)':'var(--red-bg)' ?>;color:<?= ($month_in-$month_out)>=0?'var(--grn)':'var(--red)' ?>"><i class="bi bi-calendar-month-fill"></i></div>
                <div class="sc-lbl">This Month Net</div>
                <div class="sc-val" style="color:<?= ($month_in-$month_out)>=0?'var(--grn)':'var(--red)' ?>"><?= ks(abs($month_in-$month_out)) ?></div>
                <?php $mPct = max($month_in,$month_out)>0 ? min(100,(abs($month_in-$month_out)/max($month_in,$month_out))*100) : 0; ?>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:<?= ($month_in-$month_out)>=0?'var(--grn)':'var(--red)' ?>" data-w="<?= round($mPct) ?>"></div></div>
                <div class="sc-meta"><?= date('F') ?> &middot; <?= ($month_in-$month_out)>=0?'Net positive':'Net outflow' ?></div>
            </div>
        </div>
    </div>
</div>

<!-- BODY -->
<div class="pg-body">

    <!-- Row 1: Dual area + Transaction mix doughnut -->
    <div class="row g-3 mb-3">
        <div class="col-xl-7">
            <div class="chart-card" style="animation-delay:.72s">
                <div class="cc-head">
                    <div><div class="cc-title">12-Month Cash Flow</div><div class="cc-sub">Monthly inflows vs outflows vs net position</div></div>
                    <div class="cc-stats">
                        <div><div class="cc-stat-val" style="color:var(--grn)"><?= ks(array_sum($chart_in)) ?></div><div class="cc-stat-lbl">Total In</div></div>
                        <div class="cc-stat-div"></div>
                        <div><div class="cc-stat-val" style="color:var(--red)"><?= ks(array_sum($chart_out)) ?></div><div class="cc-stat-lbl">Total Out</div></div>
                    </div>
                </div>
                <div class="leg">
                    <div class="leg-i"><span class="leg-dot" style="background:var(--grn)"></span>Inflows</div>
                    <div class="leg-i"><span class="leg-dot" style="background:var(--red)"></span>Outflows</div>
                    <div class="leg-i"><span class="leg-dot" style="background:var(--blu);border-radius:2px;width:12px;height:3px;display:inline-block"></span>Net</div>
                </div>
                <div class="chart-box" style="height:260px;margin-top:12px"><canvas id="chartFlow"></canvas></div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="chart-card" style="animation-delay:.78s">
                <div class="cc-head"><div><div class="cc-title">Transaction Mix</div><div class="cc-sub">Volume by category (KES)</div></div></div>
                <div style="position:relative;width:148px;height:148px;margin:6px auto 16px">
                    <canvas id="chartDonut" width="148" height="148"></canvas>
                    <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;pointer-events:none">
                        <div style="font-size:.85rem;font-weight:800;color:var(--t1);letter-spacing:-.3px"><?= count($type_labels) ?></div>
                        <div style="font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--t3);margin-top:1px">categories</div>
                    </div>
                </div>
                <?php $dc=['#0b2419','#a3e635','#2563eb','#dc2626','#0d9488','#d97706','#7c4dff'];
                foreach ($type_labels as $i => $lbl): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--bdr2);font-size:.76rem">
                    <span style="display:flex;align-items:center;gap:8px;font-weight:600;color:var(--t2)">
                        <span style="width:8px;height:8px;border-radius:50%;background:<?= $dc[$i%count($dc)] ?>;flex-shrink:0"></span><?= $lbl ?>
                    </span>
                    <span style="font-weight:800;color:var(--t1)"><?= ks($type_amounts[$i]) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Filter Panel -->
    <div class="filter-card" id="filterSection">
        <div class="filter-card-head">
            <div style="width:32px;height:32px;border-radius:9px;background:var(--lg);color:var(--lt);display:flex;align-items:center;justify-content:center;font-size:.9rem"><i class="bi bi-funnel-fill"></i></div>
            <span class="filter-card-title">Filter Transactions</span>
        </div>
        <form method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-xl-3 col-md-6">
                    <label class="filter-lbl">Transaction Type</label>
                    <select name="type" class="filter-ctrl">
                        <option value="">All Types</option>
                        <option value="deposit"            <?= $type_filter==='deposit'            ?'selected':'' ?>>Savings Deposit</option>
                        <option value="contribution"       <?= $type_filter==='contribution'       ?'selected':'' ?>>Contribution</option>
                        <option value="revenue_inflow"     <?= $type_filter==='revenue_inflow'     ?'selected':'' ?>>Registration Fee</option>
                        <option value="loan_disbursement"  <?= $type_filter==='loan_disbursement'  ?'selected':'' ?>>Loan Received</option>
                        <option value="loan_repayment"     <?= $type_filter==='loan_repayment'     ?'selected':'' ?>>Loan Repayment</option>
                        <option value="withdrawal"         <?= $type_filter==='withdrawal'         ?'selected':'' ?>>Withdrawal</option>
                        <option value="welfare"            <?= $type_filter==='welfare'            ?'selected':'' ?>>Welfare</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="filter-lbl">Specific Date</label>
                    <input type="date" name="date" class="filter-ctrl" value="<?= htmlspecialchars($date_filter??'') ?>">
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="filter-lbl">From</label>
                    <input type="date" name="from" class="filter-ctrl" value="<?= htmlspecialchars($from_filter??'') ?>">
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="filter-lbl">To</label>
                    <input type="date" name="to" class="filter-ctrl" value="<?= htmlspecialchars($to_filter??'') ?>">
                </div>
                <div class="col-xl-3">
                    <div style="display:flex;gap:8px">
                        <button type="submit" class="btn-apply flex-fill"><i class="bi bi-funnel"></i> Apply</button>
                        <a href="transactions.php" class="btn-clear-filter"><i class="bi bi-x"></i> Clear</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Ledger Table -->
    <div class="txn-card">
        <div class="txn-card-head">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="txn-card-title">All Transactions</span>
                <span class="txn-ct"><i class="bi bi-list-ul"></i> <?= $result->num_rows ?> records</span>
                <?php if ($type_filter || $date_filter || $from_filter || $to_filter): ?>
                <span class="filter-badge"><i class="bi bi-funnel-fill"></i> Filtered</span>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <a href="?<?= http_build_query(array_merge($_GET,['action'=>'export_pdf'])) ?>"   class="btn-exp"><i class="bi bi-file-pdf"></i> PDF</a>
                <a href="?<?= http_build_query(array_merge($_GET,['action'=>'export_excel'])) ?>" class="btn-exp"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                <a href="?<?= http_build_query(array_merge($_GET,['action'=>'print_report'])) ?>" class="btn-exp" target="_blank"><i class="bi bi-printer"></i> Print</a>
            </div>
        </div>

        <div style="overflow-x:auto">
            <table class="lt">
                <thead>
                    <tr>
                        <th style="padding-left:24px">Date &amp; Time</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Reference</th>
                        <th>Channel</th>
                        <th style="text-align:right;padding-right:24px">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $result->data_seek(0);
                if ($result && $result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                        $type    = strtolower($row['transaction_type'] ?? '');
                        $is_in   = in_array($type,[
                            'deposit','contribution','welfare','revenue_inflow',
                            'savings_deposit','share_purchase','dividend_payment','loan_disbursement',
                            'registration_fee','welfare_payout'
                        ]);
                        $is_loan = ($type === 'loan_disbursement');
                        $dt      = new DateTime($row['created_at']);

                        $ico_cls = in_array($type,['welfare','contribution','welfare_payout','welfare_contribution']) ? 'ico-welfare'
                                 : ($is_loan ? 'ico-loan' : ($is_in ? 'ico-in' : 'ico-out'));

                        $icon_map = [
                            'deposit'             => 'bi-arrow-down-circle-fill',
                            'savings_deposit'      => 'bi-arrow-down-circle-fill',
                            'contribution'         => 'bi-calendar-check-fill',
                            'withdrawal'           => 'bi-arrow-up-circle-fill',
                            'withdrawal_initiate'  => 'bi-arrow-up-circle-fill',
                            'loan_repayment'       => 'bi-cash-stack',
                            'loan_disbursement'    => 'bi-bank2',
                            'welfare'              => 'bi-heart-pulse-fill',
                            'welfare_contribution' => 'bi-heart-pulse-fill',
                            'welfare_payout'       => 'bi-heart-pulse-fill',
                            'revenue_inflow'       => 'bi-receipt',
                            'registration_fee'     => 'bi-receipt',
                            'share_purchase'       => 'bi-graph-up-arrow',
                            'dividend_payment'     => 'bi-stars',
                            'expense_outflow'      => 'bi-receipt-cutoff',
                            'expense_incurred'     => 'bi-receipt-cutoff',
                            'expense_settlement'   => 'bi-receipt-cutoff',
                        ];
                        $icon = $icon_map[$type] ?? 'bi-arrow-left-right';

                        if ($is_loan)    { $amt_cls='amt-loan'; $sign='+'; }
                        elseif ($is_in)  { $amt_cls='amt-in';  $sign='+'; }
                        else             { $amt_cls='amt-out'; $sign='−'; }

                        $valid_pills = [
                            'pill-deposit','pill-contribution','pill-loan_repayment',
                            'pill-loan_disbursement','pill-withdrawal','pill-welfare',
                            'pill-revenue_inflow','pill-savings_deposit','pill-share_purchase',
                            'pill-dividend_payment'
                        ];
                        $pill  = 'pill-'.$type;
                        if (!in_array($pill,$valid_pills)) $pill = 'pill-default';
                        $display = ucwords(str_replace('_',' ',$type));
                ?>
                <tr>
                    <td style="padding-left:24px">
                        <div class="cell-date"><?= $dt->format('d M Y') ?></div>
                        <div class="cell-time"><?= $dt->format('h:i A') ?></div>
                    </td>
                    <td><span class="type-pill <?= $pill ?>"><i class="bi <?= $icon ?>"></i> <?= $display ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:9px">
                            <div class="txn-ico <?= $ico_cls ?>"><i class="bi <?= $icon ?>"></i></div>
                            <div>
                                <div style="font-size:.82rem;font-weight:700;color:var(--t1)"><?= $display ?></div>
                                <div style="font-size:.65rem;color:var(--t3);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($row['notes']??'—') ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="ref-code"><?= htmlspecialchars($row['reference_no']??'—') ?></span></td>
                    <td><span class="chan-badge"><?= strtoupper(htmlspecialchars($row['payment_channel']??'SYS')) ?></span></td>
                    <td style="text-align:right;padding-right:24px;white-space:nowrap">
                        <span class="<?= $amt_cls ?>"><?= $sign ?> KES <?= number_format((float)$row['amount'],2) ?></span>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="6">
                    <div class="empty-well">
                        <div class="ew-ico"><i class="bi bi-inbox"></i></div>
                        <div class="ew-title">No Transactions Found</div>
                        <div class="ew-sub">No records match your current filters — try adjusting the date range or type.</div>
                    </div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /pg-body -->
</div><!-- /dash -->

<?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const MO_LABELS   = <?= json_encode($chart_labels) ?>;
const CHART_IN    = <?= json_encode($chart_in) ?>;
const CHART_OUT   = <?= json_encode($chart_out) ?>;
const CHART_NET   = <?= json_encode($chart_net) ?>;
const TYPE_LABELS = <?= json_encode($type_labels) ?>;
const TYPE_AMOUNTS= <?= json_encode($type_amounts) ?>;

const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
const GRID = dark ? 'rgba(255,255,255,.05)' : 'rgba(11,36,25,.05)';
const TICK = dark ? '#3a6050' : '#8fada0';
const SURF = dark ? '#0d1d14' : '#ffffff';

const TT = {
    backgroundColor:dark?'#0d1d14':'#0b2419',titleColor:'#a3e635',bodyColor:'#fff',
    padding:12,cornerRadius:10,borderColor:'rgba(163,230,53,.2)',borderWidth:1,
    titleFont:{family:"'Plus Jakarta Sans',sans-serif",weight:'800',size:12},
    bodyFont: {family:"'Plus Jakarta Sans',sans-serif",size:11},
};
const XS = {grid:{display:false},ticks:{color:TICK,font:{family:"'Plus Jakarta Sans',sans-serif",size:10}}};
const YS = {grid:{color:GRID},   ticks:{color:TICK,font:{family:"'Plus Jakarta Sans',sans-serif",size:10}}};
const COLORS = ['#0b2419','#a3e635','#2563eb','#dc2626','#0d9488','#d97706','#7c4dff'];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.querySelectorAll('[data-w]').forEach(el => { el.style.width = el.dataset.w + '%'; });
    }, 460);
});

// 1. Dual area — 12-month cash flow + net dashed line
(function(){
    const ctx = document.getElementById('chartFlow').getContext('2d');
    const gi = ctx.createLinearGradient(0,0,0,260);
    gi.addColorStop(0,'#16a34a3a'); gi.addColorStop(1,'#16a34a00');
    const go = ctx.createLinearGradient(0,0,0,260);
    go.addColorStop(0,'#dc26263a'); go.addColorStop(1,'#dc262600');
    new Chart(ctx,{
        type:'line',
        data:{labels:MO_LABELS,datasets:[
            {label:'Inflows', data:CHART_IN,  borderColor:'#16a34a',borderWidth:2.5,backgroundColor:gi,fill:true,tension:.45,pointRadius:4,pointBackgroundColor:'#16a34a',pointBorderColor:SURF,pointBorderWidth:2},
            {label:'Outflows',data:CHART_OUT, borderColor:'#dc2626',borderWidth:2.5,backgroundColor:go,fill:true,tension:.45,pointRadius:4,pointBackgroundColor:'#dc2626',pointBorderColor:SURF,pointBorderWidth:2},
            {label:'Net',     data:CHART_NET, borderColor:'#2563eb',borderWidth:2,  backgroundColor:'transparent',fill:false,tension:.45,borderDash:[5,3],pointRadius:3,pointBackgroundColor:'#2563eb',pointBorderColor:SURF,pointBorderWidth:1.5},
        ]},
        options:{responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:{...TT,callbacks:{label:c=>' '+c.dataset.label+': KES '+c.parsed.y.toLocaleString()}}},
            scales:{x:XS,y:YS}}
    });
})();

// 2. Doughnut — transaction mix
new Chart(document.getElementById('chartDonut'),{
    type:'doughnut',
    data:{labels:TYPE_LABELS,datasets:[{data:TYPE_AMOUNTS,backgroundColor:COLORS.map(c=>c+'cc'),borderWidth:0,hoverOffset:7}]},
    options:{cutout:'70%',responsive:false,
        plugins:{legend:{display:false},tooltip:{...TT,callbacks:{label:c=>' KES '+c.parsed.toLocaleString()}}}}
});
</script>
</body>
</html>