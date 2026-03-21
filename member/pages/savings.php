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

$member_id  = $_SESSION['member_id'];
$typeFilter = $_GET['type']       ?? '';
$startDate  = $_GET['start_date'] ?? '';
$endDate    = $_GET['end_date']   ?? '';

$allowedTypes = ['deposit','contribution','savings_deposit','withdrawal','withdrawal_initiate','withdrawal_finalize'];
$where  = "WHERE member_id = ? AND transaction_type IN ('".implode("','", $allowedTypes)."')";
$params = [$member_id];
$types  = "i";

if ($typeFilter === 'deposit') {
    $where .= " AND transaction_type IN ('deposit','contribution','savings_deposit')";
} elseif ($typeFilter === 'withdrawal') {
    $where .= " AND transaction_type IN ('withdrawal','withdrawal_initiate','withdrawal_finalize')";
}
if ($startDate && $endDate) {
    $where   .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types   .= "ss";
}

require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine           = new FinancialEngine($conn);
$balances         = $engine->getBalances($member_id);
$netSavings       = (float)($balances['savings'] ?? 0.0);
$totalSavings     = $engine->getLifetimeCredits($member_id, 'savings');
$totalWithdrawals = $engine->getCategoryWithdrawals($member_id, 'savings');

$sqlHistory = "SELECT * FROM transactions $where ORDER BY created_at DESC";
$stmt = $conn->prepare($sqlHistory);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$history = $stmt->get_result();

if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf','export_excel','print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    $format = $_GET['action']==='export_excel' ? 'excel' : ($_GET['action']==='print_report' ? 'print' : 'pdf');
    $data = [];
    $history->data_seek(0);
    while ($row = $history->fetch_assoc()) {
        $isD = in_array(strtolower($row['transaction_type']),['deposit','contribution','income']);
        $data[] = ['Date'=>date('d-M-Y H:i',strtotime($row['created_at'])),'Type'=>ucwords(str_replace('_',' ',$row['transaction_type'])),'Notes'=>$row['notes']??'-','Amount'=>($isD?'+':'-').' '.number_format((float)$row['amount'],2)];
    }
    UniversalExportEngine::handle($format,$data,['title'=>'Savings Statement','module'=>'Member Portal','headers'=>['Date','Type','Notes','Amount']]);
    exit;
}

// 6-month trend data
$trend = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $q = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=? AND transaction_type IN ('deposit','contribution','savings_deposit') AND DATE_FORMAT(created_at,'%Y-%m')=?");
    $q->bind_param("is", $member_id, $m);
    $q->execute();
    $trend[] = (float)$q->get_result()->fetch_row()[0];
}
$months_labels = [];
for ($i = 5; $i >= 0; $i--) $months_labels[] = date('M', strtotime("-{$i} months"));

$retainPct   = $totalSavings > 0 ? min(100, ($netSavings / $totalSavings) * 100) : 0;
$withdrawPct = $totalSavings > 0 ? min(100, ($totalWithdrawals / $totalSavings) * 100) : 0;

$pageTitle = "My Savings";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════
   SAVINGS · HD EDITION · Plus Jakarta Sans exclusively
   Forest green / electric lime / crisp whites
═══════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --f:       #0b2419;   /* forest dark  */
    --fm:      #154330;   /* forest mid   */
    --fs:      #1d6044;   /* forest soft  */
    --lime:    #a3e635;
    --lg:      rgba(163,230,53,0.14);
    --lt:      #6a9a1a;   /* lime text    */

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

    --r:  20px;
    --rsm:12px;
    --ease:    cubic-bezier(0.16,1,0.3,1);
    --spring:  cubic-bezier(0.34,1.56,0.64,1);
    --sh:      0 1px 3px rgba(11,36,25,0.05), 0 6px 20px rgba(11,36,25,0.08);
    --sh-lg:   0 4px 8px rgba(11,36,25,0.07), 0 20px 56px rgba(11,36,25,0.13);
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

