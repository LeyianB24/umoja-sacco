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

$member_id = (int) $_SESSION['member_id'];

/* ── Member basics ─────────────────────────────── */
$stmt = $conn->prepare("SELECT full_name, member_reg_no, created_at FROM members WHERE member_id=?");
$stmt->bind_param("i", $member_id); $stmt->execute();
$md = $stmt->get_result()->fetch_assoc(); $stmt->close();
$member_name = htmlspecialchars($md['full_name'] ?? 'Member');
$reg_no      = htmlspecialchars($md['member_reg_no'] ?? 'N/A');
$join_date   = date('M Y', strtotime($md['created_at'] ?? 'now'));
$_SESSION['reg_no'] = $reg_no;
$first_name  = htmlspecialchars(explode(' ', $member_name)[0]);

/* ── Engine balances ───────────────────────────── */
require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine       = new FinancialEngine($conn);
$balances     = $engine->getBalances($member_id);
$cur_bal      = (float)$balances['wallet'];
$total_savings= (float)$balances['savings'];
$total_shares = (float)$balances['shares'];
$active_loans = (float)$balances['loans'];
$net_worth    = $total_savings + $total_shares - $active_loans;
$loan_limit   = 500000;
$loan_pct     = $loan_limit > 0 ? min(100, ($active_loans / $loan_limit) * 100) : 0;

/* ── 12-month arrays ───────────────────────────── */
$mo_labels = $sav_arr = $ctb_arr = $rep_arr = $wdr_arr = [];
for ($i = 11; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("-$i months"));
    $me = date('Y-m-t',  strtotime("-$i months"));
    $mo_labels[] = date('M', strtotime($ms));

    $stmt = $conn->prepare("SELECT COALESCE(SUM(le.credit-le.debit),0) FROM ledger_entries le JOIN ledger_accounts la ON le.account_id=la.account_id WHERE la.member_id=? AND la.category='savings' AND le.created_at BETWEEN ? AND ?");
    $stmt->bind_param("iss", $member_id, $ms, $me); $stmt->execute();
    $sav_arr[] = (float)$stmt->get_result()->fetch_row()[0]; $stmt->close();

    foreach (['contribution'=>&$ctb_arr, 'loan_repayment'=>&$rep_arr, 'withdrawal'=>&$wdr_arr] as $type => &$arr) {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=? AND transaction_type=? AND created_at BETWEEN ? AND ?");
        $stmt->bind_param("isss", $member_id, $type, $ms, $me); $stmt->execute();
        $arr[] = (float)$stmt->get_result()->fetch_row()[0]; $stmt->close();
    }
}

/* ── 6-month income vs outflow ─────────────────── */
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

/* ── Daily last 30 days ────────────────────────── */
$day_labels = $day_in = $day_out = [];
for ($i = 29; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $day_labels[] = date('d/m', strtotime($day));

    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=? AND transaction_type IN('deposit','contribution') AND DATE(created_at)=?");
    $stmt->bind_param("is", $member_id, $day); $stmt->execute();
    $day_in[] = (float)$stmt->get_result()->fetch_row()[0]; $stmt->close();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=? AND transaction_type IN('withdrawal','loan_repayment') AND DATE(created_at)=?");
    $stmt->bind_param("is", $member_id, $day); $stmt->execute();
    $day_out[] = (float)$stmt->get_result()->fetch_row()[0]; $stmt->close();
}

/* ── Wallet running balance ────────────────────── */
$wal_labels = $wal_arr = [];
$running = 0;
for ($i = 11; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("-$i months"));
    $me = date('Y-m-t',  strtotime("-$i months"));
    $wal_labels[] = date('M y', strtotime($ms));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN transaction_type IN('deposit','contribution') THEN amount ELSE -amount END),0) FROM transactions WHERE member_id=? AND created_at BETWEEN ? AND ?");
    $stmt->bind_param("iss", $member_id, $ms, $me); $stmt->execute();
    $running += (float)$stmt->get_result()->fetch_row()[0]; $stmt->close();
    $wal_arr[] = max(0, $running);
}

/* ── Transaction type totals ───────────────────── */
$txn_type_labels = $txn_type_vals = [];
$stmt = $conn->prepare("SELECT transaction_type, COALESCE(SUM(amount),0) as total FROM transactions WHERE member_id=? GROUP BY transaction_type ORDER BY total DESC LIMIT 7");
$stmt->bind_param("i", $member_id); $stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $txn_type_labels[] = ucfirst(str_replace('_', ' ', $r['transaction_type']));
    $txn_type_vals[]   = (float)$r['total'];
}
$stmt->close();

/* ── Extra stats ───────────────────────────────── */
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

/* ── Recent transactions ───────────────────────── */
$recent_txn = [];
$stmt = $conn->prepare("SELECT transaction_type, amount, created_at, reference_no FROM transactions WHERE member_id=? ORDER BY created_at DESC LIMIT 8");
$stmt->bind_param("i",$member_id); $stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $recent_txn[] = $r;
$stmt->close();

/* ── Health score ──────────────────────────────── */
$health = max(0, round(100
    - min(30, ($loan_pct/100)*30)
    - ($month_contrib == 0 ? 15 : 0)
    - ($total_savings < 5000 ? 10 : 0)
    - ($pending_loans > 0 ? 5 : 0)
));

/* ── Radar dimensions ──────────────────────────── */
$radar = [
    min(100, ($total_savings  / 100000) * 100),
    min(100, ($total_shares   / 50000)  * 100),
    max(0,  100 - $loan_pct),
    min(100, ($month_contrib  / 5000)   * 100),
    min(100, ($welfare_total  / 10000)  * 100),
    min(100, ($cur_bal        / 20000)  * 100),
];

