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

// Member basics
$stmt = $conn->prepare("SELECT full_name, member_reg_no, created_at FROM members WHERE member_id=?");
$stmt->bind_param("i", $member_id); $stmt->execute();
$md = $stmt->get_result()->fetch_assoc(); $stmt->close();
$member_name = htmlspecialchars($md['full_name'] ?? 'Member');
$reg_no      = htmlspecialchars($md['member_reg_no'] ?? 'N/A');
$join_date   = date('M Y', strtotime($md['created_at'] ?? 'now'));
$_SESSION['reg_no'] = $reg_no;
$first_name  = htmlspecialchars(explode(' ', $member_name)[0]);

// Balances
require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine        = new FinancialEngine($conn);
$balances      = $engine->getBalances($member_id);
$cur_bal       = (float)$balances['wallet'];
$total_savings = (float)$balances['savings'];
$total_shares  = (float)$balances['shares'];
$active_loans  = (float)$balances['loans'];
$net_worth     = $total_savings + $total_shares - $active_loans;
$loan_limit    = 500000;
$loan_pct      = $loan_limit > 0 ? min(100, ($active_loans / $loan_limit) * 100) : 0;

// 12-month arrays
$mo_labels = $sav_arr = $ctb_arr = $rep_arr = [];
for ($i = 11; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("-$i months"));
    $me = date('Y-m-t',  strtotime("-$i months"));
    $mo_labels[] = date('M', strtotime($ms));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(le.credit-le.debit),0) FROM ledger_entries le JOIN ledger_accounts la ON le.account_id=la.account_id WHERE la.member_id=? AND la.category='savings' AND le.created_at BETWEEN ? AND ?");
    $stmt->bind_param("iss", $member_id, $ms, $me); $stmt->execute();
    $sav_arr[] = (float)$stmt->get_result()->fetch_row()[0]; $stmt->close();
    foreach (['contribution'=>&$ctb_arr, 'loan_repayment'=>&$rep_arr] as $type => &$arr) {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=? AND transaction_type=? AND created_at BETWEEN ? AND ?");
        $stmt->bind_param("isss", $member_id, $type, $ms, $me); $stmt->execute();
        $arr[] = (float)$stmt->get_result()->fetch_row()[0]; $stmt->close();
    }
}

// 6-month income vs outflow
$inc_labels = $inc_arr = $exp_arr = [];
for ($i = 5; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("-$i months"));
    $me = date('Y-m-t',  strtotime("-$i months"));
    $inc_labels[] = date('M', strtotime($ms));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=? AND transaction_type IN('deposit','contribution') AND created_at BETWEEN ? AND ?");
    $stmt->bind_param("iss", $member_id, $ms, $me); $stmt->execute();
    $inc_arr[] = (float)$stmt->get_result()->fetch_row()[0]; $stmt->close();
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=? AND transaction_type IN('withdrawal','loan_repayment') AND created_at BETWEEN ? AND ?");
    $stmt->bind_param("iss", $member_id, $ms, $me); $stmt->execute();
    $exp_arr[] = (float)$stmt->get_result()->fetch_row()[0]; $stmt->close();
}

// Extra stats
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=? AND transaction_type='contribution' AND MONTH(created_at)=MONTH(NOW())");
$stmt->bind_param("i",$member_id); $stmt->execute();
$month_contrib = (float)$stmt->get_result()->fetch_row()[0]; $stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=? AND transaction_type IN('deposit','contribution')");
$stmt->bind_param("i",$member_id); $stmt->execute();
$total_deposits = (float)$stmt->get_result()->fetch_row()[0]; $stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=? AND transaction_type='withdrawal'");
$stmt->bind_param("i",$member_id); $stmt->execute();
$total_withdrawals = (float)$stmt->get_result()->fetch_row()[0]; $stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=? AND transaction_type='welfare'");
$stmt->bind_param("i",$member_id); $stmt->execute();
$welfare_total = (float)$stmt->get_result()->fetch_row()[0]; $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM loans WHERE member_id=? AND status='pending'");
$stmt->bind_param("i",$member_id); $stmt->execute();
$pending_loans = (int)$stmt->get_result()->fetch_row()[0]; $stmt->close();