body, *, input, select, button {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

body { background: var(--bg); color: var(--t1); }

/* layout */
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
    padding: 0;
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

/* topbar */
.hero-nav {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 48px;
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

/* balance block */
.hero-eyebrow {
    display: flex; align-items: center; gap: 10px;
    font-size: 9.5px; font-weight: 800; letter-spacing: 1.5px;
    text-transform: uppercase; color: rgba(163,230,53,0.65);
    margin-bottom: 16px;
}
.ey-line { width: 22px; height: 1.5px; background: var(--lime); opacity:.5; border-radius:99px; }

.hero-lbl {
    font-size: 0.75rem; font-weight: 600; letter-spacing: .4px;
    color: rgba(255,255,255,0.3); text-transform: uppercase;
    margin-bottom: 8px;
}

.hero-amount {
    font-size: clamp(2.8rem, 6.5vw, 4.8rem);
    font-weight: 800;
    color: #fff;
    letter-spacing: -2.5px;
    line-height: 1;
    margin-bottom: 20px;
    animation: slide-up .9s var(--ease) both;
}
.hero-amount .cur {
    font-size: .36em; font-weight: 700;
    vertical-align: .55em; opacity: .45;
    letter-spacing: 0; margin-right: 3px;
}

.hero-apr-pill {
    display: inline-flex; align-items: center; gap: 7px;
    background: rgba(163,230,53,0.11);
    border: 1px solid rgba(163,230,53,0.2);
    color: #bff060; font-size: 11px; font-weight: 700;
    padding: 5px 14px; border-radius: 50px; margin-bottom: 0;
    animation: slide-up .9s var(--ease) .15s both;
    width: fit-content;
}
.apr-pulse {
    width: 5px; height: 5px; border-radius: 50%; background: var(--lime);
    animation: pulse 1.8s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.35;transform:scale(1.8)} }

/* CTA buttons */
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
    padding: 12px 26px; border-radius: 50px;
    cursor: pointer; text-decoration: none;
    transition: all .22s ease;
}
.btn-ghost:hover { background: rgba(255,255,255,.16); color:#fff; transform: translateY(-2px); }

/* sparkline */
.hero-spark {
    animation: slide-up .9s var(--ease) .2s both;
}
.spark-lbl {
    font-size: 9px; font-weight: 800; letter-spacing: 1.2px;
    text-transform: uppercase; color: rgba(255,255,255,.22);
    margin-bottom: 12px;
}
.spark-svg {
    width: 100%; height: 88px;
    overflow: visible; display: block;
}
.spark-month-txt {
    font-size: 7.5px; font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
    fill: rgba(255,255,255,.2);
}

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
    position: relative;
    overflow: hidden;
    transition: transform .28s var(--ease), box-shadow .28s ease;
}
.sc:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(11,36,25,.09), 0 36px 70px rgba(11,36,25,.15); }