function ks(float $n): string {
    if ($n >= 1000000) return 'KES ' . number_format($n/1000000, 2) . 'M';
    if ($n >= 1000)    return 'KES ' . number_format($n/1000, 1) . 'K';
    return 'KES ' . number_format($n, 2);
}

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<script>(function(){var s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard · <?= $member_name ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ─── TOKENS ──────────────────────────────────── */
:root {
    --forest:   #1a3a2a; --forest-mid:#234d38; --forest-lt:#2e6347;
    --lime:     #a8e063; --lime-s:rgba(168,224,99,.13);
    --blue:     #4481eb; --blue-s:rgba(68,129,235,.12);
    --green:    #1aa053; --green-s:rgba(26,160,83,.12);
    --amber:    #f0a500; --amber-s:rgba(240,165,0,.11);
    --red:      #e63757; --red-s:rgba(230,55,87,.11);
    --teal:     #20c997; --teal-s:rgba(32,201,151,.11);
    --purple:   #7c4dff; --purple-s:rgba(124,77,255,.11);
    --bg:       #eef2f7;
    --surf:     #ffffff; --surf2:#f7fafc;
    --bdr:      #e0e8f0;
    --ink:      #1a2b4a; --muted:#6b7c93; --faint:#b0bec5;
    --r:16px; --r-sm:10px;
    --t:all .22s cubic-bezier(.4,0,.2,1);
    --mono:'JetBrains Mono',monospace;
    --font:'Plus Jakarta Sans',sans-serif;
    --shd:0 2px 16px rgba(26,42,74,.06);
    --shd-h:0 8px 32px rgba(26,42,74,.13);
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
.dash{padding:22px 22px 52px;}
@media(max-width:768px){.dash{padding:14px 12px 40px;}}

/* ─── HERO ────────────────────────────────────── */
.hero{background:linear-gradient(128deg,var(--forest) 0%,var(--forest-lt) 55%,#387a56 100%);
    border-radius:var(--r);padding:24px 30px;margin-bottom:20px;
    position:relative;overflow:hidden;color:#fff;}
.hero::before{content:'';position:absolute;top:-70px;right:-70px;width:300px;height:300px;
    border-radius:50%;background:radial-gradient(circle,rgba(168,224,99,.18) 0%,transparent 65%);pointer-events:none;}
.hero-ring{position:absolute;top:-90px;right:-90px;width:380px;height:380px;border-radius:50%;
    border:1.5px solid rgba(168,224,99,.1);pointer-events:none;}
.hero-ring2{position:absolute;top:-130px;right:-130px;width:490px;height:490px;border-radius:50%;
    border:1px solid rgba(168,224,99,.06);pointer-events:none;}
.hero-chip{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.1);
    border:1px solid rgba(255,255,255,.18);border-radius:100px;padding:3px 12px;
    font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--lime);margin-bottom:10px;}
.hero h1{font-size:1.55rem;font-weight:800;color:#fff;letter-spacing:-.4px;margin:0 0 5px;}
.hero-sub{font-size:.76rem;color:rgba(255,255,255,.55);margin:0 0 18px;}
.hero-sub strong{color:var(--lime);}
.hm-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;}
.hm{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);
    border-radius:var(--r-sm);padding:9px 15px;min-width:96px;transition:var(--t);}
.hm:hover{background:rgba(255,255,255,.18);transform:translateY(-2px);}
.hm-v{font-family:var(--mono);font-size:.88rem;font-weight:600;color:#fff;letter-spacing:-.4px;line-height:1.1;}
.hm-l{font-size:.55rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.45);margin-top:2px;}
.hero-btns{display:flex;gap:7px;flex-wrap:wrap;}
.hbtn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--r-sm);
    font-family:var(--font);font-size:.79rem;font-weight:800;text-decoration:none;
    transition:var(--t);border:none;cursor:pointer;}
.hbtn-lime{background:var(--lime);color:var(--forest);box-shadow:0 4px 14px rgba(168,224,99,.4);}
.hbtn-lime:hover{background:#baea78;color:var(--forest);transform:translateY(-1px);}
.hbtn-ghost{background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.22);}
.hbtn-ghost:hover{background:rgba(255,255,255,.2);color:#fff;}

/* ─── SPARKLINE ROW ───────────────────────────── */
.spk-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;}
@media(max-width:767px){.spk-row{grid-template-columns:repeat(2,1fr);}}
.spk-panel{background:var(--surf);border:1px solid var(--bdr);border-radius:var(--r-sm);
    padding:14px 16px 10px;position:relative;overflow:hidden;box-shadow:var(--shd);transition:var(--t);}
.spk-panel:hover{box-shadow:var(--shd-h);transform:translateY(-2px);}
.spk-panel::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3.5px;border-radius:3px 0 0 3px;}
.spk-g::before{background:var(--green);}
.spk-b::before{background:var(--blue);}
.spk-a::before{background:var(--amber);}
.spk-r::before{background:var(--red);}
.spk-lbl{font-size:.57rem;font-weight:800;text-transform:uppercase;letter-spacing:.9px;color:var(--muted);margin-bottom:3px;}
.spk-val{font-family:var(--mono);font-size:1.05rem;font-weight:600;color:var(--ink);letter-spacing:-.5px;line-height:1.1;}
.spk-chg{font-size:.62rem;font-weight:700;margin-top:3px;}
.spk-chg.up{color:var(--green);} .spk-chg.dn{color:var(--red);}
.spk-canvas-wrap{height:38px;margin-top:8px;position:relative;}

/* ─── GRAPH PANELS ────────────────────────────── */
.gp{background:var(--surf);border:1px solid var(--bdr);border-radius:var(--r);
    padding:20px 22px;height:100%;box-shadow:var(--shd);transition:var(--t);
    display:flex;flex-direction:column;}
.gp:hover{box-shadow:var(--shd-h);}
.gp-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;gap:8px;flex-wrap:wrap;}
.gp-title{font-size:.86rem;font-weight:800;color:var(--ink);margin:0 0 2px;}
.gp-sub{font-size:.62rem;font-weight:600;color:var(--muted);}
.gp-tabs{display:flex;gap:3px;background:var(--surf2);border-radius:8px;padding:3px;}
.gp-tab{padding:4px 11px;border-radius:6px;font-size:.7rem;font-weight:700;
    color:var(--muted);cursor:pointer;transition:var(--t);border:none;background:none;font-family:var(--font);}
.gp-tab.active{background:var(--surf);color:var(--ink);box-shadow:0 1px 6px rgba(0,0,0,.08);}
.gp-stat-row{display:flex;gap:14px;margin-bottom:12px;flex-wrap:wrap;}
.gp-stat{display:flex;flex-direction:column;gap:1px;}
.gp-sv{font-family:var(--mono);font-size:.88rem;font-weight:600;color:var(--ink);letter-spacing:-.4px;}
.gp-sl{font-size:.57rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);}
.gp-divider{width:1px;height:30px;background:var(--bdr);align-self:center;}
.chart-box{position:relative;flex:1;min-height:0;}
.leg{display:flex;gap:12px;flex-wrap:wrap;margin-top:10px;}
.leg-i{display:flex;align-items:center;gap:5px;font-size:.66rem;font-weight:600;color:var(--muted);}
.leg-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}

/* ─── HEATMAP ─────────────────────────────────── */
.hmap-labels{display:grid;grid-template-columns:repeat(12,1fr);gap:4px;margin-bottom:4px;}
.hmap-lbl{font-size:.54rem;font-weight:700;color:var(--muted);text-align:center;}
.hmap-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:4px;}
.hmap-cell{height:26px;border-radius:5px;transition:var(--t);cursor:default;position:relative;}
.hmap-cell:hover{transform:scale(1.12);z-index:5;box-shadow:0 4px 12px rgba(26,58,42,.2);}
.hmap-scale{display:flex;align-items:center;gap:6px;margin-top:10px;font-size:.58rem;font-weight:600;color:var(--muted);}
.hmap-scale-cells{display:flex;gap:3px;}

