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

$member_id = $_SESSION['member_id'];

if (isset($_GET['msg']) && $_GET['msg'] === 'exit_requested') {
    $success_msg = "Your SACCO Exit & Share Withdrawal request has been submitted successfully and is awaiting administrative approval.";
}

require_once __DIR__ . '/../../inc/ShareValuationEngine.php';
$svEngine            = new ShareValuationEngine($conn);
$valuation           = $svEngine->getValuation();
$current_share_price = $valuation['price'];
$ownership_pct       = $svEngine->getOwnershipPercentage($member_id);

$stmt = $conn->prepare("SELECT units_owned, total_amount_paid, average_purchase_price FROM member_shareholdings WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$shareholding = $stmt->get_result()->fetch_assoc() ?? ['units_owned'=>0,'total_amount_paid'=>0,'average_purchase_price'=>0];

$totalUnits     = (float)$shareholding['units_owned'];
$portfolioValue = $totalUnits * $current_share_price;
$totalGain      = $portfolioValue - (float)$shareholding['total_amount_paid'];
$gainPct        = ($shareholding['total_amount_paid'] > 0) ? ($totalGain / $shareholding['total_amount_paid']) * 100 : 0;

$dividend_rate_projection = 12.5;
$projectedDividend        = $portfolioValue * ($dividend_rate_projection / 100);

require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine            = new FinancialEngine($conn);
$balances          = $engine->getBalances($member_id);
$totalCapital      = $balances['shares'];
$totalUnits        = $totalCapital / $current_share_price;
$projectedDividend = $totalCapital * ($dividend_rate_projection / 100);

$sqlHistory = "SELECT created_at, reference_no, units as share_units, unit_price, total_value, transaction_type FROM share_transactions WHERE member_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sqlHistory);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf','export_excel','print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    $format = $_GET['action']==='export_excel' ? 'excel' : ($_GET['action']==='print_report' ? 'print' : 'pdf');
    $data = [];
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        $units  = $row['total_value'] / $current_share_price;
        $data[] = ['Date'=>date('d-M-Y H:i',strtotime($row['created_at'])),'Reference'=>$row['reference_no'],'Units'=>number_format((float)$units,2),'Unit Price'=>number_format((float)$current_share_price,2),'Total Paid'=>number_format((float)$row['total_value'],2),'Status'=>'Confirmed'];
    }
    UniversalExportEngine::handle($format,$data,['title'=>'Share Capital Statement','module'=>'Member Portal','headers'=>['Date','Reference','Units','Unit Price','Total Paid','Status']]);
    exit;
}

$transactions = [];
$result->data_seek(0);
while ($row = $result->fetch_assoc()) {
    $row['share_units'] = $row['total_value'] / $current_share_price;
    $row['unit_price']  = $current_share_price;
    $transactions[]     = $row;
}
$stmt->close();

$chartLabels  = [];
$chartData    = [];
$runningUnits = 0;
foreach (array_reverse($transactions) as $txn) {
    if (in_array($txn['transaction_type'], ['purchase','migration'])) $runningUnits += (float)$txn['share_units'];
    $chartLabels[] = date('M d', strtotime($txn['created_at']));
    $chartData[]   = $runningUnits * $current_share_price;
}
$jsLabels = json_encode($chartLabels);
$jsData   = json_encode($chartData);

$pageTitle = "My Share Portfolio";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<style>
/* ═══════════════════════════════════════════════════════════
   SHARE PORTFOLIO · HD · Plus Jakarta Sans · Forest & Lime
═══════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --f:       #0b2419;
    --fm:      #154330;
    --fs:      #1d6044;
    --lime:    #a3e635;
    --lg:      rgba(163,230,53,0.14);
    --lt:      #6a9a1a;

    --bg:      #eff5f1;
    --bg2:     #e8f1ec;
    --surf:    #ffffff;
    --surf2:   #f7fbf8;
    --bdr:     rgba(11,36,25,0.07);
    --bdr2:    rgba(11,36,25,0.04);

    --t1: #0b2419;
    --t2: #456859;
    --t3: #8fada0;

    --grn:    #16a34a;
    --red:    #dc2626;
    --grn-bg: rgba(22,163,74,0.08);
    --red-bg: rgba(220,38,38,0.08);
    --amb:    #d97706;
    --amb-bg: rgba(217,119,6,0.08);

    --r:   20px;
    --rsm: 12px;
    --ease:   cubic-bezier(0.16,1,0.3,1);
    --spring: cubic-bezier(0.34,1.56,0.64,1);
    --sh:     0 1px 3px rgba(11,36,25,0.05), 0 6px 20px rgba(11,36,25,0.08);
    --sh-lg:  0 4px 8px rgba(11,36,25,0.07), 0 20px 56px rgba(11,36,25,0.13);
}

[data-bs-theme="dark"] {
    --bg:   #070e0b;
    --bg2:  #0a1510;
    --surf: #0d1d14;
    --surf2:#0a1810;
    --bdr:  rgba(255,255,255,0.07);
    --bdr2: rgba(255,255,255,0.04);
    --t1:   #d8eee2;
    --t2:   #4d7a60;
    --t3:   #2a4d38;
}

body, * {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

body { background: var(--bg); color: var(--t1); }

.main-content-wrapper {
    margin-left: 272px;
    min-height: 100vh;
    transition: margin-left .3s var(--ease);
}
body.sb-collapsed .main-content-wrapper { margin-left: 76px; }
@media (max-width:991px) { .main-content-wrapper { margin-left:0; } }

/* ─────────────────────────────────────────────
   HERO
───────────────────────────────────────────── */
.sv-hero {
    background: var(--f);
    position: relative;
    overflow: hidden;
}