// Recent transactions
$recent_txn = [];
$stmt = $conn->prepare("SELECT transaction_type, amount, created_at, reference_no FROM transactions WHERE member_id=? ORDER BY created_at DESC LIMIT 8");
$stmt->bind_param("i",$member_id); $stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $recent_txn[] = $r;
$stmt->close();

// Health score
$health = max(0, round(100
    - min(30, ($loan_pct/100)*30)
    - ($month_contrib == 0 ? 15 : 0)
    - ($total_savings < 5000 ? 10 : 0)
    - ($pending_loans > 0 ? 5 : 0)
));

// Radar dimensions
$radar = [
    min(100, ($total_savings  / 100000) * 100),
    min(100, ($total_shares   / 50000)  * 100),
    max(0,   100 - $loan_pct),
    min(100, ($month_contrib  / 5000)   * 100),
    min(100, ($welfare_total  / 10000)  * 100),
    min(100, ($cur_bal        / 20000)  * 100),
];

function ks(float $n): string {
    if ($n >= 1_000_000) return 'KES '.number_format($n/1_000_000, 2).'M';
    if ($n >= 1_000)     return 'KES '.number_format($n/1_000, 1).'K';
    return 'KES '.number_format($n, 2);
}

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<script>(function(){var s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard &middot; <?= $member_name ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* TOKEN MATCH: savings / shares / welfare / sidebar / topbar */
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

/* HERO */
.hero{background:linear-gradient(135deg,var(--f) 0%,var(--fm) 55%,var(--fs) 100%);border-radius:var(--r);padding:40px 48px 96px;position:relative;overflow:hidden;color:#fff;margin-bottom:0;animation:fadeUp .7s var(--ease) both}
.hero-mesh{position:absolute;inset:0;pointer-events:none;background:radial-gradient(ellipse 60% 80% at 105% -5%,rgba(163,230,53,.11) 0%,transparent 55%),radial-gradient(ellipse 35% 45% at -8% 105%,rgba(163,230,53,.07) 0%,transparent 55%)}
.hero-dots{position:absolute;inset:0;pointer-events:none;background-image:radial-gradient(rgba(255,255,255,.05) 1px,transparent 1px);background-size:20px 20px}
.hero-ring{position:absolute;border-radius:50%;pointer-events:none;border:1px solid rgba(163,230,53,.07)}
.hero-ring.r1{width:420px;height:420px;top:-140px;right:-100px}
.hero-ring.r2{width:620px;height:620px;top:-220px;right:-200px}
.hero-inner{position:relative;z-index:2}
.hero-eyebrow{display:inline-flex;align-items:center;gap:7px;background:rgba(163,230,53,.12);border:1px solid rgba(163,230,53,.2);border-radius:50px;padding:4px 14px;margin-bottom:14px;font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#bff060}
.eyebrow-dot{width:5px;height:5px;border-radius:50%;background:var(--lime);animation:pulse 1.8s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(1.8)}}
.hero h1{font-size:clamp(1.8rem,4vw,2.8rem);font-weight:800;color:#fff;letter-spacing:-.6px;line-height:1.1;margin-bottom:8px}
.hero-sub{font-size:.8rem;color:rgba(255,255,255,.45);margin-bottom:24px;font-weight:500}
.hero-sub strong{color:rgba(255,255,255,.75);font-weight:700}
.hero-bubbles{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:28px}
.hbub{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:11px 16px;transition:all .22s var(--spring);min-width:100px}
.hbub:hover{background:rgba(255,255,255,.18);transform:translateY(-2px)}
.hbub-val{font-size:.9rem;font-weight:800;color:#fff;letter-spacing:-.3px;line-height:1.1}
.hbub-lbl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.4);margin-top:3px}
.hero-ctas{display:flex;gap:9px;flex-wrap:wrap}
.btn-lime{display:inline-flex;align-items:center;gap:8px;background:var(--lime);color:var(--f);font-size:.875rem;font-weight:800;padding:11px 24px;border-radius:50px;border:none;cursor:pointer;text-decoration:none;box-shadow:0 2px 14px rgba(163,230,53,.28);transition:all .25s var(--spring)}
.btn-lime:hover{transform:translateY(-2px) scale(1.03);box-shadow:0 10px 28px rgba(163,230,53,.4);color:var(--f)}
.btn-ghost{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);font-size:.875rem;font-weight:700;padding:11px 20px;border-radius:50px;cursor:pointer;text-decoration:none;transition:all .22s ease}
.btn-ghost:hover{background:rgba(255,255,255,.17);color:#fff;transform:translateY(-2px)}
.hero-grade{text-align:right;position:relative;z-index:2;animation:fadeUp .8s var(--ease) .15s both}
.grade-big{font-size:4rem;font-weight:800;color:#fff;letter-spacing:-3px;line-height:1}
.grade-label{font-size:9px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:6px}
.grade-sub{font-size:.6rem;color:rgba(255,255,255,.35);margin-bottom:12px}
.loan-bar-wrap{background:rgba(255,255,255,.1);border-radius:10px;padding:10px 14px;display:inline-block;min-width:160px}
.loan-bar-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.4);margin-bottom:6px}
.loan-bar-track{background:rgba(255,255,255,.15);height:5px;border-radius:99px;overflow:hidden}
.loan-bar-fill{height:100%;border-radius:99px;background:var(--lime);transition:width 1s var(--ease)}
.loan-bar-pct{font-size:.6rem;color:rgba(255,255,255,.4);margin-top:4px}

/* FLOATING STAT CARDS */
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

/* CHART CARDS */
.pg-body{padding:32px 28px 0}
@media(max-width:767px){.pg-body{padding:24px 14px 0}}
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

/* HEALTH */
.gauge-half{position:relative;width:180px;height:90px;margin:0 auto 12px;overflow:hidden}
.hf{display:flex;align-items:center;gap:9px;padding:9px 0;border-bottom:1px solid var(--bdr2)}
.hf:last-child{border:none}
.hf-ico{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.72rem;flex-shrink:0}
.hf-ok{background:var(--grn-bg);color:var(--grn)}
.hf-fail{background:var(--red-bg);color:var(--red)}
.hf-lbl{font-size:.75rem;font-weight:700;color:var(--t1);flex:1}
.hf-val{font-size:.65rem;font-weight:600;color:var(--t3)}

/* TRANSACTION FEED */
.txn-feed{list-style:none;margin:0;padding:0}
.txn-row{display:flex;align-items:center;justify-content:space-between;padding:11px 0;border-bottom:1px solid var(--bdr2);gap:10px;position:relative}
.txn-row:last-child{border-bottom:none}
.txn-row::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:2.5px;border-radius:0 3px 3px 0;opacity:0;transition:opacity .18s ease}
.txn-row:hover{background:rgba(11,36,25,.015);margin:0 -14px;padding:11px 14px}
.txn-row:hover::before{opacity:1}
.txn-row.ti-in:hover::before{background:var(--grn)}
.txn-row.ti-out:hover::before{background:var(--red)}
.txn-row.ti-loan:hover::before{background:var(--amb)}
.txn-ico{width:36px;height:36px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;transition:transform .25s var(--spring)}
.txn-row:hover .txn-ico{transform:scale(1.1) rotate(5deg)}
.ico-in{background:var(--grn-bg);color:var(--grn)}
.ico-out{background:var(--red-bg);color:var(--red)}
.ico-loan{background:var(--amb-bg);color:var(--amb)}
.txn-name{font-size:.82rem;font-weight:700;color:var(--t1);text-transform:capitalize;margin-bottom:2px}
.txn-date{font-size:.65rem;font-weight:500;color:var(--t3)}
.txn-amt-in{font-size:.82rem;font-weight:800;color:var(--grn)}
.txn-amt-out{font-size:.82rem;font-weight:800;color:var(--red)}
.txn-ref{display:block;font-size:.58rem;color:var(--t3);background:var(--bg);padding:1px 7px;border-radius:5px;margin-top:3px;text-align:right;font-family:monospace}
.btn-view-all{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:10px;border-radius:var(--rsm);background:var(--bg);border:1.5px solid var(--bdr);font-size:.75rem;font-weight:700;color:var(--t2);text-decoration:none;margin-top:12px;transition:all .2s ease}
.btn-view-all:hover{border-color:rgba(11,36,25,.18);color:var(--f);background:var(--surf)}
.empty-well{display:flex;flex-direction:column;align-items:center;padding:48px 20px;text-align:center}
.ew-ico{width:64px;height:64px;border-radius:18px;background:var(--bg);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:var(--t3);margin-bottom:14px}
.ew-title{font-size:.88rem;font-weight:800;color:var(--t1);margin-bottom:4px}
.ew-sub{font-size:.76rem;color:var(--t3)}
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
            <div class="col-lg-8">
                <div class="hero-eyebrow"><span class="eyebrow-dot"></span> Verified Member</div>
                <h1>Good day, <?= $first_name ?>! 👋</h1>
                <p class="hero-sub">
                    Member <strong><?= $reg_no ?></strong> &nbsp;&middot;&nbsp;
                    Since <strong><?= $join_date ?></strong> &nbsp;&middot;&nbsp;
                    Health <strong style="color:#fff"><?= $health ?>/100</strong>
                </p>
                <div class="hero-bubbles">
                    <div class="hbub"><div class="hbub-val"><?= ks($total_savings) ?></div><div class="hbub-lbl">Savings</div></div>
                    <div class="hbub"><div class="hbub-val"><?= ks($total_shares) ?></div><div class="hbub-lbl">Shares</div></div>
                    <div class="hbub"><div class="hbub-val" style="color:<?= $active_loans>0?'#fca5a5':'#a3e635' ?>"><?= ks($active_loans) ?></div><div class="hbub-lbl">Loans</div></div>
                    <div class="hbub"><div class="hbub-val" style="color:<?= $net_worth>=0?'#a3e635':'#fca5a5' ?>"><?= ks(abs($net_worth)) ?></div><div class="hbub-lbl">Net Worth</div></div>
                    <div class="hbub"><div class="hbub-val"><?= ks($cur_bal) ?></div><div class="hbub-lbl">Wallet</div></div>
                </div>
                <div class="hero-ctas">
                    <a href="mpesa_request.php" class="btn-lime"><i class="bi bi-plus-circle-fill"></i> Deposit</a>
                    <?php if ($cur_bal > 0): ?><a href="withdraw.php" class="btn-ghost"><i class="bi bi-arrow-up-right-circle"></i> Withdraw</a><?php endif; ?>
                    <a href="loans.php" class="btn-ghost"><i class="bi bi-bank2"></i> Apply Loan</a>
                    <a href="transactions.php" class="btn-ghost"><i class="bi bi-list-ul"></i> Transactions</a>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-block">
                <div class="hero-grade">
                    <div class="grade-label">Credit Grade</div>
                    <div class="grade-big"><?= $loan_pct<30?'AAA':($loan_pct<50?'AA+':($loan_pct<70?'A+':'B+')) ?></div>
                    <div class="grade-sub">Based on loan utilization</div>
                    <div class="loan-bar-wrap">
                        <div class="loan-bar-label">Loan Limit Used</div>
                        <div class="loan-bar-track"><div class="loan-bar-fill" style="width:<?= $loan_pct ?>%"></div></div>
                        <div class="loan-bar-pct"><?= number_format($loan_pct,1) ?>% of KES 500K</div>
                    </div>
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
                <div class="sc-ico" style="background:var(--grn-bg);color:var(--grn)"><i class="bi bi-piggy-bank-fill"></i></div>
                <div class="sc-lbl">Total Savings</div>
                <div class="sc-val"><?= ks($total_savings) ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--grn)" data-w="100"></div></div>
                <div class="sc-meta">Active savings account</div>
            </div>
        </div>
        <div class="col-md-3 sa2">
            <div class="sc sc-b">
                <div class="sc-ico" style="background:var(--blu-bg);color:var(--blu)"><i class="bi bi-calendar-check-fill"></i></div>
                <div class="sc-lbl">This Month</div>
                <div class="sc-val"><?= ks($month_contrib) ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--blu)" data-w="<?= $month_contrib>0?75:0 ?>"></div></div>
                <div class="sc-meta"><?= $month_contrib>0?'Contribution made':'Not yet contributed' ?></div>
            </div>
        </div>
        <div class="col-md-3 sa3">
            <div class="sc sc-a">
                <div class="sc-ico" style="background:var(--amb-bg);color:var(--amb)"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="sc-lbl">Total Deposits</div>
                <div class="sc-val"><?= ks($total_deposits) ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--amb)" data-w="100"></div></div>
                <div class="sc-meta">All-time deposits</div>
            </div>
        </div>
        <div class="col-md-3 sa4">
            <div class="sc sc-r">
                <div class="sc-ico" style="background:var(--red-bg);color:var(--red)"><i class="bi bi-arrow-up-right-square-fill"></i></div>
                <div class="sc-lbl">Total Withdrawn</div>
                <div class="sc-val"><?= ks($total_withdrawals) ?></div>
                <?php $wPct=$total_deposits>0?min(100,($total_withdrawals/$total_deposits)*100):0; ?>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--red)" data-w="<?= round($wPct) ?>"></div></div>
                <div class="sc-meta"><?= round($wPct) ?>% of deposits</div>
            </div>
        </div>
    </div>
