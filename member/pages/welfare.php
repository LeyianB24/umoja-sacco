<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');
require_member();
$layout = LayoutManager::create('member');

$member_id = $_SESSION['member_id'];

if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false) {
        if (!$datetime) return "unknown";
        $now = new DateTime; $ago = new DateTime($datetime); $diff = $now->diff($ago);
        $weeks = floor($diff->d / 7); $days = $diff->d - ($weeks * 7);
        $string = ['y'=>'year','m'=>'month','w'=>'week','d'=>'day','h'=>'hour','i'=>'minute'];
        foreach ($string as $k => &$v) {
            $val = ($k==='w') ? $weeks : (($k==='d') ? $days : $diff->$k);
            if ($val) $v = $val.' '.$v.($val>1?'s':''); else unset($string[$k]);
        }
        if (!$full) $string = array_slice($string,0,1);
        return $string ? implode(', ',$string).' ago' : 'just now';
    }
}

// POST: new case
$success = $error = "";
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_case'])) {
    $title   = trim($_POST['title']);
    $desc    = trim($_POST['description']);
    $req_amt = floatval($_POST['requested_amount']);
    if (!empty($title) && $req_amt > 0) {
        $stmt = $conn->prepare("INSERT INTO welfare_cases (title, description, requested_amount, related_member_id, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("ssdi", $title, $desc, $req_amt, $member_id);
        $success = $stmt->execute() ? "Your welfare request has been submitted for review." : "System Error: ".$conn->error;
    } else { $error = "Please fill all required fields."; }
}

// Fetch contributions
$stmt = $conn->prepare("SELECT amount, status, reference_no, created_at FROM contributions WHERE member_id=? AND contribution_type='welfare' ORDER BY created_at ASC");
$stmt->bind_param("i", $member_id); $stmt->execute();
$contribsRaw = $stmt->get_result();
$chart_data = []; $all_contribs = [];
while ($row = $contribsRaw->fetch_assoc()) {
    $mk = date('Y-m', strtotime($row['created_at']));
    if (!isset($chart_data[$mk])) $chart_data[$mk] = 0;
    $chart_data[$mk] += $row['amount'];
    $all_contribs[] = $row;
}

// Fetch support received
$stmt = $conn->prepare("SELECT * FROM welfare_support WHERE member_id=? ORDER BY date_granted DESC");
$stmt->bind_param("i", $member_id); $stmt->execute();
$supportResult = $stmt->get_result();
$total_received = 0; $all_support = [];
while ($row = $supportResult->fetch_assoc()) {
    if (in_array($row['status'],['disbursed','approved'])) $total_received += (float)$row['amount'];
    $all_support[] = $row;
}

// Member cases
$stmtC = $conn->prepare("SELECT * FROM welfare_cases WHERE related_member_id=? ORDER BY created_at DESC");
$stmtC->bind_param("i", $member_id); $stmtC->execute();
$member_cases = $stmtC->get_result()->fetch_all(MYSQLI_ASSOC);

// Community cases
$community_cases = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM welfare_donations WHERE case_id=c.case_id) as donor_count FROM welfare_cases c WHERE c.status IN ('active','approved','funded') ORDER BY c.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Financial engine
require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine = new FinancialEngine($conn);
$welfare_pool        = $engine->getWelfarePoolBalance();
$total_given         = $engine->getMemberWelfareLifetime($member_id);
$net_standing        = $total_given - $total_received;
$standing_status     = ($net_standing >= 0) ? 'contributor' : 'beneficiary';
$balances            = $engine->getBalances($member_id);
$withdrawable_benefit = $balances['welfare'] ?? 0;

ksort($chart_data);
$json_labels = json_encode(array_keys($chart_data));
$json_values = json_encode(array_values($chart_data));