.hero-mesh {
    position: absolute; inset: 0; pointer-events: none;
    background:
        radial-gradient(ellipse 65% 85% at 108% -5%,  rgba(163,230,53,0.11) 0%, transparent 55%),
        radial-gradient(ellipse 40% 55% at -8% 105%, rgba(163,230,53,0.07) 0%, transparent 55%);
}
.hero-dots {
    position: absolute; inset: 0; pointer-events: none;
    background-image: radial-gradient(rgba(255,255,255,0.05) 1px, transparent 1px);
    background-size: 20px 20px;
}
.hero-ring {
    position: absolute; border-radius: 50%; pointer-events: none;
    border: 1px solid rgba(163,230,53,0.07);
}
.hero-ring.r1 { width: 480px; height: 480px; top: -170px; right: -110px; }
.hero-ring.r2 { width: 720px; height: 720px; top: -270px; right: -210px; }

.hero-inner {
    position: relative; z-index: 2;
    padding: 42px 52px 108px;
}
@media (max-width:767px) { .hero-inner { padding: 32px 20px 96px; } }

.hero-nav {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 44px;
}

.hero-back {
    display: inline-flex; align-items: center; gap: 7px;
    color: rgba(255,255,255,0.38); font-size: 0.78rem; font-weight: 700;
    text-decoration: none; transition: color .18s ease;
}
.hero-back:hover { color: var(--lime); }

.hero-brand-tag {
    font-size: 9.5px; font-weight: 800; letter-spacing: 1.8px;
    text-transform: uppercase; color: rgba(255,255,255,0.18);
}

.hero-eyebrow {
    display: flex; align-items: center; gap: 10px;
    font-size: 9.5px; font-weight: 800; letter-spacing: 1.5px;
    text-transform: uppercase; color: rgba(163,230,53,0.65);
    margin-bottom: 16px;
}
.ey-line { width: 22px; height: 1.5px; background: var(--lime); opacity:.5; border-radius:99px; }

.hero-lbl {
    font-size: 0.75rem; font-weight: 600; letter-spacing: .4px;
    color: rgba(255,255,255,0.30); text-transform: uppercase; margin-bottom: 8px;
}

.hero-amount {
    font-size: clamp(2.8rem, 6.5vw, 4.8rem);
    font-weight: 800; color: #fff;
    letter-spacing: -2.5px; line-height: 1; margin-bottom: 6px;
    animation: slide-up .9s var(--ease) both;
}
.hero-amount .cur {
    font-size: .36em; font-weight: 700; vertical-align: .55em;
    opacity: .45; letter-spacing: 0; margin-right: 3px;
}

/* gain badge */
.hero-gain {
    display: inline-flex; align-items: center; gap: 7px;
    font-size: 11px; font-weight: 700;
    padding: 5px 14px; border-radius: 50px; margin-bottom: 0;
    animation: slide-up .9s var(--ease) .15s both; width: fit-content;
}
.hero-gain.positive {
    background: rgba(163,230,53,0.11);
    border: 1px solid rgba(163,230,53,0.2);
    color: #bff060;
}
.hero-gain.negative {
    background: rgba(220,38,38,0.12);
    border: 1px solid rgba(220,38,38,0.2);
    color: #fca5a5;
}
.gain-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--lime); animation: pulse 1.8s ease-in-out infinite; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.35;transform:scale(1.8)} }

/* hero action buttons */
.hero-ctas {
    display: flex; gap: 10px; flex-wrap: wrap;
    margin-top: 30px;
    animation: slide-up .9s var(--ease) .25s both;
}

.btn-lime {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--lime); color: var(--f);
    font-size: .875rem; font-weight: 800;
    padding: 12px 26px; border-radius: 50px;
    border: none; cursor: pointer; text-decoration: none;
    box-shadow: 0 2px 14px rgba(163,230,53,.28);
    transition: all .25s var(--spring);
}
.btn-lime:hover { transform: translateY(-3px) scale(1.03); box-shadow: 0 10px 32px rgba(163,230,53,.4); color: var(--f); }