/* ─── TXN FEED ────────────────────────────────── */
.txn-feed{list-style:none;margin:0;padding:0;flex:1;overflow:auto;}
.txn-item{display:flex;align-items:center;justify-content:space-between;
    padding:9px 0;border-bottom:1px solid var(--bdr);gap:8px;}
.txn-item:last-child{border-bottom:none;}
.txn-ico{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;}
.ti-in{background:rgba(26,160,83,.1);color:var(--green);}
.ti-out{background:rgba(230,55,87,.1);color:var(--red);}
.ti-loan{background:rgba(240,165,0,.1);color:var(--amber);}
.txn-name{font-size:.78rem;font-weight:700;color:var(--ink);text-transform:capitalize;margin-bottom:1px;}
.txn-date{font-size:.61rem;font-weight:600;color:var(--faint);}
.t-in{font-family:var(--mono);font-size:.78rem;font-weight:600;color:var(--green);}
.t-out{font-family:var(--mono);font-size:.78rem;font-weight:600;color:var(--red);}
.txn-ref{font-size:.55rem;color:var(--muted);background:var(--surf2);padding:1px 6px;
    border-radius:5px;margin-top:2px;display:block;text-align:right;}
.btn-all{display:flex;align-items:center;justify-content:center;gap:5px;width:100%;
    padding:9px;border-radius:var(--r-sm);background:var(--surf2);border:1.5px solid var(--bdr);
    font-family:var(--font);font-size:.73rem;font-weight:700;color:var(--muted);
    text-decoration:none;margin-top:10px;transition:var(--t);}
.btn-all:hover{border-color:var(--lime);color:var(--forest);background:var(--lime-s);}

/* ─── HEALTH FACTORS ──────────────────────────── */
.hf{display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--bdr);}
.hf:last-child{border:none;}
.hf-ico{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0;}
.hf-ok{background:rgba(26,160,83,.12);color:var(--green);}
.hf-fail{background:rgba(230,55,87,.1);color:var(--red);}
.hf-lbl{font-size:.73rem;font-weight:700;color:var(--ink);flex:1;}
.hf-val{font-size:.64rem;font-weight:600;color:var(--muted);}

/* ─── GAUGE ───────────────────────────────────── */
.gauge-wrap{position:relative;width:180px;height:100px;margin:4px auto 10px;overflow:hidden;}

/* ─── RECEIPT MODAL ───────────────────────────── */
.rmodal .modal-content{border:none;border-radius:var(--r);box-shadow:0 28px 60px rgba(0,0,0,.2);overflow:hidden;}
.rh{background:linear-gradient(135deg,var(--forest),var(--forest-lt));color:#fff;padding:32px 26px 24px;text-align:center;position:relative;}
.rh::before{content:'';position:absolute;top:-40px;right:-40px;width:150px;height:150px;border-radius:50%;
    background:radial-gradient(circle,rgba(168,224,99,.12) 0%,transparent 65%);}
.rh-ico{width:60px;height:60px;border-radius:16px;background:rgba(168,224,99,.15);
    border:1px solid rgba(168,224,99,.25);display:flex;align-items:center;justify-content:center;
    color:var(--lime);font-size:1.5rem;margin:0 auto 12px;position:relative;z-index:1;}
.rh h4{font-weight:800;margin:0 0 4px;font-size:1rem;position:relative;z-index:1;}
.rh p{opacity:.5;font-size:.75rem;margin:0;position:relative;z-index:1;}
.rb{padding:24px;background:var(--surf);}
.r-amt{font-family:var(--mono);font-size:1.9rem;font-weight:600;color:var(--green);letter-spacing:-.5px;margin-bottom:4px;}
.r-stamp{display:inline-block;border:2.5px solid var(--green);color:var(--green);padding:2px 12px;
    border-radius:7px;font-weight:800;font-size:.68rem;text-transform:uppercase;
    letter-spacing:1px;transform:rotate(-10deg);margin-top:5px;}
.r-div{border:none;border-top:2px dashed var(--bdr);margin:16px 0;}
.r-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;font-size:.8rem;}
.r-lbl{color:var(--muted);font-weight:600;} .r-val{font-weight:800;color:var(--ink);}
.btn-rc{width:100%;padding:11px;border-radius:var(--r-sm);background:linear-gradient(135deg,var(--forest),var(--forest-lt));
    color:#fff;border:none;font-family:var(--font);font-size:.85rem;font-weight:800;
    cursor:pointer;transition:var(--t);margin-top:16px;}
.btn-rc:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(26,58,42,.3);}

/* ─── ANIMATIONS ──────────────────────────────── */
@keyframes fadeUp{from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:none;}}
.a1{animation:fadeUp .38s .04s both;} .a2{animation:fadeUp .38s .10s both;}
.a3{animation:fadeUp .38s .16s both;} .a4{animation:fadeUp .38s .22s both;}
.a5{animation:fadeUp .38s .28s both;} .a6{animation:fadeUp .38s .34s both;}
.a7{animation:fadeUp .38s .40s both;} .a8{animation:fadeUp .38s .46s both;}
</style>
</head>
<body>
<div class="d-flex">
<?php $layout->sidebar(); ?>
<div class="flex-fill main-content-wrapper">
<?php $layout->topbar($pageTitle ?? ''); ?>
<div class="dash">

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<div class="hero a1">
    <div class="hero-ring"></div><div class="hero-ring2"></div>
    <div class="row align-items-center gy-3">
        <div class="col-lg-8">
            <div class="hero-chip"><i class="bi bi-shield-check-fill"></i> Verified Member</div>
            <h1>Good day, <?= $first_name ?>! 👋</h1>
            <p class="hero-sub">
                Member <strong><?= $reg_no ?></strong> &nbsp;·&nbsp;
                Since <strong><?= $join_date ?></strong> &nbsp;·&nbsp;
                Health Score <strong style="color:#fff"><?= $health ?>/100</strong>
            </p>
            <div class="hm-row">
                <div class="hm"><div class="hm-v"><?= ks($total_savings) ?></div><div class="hm-l">Savings</div></div>
                <div class="hm"><div class="hm-v"><?= ks($total_shares) ?></div><div class="hm-l">Shares</div></div>
                <div class="hm"><div class="hm-v" style="color:<?= $active_loans>0 ? '#f87171' : '#a8e063' ?>"><?= ks($active_loans) ?></div><div class="hm-l">Loans</div></div>
                <div class="hm"><div class="hm-v" style="color:<?= $net_worth>=0 ? '#a8e063' : '#f87171' ?>"><?= ks(abs($net_worth)) ?></div><div class="hm-l">Net Worth</div></div>
                <div class="hm"><div class="hm-v"><?= ks($cur_bal) ?></div><div class="hm-l">Wallet</div></div>
            </div>
            <div class="hero-btns">
                <a href="mpesa_request.php" class="hbtn hbtn-lime"><i class="bi bi-plus-lg"></i> Deposit</a>
                <?php if ($cur_bal > 0): ?><a href="withdraw.php" class="hbtn hbtn-ghost"><i class="bi bi-arrow-up-right"></i> Withdraw</a><?php endif; ?>
                <a href="loans.php" class="hbtn hbtn-ghost"><i class="bi bi-bank"></i> Apply Loan</a>
                <a href="transactions.php" class="hbtn hbtn-ghost"><i class="bi bi-list-ul"></i> Transactions</a>
            </div>
        </div>
        <div class="col-lg-4 d-none d-lg-block text-end" style="position:relative;z-index:2;">
            <div style="font-size:.58rem;font-weight:700;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:1px;">Credit Grade</div>
            <div style="font-family:var(--mono);font-size:3.8rem;font-weight:600;color:#fff;letter-spacing:-3px;line-height:1;">
                <?= $loan_pct<30?'AAA':($loan_pct<50?'AA+':($loan_pct<70?'A+':'B+')) ?>
            </div>
            <div style="font-size:.6rem;color:rgba(255,255,255,.4);margin-bottom:10px;">Loan utilization grade</div>
            <div style="background:rgba(255,255,255,.1);border-radius:8px;padding:8px 14px;display:inline-block;min-width:150px;">
                <div style="font-size:.58rem;color:rgba(255,255,255,.45);font-weight:700;text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px;">Loan Limit Used</div>
                <div style="background:rgba(255,255,255,.15);height:6px;border-radius:100px;overflow:hidden;">
                    <div style="height:100%;width:<?= $loan_pct ?>%;background:var(--lime);border-radius:100px;"></div>
                </div>
                <div style="font-size:.6rem;color:rgba(255,255,255,.5);margin-top:4px;"><?= number_format($loan_pct, 1) ?>% of KES 500K</div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     SPARKLINE ROW