</div>

<!-- BODY -->
<div class="pg-body">

    <!-- Row 1: Stacked bar + Income vs Outflow -->
    <div class="row g-3 mb-3">
        <div class="col-xl-7">
            <div class="chart-card" style="animation-delay:.72s">
                <div class="cc-head">
                    <div><div class="cc-title">12-Month Financial Activity</div><div class="cc-sub">Savings · Contributions · Repayments</div></div>
                    <div class="cc-stats">
                        <div><div class="cc-stat-val"><?= ks($total_savings) ?></div><div class="cc-stat-lbl">Net Savings</div></div>
                        <div class="cc-stat-div"></div>
                        <div><div class="cc-stat-val"><?= ks(array_sum($ctb_arr)) ?></div><div class="cc-stat-lbl">Total Contributions</div></div>
                    </div>
                </div>
                <div class="leg">
                    <div class="leg-i"><span class="leg-dot" style="background:var(--f)"></span>Savings</div>
                    <div class="leg-i"><span class="leg-dot" style="background:var(--lime)"></span>Contributions</div>
                    <div class="leg-i"><span class="leg-dot" style="background:var(--blu)"></span>Repayments</div>
                </div>
                <div class="chart-box" style="height:260px;margin-top:12px"><canvas id="chartStackedBar"></canvas></div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="chart-card" style="animation-delay:.78s">
                <div class="cc-head"><div><div class="cc-title">Income vs Outflow</div><div class="cc-sub">6-month deposits vs withdrawals</div></div></div>
                <div class="cc-stats">
                    <div><div class="cc-stat-val"><?= ks(array_sum($inc_arr)) ?></div><div class="cc-stat-lbl">Total In</div></div>
                    <div class="cc-stat-div"></div>
                    <div><div class="cc-stat-val"><?= ks(array_sum($exp_arr)) ?></div><div class="cc-stat-lbl">Total Out</div></div>
                </div>
                <div class="leg">
                    <div class="leg-i"><span class="leg-dot" style="background:var(--grn)"></span>Income</div>
                    <div class="leg-i"><span class="leg-dot" style="background:var(--red)"></span>Outflow</div>
                </div>
                <div class="chart-box" style="height:230px;margin-top:10px"><canvas id="chartGrouped"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Row 2: Portfolio + Health + Transactions -->
    <div class="row g-3 mb-3">

        <!-- Portfolio Doughnut -->
        <div class="col-xl-4">
            <div class="chart-card" style="animation-delay:.84s">
                <div class="cc-head"><div><div class="cc-title">Portfolio Composition</div><div class="cc-sub">Asset vs liability breakdown</div></div></div>
                <div style="position:relative;width:160px;height:160px;margin:8px auto 18px">
                    <canvas id="chartDonut" width="160" height="160"></canvas>
                    <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;pointer-events:none">
                        <div style="font-size:.88rem;font-weight:800;color:var(--t1);letter-spacing:-.3px;line-height:1.1"><?= ks($total_savings+$total_shares) ?></div>
                        <div style="font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--t3);margin-top:2px">Total Assets</div>
                    </div>
                </div>
                <?php foreach ([['Savings','var(--f)',$total_savings],['Shares','var(--lime)',$total_shares],['Loans','var(--red)',$active_loans],['Wallet','var(--blu)',$cur_bal]] as $d): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--bdr2);font-size:.76rem">
                    <span style="display:flex;align-items:center;gap:8px;font-weight:600;color:var(--t2)">
                        <span style="width:8px;height:8px;border-radius:50%;background:<?= $d[1] ?>;flex-shrink:0"></span><?= $d[0] ?>
                    </span>
                    <span style="font-weight:800;color:var(--t1)"><?= ks($d[2]) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Health Gauge -->
        <div class="col-xl-4">
            <div class="chart-card" style="animation-delay:.90s">
                <div class="cc-head"><div><div class="cc-title">Account Health</div><div class="cc-sub">Composite financial score</div></div></div>
                <div class="gauge-half"><canvas id="chartGauge" width="180" height="180" style="margin-top:-45px"></canvas></div>
                <div style="text-align:center;margin-bottom:16px">
                    <div style="font-size:2rem;font-weight:800;color:var(--t1);letter-spacing:-1px;line-height:1"><?= $health ?></div>
                    <?php $hc=$health>=80?'var(--grn)':($health>=60?'var(--blu)':'var(--amb)'); $hbg=$health>=80?'var(--grn-bg)':($health>=60?'var(--blu-bg)':'var(--amb-bg)'); ?>
                    <div style="display:inline-flex;align-items:center;gap:5px;background:<?= $hbg ?>;color:<?= $hc ?>;border-radius:50px;padding:3px 12px;font-size:.7rem;font-weight:800;margin-top:5px">
                        <?= $health>=80?'Excellent':($health>=60?'Good':'Fair') ?>
                    </div>
                </div>
                <?php foreach ([
                    ['Loan Utilization', $loan_pct<50,      'bi-bank2',             number_format($loan_pct,0).'% used'],
                    ['Monthly Contrib',  $month_contrib>0,  'bi-calendar-check-fill',ks($month_contrib)],
                    ['Savings Balance',  $total_savings>=5000,'bi-piggy-bank-fill', ks($total_savings)],
                    ['Welfare Active',   $welfare_total>0,  'bi-heart-pulse-fill',   ks($welfare_total)],
                ] as $hf): ?>
                <div class="hf">
                    <div class="hf-ico <?= $hf[1]?'hf-ok':'hf-fail' ?>"><i class="bi <?= $hf[2] ?>"></i></div>
                    <div class="hf-lbl"><?= $hf[0] ?></div>
                    <div class="hf-val"><?= $hf[3] ?></div>
                    <i class="bi bi-<?= $hf[1]?'check-circle-fill':'x-circle-fill' ?>" style="font-size:.8rem;color:<?= $hf[1]?'var(--grn)':'var(--red)' ?>"></i>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Transaction Feed -->
        <div class="col-xl-4">
            <div class="chart-card" style="animation-delay:.96s">
                <div class="cc-head"><div><div class="cc-title">Recent Transactions</div><div class="cc-sub">Latest 8 entries</div></div></div>
                <?php if (empty($recent_txn)): ?>
                <div class="empty-well"><div class="ew-ico"><i class="bi bi-inbox"></i></div><div class="ew-title">No Transactions Yet</div><div class="ew-sub">Your activity will appear here.</div></div>
                <?php else: ?>
                <ul class="txn-feed">
                    <?php foreach ($recent_txn as $t):
                        $is_in   = in_array($t['transaction_type'],['deposit','income','contribution','revenue_inflow']);
                        $is_loan = str_contains($t['transaction_type'],'loan');
                        $tr_cls  = $is_loan?'ti-loan':($is_in?'ti-in':'ti-out');
                        $ic_cls  = $is_loan?'ico-loan':($is_in?'ico-in':'ico-out');
                        $ib      = $is_loan?'bi-bank2':($is_in?'bi-arrow-down-circle-fill':'bi-arrow-up-circle-fill');
                    ?>
                    <li class="txn-row <?= $tr_cls ?>">
                        <div style="display:flex;align-items:center;gap:9px">
                            <div class="txn-ico <?= $ic_cls ?>"><i class="bi <?= $ib ?>"></i></div>
                            <div><div class="txn-name"><?= htmlspecialchars($t['transaction_type']) ?></div><div class="txn-date"><?= date('d M, h:i A', strtotime($t['created_at'])) ?></div></div>
                        </div>
                        <div style="text-align:right;flex-shrink:0">
                            <div class="<?= $is_in?'txn-amt-in':'txn-amt-out' ?>"><?= $is_in?'+':'−' ?> <?= number_format((float)$t['amount'],2) ?></div>
                            <span class="txn-ref"><?= strtoupper(htmlspecialchars($t['reference_no'])) ?></span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <a href="transactions.php" class="btn-view-all"><i class="bi bi-list-ul"></i> View All Transactions</a>
            </div>
        </div>

    </div>

    <!-- Row 3: Radar + Quick Actions -->
    <div class="row g-3 mb-3">
        <div class="col-xl-5">
            <div class="chart-card" style="animation-delay:1.02s">
                <div class="cc-head"><div><div class="cc-title">Financial Radar</div><div class="cc-sub">Multi-dimension performance score</div></div></div>
                <div class="chart-box" style="height:260px"><canvas id="chartRadar"></canvas></div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="chart-card" style="animation-delay:1.08s">
                <div class="cc-head"><div><div class="cc-title">Quick Actions</div><div class="cc-sub">Common financial operations</div></div></div>
                <div class="row g-3 mt-1">
                    <?php foreach ([
                        ['bi-plus-circle-fill',  'var(--grn-bg)','var(--grn)','Add Funds',   'Deposit via M-Pesa',   'mpesa_request.php?type=savings'],
                        ['bi-pie-chart-fill',    'var(--lg)',    'var(--lt)', 'Buy Shares',  'Invest in equity',     'mpesa_request.php?type=shares'],
                        ['bi-heart-fill',        'var(--red-bg)','var(--red)','Contribute',  'Welfare fund deposit', 'mpesa_request.php?type=welfare'],
                        ['bi-bank2',             'var(--amb-bg)','var(--amb)','Apply Loan',  'Request financing',    'loans.php'],
                        ['bi-wallet2',           'var(--blu-bg)','var(--blu)','Withdraw',    'Transfer to M-Pesa',   'withdraw.php'],
                        ['bi-file-earmark-text', 'var(--bdr)',   'var(--t2)', 'Statement',   'Download PDF report',  'savings.php'],
                    ] as $a): ?>
                    <div class="col-md-4">
                        <a href="<?= $a[5] ?>" style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--surf2);border:1px solid var(--bdr);border-radius:14px;text-decoration:none;transition:all .2s var(--ease)"
                           onmouseover="this.style.borderColor='rgba(11,36,25,.18)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(11,36,25,.09)'"
                           onmouseout="this.style.borderColor='var(--bdr)';this.style.transform='';this.style.boxShadow=''">
                            <div style="width:38px;height:38px;border-radius:11px;background:<?= $a[1] ?>;color:<?= $a[2] ?>;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0"><i class="bi <?= $a[0] ?>"></i></div>
                            <div>
                                <div style="font-size:.83rem;font-weight:800;color:var(--t1);margin-bottom:2px"><?= $a[3] ?></div>
                                <div style="font-size:.68rem;font-weight:500;color:var(--t3)"><?= $a[4] ?></div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