.btn-ghost {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.14);
    color: rgba(255,255,255,.8);
    font-size: .875rem; font-weight: 700;
    padding: 12px 22px; border-radius: 50px;
    cursor: pointer; text-decoration: none;
    transition: all .22s ease;
}
.btn-ghost:hover { background: rgba(255,255,255,.16); color: #fff; transform: translateY(-2px); }

.btn-danger-ghost {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(220,38,38,.1);
    border: 1px solid rgba(220,38,38,.25);
    color: #fca5a5;
    font-size: .875rem; font-weight: 700;
    padding: 12px 22px; border-radius: 50px;
    cursor: pointer; text-decoration: none;
    transition: all .22s ease;
}
.btn-danger-ghost:hover { background: rgba(220,38,38,.18); color: #fecaca; transform: translateY(-2px); }

/* right side chart block */
.hero-chart-wrap {
    animation: slide-up .9s var(--ease) .2s both;
}
.hero-chart-lbl {
    font-size: 9px; font-weight: 800; letter-spacing: 1.2px;
    text-transform: uppercase; color: rgba(255,255,255,.22); margin-bottom: 12px;
}
.chart-svg { width: 100%; height: 88px; overflow: visible; display: block; }
.spark-txt { font-size: 7.5px; font-weight: 700; font-family: 'Plus Jakarta Sans',sans-serif; fill: rgba(255,255,255,.2); }

@keyframes slide-up { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

/* ─────────────────────────────────────────────
   FLOATING STAT CARDS
───────────────────────────────────────────── */
.stats-float {
    margin-top: -68px;
    position: relative; z-index: 10;
    padding: 0 52px;
}
@media (max-width:767px) { .stats-float { padding: 0 16px; } }

.sc {
    background: var(--surf);
    border-radius: var(--r);
    padding: 26px 28px;
    border: 1px solid var(--bdr);
    box-shadow: var(--sh-lg);
    height: 100%;
    position: relative; overflow: hidden;
    transition: transform .28s var(--ease), box-shadow .28s ease;
}
.sc:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(11,36,25,.09), 0 36px 70px rgba(11,36,25,.15); }

.sc::after {
    content: '';
    position: absolute; bottom:0; left:0; right:0; height:2.5px;
    border-radius: 0 0 var(--r) var(--r);
    transform: scaleX(0); transform-origin: left;
    transition: transform .38s var(--ease);
}
.sc:hover::after { transform: scaleX(1); }
.sc-g::after  { background: linear-gradient(90deg,#16a34a,#4ade80); }
.sc-a::after  { background: linear-gradient(90deg,#d97706,#fbbf24); }
.sc-l::after  { background: linear-gradient(90deg,var(--lime),#d4f98a); }
.sc-b::after  { background: linear-gradient(90deg,#2563eb,#60a5fa); }

.sc-ico {
    width: 46px; height: 46px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; margin-bottom: 18px;
    transition: transform .3s var(--spring);
}
.sc:hover .sc-ico { transform: scale(1.12) rotate(7deg); }

.sc-lbl { font-size: 10px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; color: var(--t3); margin-bottom: 6px; }
.sc-val { font-size: 1.6rem; font-weight: 800; color: var(--t1); letter-spacing: -0.8px; line-height: 1.1; margin-bottom: 16px; }
.sc-bar { height: 4px; border-radius: 99px; background: var(--bg); overflow: hidden; margin-bottom: 10px; }
.sc-bar-fill { height: 100%; border-radius: 99px; width: 0; transition: width 1.4s var(--ease); }
.sc-meta { font-size: .72rem; font-weight: 600; color: var(--t3); }

/* stagger */
.sa1 { animation: slide-up .7s var(--ease) .42s both; }
.sa2 { animation: slide-up .7s var(--ease) .52s both; }
.sa3 { animation: slide-up .7s var(--ease) .62s both; }
.sa4 { animation: slide-up .7s var(--ease) .72s both; }

/* ─────────────────────────────────────────────
   PAGE BODY
───────────────────────────────────────────── */
.pg-body { padding: 40px 52px 80px; }
@media (max-width:767px) { .pg-body { padding: 28px 16px 60px; } }

/* section label */
.sec-label {
    display: flex; align-items: center; gap: 12px;
    font-size: 9.5px; font-weight: 800; letter-spacing: 1.2px;
    text-transform: uppercase; color: var(--t3); margin-bottom: 18px;
}
.sec-label::after { content:''; flex:1; height:1px; background:var(--bdr); }

/* chart card */
.growth-card {
    background: var(--surf);
    border-radius: var(--r);
    padding: 24px 26px;
    border: 1px solid var(--bdr);
    box-shadow: var(--sh);
    animation: slide-up .7s var(--ease) .78s both;
}

.growth-head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 16px;
}

.growth-title { font-size: .88rem; font-weight: 800; color: var(--t1); }

.growth-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--grn-bg); color: var(--grn);
    font-size: 9.5px; font-weight: 800;
    padding: 3px 10px; border-radius: 7px;
}

.chart-wrap { position: relative; height: 160px; }

/* ─────────────────────────────────────────────
   FLASH BANNER
───────────────────────────────────────────── */
.flash-ok {
    display: flex; align-items: flex-start; gap: 12px;
    background: var(--grn-bg); border: 1px solid rgba(22,163,74,0.2);
    border-radius: var(--rsm); padding: 14px 18px; margin-bottom: 24px;
    font-size: .82rem; font-weight: 600; color: var(--grn);
    animation: slide-up .4s var(--ease) both;
}
.flash-ok i { font-size: 1.1rem; flex-shrink:0; margin-top:1px; }
.flash-ok strong { font-weight: 800; display: block; margin-bottom: 2px; }

/* ─────────────────────────────────────────────
   TRANSACTION CARD
───────────────────────────────────────────── */
.txn-card {
    background: var(--surf);
    border-radius: 22px;
    border: 1px solid var(--bdr);
    box-shadow: var(--sh);
    overflow: hidden;
    animation: slide-up .7s var(--ease) .82s both;
    margin-top: 28px;
}

.txn-card-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 26px; border-bottom: 1px solid var(--bdr2);
    gap: 12px; flex-wrap: wrap;
    background: var(--surf2);
}

.txn-card-title { font-size: .88rem; font-weight: 800; color: var(--t1); letter-spacing: -.2px; }

.txn-card-ct {
    font-size: 9.5px; font-weight: 800; letter-spacing: .5px;
    background: var(--bg); color: var(--t3);
    padding: 3px 10px; border-radius: 7px; border: 1px solid var(--bdr);
}

.txn-head-right { display: flex; align-items: center; gap: 8px; }

.btn-exp {
    display: inline-flex; align-items: center; gap: 7px;
    background: var(--bg); border: 1px solid var(--bdr);
    color: var(--t2); font-size: .78rem; font-weight: 700;
    padding: 7px 16px; border-radius: 50px; cursor: pointer;
    transition: all .18s ease;
}
.btn-exp:hover { border-color: rgba(11,36,25,.18); color: var(--t1); background: var(--surf); }

/* dropdown */
.exp-dd {
    border-radius: 16px !important; padding: 7px !important;
    border-color: var(--bdr) !important;
    box-shadow: var(--sh-lg) !important;
    background: var(--surf) !important;
    min-width: 185px;
}
.dd-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px; border-radius: 10px;
    text-decoration: none; font-size: .82rem; font-weight: 600;
    color: var(--t1); transition: background .14s ease;
}
.dd-item:hover { background: var(--bg); color: var(--t1); }
.dd-ic { width: 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: .88rem; flex-shrink:0; }

/* table */
.sh-table { width: 100%; border-collapse: collapse; }

.sh-table thead th {
    background: var(--surf2);
    font-size: 10px; font-weight: 800; letter-spacing: .8px;
    text-transform: uppercase; color: var(--t3);
    padding: 11px 18px; border: none;
    border-bottom: 1px solid var(--bdr2); white-space: nowrap;
}

.sh-table tbody tr { border-bottom: 1px solid var(--bdr2); transition: background .13s ease; }
.sh-table tbody tr:last-child { border-bottom: none; }
.sh-table tbody tr:hover { background: rgba(11,36,25,0.018); }
.sh-table tbody td { padding: 13px 18px; vertical-align: middle; }

/* cell components */
.ref-chip {
    display: inline-flex; align-items: center;
    background: var(--bg); border: 1px solid var(--bdr);
    border-radius: 8px; padding: 3px 9px;
    font-size: .7rem; font-weight: 700; color: var(--t3);
    font-family: monospace;
}

.unit-chip {
    display: inline-flex; align-items: center; gap: 7px;
    font-size: .88rem; font-weight: 700; color: var(--t1);
}
.unit-pip {
    width: 24px; height: 24px; border-radius: 7px;
    background: var(--grn-bg); color: var(--grn);
    display: flex; align-items: center; justify-content: center;
    font-size: .6rem;
}

.status-chip {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--grn-bg); color: var(--grn);
    font-size: 9.5px; font-weight: 800; letter-spacing: .4px;
    padding: 4px 10px; border-radius: 7px;
}
.status-chip::before { content:''; width:5px; height:5px; border-radius:50%; background:var(--grn); }

/* date cell */
.cell-date { font-size: .88rem; font-weight: 700; color: var(--t1); }
.cell-time { font-size: .68rem; font-weight: 500; color: var(--t3); margin-top: 2px; }

/* amount */
.cell-amt { font-size: .88rem; font-weight: 800; color: var(--t1); }

/* DataTables overrides */
.dataTables_wrapper .dataTables_filter input,
.dataTables_wrapper .dataTables_length select {
    background: var(--surf); border: 1px solid var(--bdr);
    border-radius: 10px; padding: 6px 12px;
    font-size: .8rem; color: var(--t1); outline: none;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_filter label,
.dataTables_wrapper .dataTables_length label {
    font-size: .78rem; color: var(--t3); font-weight: 600;
}
.dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: 9px !important; font-size: .78rem !important;
    font-weight: 700 !important; color: var(--t2) !important;
    border: none !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current,
.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    background: var(--f) !important; color: #fff !important; border: none !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--bg) !important; color: var(--f) !important;
}
.dataTables_wrapper .dt-buttons .btn {
    background: var(--bg); border: 1px solid var(--bdr);
    color: var(--t2); font-size: .78rem; font-weight: 700;
    border-radius: 9px; padding: 6px 14px;
}

/* empty state */
.empty-well {
    display: flex; flex-direction: column; align-items: center;
    padding: 72px 24px; text-align: center;
}
.ew-ico {
    width: 72px; height: 72px; border-radius: 20px;
    background: var(--bg); border: 1px solid var(--bdr);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; color: var(--t3); margin-bottom: 18px;
}
.ew-title { font-size: .9rem; font-weight: 800; color: var(--t1); margin-bottom: 5px; }
.ew-sub   { font-size: .78rem; font-weight: 500; color: var(--t3); }

/* ─────────────────────────────────────────────
   EDUCATION CARD
   ───────────────────────────────────────────── */
.edu-card {
    background: var(--surf);
    border-radius: 22px;
    border: 1px solid var(--bdr);
    box-shadow: var(--sh);
    margin-top: 28px;
    overflow: hidden;
    animation: slide-up .7s var(--ease) .86s both;
}
.edu-head {
    padding: 18px 26px;
    background: var(--f);
    color: #fff;
    display: flex;
    align-items: center;
    gap: 12px;
}
.edu-title { font-size: .88rem; font-weight: 800; letter-spacing: -.2px; }
.edu-body { padding: 30px; }
.edu-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; }
.edu-item { display: flex; gap: 16px; }
.edu-ico {
    width: 42px; height: 42px; border-radius: 12px;
    background: var(--bg); color: var(--fm);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
}
.edu-h { font-size: .82rem; font-weight: 800; color: var(--t1); margin-bottom: 4px; }
.edu-p { font-size: .78rem; font-weight: 500; color: var(--t3); line-height: 1.5; margin-bottom: 0; }
.edu-footer {
    padding: 20px 30px; background: var(--surf2);
    border-top: 1px solid var(--bdr2);
    font-size: .8rem; font-weight: 600; color: var(--t2); text-align: center;
}
</style>
</head>
<body>
<?php $layout->sidebar(); ?>

<div class="main-content-wrapper">
<?php $layout->topbar($pageTitle ?? ''); ?>

<!-- ═══════════════════════ HERO ═════════════════════════ -->
<div class="sv-hero">
    <div class="hero-mesh"></div>
    <div class="hero-dots"></div>
    <div class="hero-ring r1"></div>
    <div class="hero-ring r2"></div>

    <div class="hero-inner">
        <div class="hero-nav">
            <a href="<?= BASE_URL ?>/member/pages/dashboard.php" class="hero-back">
                <i class="bi bi-arrow-left" style="font-size:.65rem;"></i> Dashboard
            </a>
            <span class="hero-brand-tag"><?= defined('SITE_NAME') ? strtoupper(SITE_NAME) : 'UMOJA SACCO' ?></span>
        </div>

        <div class="row align-items-end g-5">
            <!-- Left: portfolio value + actions -->
            <div class="col-md-6">
                <div class="hero-eyebrow"><div class="ey-line"></div> Equity Portfolio</div>
                <div class="hero-lbl">Current Portfolio Value</div>
                <div class="hero-amount"><span class="cur">KES</span><span id="heroAmt"><?= number_format((float)$portfolioValue, 2) ?></span></div>
                <?php $gainSign = $gainPct >= 0 ? '+' : ''; ?>
                <div class="hero-gain <?= $gainPct >= 0 ? 'positive' : 'negative' ?>">
                    <span class="gain-dot" style="background:<?= $gainPct >= 0 ? 'var(--lime)' : '#ef4444' ?>;"></span>
                    <?= $gainSign ?><?= number_format($gainPct, 2) ?>% capital growth
                </div>
                <div class="hero-ctas">
                    <a href="<?= BASE_URL ?>/member/pages/mpesa_request.php?type=shares" class="btn-lime">
                        <i class="bi bi-plus-circle-fill"></i> Buy Shares
                    </a>
                    <a href="<?= BASE_URL ?>/member/pages/withdraw.php?type=wallet&source=shares" class="btn-ghost">
                        <i class="bi bi-cash-stack"></i> Dividends
                    </a>
                    <a href="<?= BASE_URL ?>/member/pages/withdraw.php?type=shares&source=shares" class="btn-danger-ghost">
                        <i class="bi bi-door-open"></i> Quit SACCO
                    </a>
                </div>
            </div>

            <!-- Right: portfolio growth sparkline -->
            <div class="col-md-6 d-none d-md-block">
                <div class="hero-chart-wrap">
                    <div class="hero-chart-lbl">Portfolio Growth History</div>
                    <?php
                    $maxC = max(max($chartData ?: [0]), 1);
                    $SW = 380; $SH = 88; $PD = 10; $N = max(count($chartData), 1);
                    $cPts = [];
                    foreach ($chartData as $i => $v) {
                        $x = $N > 1 ? $PD + ($i / ($N - 1)) * ($SW - $PD * 2) : $SW / 2;
                        $y = $SH - $PD - (($v / $maxC) * ($SH - $PD * 2 - 10));
                        $cPts[] = "$x,$y";
                    }
                    $cpoly   = implode(' ', $cPts);
                    $clastPt = $cPts ? explode(',', end($cPts)) : ['190','44'];
                    $lastN   = min(6, count($chartLabels));
                    $step    = $lastN > 1 ? (int)floor((count($chartLabels) - 1) / ($lastN - 1)) : 1;
                    ?>
                    <svg class="chart-svg" viewBox="0 0 <?= $SW ?> <?= $SH ?>"
                         xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="cg" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%"   stop-color="#a3e635" stop-opacity=".22"/>
                                <stop offset="100%" stop-color="#a3e635" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <?php if (count($cPts) > 1): ?>
                        <polygon points="<?= $cPts[0] ?> <?= $cpoly ?> <?= $SW-$PD ?>,<?= $SH ?> <?= $PD ?>,<?= $SH ?>"
                            fill="url(#cg)"/>
                        <polyline points="<?= $cpoly ?>"
                            fill="none" stroke="#a3e635" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round"/>
                        <?php foreach ($cPts as $pt): [$px,$py] = explode(',',$pt); ?>
                        <circle cx="<?= $px ?>" cy="<?= $py ?>" r="2.5"
                            fill="var(--f)" stroke="#a3e635" stroke-width="1.5"/>
                        <?php endforeach; ?>
                        <circle cx="<?= $clastPt[0] ?>" cy="<?= $clastPt[1] ?>" r="4.5" fill="#a3e635" opacity=".9"/>
                        <circle cx="<?= $clastPt[0] ?>" cy="<?= $clastPt[1] ?>" r="9"   fill="#a3e635" opacity=".1"/>
                        <?php endif; ?>
                        <!-- x-axis labels (sample every N entries) -->
                        <?php foreach ($chartLabels as $i => $lbl):
                            if ($lastN > 1 && $i % $step !== 0 && $i !== count($chartLabels)-1) continue;
                            $lx = $N > 1 ? $PD + ($i / ($N - 1)) * ($SW - $PD * 2) : $SW / 2;
                        ?>
                        <text x="<?= $lx ?>" y="<?= $SH - 1 ?>" text-anchor="middle" class="spark-txt"><?= $lbl ?></text>
                        <?php endforeach; ?>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════ FLOATING STATS ══════════════════ -->
<div class="stats-float">
    <div class="row g-3">
        <div class="col-md-3 sa1">
            <div class="sc sc-g">
                <div class="sc-ico" style="background:var(--grn-bg);color:var(--grn);">
                    <i class="bi bi-pie-chart-fill"></i>
                </div>
                <div class="sc-lbl">Ownership Units</div>
                <div class="sc-val"><?= number_format((float)$totalUnits, 4) ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--grn);" data-w="100"></div></div>
                <div class="sc-meta"><?= number_format((float)$ownership_pct, 4) ?>% of total SACCO equity</div>
            </div>
        </div>
        <div class="col-md-3 sa2">
            <div class="sc sc-l">
                <div class="sc-ico" style="background:var(--lg);color:var(--lt);">
                    <i class="bi bi-currency-exchange"></i>
                </div>
                <div class="sc-lbl">Share Price</div>
                <div class="sc-val">KES <?= number_format($current_share_price, 2) ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--lime);" data-w="65"></div></div>
                <div class="sc-meta">Current corporate valuation</div>
            </div>
        </div>
        <div class="col-md-3 sa3">
            <div class="sc sc-a">
                <div class="sc-ico" style="background:var(--amb-bg);color:var(--amb);">
                    <i class="bi bi-award-fill"></i>
                </div>
                <div class="sc-lbl">Projected Dividend</div>
                <div class="sc-val">KES <?= number_format((float)$projectedDividend, 0) ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--amb);" data-w="<?= min(100,round(($dividend_rate_projection/20)*100)) ?>"></div></div>
                <div class="sc-meta">At <?= $dividend_rate_projection ?>% projected rate · Annual est.</div>
            </div>
        </div>
        <div class="col-md-3 sa4">
            <div class="sc sc-b">
                <div class="sc-ico" style="background:rgba(37,99,235,0.09);color:#2563eb;">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="sc-lbl">Capital Gain</div>
                <div class="sc-val" style="color:<?= $gainPct >= 0 ? 'var(--grn)' : 'var(--red)' ?>"><?= ($gainPct >= 0 ? '+' : '') . number_format($gainPct, 2) ?>%</div>
                <div class="sc-bar">
                    <div class="sc-bar-fill" style="background:<?= $gainPct >= 0 ? 'var(--grn)' : 'var(--red)' ?>;" data-w="<?= min(100,abs(round($gainPct))) ?>"></div>
                </div>
                <div class="sc-meta">vs. total amount paid</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════ BODY ═════════════════════════ -->