══════════════════════════════════════ -->
<div class="spk-row a2">
    <div class="spk-panel spk-g">
        <div class="spk-lbl">Total Savings</div>
        <div class="spk-val"><?= ks($total_savings) ?></div>
        <div class="spk-chg up"><i class="bi bi-arrow-up-short"></i> Active account</div>
        <div class="spk-canvas-wrap"><canvas id="spk1"></canvas></div>
    </div>
    <div class="spk-panel spk-b">
        <div class="spk-lbl">Monthly Contributions</div>
        <div class="spk-val"><?= ks($month_contrib) ?></div>
        <div class="spk-chg <?= $month_contrib > 0 ? 'up' : 'dn' ?>">
            <i class="bi bi-<?= $month_contrib > 0 ? 'arrow-up' : 'exclamation' ?>-short"></i>
            <?= $month_contrib > 0 ? 'This month' : 'Not yet made' ?>
        </div>
        <div class="spk-canvas-wrap"><canvas id="spk2"></canvas></div>
    </div>
    <div class="spk-panel spk-a">
        <div class="spk-lbl">Total Deposits</div>
        <div class="spk-val"><?= ks($total_deposits) ?></div>
        <div class="spk-chg up"><i class="bi bi-arrow-up-short"></i> All time</div>
        <div class="spk-canvas-wrap"><canvas id="spk3"></canvas></div>
    </div>
    <div class="spk-panel spk-r">
        <div class="spk-lbl">Total Withdrawals</div>
        <div class="spk-val"><?= ks($total_withdrawals) ?></div>
        <div class="spk-chg dn"><i class="bi bi-arrow-down-short"></i> All time</div>
        <div class="spk-canvas-wrap"><canvas id="spk4"></canvas></div>
    </div>
</div>

<!-- ══════════════════════════════════════
     ROW 1: Stacked Bar + Area Line
══════════════════════════════════════ -->
<div class="row g-3 mb-3 a3">
    <div class="col-xl-7">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">12-Month Financial Activity</div>
                    <div class="gp-sub">Savings · Contributions · Repayments — stacked bar</div>
                </div>
                <div class="gp-stat-row" style="margin-bottom:0;">
                    <div class="gp-stat"><div class="gp-sv"><?= ks($total_savings) ?></div><div class="gp-sl">Net Savings</div></div>
                    <div class="gp-divider"></div>
                    <div class="gp-stat"><div class="gp-sv"><?= ks(array_sum($ctb_arr)) ?></div><div class="gp-sl">Total Contrib</div></div>
                </div>
            </div>
            <div class="leg">
                <div class="leg-i"><span class="leg-dot" style="background:#1a3a2a"></span>Savings</div>
                <div class="leg-i"><span class="leg-dot" style="background:#a8e063"></span>Contributions</div>
                <div class="leg-i"><span class="leg-dot" style="background:#4481eb"></span>Repayments</div>
            </div>
            <div class="chart-box" style="height:260px;margin-top:10px;">
                <canvas id="chartStackedBar"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Wallet Balance Trajectory</div>
                    <div class="gp-sub">Cumulative running balance — area chart</div>
                </div>
            </div>
            <div class="gp-stat-row">
                <div class="gp-stat"><div class="gp-sv"><?= ks($cur_bal) ?></div><div class="gp-sl">Current</div></div>
                <div class="gp-divider"></div>
                <div class="gp-stat"><div class="gp-sv"><?= ks(max($wal_arr ?: [0])) ?></div><div class="gp-sl">Peak</div></div>
            </div>
            <div class="chart-box" style="height:240px;">
                <canvas id="chartWalletArea"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     ROW 2: Grouped Bar + Doughnut + Gauge