</div><!-- /pg-body -->
</div><!-- /dash -->

<?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const MO_LABELS=<?= json_encode($mo_labels) ?>;
const SAV_DATA =<?= json_encode($sav_arr) ?>;
const CTB_DATA =<?= json_encode($ctb_arr) ?>;
const REP_DATA =<?= json_encode($rep_arr) ?>;
const INC_LABELS=<?= json_encode($inc_labels) ?>;
const INC_DATA =<?= json_encode($inc_arr) ?>;
const EXP_DATA =<?= json_encode($exp_arr) ?>;
const RADAR_D  =<?= json_encode($radar) ?>;
const TOTAL_SAV=<?= $total_savings ?>;
const TOTAL_SHR=<?= $total_shares ?>;
const LOANS_BAL=<?= $active_loans ?>;
const WAL_BAL  =<?= $cur_bal ?>;
const HEALTH   =<?= $health ?>;

const dark=document.documentElement.getAttribute('data-bs-theme')==='dark';
const GRID=dark?'rgba(255,255,255,.05)':'rgba(11,36,25,.05)';
const TICK=dark?'#3a6050':'#8fada0';
const SURF=dark?'#0d1d14':'#ffffff';

const TT={backgroundColor:dark?'#0d1d14':'#0b2419',titleColor:'#a3e635',bodyColor:'#fff',padding:12,cornerRadius:10,borderColor:'rgba(163,230,53,.2)',borderWidth:1,titleFont:{family:"'Plus Jakarta Sans',sans-serif",weight:'800',size:12},bodyFont:{family:"'Plus Jakarta Sans',sans-serif",size:11}};
const XS={grid:{display:false},ticks:{color:TICK,font:{family:"'Plus Jakarta Sans',sans-serif",size:10}}};
const YS={grid:{color:GRID},ticks:{color:TICK,font:{family:"'Plus Jakarta Sans',sans-serif",size:10}}};