// Export
if (isset($_GET['action']) && in_array($_GET['action'],['export_pdf','export_excel','print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    $format = $_GET['action']==='export_excel' ? 'excel' : ($_GET['action']==='print_report' ? 'print' : 'pdf');
    $data = [];
    foreach ($all_contribs as $row) $data[] = ['Date'=>date('d-M-Y',strtotime($row['created_at'])),'Reference'=>$row['reference_no'],'Type'=>'Contribution','Amount'=>'+ '.number_format((float)$row['amount'],2),'Status'=>ucfirst($row['status']??'completed')];
    foreach ($all_support as $row) { if (!in_array($row['status'],['approved','disbursed'])) continue; $data[] = ['Date'=>date('d-M-Y',strtotime($row['date_granted'])),'Reference'=>'Support: '.($row['reason']??'N/A'),'Type'=>'Support Received','Amount'=>'- '.number_format((float)$row['amount'],2),'Status'=>ucfirst($row['status'])]; }
    usort($data, fn($a,$b) => strtotime($b['Date'])-strtotime($a['Date']));
    UniversalExportEngine::handle($format,$data,['title'=>'Welfare Fund Statement','module'=>'Member Portal','headers'=>['Date','Reference','Type','Amount','Status']]);
    exit;
}

$pageTitle = "Welfare Hub";
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
   WELFARE HUB · HD · Plus Jakarta Sans · Forest & Lime
═══════════════════════════════════════════════════════════ */
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

:root {
    --f:      #0b2419;
    --fm:     #154330;
    --fs:     #1d6044;
    --lime:   #a3e635;
    --lg:     rgba(163,230,53,0.14);
    --lt:     #6a9a1a;

    --bg:     #eff5f1;
    --bg2:    #e8f1ec;
    --surf:   #ffffff;
    --surf2:  #f7fbf8;
    --bdr:    rgba(11,36,25,0.07);
    --bdr2:   rgba(11,36,25,0.04);

    --t1: #0b2419;
    --t2: #456859;
    --t3: #8fada0;

    --grn:    #16a34a;
    --red:    #dc2626;
    --amb:    #d97706;
    --blu:    #2563eb;
    --grn-bg: rgba(22,163,74,0.08);
    --red-bg: rgba(220,38,38,0.08);
    --amb-bg: rgba(217,119,6,0.08);
    --blu-bg: rgba(37,99,235,0.08);

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

body, * { font-family:'Plus Jakarta Sans',sans-serif !important; -webkit-font-smoothing:antialiased; }
body { background:var(--bg); color:var(--t1); }

.main-content-wrapper { margin-left:272px; min-height:100vh; transition:margin-left .3s var(--ease); }
body.sb-collapsed .main-content-wrapper { margin-left:76px; }
@media (max-width:991px) { .main-content-wrapper { margin-left:0; } }

/* ─────────────────────────────────────────────
   HERO
───────────────────────────────────────────── */
.sv-hero { background:var(--f); position:relative; overflow:hidden; }

.hero-mesh {
    position:absolute; inset:0; pointer-events:none;
    background:
        radial-gradient(ellipse 65% 85% at 108% -5%, rgba(163,230,53,.11) 0%,transparent 55%),
        radial-gradient(ellipse 40% 55% at -8% 105%,rgba(163,230,53,.07) 0%,transparent 55%);
}
.hero-dots {
    position:absolute; inset:0; pointer-events:none;
    background-image:radial-gradient(rgba(255,255,255,.05) 1px,transparent 1px);
    background-size:20px 20px;
}
.hero-ring { position:absolute; border-radius:50%; pointer-events:none; border:1px solid rgba(163,230,53,.07); }
.hero-ring.r1 { width:480px; height:480px; top:-170px; right:-110px; }
.hero-ring.r2 { width:720px; height:720px; top:-270px; right:-210px; }

.hero-inner { position:relative; z-index:2; padding:42px 52px 108px; }
@media (max-width:767px) { .hero-inner { padding:32px 20px 96px; } }

.hero-nav { display:flex; align-items:center; justify-content:space-between; margin-bottom:44px; }

.hero-back { display:inline-flex; align-items:center; gap:7px; color:rgba(255,255,255,.38); font-size:.78rem; font-weight:700; text-decoration:none; transition:color .18s ease; }
.hero-back:hover { color:var(--lime); }

.hero-brand-tag { font-size:9.5px; font-weight:800; letter-spacing:1.8px; text-transform:uppercase; color:rgba(255,255,255,.18); }

.hero-eyebrow { display:flex; align-items:center; gap:10px; font-size:9.5px; font-weight:800; letter-spacing:1.5px; text-transform:uppercase; color:rgba(163,230,53,.65); margin-bottom:16px; }
.ey-line { width:22px; height:1.5px; background:var(--lime); opacity:.5; border-radius:99px; }

.hero-lbl { font-size:.75rem; font-weight:600; letter-spacing:.4px; color:rgba(255,255,255,.3); text-transform:uppercase; margin-bottom:8px; }

.hero-amount { font-size:clamp(2.8rem,6.5vw,4.8rem); font-weight:800; color:#fff; letter-spacing:-2.5px; line-height:1; margin-bottom:18px; animation:slide-up .9s var(--ease) both; }
.hero-amount .cur { font-size:.36em; font-weight:700; vertical-align:.55em; opacity:.45; letter-spacing:0; margin-right:3px; }

.hero-pill { display:inline-flex; align-items:center; gap:7px; font-size:11px; font-weight:700; padding:5px 14px; border-radius:50px; animation:slide-up .9s var(--ease) .15s both; width:fit-content; }
.hero-pill.green { background:rgba(163,230,53,.11); border:1px solid rgba(163,230,53,.2); color:#bff060; }
.hero-pill.rose  { background:rgba(244,63,94,.12);  border:1px solid rgba(244,63,94,.25);  color:#fca5a5; }

.pill-dot { width:5px; height:5px; border-radius:50%; background:var(--lime); animation:pulse 1.8s ease-in-out infinite; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.35;transform:scale(1.8)} }

.hero-ctas { display:flex; gap:10px; flex-wrap:wrap; margin-top:30px; animation:slide-up .9s var(--ease) .25s both; }

.btn-lime { display:inline-flex; align-items:center; gap:8px; background:var(--lime); color:var(--f); font-size:.875rem; font-weight:800; padding:12px 26px; border-radius:50px; border:none; cursor:pointer; text-decoration:none; box-shadow:0 2px 14px rgba(163,230,53,.28); transition:all .25s var(--spring); }
.btn-lime:hover { transform:translateY(-3px) scale(1.03); box-shadow:0 10px 32px rgba(163,230,53,.4); color:var(--f); }

.btn-ghost { display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.14); color:rgba(255,255,255,.8); font-size:.875rem; font-weight:700; padding:12px 22px; border-radius:50px; cursor:pointer; text-decoration:none; transition:all .22s ease; }
.btn-ghost:hover { background:rgba(255,255,255,.16); color:#fff; transform:translateY(-2px); }

.btn-ghost-red { display:inline-flex; align-items:center; gap:8px; background:rgba(220,38,38,.1); border:1px solid rgba(220,38,38,.25); color:#fca5a5; font-size:.875rem; font-weight:700; padding:12px 22px; border-radius:50px; cursor:pointer; text-decoration:none; transition:all .22s ease; }
.btn-ghost-red:hover { background:rgba(220,38,38,.18); color:#fecaca; transform:translateY(-2px); }

/* hero right — pool info block */
.hero-pool-block { animation:slide-up .9s var(--ease) .2s both; }
.pool-lbl { font-size:9px; font-weight:800; letter-spacing:1.2px; text-transform:uppercase; color:rgba(255,255,255,.25); margin-bottom:14px; }

.pool-info-row { display:flex; flex-direction:column; gap:14px; }
.pool-info-item { display:flex; align-items:center; gap:14px; }
.pool-ico { width:42px; height:42px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
.pool-val { font-size:1.15rem; font-weight:800; color:#fff; letter-spacing:-.5px; }
.pool-sub { font-size:.7rem; font-weight:600; color:rgba(255,255,255,.35); margin-top:1px; }

@keyframes slide-up { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

/* ─────────────────────────────────────────────
   FLOATING STAT CARDS
───────────────────────────────────────────── */
.stats-float { margin-top:-68px; position:relative; z-index:10; padding:0 52px; }
@media (max-width:767px) { .stats-float { padding:0 16px; } }

.sc { background:var(--surf); border-radius:var(--r); padding:26px 28px; border:1px solid var(--bdr); box-shadow:var(--sh-lg); height:100%; position:relative; overflow:hidden; transition:transform .28s var(--ease),box-shadow .28s ease; }
.sc:hover { transform:translateY(-5px); box-shadow:0 8px 20px rgba(11,36,25,.09),0 36px 70px rgba(11,36,25,.15); }

.sc::after { content:''; position:absolute; bottom:0; left:0; right:0; height:2.5px; border-radius:0 0 var(--r) var(--r); transform:scaleX(0); transform-origin:left; transition:transform .38s var(--ease); }
.sc:hover::after { transform:scaleX(1); }
.sc-g::after { background:linear-gradient(90deg,#16a34a,#4ade80); }
.sc-r::after { background:linear-gradient(90deg,#dc2626,#f87171); }
.sc-l::after { background:linear-gradient(90deg,var(--lime),#d4f98a); }
.sc-a::after { background:linear-gradient(90deg,#d97706,#fbbf24); }
.sc-b::after { background:linear-gradient(90deg,#2563eb,#60a5fa); }

.sc-ico { width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.15rem; margin-bottom:18px; transition:transform .3s var(--spring); }
.sc:hover .sc-ico { transform:scale(1.12) rotate(7deg); }

.sc-lbl { font-size:10px; font-weight:800; letter-spacing:1px; text-transform:uppercase; color:var(--t3); margin-bottom:6px; }
.sc-val { font-size:1.55rem; font-weight:800; color:var(--t1); letter-spacing:-.8px; line-height:1.1; margin-bottom:16px; }
.sc-bar { height:4px; border-radius:99px; background:var(--bg); overflow:hidden; margin-bottom:10px; }
.sc-bar-fill { height:100%; border-radius:99px; width:0; transition:width 1.4s var(--ease); }
.sc-meta { font-size:.72rem; font-weight:600; color:var(--t3); }

.sa1 { animation:slide-up .7s var(--ease) .42s both; }
.sa2 { animation:slide-up .7s var(--ease) .52s both; }
.sa3 { animation:slide-up .7s var(--ease) .62s both; }
.sa4 { animation:slide-up .7s var(--ease) .72s both; }
.sa5 { animation:slide-up .7s var(--ease) .82s both; }

/* ─────────────────────────────────────────────
   PAGE BODY
───────────────────────────────────────────── */
.pg-body { padding:40px 52px 80px; }
@media (max-width:767px) { .pg-body { padding:28px 16px 60px; } }

/* section label */
.sec-label { display:flex; align-items:center; gap:12px; font-size:9.5px; font-weight:800; letter-spacing:1.2px; text-transform:uppercase; color:var(--t3); margin-bottom:18px; }
.sec-label::after { content:''; flex:1; height:1px; background:var(--bdr); }

/* flash */
.flash-ok { display:flex; align-items:flex-start; gap:12px; background:var(--grn-bg); border:1px solid rgba(22,163,74,.2); border-radius:var(--rsm); padding:14px 18px; margin-bottom:22px; font-size:.82rem; font-weight:600; color:var(--grn); animation:slide-up .4s var(--ease) both; }
.flash-ok i { font-size:1.1rem; flex-shrink:0; margin-top:1px; }
.flash-ok strong { font-weight:800; display:block; margin-bottom:2px; }

.flash-err { display:flex; align-items:flex-start; gap:12px; background:var(--red-bg); border:1px solid rgba(220,38,38,.2); border-radius:var(--rsm); padding:14px 18px; margin-bottom:22px; font-size:.82rem; font-weight:600; color:var(--red); animation:slide-up .4s var(--ease) both; }
.flash-err i { font-size:1.1rem; flex-shrink:0; margin-top:1px; }

/* chart + policy row */
.chart-card { background:var(--surf); border-radius:var(--r); padding:26px 28px; border:1px solid var(--bdr); box-shadow:var(--sh); animation:slide-up .7s var(--ease) .85s both; }
.chart-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
.chart-title { font-size:.9rem; font-weight:800; color:var(--t1); }
.chart-badge { display:inline-flex; align-items:center; gap:5px; background:var(--grn-bg); color:var(--grn); font-size:9.5px; font-weight:800; padding:3px 10px; border-radius:7px; }
.chart-wrap { position:relative; height:240px; }

/* policy card */
.policy-card { background:var(--f); border-radius:var(--r); padding:28px; height:100%; position:relative; overflow:hidden; animation:slide-up .7s var(--ease) .88s both; }
.policy-card::before { content:''; position:absolute; top:-60px; right:-60px; width:220px; height:220px; border-radius:50%; background:radial-gradient(circle,rgba(163,230,53,.1) 0%,transparent 65%); pointer-events:none; }
.policy-card::after { content:''; position:absolute; inset:0; background-image:radial-gradient(rgba(255,255,255,.04) 1px,transparent 1px); background-size:20px 20px; pointer-events:none; }
.policy-inner { position:relative; z-index:2; }
.policy-title { font-size:.95rem; font-weight:800; color:#fff; margin-bottom:10px; }
.policy-desc  { font-size:.78rem; font-weight:500; color:rgba(255,255,255,.45); line-height:1.6; margin-bottom:18px; }
.policy-divider { border-top:1px solid rgba(255,255,255,.1); padding-top:16px; margin-top:4px; }
.policy-item { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.policy-item:last-child { margin-bottom:0; }
.policy-check { width:20px; height:20px; border-radius:6px; background:var(--grn-bg); color:var(--grn); display:flex; align-items:center; justify-content:center; font-size:.65rem; flex-shrink:0; }
.policy-item-txt { font-size:.82rem; font-weight:600; color:rgba(255,255,255,.7); }

/* ─────────────────────────────────────────────
   TAB CARD
───────────────────────────────────────────── */
.tab-card { background:var(--surf); border-radius:22px; border:1px solid var(--bdr); box-shadow:var(--sh); overflow:hidden; animation:slide-up .7s var(--ease) .92s both; }

.tab-card-head { display:flex; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid var(--bdr2); flex-wrap:wrap; gap:14px; background:var(--surf2); }

/* tab switcher */
.tab-pills { display:flex; gap:3px; background:var(--bg); border:1px solid var(--bdr); border-radius:12px; padding:4px; }
.tpill { display:flex; align-items:center; gap:5px; padding:7px 14px; border-radius:9px; font-size:.78rem; font-weight:700; color:var(--t3); text-decoration:none; transition:all .18s var(--ease); border:none; background:transparent; cursor:pointer; white-space:nowrap; }
.tpill:hover { color:var(--t2); }
.tpill.active { background:var(--f); color:#fff; box-shadow:0 2px 8px rgba(11,36,25,.2); }

/* search */
.tab-search { position:relative; }
.tab-search i { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:var(--t3); font-size:.82rem; pointer-events:none; }
.tab-search input { padding:8px 13px 8px 32px; border:1px solid var(--bdr); border-radius:10px; font-size:.78rem; font-weight:600; color:var(--t1); background:var(--bg); box-shadow:none; width:200px; outline:none; transition:border-color .18s ease,box-shadow .18s ease; }
.tab-search input:focus { border-color:rgba(11,36,25,.25); box-shadow:0 0 0 3px rgba(11,36,25,.06); width:240px; }

/* tab content */
.tab-pane { display:none; }
.tab-pane.show { display:block; }

/* tables */
.wt-table { width:100%; border-collapse:collapse; }
.wt-table thead th { background:var(--surf2); font-size:10px; font-weight:800; letter-spacing:.8px; text-transform:uppercase; color:var(--t3); padding:11px 18px; border:none; border-bottom:1px solid var(--bdr2); white-space:nowrap; }
.wt-table tbody tr { border-bottom:1px solid var(--bdr2); transition:background .13s ease; }
.wt-table tbody tr:last-child { border-bottom:none; }
.wt-table tbody tr:hover { background:rgba(11,36,25,.018); }
.wt-table tbody td { padding:13px 18px; vertical-align:middle; font-size:.875rem; }

/* cell styles */
.cell-date { font-size:.88rem; font-weight:700; color:var(--t1); }
.cell-time { font-size:.68rem; font-weight:500; color:var(--t3); margin-top:2px; }
.cell-title { font-size:.88rem; font-weight:700; color:var(--t1); }
.cell-sub   { font-size:.7rem; font-weight:500; color:var(--t3); margin-top:2px; font-family:monospace; }
.cell-desc  { font-size:.7rem; font-weight:500; color:var(--t3); margin-top:2px; max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

.sc-chip { display:inline-flex; align-items:center; gap:4px; font-size:9.5px; font-weight:800; letter-spacing:.3px; padding:3px 10px; border-radius:7px; }
.sc-chip::before { content:''; width:4px; height:4px; border-radius:50%; background:currentColor; }
.chip-grn  { background:var(--grn-bg); color:var(--grn); }
.chip-red  { background:var(--red-bg); color:var(--red); }
.chip-amb  { background:var(--amb-bg); color:var(--amb); }
.chip-blu  { background:var(--blu-bg); color:var(--blu); }
.chip-grey { background:rgba(11,36,25,.06); color:var(--t3); }

.amt-in  { font-size:.88rem; font-weight:800; color:var(--grn); }
.amt-out { font-size:.88rem; font-weight:800; color:var(--red); }
.amt-neu { font-size:.88rem; font-weight:800; color:var(--t1); }

/* empty state */
.empty-well { display:flex; flex-direction:column; align-items:center; padding:64px 24px; text-align:center; }
.ew-ico { width:72px; height:72px; border-radius:20px; background:var(--bg); border:1px solid var(--bdr); display:flex; align-items:center; justify-content:center; font-size:1.8rem; color:var(--t3); margin-bottom:18px; }
.ew-title { font-size:.9rem; font-weight:800; color:var(--t1); margin-bottom:5px; }
.ew-sub   { font-size:.78rem; font-weight:500; color:var(--t3); }

/* community case cards */
.case-card { background:var(--surf); border-radius:var(--r); border:1px solid var(--bdr); overflow:hidden; height:100%; transition:transform .28s var(--ease),box-shadow .28s ease; }
.case-card:hover { transform:translateY(-4px); box-shadow:var(--sh-lg); }

.case-card-top { background:linear-gradient(135deg,var(--f),var(--fm)); padding:18px 20px; color:#fff; }
.case-card-meta { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
.case-id-tag { font-size:9.5px; font-weight:800; letter-spacing:1px; text-transform:uppercase; color:rgba(255,255,255,.4); }

.case-status-chip { font-size:9.5px; font-weight:800; letter-spacing:.3px; padding:3px 9px; border-radius:6px; }
.cs-active  { background:rgba(163,230,53,.15); color:#bff060; }
.cs-approved{ background:rgba(22,163,74,.15);  color:#86efac; }
.cs-funded  { background:rgba(37,99,235,.15);  color:#93c5fd; }

.case-card-title { font-size:.88rem; font-weight:800; color:#fff; line-height:1.3; }

.case-card-body { padding:18px 20px; }
.case-desc { font-size:.78rem; color:var(--t3); line-height:1.5; margin-bottom:14px; display:-webkit-box; -webkit-line-clamp:2; line-clamp: 2; -webkit-box-orient:vertical; overflow:hidden; }

.case-progress-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; }
.case-raised { font-size:.82rem; font-weight:800; color:var(--t1); }
.case-target { font-size:.72rem; font-weight:600; color:var(--t3); }
.case-prog-bar { height:4px; border-radius:99px; background:var(--bg); overflow:hidden; margin-bottom:14px; }
.case-prog-fill { height:100%; border-radius:99px; background:var(--lime); transition:width 1.2s var(--ease); width:0; }

.case-footer { display:flex; align-items:center; justify-content:space-between; }
.case-donors { font-size:.72rem; font-weight:700; color:var(--t3); }

.btn-support { display:inline-flex; align-items:center; gap:6px; background:var(--lime); color:var(--f); font-size:.78rem; font-weight:800; padding:7px 16px; border-radius:50px; border:none; cursor:pointer; text-decoration:none; transition:all .22s var(--spring); }
.btn-support:hover { transform:translateY(-2px) scale(1.04); box-shadow:0 6px 18px rgba(163,230,53,.35); color:var(--f); }

/* export button */
.btn-exp { display:inline-flex; align-items:center; gap:7px; background:var(--bg); border:1px solid var(--bdr); color:var(--t2); font-size:.78rem; font-weight:700; padding:7px 16px; border-radius:50px; cursor:pointer; transition:all .18s ease; }
.btn-exp:hover { border-color:rgba(11,36,25,.18); color:var(--t1); background:var(--surf); }
.exp-dd { border-radius:16px !important; padding:7px !important; border-color:var(--bdr) !important; box-shadow:var(--sh-lg) !important; background:var(--surf) !important; min-width:185px; }
.dd-item { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:10px; text-decoration:none; font-size:.82rem; font-weight:600; color:var(--t1); transition:background .14s ease; }
.dd-item:hover { background:var(--bg); color:var(--t1); }
.dd-ic { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:.88rem; flex-shrink:0; }

/* modal */
.modal-content { border-radius:20px !important; border:none !important; box-shadow:var(--sh-lg) !important; }
.modal-content .form-control, .modal-content .form-select { border:1px solid var(--bdr); border-radius:10px; padding:9px 13px; font-size:.875rem; font-weight:500; color:var(--t1); box-shadow:none; }
.modal-content .form-control:focus, .modal-content .form-select:focus { border-color:rgba(11,36,25,.35); box-shadow:0 0 0 3px rgba(11,36,25,.07); }
.modal-content .form-label { font-size:10.5px; font-weight:800; letter-spacing:.7px; text-transform:uppercase; color:var(--t3); margin-bottom:6px; }
.btn-modal-submit { width:100%; padding:12px; border-radius:50px; background:var(--f); color:#fff; font-size:.875rem; font-weight:800; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:all .22s var(--ease); }
.btn-modal-submit:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(11,36,25,.25); }
.btn-modal-cancel { padding:10px 24px; border-radius:50px; background:var(--bg); color:var(--t2); font-size:.875rem; font-weight:700; border:1px solid var(--bdr); cursor:pointer; transition:all .18s ease; }
.btn-modal-cancel:hover { background:var(--surf); color:var(--t1); }

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
            <!-- Left: welfare pool + actions -->
            <div class="col-md-6">
                <div class="hero-eyebrow"><div class="ey-line"></div> Welfare Fund</div>
                <div class="hero-lbl">Global Solidarity Pool</div>
                <div class="hero-amount"><span class="cur">KES</span><span id="heroAmt"><?= number_format((float)$welfare_pool, 2) ?></span></div>
                <div class="hero-pill <?= $standing_status==='contributor' ? 'green' : 'rose' ?>">
                    <span class="pill-dot" style="background:<?= $standing_status==='contributor' ? 'var(--lime)' : '#f43f5e' ?>;"></span>
                    Community <?= ucfirst($standing_status) ?> · KES <?= ($net_standing>=0?'+':'').number_format($net_standing,2) ?> net
                </div>
                <div class="hero-ctas">
                    <a href="<?= BASE_URL ?>/member/pages/mpesa_request.php?type=welfare" class="btn-lime">
                        <i class="bi bi-heart-fill"></i> Contribute
                    </a>
                    <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#newCaseModal">
                        <i class="bi bi-plus-circle"></i> Report Case
                    </button>
                    <?php if ($withdrawable_benefit > 0): ?>
                    <a href="<?= BASE_URL ?>/member/pages/withdraw.php?type=welfare" class="btn-ghost-red">
                        <i class="bi bi-wallet2"></i> Withdraw Benefit
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: quick stats block -->
            <div class="col-md-6 d-none d-md-block">
                <div class="hero-pool-block">
                    <div class="pool-lbl">Your Standing</div>
                    <div class="pool-info-row">
                        <div class="pool-info-item">
                            <div class="pool-ico" style="background:var(--grn-bg);color:var(--grn);">
                                <i class="bi bi-heart-fill"></i>
                            </div>
                            <div>
                                <div class="pool-val">KES <?= number_format((float)$total_given, 0) ?></div>
                                <div class="pool-sub">Total Contributed</div>
                            </div>
                        </div>
                        <div class="pool-info-item">
                            <div class="pool-ico" style="background:var(--red-bg);color:var(--red);">
                                <i class="bi bi-arrow-down-left-circle-fill"></i>
                            </div>
                            <div>
                                <div class="pool-val">KES <?= number_format((float)$total_received, 0) ?></div>
                                <div class="pool-sub">Support Received</div>
                            </div>
                        </div>
                        <?php if ($withdrawable_benefit > 0): ?>
                        <div class="pool-info-item">
                            <div class="pool-ico" style="background:var(--amb-bg);color:var(--amb);">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <div>
                                <div class="pool-val">KES <?= number_format((float)$withdrawable_benefit, 0) ?></div>
                                <div class="pool-sub">Withdrawable Benefit</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
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
                <div class="sc-ico" style="background:var(--grn-bg);color:var(--grn);"><i class="bi bi-heart-fill"></i></div>
                <div class="sc-lbl">My Contributions</div>
                <div class="sc-val">KES <?= number_format((float)$total_given, 2) ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--grn);" data-w="100"></div></div>
                <div class="sc-meta">Lifetime welfare contributions</div>
            </div>
        </div>
        <div class="col-md-3 sa2">
            <div class="sc sc-r">
                <div class="sc-ico" style="background:var(--red-bg);color:var(--red);"><i class="bi bi-arrow-down-left-square-fill"></i></div>
                <div class="sc-lbl">Support Received</div>
                <div class="sc-val">KES <?= number_format((float)$total_received, 2) ?></div>
                <?php $recvPct = $total_given > 0 ? min(100,($total_received/$total_given)*100) : 0; ?>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--red);" data-w="<?= round($recvPct) ?>"></div></div>
                <div class="sc-meta">Total support disbursed to you</div>
            </div>
        </div>
        <div class="col-md-3 sa3">
            <div class="sc sc-<?= $net_standing>=0 ? 'l' : 'r' ?>">
                <div class="sc-ico" style="background:<?= $net_standing>=0 ? 'var(--lg)' : 'var(--red-bg)' ?>;color:<?= $net_standing>=0 ? 'var(--lt)' : 'var(--red)' ?>;"><i class="bi bi-scale"></i></div>
                <div class="sc-lbl">Net Impact</div>
                <div class="sc-val" style="color:<?= $net_standing>=0 ? 'var(--grn)' : 'var(--red)' ?>;"><?= ($net_standing>=0?'+':'').number_format($net_standing,2) ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:<?= $net_standing>=0 ? 'var(--lime)' : 'var(--red)' ?>;" data-w="<?= min(100,abs(round($net_standing > 0 ? ($net_standing/max($total_given,1))*100 : $recvPct))) ?>"></div></div>
                <div class="sc-meta">Community <?= ucfirst($standing_status) ?> status</div>
            </div>
        </div>
        <div class="col-md-3 sa4">
            <div class="sc sc-a">
                <div class="sc-ico" style="background:var(--amb-bg);color:var(--amb);"><i class="bi bi-wallet2"></i></div>
                <div class="sc-lbl">Withdrawable</div>
                <div class="sc-val">KES <?= number_format((float)$withdrawable_benefit, 2) ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--amb);" data-w="<?= $withdrawable_benefit > 0 ? 80 : 0 ?>"></div></div>
                <div class="sc-meta">Available for withdrawal now</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════ BODY ═════════════════════════ -->
<div class="pg-body">

    <?php if ($success): ?>
    <div class="flash-ok"><i class="bi bi-check-circle-fill"></i><div><strong>Request Submitted</strong><?= $success ?></div></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="flash-err"><i class="bi bi-exclamation-triangle-fill"></i><div><?= $error ?></div></div>
    <?php endif; ?>

    <!-- Chart + Policy -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-head">
                    <span class="chart-title">Contribution Trend</span>
                    <span class="chart-badge"><i class="bi bi-activity" style="font-size:.7rem;"></i> Lifetime</span>
                </div>
                <div class="chart-wrap">
                    <canvas id="welfareChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="policy-card">
                <div class="policy-inner">
                    <div class="policy-title">Welfare Policy</div>
                    <p class="policy-desc">Contributions assist members during bereavement and hospitalization. Active membership status is required to be eligible for support.</p>
                    <div class="policy-divider">
                        <div class="policy-item">
                            <div class="policy-check"><i class="bi bi-check-lg"></i></div>
                            <span class="policy-item-txt">Bereavement Support</span>
                        </div>
                        <div class="policy-item">
                            <div class="policy-check"><i class="bi bi-check-lg"></i></div>
                            <span class="policy-item-txt">Medical Emergency</span>
                        </div>
                        <div class="policy-item">
                            <div class="policy-check"><i class="bi bi-check-lg"></i></div>
                            <span class="policy-item-txt">Community Fundraisers</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabbed Table Card -->
    <div class="tab-card">
        <div class="tab-card-head">
            <div class="tab-pills" id="tabPills">
                <button class="tpill active" data-tab="contributions">Contributions</button>
                <button class="tpill" data-tab="community">Community</button>
                <button class="tpill" data-tab="received">Support Received</button>
                <button class="tpill" data-tab="cases">My Cases</button>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="tab-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="tableSearch" placeholder="Search…">
                </div>
                <div class="dropdown">
                    <button class="btn-exp dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-cloud-download-fill"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end exp-dd">
                        <li><a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'export_pdf'])) ?>"><div class="dd-ic" style="background:rgba(220,38,38,.09);color:#dc2626;"><i class="bi bi-file-pdf"></i></div> PDF</a></li>
                        <li><a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'export_excel'])) ?>"><div class="dd-ic" style="background:rgba(5,150,105,.09);color:#059669;"><i class="bi bi-file-earmark-excel"></i></div> Excel</a></li>
                        <li><hr class="dropdown-divider mx-2"></li>
                        <li><a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'print_report'])) ?>" target="_blank"><div class="dd-ic" style="background:rgba(79,70,229,.09);color:#4f46e5;"><i class="bi bi-printer"></i></div> Print</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Tab: Contributions -->
        <div class="tab-pane show" id="tab-contributions">
            <div class="table-responsive">
                <table class="wt-table">
                    <thead>
                        <tr>
                            <th style="padding-left:24px;">Date</th>
                            <th>Reference</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th style="text-align:right;padding-right:24px;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_contribs)): ?>
                        <tr><td colspan="5">
                            <div class="empty-well"><div class="ew-ico"><i class="bi bi-heart"></i></div><div class="ew-title">No Contributions Yet</div><div class="ew-sub">Your welfare contributions will appear here.</div></div>
                        </td></tr>
                        <?php else: foreach ($all_contribs as $row):
                            $st = strtolower($row['status'] ?? 'completed');
                            $chipCls = match($st) { 'completed','active'=>'chip-grn','pending'=>'chip-amb', default=>'chip-grey' };
                        ?>
                        <tr>
                            <td style="padding-left:24px;">
                                <div class="cell-date"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                                <div class="cell-time"><?= date('H:i', strtotime($row['created_at'])) ?></div>
                            </td>
                            <td><span style="font-family:monospace;font-size:.78rem;font-weight:700;color:var(--t3);background:var(--bg);padding:3px 9px;border-radius:7px;border:1px solid var(--bdr);"><?= esc($row['reference_no']??'N/A') ?></span></td>
                            <td style="font-size:.85rem;font-weight:600;color:var(--t2);">Welfare Contribution</td>
                            <td><span class="sc-chip <?= $chipCls ?>"><?= ucfirst($st) ?></span></td>
                            <td style="text-align:right;padding-right:24px;"><span class="amt-in">+ KES <?= number_format((float)$row['amount'], 2) ?></span></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Community -->
        <div class="tab-pane" id="tab-community">
            <div class="p-4">
                <?php if (empty($community_cases)): ?>
                <div class="empty-well">
                    <div class="ew-ico"><i class="bi bi-emoji-smile"></i></div>
                    <div class="ew-title">All Clear</div>
                    <div class="ew-sub">No active community situations. The SACCO is doing well!</div>
                </div>
                <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($community_cases as $crow):
                        $target  = (float)$crow['target_amount'];
                        $raised  = (float)$crow['total_raised'];
                        $pct     = $target > 0 ? min(100, ($raised/$target)*100) : 0;
                        $stKey   = strtolower($crow['status']);
                        $csClass = match($stKey) { 'approved'=>'cs-approved','funded'=>'cs-funded',default=>'cs-active' };
                    ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="case-card">
                            <div class="case-card-top">
                                <div class="case-card-meta">
                                    <span class="case-id-tag">Case #<?= $crow['case_id'] ?></span>
                                    <span class="case-status-chip <?= $csClass ?>"><?= ucfirst($crow['status']) ?></span>
                                </div>
                                <div class="case-card-title"><?= htmlspecialchars($crow['title']) ?></div>
                            </div>
                            <div class="case-card-body">
                                <p class="case-desc"><?= htmlspecialchars($crow['description']) ?></p>
                                <div class="case-progress-row">
                                    <span class="case-raised">KES <?= number_format($raised) ?></span>
                                    <span class="case-target">Target: <?= number_format($target) ?></span>
                                </div>
                                <div class="case-prog-bar">
                                    <div class="case-prog-fill" data-w="<?= round($pct) ?>"></div>
                                </div>
                                <div class="case-footer">
                                    <span class="case-donors"><i class="bi bi-people me-1"></i> <?= $crow['donor_count'] ?> donors</span>
                                    <a href="<?= BASE_URL ?>/member/pages/mpesa_request.php?type=welfare_case&case_id=<?= $crow['case_id'] ?>" class="btn-support">
                                        <i class="bi bi-heart-fill" style="font-size:.72rem;"></i> Support
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Support Received -->
        <div class="tab-pane" id="tab-received">
            <div class="table-responsive">
                <table class="wt-table">
                    <thead>
                        <tr>
                            <th style="padding-left:24px;">Date</th>
                            <th>Reason</th>
                            <th>Approved By</th>
                            <th>Status</th>
                            <th style="text-align:right;padding-right:24px;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_support)): ?>
                        <tr><td colspan="5"><div class="empty-well"><div class="ew-ico"><i class="bi bi-inbox"></i></div><div class="ew-title">No Support Records</div><div class="ew-sub">No welfare support has been disbursed to your account yet.</div></div></td></tr>
                        <?php else: foreach ($all_support as $row):
                            $st      = strtolower($row['status']);
                            $chipCls = match($st) { 'approved','disbursed'=>'chip-grn','rejected'=>'chip-red',default=>'chip-grey' };
                        ?>
                        <tr>
                            <td style="padding-left:24px;">
                                <div class="cell-date"><?= date('M d, Y', strtotime($row['date_granted'])) ?></div>
                            </td>
                            <td>
                                <div class="cell-title"><?= htmlspecialchars($row['reason'] ?? 'Welfare Support') ?></div>
                            </td>
                            <td style="font-size:.82rem;color:var(--t3);font-weight:600;">SACCO Admin</td>
                            <td><span class="sc-chip <?= $chipCls ?>"><?= ucfirst($st) ?></span></td>
                            <td style="text-align:right;padding-right:24px;"><span class="amt-out">− KES <?= number_format((float)$row['amount'], 2) ?></span></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: My Cases -->
        <div class="tab-pane" id="tab-cases">
            <div class="table-responsive">
                <table class="wt-table">
                    <thead>
                        <tr>
                            <th style="padding-left:24px;">Date</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th style="text-align:right;padding-right:24px;">Approved Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($member_cases)): ?>
                        <tr><td colspan="5"><div class="empty-well"><div class="ew-ico"><i class="bi bi-folder2-open"></i></div><div class="ew-title">No Cases Found</div><div class="ew-sub">No welfare cases are associated with your account.</div></div></td></tr>
                        <?php else: foreach ($member_cases as $row):
                            $st      = strtolower($row['status']);
                            $chipCls = match($st) { 'approved','disbursed','funded'=>'chip-grn','active'=>'chip-blu','pending'=>'chip-amb','closed'=>'chip-grey',default=>'chip-grey' };
                        ?>
                        <tr>
                            <td style="padding-left:24px;"><div class="cell-date"><?= date('M d, Y', strtotime($row['created_at'])) ?></div></td>
                            <td><div class="cell-title"><?= htmlspecialchars($row['title']) ?></div></td>
                            <td><div class="cell-desc"><?= htmlspecialchars($row['description']) ?></div></td>
                            <td><span class="sc-chip <?= $chipCls ?>"><?= ucfirst($st) ?></span></td>
                            <td style="text-align:right;padding-right:24px;"><span class="amt-neu">KES <?= number_format((float)$row['approved_amount'], 2) ?></span></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /tab-card -->

</div><!-- /pg-body -->

<!-- ═══════════════ REPORT CASE MODAL ════════════════ -->
<div class="modal fade" id="newCaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content shadow-lg">
            <div class="modal-header border-0 pt-4 px-4 pb-0">
                <div>
                    <div style="font-size:1rem;font-weight:800;color:var(--f);margin-bottom:4px;"><i class="bi bi-plus-circle-fill me-2" style="color:var(--grn);"></i>Report Welfare Situation</div>
                    <div style="font-size:.78rem;color:var(--t3);">Describe the situation to request community support.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="mb-3">
                    <label class="form-label">Situation Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Hospitalization, Bereavement…" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Requested Amount (KES)</label>
                    <div class="input-group">
                        <span class="input-group-text" style="background:var(--grn-bg);color:var(--grn);border-color:var(--bdr);font-weight:800;font-size:.8rem;border-radius:10px 0 0 10px;">KES</span>
                        <input type="number" name="requested_amount" class="form-control" style="border-radius:0 10px 10px 0;" placeholder="0.00" step="0.01" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Detailed Description</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Provide as much detail as possible…" required></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_case" class="btn-modal-submit">
                        <i class="bi bi-send-fill"></i> Submit Request
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ── Animate bars + progress fills
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.querySelectorAll('[data-w]').forEach(el => {
            el.style.width = el.dataset.w + '%';
        });
    }, 480);
});

// ── Animated balance counter
(function () {
    const el  = document.getElementById('heroAmt');
    if (!el) return;
    const raw = parseFloat(el.textContent.replace(/,/g,''));
    if (!raw || isNaN(raw)) return;
    const dur = 1500, t0 = performance.now();
    const fmt = n => n.toLocaleString('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2});
    function tick(now) {
        const p = Math.min((now-t0)/dur,1), e = 1-Math.pow(1-p,4);
        el.textContent = fmt(e*raw);
        if (p<1) requestAnimationFrame(tick); else el.textContent = fmt(raw);
    }
    requestAnimationFrame(tick);
})();

// ── Contribution chart
(function () {
    const ctx   = document.getElementById('welfareChart').getContext('2d');
    const grad  = ctx.createLinearGradient(0,0,0,240);
    grad.addColorStop(0,'rgba(163,230,53,0.28)');
    grad.addColorStop(1,'rgba(163,230,53,0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels:   <?= $json_labels ?>,
            datasets: [{ data:<?= $json_values ?>, borderColor:'#7db820', borderWidth:2.5, backgroundColor:grad, fill:true, tension:0.42, pointRadius:0, pointHoverRadius:5, pointHoverBackgroundColor:'#a3e635' }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins: {
                legend: { display:false },
                tooltip: {
                    backgroundColor:'#0b2419', titleColor:'#a3e635', bodyColor:'#fff',
                    padding:12, cornerRadius:10,
                    titleFont:{ family:"'Plus Jakarta Sans',sans-serif",size:11,weight:'800' },
                    bodyFont: { family:"'Plus Jakarta Sans',sans-serif",size:11 },
                    callbacks:{ label: c => ' KES ' + c.parsed.y.toLocaleString('en-KE',{minimumFractionDigits:0}) }
                }
            },
            scales: {
                x: { grid:{ display:false }, ticks:{ font:{ family:"'Plus Jakarta Sans',sans-serif",size:10,weight:'700' }, color:'#8fada0', maxTicksLimit:8 }, border:{ display:false } },
                y: { grid:{ color:'rgba(11,36,25,0.04)' }, ticks:{ font:{ family:"'Plus Jakarta Sans',sans-serif",size:10,weight:'700' }, color:'#8fada0', callback: v => 'KES '+(v>=1000?(v/1000).toFixed(0)+'K':v), maxTicksLimit:5 }, border:{ display:false }, beginAtZero:true }
            }
        }
    });
})();

// ── Custom tab switcher
document.querySelectorAll('.tpill').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tpill').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('show'));
        this.classList.add('active');
        const target = document.getElementById('tab-' + this.dataset.tab);
        if (target) target.classList.add('show');
    });
});

// ── Table search
document.getElementById('tableSearch')?.addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const active = document.querySelector('.tab-pane.show');
    if (!active) return;
    active.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
});
</script>
</body>
</html>