══════════════════════════════════════ -->
<div class="row g-3 mb-3 a4">
    <div class="col-xl-5">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Income vs Outflow</div>
                    <div class="gp-sub">Deposits &amp; withdrawals — grouped bar</div>
                </div>
            </div>
            <div class="leg" style="margin-bottom:8px;">
                <div class="leg-i"><span class="leg-dot" style="background:#1aa053"></span>Income</div>
                <div class="leg-i"><span class="leg-dot" style="background:#e63757"></span>Outflow</div>
            </div>
            <div class="chart-box" style="height:240px;">
                <canvas id="chartGrouped"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Portfolio Composition</div>
                    <div class="gp-sub">Asset vs liability — doughnut chart</div>
                </div>
            </div>
            <div style="position:relative;width:160px;height:160px;margin:8px auto 14px;">
                <canvas id="chartDonut" width="160" height="160"></canvas>
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;pointer-events:none;">
                    <div style="font-family:var(--mono);font-size:.9rem;font-weight:600;color:var(--ink);letter-spacing:-.4px;line-height:1.1;"><?= ks($total_savings + $total_shares) ?></div>
                    <div style="font-size:.54rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-top:2px;">Total Assets</div>
                </div>
            </div>
            <?php foreach ([['Savings','#1a3a2a',$total_savings],['Shares','#a8e063',$total_shares],['Loans','#e63757',$active_loans],['Wallet','#4481eb',$cur_bal]] as $d): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--bdr);font-size:.72rem;">
                <span style="display:flex;align-items:center;gap:7px;font-weight:600;color:var(--muted);">
                    <span style="width:8px;height:8px;border-radius:50%;background:<?= $d[1] ?>;flex-shrink:0;"></span><?= $d[0] ?>
                </span>
                <span style="font-family:var(--mono);font-weight:600;color:var(--ink);font-size:.7rem;"><?= ks($d[2]) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Account Health</div>
                    <div class="gp-sub">Composite score — gauge</div>
                </div>
            </div>
            <div class="gauge-wrap">
                <canvas id="chartGauge" width="180" height="180" style="margin-top:-45px;"></canvas>
            </div>
            <div style="text-align:center;margin-bottom:14px;">
                <div style="font-family:var(--mono);font-size:2rem;font-weight:600;color:var(--ink);letter-spacing:-1px;"><?= $health ?></div>
                <?php $hc = $health>=80?'#1aa053':($health>=60?'#4481eb':'#f0a500'); ?>
                <div style="display:inline-flex;align-items:center;gap:4px;background:<?= $hc ?>22;color:<?= $hc ?>;border-radius:100px;padding:3px 12px;font-size:.68rem;font-weight:800;">
                    <?= $health>=80?'Excellent':($health>=60?'Good':'Fair') ?>
                </div>
            </div>
            <?php foreach ([
                ['Loan Utilization',  $loan_pct<50,       'bi-bank',             number_format($loan_pct,0).'% used'],
                ['Monthly Contrib',   $month_contrib>0,   'bi-calendar-check-fill', ks($month_contrib)],
                ['Savings Balance',   $total_savings>=5000,'bi-piggy-bank-fill', ks($total_savings)],
                ['Welfare Active',    $welfare_total>0,   'bi-heart-pulse-fill', ks($welfare_total)],
            ] as $hf): ?>
            <div class="hf">
                <div class="hf-ico <?= $hf[1]?'hf-ok':'hf-fail' ?>"><i class="bi <?= $hf[2] ?>"></i></div>
                <div class="hf-lbl"><?= $hf[0] ?></div>
                <div class="hf-val"><?= $hf[3] ?></div>
                <i class="bi bi-<?= $hf[1]?'check-circle-fill':'x-circle-fill' ?>" style="font-size:.8rem;color:<?= $hf[1]?'#1aa053':'#e63757' ?>"></i>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     ROW 3: Daily Line + Radar + Polar
══════════════════════════════════════ -->
<div class="row g-3 mb-3 a5">
    <div class="col-xl-6">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Daily Cash Flow — Last 30 Days</div>
                    <div class="gp-sub">Inflows vs outflows per day — dual line chart</div>
                </div>
                <div class="gp-stat-row" style="margin-bottom:0;">
                    <div class="gp-stat"><div class="gp-sv"><?= ks(array_sum($day_in)) ?></div><div class="gp-sl">30-day In</div></div>
                    <div class="gp-divider"></div>
                    <div class="gp-stat"><div class="gp-sv"><?= ks(array_sum($day_out)) ?></div><div class="gp-sl">30-day Out</div></div>
                </div>
            </div>
            <div class="leg" style="margin-bottom:8px;">
                <div class="leg-i"><span class="leg-dot" style="background:#1aa053"></span>Inflow</div>
                <div class="leg-i"><span class="leg-dot" style="background:#e63757"></span>Outflow</div>
            </div>
            <div class="chart-box" style="height:240px;">
                <canvas id="chartDailyLine"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Financial Radar</div>
                    <div class="gp-sub">Multi-dimension performance</div>
                </div>
            </div>
            <div class="chart-box" style="height:270px;">
                <canvas id="chartRadar"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Transaction Types</div>
                    <div class="gp-sub">Volume by category — polar area</div>
                </div>
            </div>
            <div class="chart-box" style="height:200px;">
                <canvas id="chartPolar"></canvas>
            </div>
            <div class="leg" style="margin-top:8px;flex-wrap:wrap;">
                <?php
                $pc = ['#1a3a2a','#a8e063','#4481eb','#e63757','#20c997','#f0a500','#7c4dff'];
                foreach ($txn_type_labels as $i => $lbl): ?>
                <div class="leg-i"><span class="leg-dot" style="background:<?= $pc[$i % count($pc)] ?>"></span><?= $lbl ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     ROW 4: Heatmap + Waterfall + Txn Feed
══════════════════════════════════════ -->
<div class="row g-3 mb-3 a6">
    <div class="col-xl-5">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Monthly Activity Heatmap</div>
                    <div class="gp-sub">Transaction volume intensity — last 12 months</div>
                </div>
            </div>
            <div class="hmap-labels" id="hmapLabels"></div>
            <div class="hmap-grid" id="hmapGrid"></div>
            <div class="hmap-scale">
                <span>Low</span>
                <div class="hmap-scale-cells" id="hmapScale"></div>
                <span>High</span>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Net Position Waterfall</div>
                    <div class="gp-sub">How your net worth is constructed</div>
                </div>
            </div>
            <div class="chart-box" style="height:270px;">
                <canvas id="chartWaterfall"></canvas>
            </div>
            <div style="text-align:center;font-size:.65rem;font-weight:600;color:var(--muted);margin-top:8px;">
                Net Worth: <strong style="color:<?= $net_worth>=0 ? '#1aa053' : '#e63757' ?>"><?= ks($net_worth) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Recent Transactions</div>
                    <div class="gp-sub">Latest 8 entries</div>
                </div>
            </div>
            <?php if (empty($recent_txn)): ?>
            <div style="text-align:center;padding:40px 0;color:var(--faint);">
                <i class="bi bi-inbox" style="font-size:2rem;display:block;opacity:.3;margin-bottom:8px;"></i>
                <p style="font-size:.75rem;font-weight:600;margin:0;">No transactions yet</p>
            </div>
            <?php else: ?>
            <ul class="txn-feed">
                <?php foreach ($recent_txn as $t):
                    $is_in  = in_array($t['transaction_type'], ['deposit','income','contribution','revenue_inflow']);
                    $is_loan= str_contains($t['transaction_type'], 'loan');
                    $ic     = $is_loan ? 'ti-loan' : ($is_in ? 'ti-in' : 'ti-out');
                    $ib     = $is_loan ? 'bi-bank'  : ($is_in ? 'bi-arrow-down-left' : 'bi-arrow-up-right');
                ?>
                <li class="txn-item">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="txn-ico <?= $ic ?>"><i class="bi <?= $ib ?>"></i></div>
                        <div>
                            <div class="txn-name"><?= htmlspecialchars($t['transaction_type']) ?></div>
                            <div class="txn-date"><?= date('d M, h:i A', strtotime($t['created_at'])) ?></div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div class="<?= $is_in ? 't-in' : 't-out' ?>"><?= $is_in ? '+' : '-' ?><?= number_format((float)$t['amount'], 2) ?></div>
                        <span class="txn-ref"><?= strtoupper(htmlspecialchars($t['reference_no'])) ?></span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <a href="transactions.php" class="btn-all"><i class="bi bi-list-ul"></i> View All</a>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     ROW 5: Bubble + Horizontal Bar