<div class="pg-body">

    <?php if (isset($success_msg)): ?>
    <div class="flash-ok">
        <i class="bi bi-check-circle-fill"></i>
        <div><strong>Request Submitted</strong><?= $success_msg ?></div>
    </div>
    <?php endif; ?>

    <!-- Growth Chart -->
    <div class="growth-card">
        <div class="growth-head">
            <span class="growth-title">Portfolio Value Over Time</span>
            <span class="growth-badge"><i class="bi bi-graph-up-arrow" style="font-size:.7rem;"></i> Active</span>
        </div>
        <div class="chart-wrap">
            <canvas id="growthChart"></canvas>
        </div>
    </div>

    <!-- Transaction History -->
    <div class="txn-card">
        <div class="txn-card-head">
            <div class="d-flex align-items-center gap-3">
                <span class="txn-card-title">Transaction History</span>
                <span class="txn-card-ct"><?= count($transactions) ?> records</span>
            </div>
            <div class="txn-head-right">
                <button class="btn-exp" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <div class="dropdown">
                    <button class="btn-exp dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-cloud-download-fill"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end exp-dd">
                        <li>
                            <a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'export_pdf'])) ?>">
                                <div class="dd-ic" style="background:rgba(220,38,38,.09);color:#dc2626;"><i class="bi bi-file-pdf"></i></div> PDF Document
                            </a>
                        </li>
                        <li>
                            <a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'export_excel'])) ?>">
                                <div class="dd-ic" style="background:rgba(5,150,105,.09);color:#059669;"><i class="bi bi-file-earmark-excel"></i></div> Excel Sheet
                            </a>
                        </li>
                        <li><hr class="dropdown-divider mx-2"></li>
                        <li>
                            <a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'print_report'])) ?>" target="_blank">
                                <div class="dd-ic" style="background:rgba(79,70,229,.09);color:#4f46e5;"><i class="bi bi-printer"></i></div> Print Layout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="table-responsive p-3">
            <table id="historyTable" class="sh-table w-100">
                <thead>
                    <tr>
                        <th style="padding-left:20px;">Date</th>
                        <th>Reference</th>
                        <th>Units</th>
                        <th>Unit Price</th>
                        <th>Total Paid</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)): foreach ($transactions as $row): ?>
                    <tr>
                        <td style="padding-left:20px;">
                            <div class="cell-date"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                            <div class="cell-time"><?= date('H:i', strtotime($row['created_at'])) ?></div>
                        </td>
                        <td><span class="ref-chip"><?= htmlspecialchars($row['reference_no']) ?></span></td>
                        <td>
                            <span class="unit-chip">
                                <span class="unit-pip"><i class="bi bi-plus-lg"></i></span>
                                <?= number_format((float)$row['share_units'], 2) ?>
                            </span>
                        </td>
                        <td style="font-size:.85rem;font-weight:600;color:var(--t2);">KES <?= number_format((float)$row['unit_price'], 0) ?></td>
                        <td><span class="cell-amt">KES <?= number_format((float)$row['total_value'], 2) ?></span></td>
                        <td style="text-align:center;"><span class="status-chip">Confirmed</span></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="6">
                        <div class="empty-well">
                            <div class="ew-ico"><i class="bi bi-inbox"></i></div>
                            <div class="ew-title">No Share Transactions</div>
                            <div class="ew-sub">No share purchase records found. Buy your first shares to get started.</div>
                        </div>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Understanding Shares Education Section -->
    <div class="edu-card">
        <div class="edu-head">
            <i class="bi bi-info-circle-fill" style="color:var(--lime);"></i>
            <span class="edu-title">Understanding Your Shares</span>
        </div>
        <div class="edu-body">
            <div class="edu-grid">
                <div class="edu-item">
                    <div class="edu-ico"><i class="bi bi-person-badge-fill"></i></div>
                    <div>
                        <div class="edu-h">Ownership & Membership</div>
                        <p class="edu-p">Purchasing shares makes you a part-owner of the society. You are not just a customer; you're a member with a stake in our future.</p>
                    </div>
                </div>
                <div class="edu-item">
                    <div class="edu-ico"><i class="bi bi-safe2-fill"></i></div>
                    <div>
                        <div class="edu-h">Shares vs. Savings</div>
                        <p class="edu-p"><strong>Shares</strong> are your permanent capital (ownership). <strong>Savings</strong> are your liquid deposits used for day-to-day borrowing and withdrawals.</p>
                    </div>
                </div>
                <div class="edu-item">
                    <div class="edu-ico"><i class="bi bi-graph-up-arrow"></i></div>
                    <div>
                        <div class="edu-h">Dividend Earnings</div>
                        <p class="edu-p">Every year, the SACCO distributes a portion of its surplus to members as dividends based on the number of shares you hold.</p>
                    </div>
                </div>
                <div class="edu-item">
                    <div class="edu-ico"><i class="bi bi-lightning-charge-fill"></i></div>
                    <div>
                        <div class="edu-h">Borrowing Power</div>
                        <p class="edu-p">Your share capital acts as collateral and directly influences your eligibility for larger loan products and competitive rates.</p>
                    </div>
                </div>
                <div class="edu-item">
                    <div class="edu-ico"><i class="bi bi-megaphone-fill"></i></div>
                    <div>
                        <div class="edu-h">Voting Rights</div>
                        <p class="edu-p">Regardless of how many shares you own, each member gets <strong>one vote</strong> in the AGM, ensuring democratic control of the SACCO.</p>
                    </div>
                </div>
                <div class="edu-item">
                    <div class="edu-ico"><i class="bi bi-arrow-left-right"></i></div>
                    <div>
                        <div class="edu-h">Withdrawal & Transfer</div>
                        <p class="edu-p">Shares form the core capital and are not freely withdrawable. They can be transferred to another member if you choose to exit.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="edu-footer">
            <i class="bi bi-stars" style="color:var(--lt);"></i> Tip: Growing your shares steadily is the smartest way to maximize your long-term SACCO benefits.
        </div>
    </div>