document.addEventListener('DOMContentLoaded',()=>{
    setTimeout(()=>{ document.querySelectorAll('[data-w]').forEach(el=>{el.style.width=el.dataset.w+'%'}); },460);
});

// 1. Stacked Bar
new Chart(document.getElementById('chartStackedBar'),{type:'bar',data:{labels:MO_LABELS,datasets:[
    {label:'Savings',data:SAV_DATA,backgroundColor:'#0b2419cc',borderRadius:5,barPercentage:.75},
    {label:'Contributions',data:CTB_DATA,backgroundColor:'#a3e635cc',borderRadius:5,barPercentage:.75},
    {label:'Repayments',data:REP_DATA,backgroundColor:'#2563ebcc',borderRadius:5,barPercentage:.75},
]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{...TT,callbacks:{label:c=>' '+c.dataset.label+': KES '+c.parsed.y.toLocaleString()}}},scales:{x:{...XS,stacked:true},y:{...YS,stacked:true}}}});

// 2. Grouped Bar
new Chart(document.getElementById('chartGrouped'),{type:'bar',data:{labels:INC_LABELS,datasets:[
    {label:'Income',data:INC_DATA,backgroundColor:'#16a34acc',borderRadius:8,barPercentage:.72},
    {label:'Outflow',data:EXP_DATA,backgroundColor:'#dc2626cc',borderRadius:8,barPercentage:.72},
]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{...TT,callbacks:{label:c=>' '+c.dataset.label+': KES '+c.parsed.y.toLocaleString()}}},scales:{x:XS,y:YS}}});