══════════════════════════════════════ -->
<div class="row g-3 a7">
    <div class="col-xl-7">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Savings vs Contributions — Bubble Chart</div>
                    <div class="gp-sub">Each bubble = 1 month · X=contributions · Y=savings · Size=repayment</div>
                </div>
            </div>
            <div class="chart-box" style="height:270px;">
                <canvas id="chartBubble"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="gp">
            <div class="gp-head">
                <div>
                    <div class="gp-title">Transaction Totals by Type</div>
                    <div class="gp-sub">Cumulative KES per category — horizontal bar</div>
                </div>
            </div>
            <div class="chart-box" style="height:270px;">
                <canvas id="chartHBar"></canvas>
            </div>
        </div>
    </div>
</div>

</div><!-- /dash -->

<!-- RECEIPT MODAL -->
<div class="modal fade rmodal" id="receiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:390px;">
        <div class="modal-content">
            <div class="rh">
                <div class="rh-ico"><i class="bi bi-check-lg"></i></div>
                <h4>Transaction Successful</h4><p>Receipt generated automatically</p>
            </div>
            <div class="rb">
                <div style="text-align:center;margin-bottom:6px;">
                    <div class="r-amt" id="receiptAmount">KES 0.00</div>
                    <div class="r-stamp">Verified</div>
                </div>
                <hr class="r-div">
                <div class="r-row"><span class="r-lbl">Receipt No.</span><span class="r-val" id="receiptNo">—</span></div>
                <div class="r-row"><span class="r-lbl">Account</span><span class="r-val" id="receiptAccount">—</span></div>
                <div class="r-row"><span class="r-lbl">Reference</span><span class="r-val" id="receiptRef">—</span></div>
                <div class="r-row"><span class="r-lbl">Date &amp; Time</span><span class="r-val" id="receiptDate"><?= date('d M, Y H:i:s') ?></span></div>
                <hr class="r-div">
                <button type="button" class="btn-rc" data-bs-dismiss="modal">Close Receipt</button>
            </div>
        </div>
    </div>
</div>

<?php $layout->footer(); ?>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
/* ── PHP → JS DATA ──────────────────────────── */
const MO_LABELS = <?= json_encode($mo_labels) ?>;
const SAV_DATA  = <?= json_encode($sav_arr) ?>;
const CTB_DATA  = <?= json_encode($ctb_arr) ?>;
const REP_DATA  = <?= json_encode($rep_arr) ?>;
const WDR_DATA  = <?= json_encode($wdr_arr) ?>;
const WAL_LABELS= <?= json_encode($wal_labels) ?>;
const WAL_DATA  = <?= json_encode($wal_arr) ?>;
const INC_LABELS= <?= json_encode($inc_labels) ?>;
const INC_DATA  = <?= json_encode($inc_arr) ?>;
const EXP_DATA  = <?= json_encode($exp_arr) ?>;
const DAY_LABELS= <?= json_encode($day_labels) ?>;
const DAY_IN    = <?= json_encode($day_in) ?>;
const DAY_OUT   = <?= json_encode($day_out) ?>;
const TXN_LBLS  = <?= json_encode($txn_type_labels) ?>;
const TXN_VALS  = <?= json_encode($txn_type_vals) ?>;
const RADAR_D   = <?= json_encode($radar) ?>;

const TOTAL_SAV = <?= $total_savings ?>;
const TOTAL_SHR = <?= $total_shares ?>;
const LOANS_BAL = <?= $active_loans ?>;
const WAL_BAL   = <?= $cur_bal ?>;
const TOT_DEP   = <?= $total_deposits ?>;
const TOT_WDR   = <?= $total_withdrawals ?>;
const NET_WORTH = <?= $net_worth ?>;
const HEALTH    = <?= $health ?>;

/* ── THEME ──────────────────────────────────── */
const dark   = document.documentElement.getAttribute('data-bs-theme') === 'dark';
const GRID   = dark ? 'rgba(255,255,255,.05)' : 'rgba(26,42,74,.05)';
const TICK   = dark ? '#3a5a76' : '#8a9fb0';
const SURF   = dark ? '#0f1e2d' : '#ffffff';

/* ── SHARED TOOLTIP STYLE ───────────────────── */
const TT = {
    backgroundColor: dark ? '#0f1e2d' : '#1a3a2a',
    titleColor: '#a8e063', bodyColor: '#fff',
    padding: 12, cornerRadius: 10,
    borderColor: 'rgba(168,224,99,.2)', borderWidth: 1,
    titleFont: { family:"'Plus Jakarta Sans',sans-serif", weight:'800', size:12 },
    bodyFont:  { family:"'JetBrains Mono',monospace", size:11 },
};

/* ── SHARED SCALE DEFAULTS ──────────────────── */
const XSCALE = { grid:{ display:false }, ticks:{ color:TICK, font:{ family:"'Plus Jakarta Sans',sans-serif", size:10 } } };
const YSCALE = { grid:{ color:GRID },    ticks:{ color:TICK, font:{ family:"'Plus Jakarta Sans',sans-serif", size:10 } } };

/* ══════════════════════════════════════════════
   1. SPARKLINES
══════════════════════════════════════════════ */
function sparkline(id, data, color) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    canvas.width  = canvas.parentElement.offsetWidth || 200;
    canvas.height = 38;
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: MO_LABELS.slice(-data.length),
            datasets: [{ data, borderColor: color, borderWidth: 2,
                fill: true, backgroundColor: color + '28',
                tension: .5, pointRadius: 0 }]
        },
        options: {
            responsive: false, animation: { duration: 900 },
            plugins: { legend:{ display:false }, tooltip:{ enabled:false } },
            scales: { x:{ display:false }, y:{ display:false } }
        }
    });
}
sparkline('spk1', SAV_DATA.slice(-6), '#1aa053');
sparkline('spk2', CTB_DATA.slice(-6), '#4481eb');
sparkline('spk3', INC_DATA,           '#f0a500');
sparkline('spk4', EXP_DATA,           '#e63757');

/* ══════════════════════════════════════════════
   2. STACKED BAR — 12-month activity
══════════════════════════════════════════════ */
new Chart(document.getElementById('chartStackedBar'), {
    type: 'bar',
    data: {
        labels: MO_LABELS,
        datasets: [
            { label:'Savings',       data:SAV_DATA, backgroundColor:'#1a3a2acc', borderRadius:5, barPercentage:.75 },
            { label:'Contributions', data:CTB_DATA, backgroundColor:'#a8e063cc', borderRadius:5, barPercentage:.75 },
            { label:'Repayments',    data:REP_DATA, backgroundColor:'#4481ebcc', borderRadius:5, barPercentage:.75 },
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend:{ display:false }, tooltip:{ ...TT,
            callbacks: { label: c => ' ' + c.dataset.label + ': KES ' + c.parsed.y.toLocaleString() }
        }},
        scales: { x:{ ...XSCALE, stacked:true }, y:{ ...YSCALE, stacked:true } }
    }
});