/* animated bottom bar */
.sc::after {
    content: '';
    position: absolute; bottom:0; left:0; right:0; height:2.5px;
    border-radius: 0 0 var(--r) var(--r);
    transform: scaleX(0); transform-origin: left;
    transition: transform .38s var(--ease);
}
.sc:hover::after { transform: scaleX(1); }
.sc-g::after { background: linear-gradient(90deg,#16a34a,#4ade80); }
.sc-r::after { background: linear-gradient(90deg,#dc2626,#f87171); }
.sc-l::after { background: linear-gradient(90deg,var(--lime),#d4f98a); }

.sc-ico {
    width: 46px; height: 46px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; margin-bottom: 18px;
    transition: transform .3s var(--spring);
}
.sc:hover .sc-ico { transform: scale(1.12) rotate(7deg); }

.sc-lbl {
    font-size: 10px; font-weight: 800; letter-spacing: 1px;
    text-transform: uppercase; color: var(--t3); margin-bottom: 6px;
}
.sc-val {
    font-size: 1.6rem; font-weight: 800;
    color: var(--t1); letter-spacing: -0.8px;
    line-height: 1.1; margin-bottom: 16px;
}
.sc-bar {
    height: 4px; border-radius: 99px;
    background: var(--bg); overflow: hidden; margin-bottom: 10px;
}
.sc-bar-fill { height: 100%; border-radius: 99px; width: 0; transition: width 1.4s var(--ease); }
.sc-meta { font-size: .72rem; font-weight: 600; color: var(--t3); }

/* staggered float animations */
.sa1 { animation: slide-up .7s var(--ease) .42s both; }
.sa2 { animation: slide-up .7s var(--ease) .52s both; }
.sa3 { animation: slide-up .7s var(--ease) .62s both; }

@keyframes slide-up { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

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

/* filter row */
.filter-row {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; flex-wrap: wrap; margin-bottom: 20px;
    animation: slide-up .7s var(--ease) .68s both;
}

/* type pills */
.type-pills {
    display: flex; gap: 3px;
    background: var(--surf); border: 1px solid var(--bdr);
    border-radius: 13px; padding: 4px; box-shadow: var(--sh);
}
.tpill {
    display: flex; align-items: center; gap: 5px;
    padding: 7px 14px; border-radius: 10px;
    font-size: .78rem; font-weight: 700; color: var(--t3);
    text-decoration: none; transition: all .18s var(--ease);
    border: none; background: transparent; cursor: pointer;
    white-space: nowrap;
}
.tpill:hover { color: var(--t2); }
.tpill.on { background: var(--f); color: #fff; box-shadow: 0 2px 8px rgba(11,36,25,.2); }

/* date strip */
.date-strip {
    display: flex; align-items: center; gap: 8px;
    background: var(--surf); border: 1px solid var(--bdr);
    border-radius: 13px; padding: 7px 14px; box-shadow: var(--sh);
}
.date-strip i { font-size: .78rem; color: var(--t3); flex-shrink:0; }
.date-strip input {
    border: none; background: transparent; outline: none;
    font-size: .78rem; font-weight: 600; color: var(--t2); width: 116px;
}
.date-div { width: 1px; height: 16px; background: var(--bdr); flex-shrink:0; }
.btn-go {
    width: 28px; height: 28px; border-radius: 8px;
    background: var(--f); border: none; color: var(--lime);
    display: flex; align-items: center; justify-content: center;
    font-size: .72rem; cursor: pointer;
    transition: all .22s var(--spring);
}
.btn-go:hover { transform: scale(1.15); background: var(--fs); }

/* ─────────────────────────────────────────────
   TRANSACTION CARD
───────────────────────────────────────────── */
.txn-card {
    background: var(--surf);
    border-radius: 22px;
    border: 1px solid var(--bdr);
    box-shadow: var(--sh);
    overflow: hidden;
    animation: slide-up .7s var(--ease) .73s both;
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

/* export button */
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
.dd-ic {
    width: 32px; height: 32px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: .88rem; flex-shrink:0;
}

/* transaction rows */
.txn-list { display: flex; flex-direction: column; }

.txn-row {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 26px;
    border-bottom: 1px solid var(--bdr2);
    position: relative;
    transition: background .13s ease;
}
.txn-row:last-child { border-bottom: none; }

/* left accent bar */
.txn-row::before {
    content: '';
    position: absolute; left: 0; top: 18%; bottom: 18%;
    width: 2.5px; border-radius: 0 3px 3px 0;
    opacity: 0; transition: opacity .18s ease;
}
.txn-row:hover { background: rgba(11,36,25,.018); }
.txn-row:hover::before { opacity: 1; }
.tr-in:hover::before  { background: var(--grn); }
.tr-out:hover::before { background: var(--red); }

/* icon */
.txn-ico {
    width: 42px; height: 42px; border-radius: 13px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.05rem; flex-shrink:0;
    transition: transform .28s var(--spring);
}
.txn-row:hover .txn-ico { transform: scale(1.1) rotate(5deg); }
.ico-in  { background: var(--grn-bg); color: var(--grn); }
.ico-out { background: var(--red-bg); color: var(--red); }

/* text */
.txn-body { flex:1; min-width:0; }
.txn-name { font-size: .88rem; font-weight: 700; color: var(--t1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.txn-note { font-size: .7rem; font-weight: 500; color: var(--t3); margin-top: 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* status chip */
.txn-chip {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 9.5px; font-weight: 800; letter-spacing:.3px;
    padding: 3px 9px; border-radius: 6px; flex-shrink:0;
    background: var(--grn-bg); color: var(--grn);
}
.chip-dot { width:4px; height:4px; border-radius:50%; background:var(--grn); }

/* amount */
.txn-right { text-align:right; flex-shrink:0; }
.txn-amt {
    font-size: .92rem; font-weight: 800; letter-spacing:-.3px; line-height:1;
}
.amt-in  { color: var(--grn); }
.amt-out { color: var(--red); }
.txn-ts { font-size: .66rem; font-weight: 600; color: var(--t3); margin-top: 4px; }

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

::-webkit-scrollbar { width:4px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:var(--bdr); border-radius:99px; }
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
            <!-- Left -->
            <div class="col-md-6">
                <div class="hero-eyebrow"><div class="ey-line"></div> Savings Portfolio</div>
                <div class="hero-lbl">Net Withdrawable Balance</div>
                <div class="hero-amount"><span class="cur">KES</span><span id="heroAmt"><?= number_format($netSavings, 2) ?></span></div>
                <div class="hero-apr-pill"><span class="apr-pulse"></span> Interest-bearing · 2.4% APR</div>
                <div class="hero-ctas">
                    <a href="<?= BASE_URL ?>/member/pages/mpesa_request.php?type=savings" class="btn-lime">
                        <i class="bi bi-plus-circle-fill"></i> Add Funds
                    </a>
                    <a href="<?= BASE_URL ?>/member/pages/withdraw.php?type=savings&source=savings" class="btn-ghost">
                        <i class="bi bi-arrow-up-right-circle"></i> Withdraw
                    </a>
                </div>
            </div>

            <!-- Right: sparkline -->
            <div class="col-md-6 d-none d-md-block">
                <div class="hero-spark">
                    <div class="spark-lbl">6-Month Deposit Trend</div>
                    <?php
                    $maxT = max(max($trend ?: [0]), 1);
                    $SW = 380; $SH = 88; $PD = 10;
                    $N  = count($trend);
                    $pts = [];
                    foreach ($trend as $i => $v) {
                        $x = $PD + ($i / ($N - 1)) * ($SW - $PD * 2);
                        $y = $SH - $PD - (($v / $maxT) * ($SH - $PD * 2 - 10));
                        $pts[] = "$x,$y";
                    }
                    $poly   = implode(' ', $pts);
                    $lastPt = explode(',', end($pts));
                    ?>
                    <svg class="spark-svg" viewBox="0 0 <?= $SW ?> <?= $SH ?>"
                         xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="sg" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%"   stop-color="#a3e635" stop-opacity=".22"/>
                                <stop offset="100%" stop-color="#a3e635" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <polygon
                            points="<?= $pts[0] ?> <?= $poly ?> <?= $SW - $PD ?>,<?= $SH ?> <?= $PD ?>,<?= $SH ?>"
                            fill="url(#sg)"/>
                        <polyline points="<?= $poly ?>"
                            fill="none" stroke="#a3e635" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round"/>
                        <?php foreach ($pts as $pt): [$px,$py] = explode(',', $pt); ?>
                        <circle cx="<?= $px ?>" cy="<?= $py ?>" r="2.5"
                            fill="var(--f)" stroke="#a3e635" stroke-width="1.5"/>
                        <?php endforeach; ?>
                        <circle cx="<?= $lastPt[0] ?>" cy="<?= $lastPt[1] ?>" r="4.5"
                            fill="#a3e635" opacity=".9"/>
                        <circle cx="<?= $lastPt[0] ?>" cy="<?= $lastPt[1] ?>" r="9"
                            fill="#a3e635" opacity=".1"/>
                        <?php foreach ($months_labels as $i => $m):
                            $mx = $PD + ($i / ($N - 1)) * ($SW - $PD * 2); ?>
                        <text x="<?= $mx ?>" y="<?= $SH - 1 ?>"
                            text-anchor="middle" class="spark-month-txt"><?= $m ?></text>
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
        <div class="col-md-4 sa1">
            <div class="sc sc-g">
                <div class="sc-ico" style="background:var(--grn-bg);color:var(--grn);">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="sc-lbl">Total Deposited</div>
                <div class="sc-val">KES <?= number_format($totalSavings, 2) ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--grn);" data-w="100"></div></div>
                <div class="sc-meta">Cumulative deposits &amp; contributions</div>
            </div>
        </div>
        <div class="col-md-4 sa2">
            <div class="sc sc-r">
                <div class="sc-ico" style="background:var(--red-bg);color:var(--red);">
                    <i class="bi bi-arrow-up-right-square-fill"></i>
                </div>
                <div class="sc-lbl">Total Withdrawn</div>
                <div class="sc-val">KES <?= number_format($totalWithdrawals, 2) ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--red);" data-w="<?= round($withdrawPct) ?>"></div></div>
                <div class="sc-meta"><?= round($withdrawPct) ?>% of deposits withdrawn</div>
            </div>
        </div>
        <div class="col-md-4 sa3">
            <div class="sc sc-l">
                <div class="sc-ico" style="background:var(--lg);color:var(--lt);">
                    <i class="bi bi-shield-fill-check"></i>
                </div>
                <div class="sc-lbl">Retention Rate</div>
                <div class="sc-val"><?= number_format($retainPct, 1) ?>%</div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--lime);" data-w="<?= round($retainPct) ?>"></div></div>
                <div class="sc-meta">Net savings vs. total contributed</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════ BODY ═════════════════════════ -->
<div class="pg-body">

    <!-- Filter row -->
    <div class="filter-row">
        <div class="sec-label">Transaction History</div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="type-pills">
                <a href="?" class="tpill <?= $typeFilter==='' ? 'on' : '' ?>">All</a>
                <a href="?type=deposit" class="tpill <?= $typeFilter==='deposit' ? 'on' : '' ?>">
                    <i class="bi bi-arrow-down-circle" style="font-size:.72rem;"></i> Deposits
                </a>
                <a href="?type=withdrawal" class="tpill <?= $typeFilter==='withdrawal' ? 'on' : '' ?>">
                    <i class="bi bi-arrow-up-circle" style="font-size:.72rem;"></i> Withdrawals
                </a>
            </div>
            <form method="GET">
                <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">
                <div class="date-strip">
                    <i class="bi bi-calendar3"></i>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                    <div class="date-div"></div>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                    <button type="submit" class="btn-go"><i class="bi bi-arrow-right"></i></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transaction card -->
    <div class="txn-card">
        <div class="txn-card-head">
            <div class="d-flex align-items-center gap-3">
                <span class="txn-card-title">Recent Activity</span>
                <span class="txn-card-ct"><?= $history->num_rows ?> records</span>
            </div>
            <div class="dropdown">
                <button class="btn-exp" data-bs-toggle="dropdown">
                    <i class="bi bi-cloud-download-fill"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end exp-dd">
                    <li>
                        <a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'export_pdf'])) ?>">
                            <div class="dd-ic" style="background:rgba(220,38,38,.09);color:#dc2626;"><i class="bi bi-file-pdf"></i></div>
                            PDF Document
                        </a>
                    </li>
                    <li>
                        <a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'export_excel'])) ?>">
                            <div class="dd-ic" style="background:rgba(5,150,105,.09);color:#059669;"><i class="bi bi-file-earmark-excel"></i></div>
                            Excel Sheet
                        </a>
                    </li>
                    <li><hr class="dropdown-divider mx-2"></li>
                    <li>
                        <a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'print_report'])) ?>" target="_blank">
                            <div class="dd-ic" style="background:rgba(79,70,229,.09);color:#4f46e5;"><i class="bi bi-printer"></i></div>
                            Print Layout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="txn-list">
            <?php if ($history->num_rows > 0):
                $history->data_seek(0);
                while ($row = $history->fetch_assoc()):
                    $isIn   = in_array(strtolower($row['transaction_type']),['deposit','contribution','income','savings_deposit']);
                    $icon   = $isIn ? 'bi-arrow-down-circle-fill' : 'bi-arrow-up-circle-fill';
                    $label  = ucwords(str_replace('_',' ',$row['transaction_type']));
                    $note   = $row['notes'] ?: 'Completed Transaction';
                    $sign   = $isIn ? '+' : '−';
                    $trCls  = $isIn ? 'tr-in' : 'tr-out';
                    $icoCls = $isIn ? 'ico-in' : 'ico-out';
                    $amtCls = $isIn ? 'amt-in' : 'amt-out';
            ?>
            <div class="txn-row <?= $trCls ?>">
                <div class="txn-ico <?= $icoCls ?>"><i class="bi <?= $icon ?>"></i></div>
                <div class="txn-body">
                    <div class="txn-name"><?= htmlspecialchars($label) ?></div>
                    <div class="txn-note"><?= htmlspecialchars($note) ?></div>
                </div>
                <div class="txn-chip"><div class="chip-dot"></div> Done</div>
                <div class="txn-right">
                    <div class="txn-amt <?= $amtCls ?>"><?= $sign ?> <?= number_format((float)$row['amount'], 2) ?> <span style="font-size:.54em;font-weight:700;opacity:.38;">KES</span></div>
                    <div class="txn-ts"><?= date('D d M, H:i', strtotime($row['created_at'])) ?></div>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="empty-well">
                <div class="ew-ico"><i class="bi bi-inbox"></i></div>
                <div class="ew-title">No Transactions Found</div>
                <div class="ew-sub">No activity matches your current filter. Try adjusting the date range.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /pg-body -->

<?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Animate bars
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.querySelectorAll('.sc-bar-fill[data-w]').forEach(el => {
            el.style.width = el.dataset.w + '%';
        });
    }, 480);
});

// Counter animation
(function () {
    const el = document.getElementById('heroAmt');
    if (!el) return;
    const raw = parseFloat(el.textContent.replace(/,/g, ''));
    if (!raw || isNaN(raw)) return;
    const dur = 1500;
    const t0  = performance.now();
    const fmt = n => n.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function tick(now) {
        const p    = Math.min((now - t0) / dur, 1);
        const ease = 1 - Math.pow(1 - p, 4);
        el.textContent = fmt(ease * raw);
        if (p < 1) requestAnimationFrame(tick);
        else el.textContent = fmt(raw);
    }
    requestAnimationFrame(tick);
})();
</script>
</body>
</html>