// 3. Doughnut
new Chart(document.getElementById('chartDonut'),{type:'doughnut',data:{labels:['Savings','Shares','Loans','Wallet'],datasets:[{data:[TOTAL_SAV,TOTAL_SHR,LOANS_BAL,WAL_BAL],backgroundColor:['#0b2419','#a3e635','#dc2626','#2563eb'],borderWidth:0,hoverOffset:7}]},options:{cutout:'72%',responsive:false,plugins:{legend:{display:false},tooltip:{...TT,callbacks:{label:c=>' KES '+c.parsed.toLocaleString()}}}}});

// 4. Gauge
(function(){const hc=HEALTH>=80?'#16a34a':HEALTH>=60?'#2563eb':'#d97706';
new Chart(document.getElementById('chartGauge'),{type:'doughnut',data:{datasets:[{data:[HEALTH,100-HEALTH],backgroundColor:[hc,dark?'rgba(255,255,255,.07)':'rgba(11,36,25,.06)'],borderWidth:0,circumference:180,rotation:270}]},options:{responsive:false,cutout:'70%',plugins:{legend:{display:false},tooltip:{enabled:false}}}});})();

// 5. Radar
new Chart(document.getElementById('chartRadar'),{type:'radar',data:{labels:['Savings','Shares','Loan Health','Contributions','Welfare','Wallet'],datasets:[{label:'Score',data:RADAR_D,borderColor:'#a3e635',borderWidth:2,backgroundColor:'rgba(163,230,53,.12)',pointBackgroundColor:'#a3e635',pointBorderColor:SURF,pointBorderWidth:2,pointRadius:4}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{...TT,callbacks:{label:c=>' '+c.parsed.r.toFixed(0)+'%'}}},scales:{r:{grid:{color:GRID},ticks:{display:false},min:0,max:100,pointLabels:{color:TICK,font:{family:"'Plus Jakarta Sans',sans-serif",size:10,weight:'700'}}}}}});

<?php if(isset($_SESSION['success'])):?>typeof showToast!=='undefined'&&showToast(<?=json_encode($_SESSION['success'])?>, 'success');<?php unset($_SESSION['success']);endif;?>
<?php if(isset($_SESSION['error'])):?>typeof showToast!=='undefined'&&showToast(<?=json_encode($_SESSION['error'])?>,'error');<?php unset($_SESSION['error']);endif;?>
</script>
</body>
</html>