/* ══════════════════════════════════════════════
   3. AREA LINE — Wallet running balance
══════════════════════════════════════════════ */
(function() {
    const ctx = document.getElementById('chartWalletArea').getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 0, 240);
    g.addColorStop(0, '#1a3a2a55'); g.addColorStop(1, '#1a3a2a00');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: WAL_LABELS,
            datasets: [{ label:'Balance', data:WAL_DATA,
                borderColor:'#1a3a2a', borderWidth:2.5,
                backgroundColor:g, fill:true, tension:.45,
                pointRadius:4, pointBackgroundColor:'#1a3a2a',
                pointBorderColor:SURF, pointBorderWidth:2
            }]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{ display:false }, tooltip:{ ...TT,
                callbacks:{ label:c=>' KES '+c.parsed.y.toLocaleString() }
            }},
            scales:{ x:XSCALE, y:YSCALE }
        }
    });
})();

/* ══════════════════════════════════════════════
   4. GROUPED BAR — Income vs Outflow 6mo
══════════════════════════════════════════════ */
new Chart(document.getElementById('chartGrouped'), {
    type: 'bar',
    data: {
        labels: INC_LABELS,
        datasets: [
            { label:'Income',  data:INC_DATA, backgroundColor:'#1aa053cc', borderRadius:7, barPercentage:.7 },
            { label:'Outflow', data:EXP_DATA, backgroundColor:'#e63757cc', borderRadius:7, barPercentage:.7 },
        ]
    },
    options: { responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ display:false }, tooltip:TT },
        scales:{ x:XSCALE, y:YSCALE }
    }
});

/* ══════════════════════════════════════════════
   5. DOUGHNUT — Portfolio breakdown
══════════════════════════════════════════════ */
new Chart(document.getElementById('chartDonut'), {
    type: 'doughnut',
    data: {
        labels: ['Savings','Shares','Loans','Wallet'],
        datasets: [{ data:[TOTAL_SAV, TOTAL_SHR, LOANS_BAL, WAL_BAL],
            backgroundColor:['#1a3a2a','#a8e063','#e63757','#4481eb'],
            borderWidth:0, hoverOffset:7
        }]
    },
    options: { cutout:'72%', responsive:false,
        plugins:{ legend:{ display:false }, tooltip:{ ...TT,
            callbacks:{ label:c=>' KES '+c.parsed.toLocaleString() }
        }}
    }
});

/* ══════════════════════════════════════════════
   6. GAUGE — Account health (half doughnut)
══════════════════════════════════════════════ */
(function() {
    const hc = HEALTH >= 80 ? '#1aa053' : HEALTH >= 60 ? '#4481eb' : '#f0a500';
    new Chart(document.getElementById('chartGauge'), {
        type: 'doughnut',
        data: { datasets:[{
            data:[HEALTH, 100 - HEALTH],
            backgroundColor:[hc, dark ? 'rgba(255,255,255,.07)' : 'rgba(26,58,42,.06)'],
            borderWidth:0, circumference:180, rotation:270
        }]},
        options: { responsive:false, cutout:'70%',
            plugins:{ legend:{ display:false }, tooltip:{ enabled:false } }
        }
    });
})();

/* ══════════════════════════════════════════════
   7. DUAL LINE — Daily Cash Flow 30 days
══════════════════════════════════════════════ */
(function() {
    const ctx = document.getElementById('chartDailyLine').getContext('2d');
    const gi = ctx.createLinearGradient(0, 0, 0, 240);
    gi.addColorStop(0, '#1aa05344'); gi.addColorStop(1, '#1aa05300');
    const go = ctx.createLinearGradient(0, 0, 0, 240);
    go.addColorStop(0, '#e6375744'); go.addColorStop(1, '#e6375700');

    // Sample every 3rd day to reduce clutter
    const step = 3;
    const labels = DAY_LABELS.filter((_,i) => i % step === 0);
    const din    = DAY_IN.filter((_,i)    => i % step === 0);
    const dout   = DAY_OUT.filter((_,i)   => i % step === 0);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label:'Inflow',  data:din,  borderColor:'#1aa053', borderWidth:2.5,
                  backgroundColor:gi, fill:true, tension:.4,
                  pointRadius:3, pointBackgroundColor:'#1aa053', pointBorderColor:SURF, pointBorderWidth:1.5 },
                { label:'Outflow', data:dout, borderColor:'#e63757', borderWidth:2.5,
                  backgroundColor:go, fill:true, tension:.4,
                  pointRadius:3, pointBackgroundColor:'#e63757', pointBorderColor:SURF, pointBorderWidth:1.5 }
            ]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{ display:false }, tooltip:{ ...TT,
                callbacks:{ label:c=>' '+c.dataset.label+': KES '+c.parsed.y.toLocaleString() }
            }},
            scales:{ x:XSCALE, y:YSCALE }
        }
    });
})();

/* ══════════════════════════════════════════════
   8. RADAR — Financial dimensions
══════════════════════════════════════════════ */
new Chart(document.getElementById('chartRadar'), {
    type: 'radar',
    data: {
        labels: ['Savings','Shares','Loan Health','Contributions','Welfare','Wallet'],
        datasets: [{ label:'Score',
            data: RADAR_D,
            borderColor: '#a8e063', borderWidth: 2,
            backgroundColor: 'rgba(168,224,99,.12)',
            pointBackgroundColor: '#a8e063',
            pointBorderColor: SURF, pointBorderWidth: 2, pointRadius: 4
        }]
    },
    options: { responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ display:false }, tooltip:{ ...TT,
            callbacks:{ label:c=>' '+c.parsed.r.toFixed(0)+'%' }
        }},
        scales:{ r:{
            grid:{ color:dark?'rgba(255,255,255,.07)':'rgba(26,42,74,.07)' },
            ticks:{ display:false }, min:0, max:100,
            pointLabels:{ color:TICK, font:{ family:"'Plus Jakarta Sans',sans-serif", size:10, weight:'700' } }
        }}
    }
});

/* ══════════════════════════════════════════════
   9. POLAR AREA — Transaction type volumes
══════════════════════════════════════════════ */
new Chart(document.getElementById('chartPolar'), {
    type: 'polarArea',
    data: {
        labels: TXN_LBLS,
        datasets: [{ data: TXN_VALS,
            backgroundColor: ['#1a3a2acc','#a8e063cc','#4481ebcc','#e63757cc','#20c997cc','#f0a500cc','#7c4dffcc'],
            borderWidth: 0
        }]
    },
    options: { responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ display:false }, tooltip:{ ...TT,
            callbacks:{ label:c=>' KES '+c.parsed.r.toLocaleString() }
        }},
        scales:{ r:{ grid:{ color:dark?'rgba(255,255,255,.06)':'rgba(26,42,74,.06)' }, ticks:{ display:false } } }
    }
});