</div><!-- /pg-body -->

<?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ── Animate bars
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.querySelectorAll('.sc-bar-fill[data-w]').forEach(el => {
            el.style.width = el.dataset.w + '%';
        });
    }, 480);

    // DataTable
    $('#historyTable').DataTable({
        order: [[0,'desc']],
        pageLength: 10,
        dom: '<"d-flex justify-content-between align-items-center mb-3"fl>t<"d-flex justify-content-between align-items-center mt-3"ip>'
    });
});

// ── Animated counter
(function () {
    const el = document.getElementById('heroAmt');
    if (!el) return;
    const raw = parseFloat(el.textContent.replace(/,/g,''));
    if (!raw || isNaN(raw)) return;
    const dur = 1500, t0 = performance.now();
    const fmt = n => n.toLocaleString('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2});
    function tick(now) {
        const p = Math.min((now-t0)/dur,1), ease = 1-Math.pow(1-p,4);
        el.textContent = fmt(ease*raw);
        if (p < 1) requestAnimationFrame(tick); else el.textContent = fmt(raw);
    }
    requestAnimationFrame(tick);
})();

// ── Portfolio growth chart
(function() {
    const ctx = document.getElementById('growthChart').getContext('2d');
    const grad = ctx.createLinearGradient(0,0,0,160);
    grad.addColorStop(0,'rgba(163,230,53,0.28)');
    grad.addColorStop(1,'rgba(163,230,53,0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= $jsLabels ?>,
            datasets: [{
                data: <?= $jsData ?>,
                borderColor: '#7db820',
                borderWidth: 2.5,
                backgroundColor: grad,
                fill: true,
                tension: 0.42,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: '#a3e635',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0b2419',
                    titleColor: '#a3e635',
                    bodyColor: '#fff',
                    padding: 12, cornerRadius: 10,
                    titleFont: { family:"'Plus Jakarta Sans',sans-serif", size:11, weight:'800' },
                    bodyFont:  { family:"'Plus Jakarta Sans',sans-serif", size:11 },
                    callbacks: { label: c => ' KES ' + c.parsed.y.toLocaleString('en-KE',{minimumFractionDigits:0}) }
                }
            },
            scales: {
                x: {
                    display: true,
                    grid: { display: false },
                    ticks: {
                        font: { family:"'Plus Jakarta Sans',sans-serif", size:10, weight:'700' },
                        color: '#8fada0',
                        maxTicksLimit: 8,
                    },
                    border: { display: false }
                },
                y: {
                    display: true,
                    grid: { color: 'rgba(11,36,25,0.04)', drawTicks: false },
                    ticks: {
                        font: { family:"'Plus Jakarta Sans',sans-serif", size:10, weight:'700' },
                        color: '#8fada0',
                        callback: v => 'KES ' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v),
                        maxTicksLimit: 5,
                    },
                    border: { display: false }
                }
            }
        }
    });
})();
</script>
</body>
</html>