/* ══════════════════════════════════════════════
   10. HEATMAP — Monthly intensity (CSS-driven)
══════════════════════════════════════════════ */
(function() {
    // Build monthly totals from day_in + day_out chunked into 12 groups
    const monthlyTotals = MO_LABELS.map((_, mi) => {
        const daysPerMonth = Math.floor(DAY_IN.length / 12);
        const start = mi * daysPerMonth;
        const end   = start + daysPerMonth;
        return DAY_IN.slice(start, end).reduce((a, b) => a + b, 0)
             + DAY_OUT.slice(start, end).reduce((a, b) => a + b, 0);
    });
    const maxVal = Math.max(...monthlyTotals, 1);

    // Labels
    const labelsEl = document.getElementById('hmapLabels');
    MO_LABELS.forEach(m => {
        const d = document.createElement('div');
        d.className = 'hmap-lbl'; d.textContent = m;
        labelsEl.appendChild(d);
    });

    // Cells
    const gridEl = document.getElementById('hmapGrid');
    monthlyTotals.forEach((v, i) => {
        const intensity = v / maxVal;
        const alpha     = 0.07 + intensity * 0.83;
        const cell      = document.createElement('div');
        cell.className  = 'hmap-cell';
        cell.style.background = `rgba(26,58,42,${alpha.toFixed(2)})`;
        cell.title = `${MO_LABELS[i]}: KES ${v.toLocaleString()}`;
        gridEl.appendChild(cell);
    });

    // Scale
    const scaleEl = document.getElementById('hmapScale');
    for (let l = 1; l <= 5; l++) {
        const d = document.createElement('div');
        d.style.cssText = `width:20px;height:16px;border-radius:4px;background:rgba(26,58,42,${(0.07 + l * 0.17).toFixed(2)})`;
        scaleEl.appendChild(d);
    }
})();

/* ══════════════════════════════════════════════
   11. WATERFALL — Net position breakdown
══════════════════════════════════════════════ */
(function() {
    const labels = ['Deposits','Savings','Shares','Loans','Withdrawals','Net Worth'];
    const values = [TOT_DEP, TOTAL_SAV, TOTAL_SHR, LOANS_BAL, TOT_WDR, Math.abs(NET_WORTH)];
    const colors = ['#1aa053cc','#1a3a2acc','#a8e063cc','#e63757cc','#f0a500cc',
        NET_WORTH >= 0 ? '#1aa053cc' : '#e63757cc'];
    new Chart(document.getElementById('chartWaterfall'), {
        type: 'bar',
        data: { labels, datasets:[{
            data: values,
            backgroundColor: colors,
            borderRadius: 8, barPercentage: .65
        }]},
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{ display:false }, tooltip:{ ...TT,
                callbacks:{ label:c=>' KES '+c.parsed.y.toLocaleString() }
            }},
            scales:{ x:XSCALE, y:YSCALE }
        }
    });
})();

/* ══════════════════════════════════════════════
   12. BUBBLE — Savings vs Contributions
══════════════════════════════════════════════ */
new Chart(document.getElementById('chartBubble'), {
    type: 'bubble',
    data: { datasets:[{
        label: 'Monthly Data',
        data: SAV_DATA.map((s, i) => ({
            x: CTB_DATA[i],
            y: s,
            r: Math.min(22, Math.max(4, (REP_DATA[i] / 3000)))
        })),
        backgroundColor: SAV_DATA.map((_, i) =>
            `hsla(${130 + i * 10}, 48%, 28%, 0.68)`
        ),
        borderColor: '#a8e063', borderWidth: 1.5
    }]},
    options: { responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ display:false }, tooltip:{ ...TT,
            callbacks:{ label:c=>[
                ' Savings: KES '      + c.parsed.y.toLocaleString(),
                ' Contributions: KES '+ c.parsed.x.toLocaleString(),
                ' Repaid bubble size'
            ]}
        }},
        scales: {
            x: { ...YSCALE, title:{ display:true, text:'Contributions (KES)', color:TICK, font:{ size:10 } } },
            y: { ...YSCALE, title:{ display:true, text:'Savings (KES)',        color:TICK, font:{ size:10 } } }
        }
    }
});

/* ══════════════════════════════════════════════
   13. HORIZONTAL BAR — Transaction type totals
══════════════════════════════════════════════ */
new Chart(document.getElementById('chartHBar'), {
    type: 'bar',
    data: {
        labels: TXN_LBLS,
        datasets:[{ label:'KES Total', data:TXN_VALS,
            backgroundColor:['#1a3a2acc','#a8e063cc','#4481ebcc','#e63757cc','#20c997cc','#f0a500cc','#7c4dffcc'],
            borderRadius: 8, barPercentage: .65
        }]
    },
    options: { indexAxis:'y', responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ display:false }, tooltip:{ ...TT,
            callbacks:{ label:c=>' KES '+c.parsed.x.toLocaleString() }
        }},
        scales:{
            x:{ ...YSCALE },
            y:{ grid:{ display:false }, ticks:{ color:TICK, font:{ family:"'Plus Jakarta Sans',sans-serif", size:10 } } }
        }
    }
});

/* ── SESSION FLASH ──────────────────────────── */
<?php if (isset($_SESSION['success'])): ?>
    typeof showToast !== 'undefined' && showToast(<?= json_encode($_SESSION['success']) ?>, 'success');
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    typeof showToast !== 'undefined' && showToast(<?= json_encode($_SESSION['error']) ?>, 'error');
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

/* ── RECEIPT MODAL TRIGGER ──────────────────── */
<?php if (isset($_SESSION['payment_success_trigger'])):
    $tid = (int)$_SESSION['payment_success_trigger'];
    unset($_SESSION['payment_success_trigger']);
    $sr = $conn->prepare("SELECT * FROM transactions WHERE transaction_id=? AND member_id=?");
    $sr->bind_param("ii", $tid, $member_id); $sr->execute();
    $td = $sr->get_result()->fetch_assoc(); $sr->close();
    if ($td): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('receiptAmount').innerText  = 'KES <?= number_format((float)$td['amount'], 2) ?>';
    document.getElementById('receiptNo').innerText      = '<?= $td['reference_no'] ?>';
    document.getElementById('receiptAccount').innerText = '<?= ucfirst($td['transaction_type']) ?> Account';
    document.getElementById('receiptRef').innerText     = 'TXN-<?= strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)) ?>';
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
});
<?php endif; endif; ?>
</script>
</body>
</html>