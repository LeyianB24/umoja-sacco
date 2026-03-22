<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');
require_member();
$layout = LayoutManager::create('member');

$member_id   = $_SESSION['member_id'];
$member_name = $_SESSION['member_name'] ?? 'Member';

$page             = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset           = ($page - 1) * $records_per_page;

$filter_from = $_GET['from']  ?? '';
$filter_to   = $_GET['to']    ?? '';
$filter_type = $_GET['type']  ?? '';

// ── Stats ──────────────────────────────────────────────────
$stmt_stats = $conn->prepare("SELECT
    COALESCE(SUM(amount),0) as grand_total,
    COALESCE(SUM(CASE WHEN contribution_type='savings' THEN amount ELSE 0 END),0) as total_savings,
    COALESCE(SUM(CASE WHEN contribution_type='shares'  THEN amount ELSE 0 END),0) as total_shares,
    COALESCE(SUM(CASE WHEN contribution_type='welfare' THEN amount ELSE 0 END),0) as total_welfare,
    COUNT(*) as total_count,
    COUNT(CASE WHEN contribution_type='savings' THEN 1 END) as count_savings,
    COUNT(CASE WHEN contribution_type='shares'  THEN 1 END) as count_shares,
    COUNT(CASE WHEN contribution_type='welfare' THEN 1 END) as count_welfare
    FROM contributions WHERE member_id = ?");
$stmt_stats->bind_param("i", $member_id);
$stmt_stats->execute();
$stats       = $stmt_stats->get_result()->fetch_assoc();
$savings_val = (float)($stats['total_savings'] ?? 0);
$shares_val  = (float)($stats['total_shares']  ?? 0);
$welfare_val = (float)($stats['total_welfare'] ?? 0);
$grand_total = (float)($stats['grand_total']   ?? 0);
$total_count = (int)($stats['total_count']     ?? 0);
$cnt_savings = (int)($stats['count_savings']   ?? 0);
$cnt_shares  = (int)($stats['count_shares']    ?? 0);
$cnt_welfare = (int)($stats['count_welfare']   ?? 0);

// Use ledger engine for the real savings balance (matches dashboard)
require_once __DIR__ . '/../../inc/FinancialEngine.php';
$_engine = new FinancialEngine($conn);
$_balances = $_engine->getBalances((int)$member_id);
$ledger_savings = (float)$_balances['savings']; // Authoritative savings figure

// ── Monthly trend – last 7 months ──────────────────────────
$trend_labels  = $trend_savings = $trend_shares = $trend_welfare = [];
for ($i = 6; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("-$i months"));
    $me = date('Y-m-t',  strtotime("-$i months"));
    $trend_labels[] = date('M', strtotime($ms));
    $stmt_t = $conn->prepare("SELECT
        COALESCE(SUM(CASE WHEN contribution_type='savings' THEN amount ELSE 0 END),0) as sv,
        COALESCE(SUM(CASE WHEN contribution_type='shares'  THEN amount ELSE 0 END),0) as sh,
        COALESCE(SUM(CASE WHEN contribution_type='welfare' THEN amount ELSE 0 END),0) as wf
        FROM contributions WHERE member_id=? AND DATE(created_at) BETWEEN ? AND ?");
    $stmt_t->bind_param("iss", $member_id, $ms, $me); $stmt_t->execute();
    $tr = $stmt_t->get_result()->fetch_assoc();
    $trend_savings[] = round((float)$tr['sv'], 2);
    $trend_shares[]  = round((float)$tr['sh'], 2);
    $trend_welfare[] = round((float)$tr['wf'], 2);
    $stmt_t->close();
}

// ── Active days streak ─────────────────────────────────────
$stmt_streak = $conn->prepare("SELECT COUNT(DISTINCT DATE(created_at)) as active_days FROM contributions WHERE member_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt_streak->bind_param("i", $member_id); $stmt_streak->execute();
$active_days = (int)($stmt_streak->get_result()->fetch_assoc()['active_days'] ?? 0);

// ── Base query ─────────────────────────────────────────────
$sql_base = "FROM contributions WHERE member_id = ?";
$params = [$member_id]; $types = "i";
if (!empty($filter_type)) { $sql_base .= " AND contribution_type = ?"; $params[] = $filter_type; $types .= "s"; }
if (!empty($filter_from) && !empty($filter_to)) { $sql_base .= " AND DATE(created_at) BETWEEN ? AND ?"; $params[] = $filter_from; $params[] = $filter_to; $types .= "ss"; }

$stmt_count = $conn->prepare("SELECT COUNT(*) as total " . $sql_base);
$ref_params = []; foreach ($params as $k => $v) $ref_params[$k] = &$params[$k];
$stmt_count->bind_param($types, ...$ref_params); $stmt_count->execute();
$total_rows  = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = (int)ceil($total_rows / $records_per_page);

$stmt = $conn->prepare("SELECT contribution_id, reference_no, contribution_type, amount, payment_method, created_at, status " . $sql_base . " ORDER BY created_at DESC LIMIT ?, ?");
$all_params = array_merge($params, [$offset, $records_per_page]);
$final_refs = []; foreach ($all_params as $k => $v) $final_refs[$k] = &$all_params[$k];
$stmt->bind_param($types . "ii", ...$final_refs);
$stmt->execute();
$result = $stmt->get_result();

// ── Export ─────────────────────────────────────────────────
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf','export_excel','print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    $format  = $_GET['action'] === 'export_excel' ? 'excel' : ($_GET['action'] === 'print_report' ? 'print' : 'pdf');
    $stmt_ex = $conn->prepare("SELECT reference_no, contribution_type, amount, payment_method, created_at, status " . $sql_base . " ORDER BY created_at DESC");
    $stmt_ex->bind_param($types, ...$params); $stmt_ex->execute();
    $res_ex = $stmt_ex->get_result();
    $data   = [];
    while ($row = $res_ex->fetch_assoc())
        $data[] = ['Date'=>date('d-M-Y H:i',strtotime($row['created_at'])),'Type'=>ucwords(str_replace('_',' ',$row['contribution_type'])),'Reference'=>$row['reference_no']?:'-','Method'=>$row['payment_method']?:'M-Pesa','Amount'=>'+ '.number_format((float)$row['amount'],2),'Status'=>ucfirst($row['status'])];
    $stmt_ex->close();
    UniversalExportEngine::handle($format,$data,['title'=>'Contribution History','module'=>'Member Portal','headers'=>['Date','Type','Reference','Method','Amount','Status']]);
    exit;
}

$pageTitle = "My Contributions";
$safe_gt   = $grand_total ?: 1;

function ks(float $n): string {
    if ($n >= 1_000_000) return 'KES '.number_format($n/1_000_000, 2).'M';
    if ($n >= 1_000)     return 'KES '.number_format($n/1_000, 1).'K';
    return 'KES '.number_format($n, 2);
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<script>(function(){var s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> · <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'SACCO' ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════
   CONTRIBUTIONS · HD EDITION · Forest & Lime
═══════════════════════════════════════════════════════════ */
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

:root {
    --f:      #0b2419;  --fm: #154330;  --fs: #1d6044;
    --lime:   #a3e635;  --lt: #6a9a1a;  --lg: rgba(163,230,53,.14);
    --bg:     #eff5f1;  --bg2: #e8f1ec;
    --surf:   #ffffff;  --surf2: #f7fbf8;
    --bdr:    rgba(11,36,25,.07);  --bdr2: rgba(11,36,25,.04);
    --t1: #0b2419;  --t2: #456859;  --t3: #8fada0;
    --grn:    #16a34a;  --red: #dc2626;  --amb: #d97706;  --blu: #2563eb;  --pur: #7c4dff;
    --grn-bg: rgba(22,163,74,.08);    --red-bg: rgba(220,38,38,.08);
    --amb-bg: rgba(217,119,6,.08);    --blu-bg: rgba(37,99,235,.08);
    --pur-bg: rgba(124,77,255,.08);
    --c-sav: #16a34a;  --c-sha: #2563eb;  --c-wel: #dc2626;
    --r:   20px;  --rsm: 12px;
    --ease:   cubic-bezier(.16,1,.3,1);
    --spring: cubic-bezier(.34,1.56,.64,1);
    --sh:     0 1px 3px rgba(11,36,25,.05), 0 6px 20px rgba(11,36,25,.08);
    --sh-lg:  0 4px 8px rgba(11,36,25,.07), 0 20px 56px rgba(11,36,25,.13);
}

[data-bs-theme="dark"] {
    --bg:   #070e0b;  --bg2:  #0a1510;
    --surf: #0d1d14;  --surf2: #0a1810;
    --bdr:  rgba(255,255,255,.07);  --bdr2: rgba(255,255,255,.04);
    --t1:   #d8eee2;  --t2: #4d7a60;  --t3: #2a4d38;
}

body,* { font-family:'Plus Jakarta Sans',sans-serif !important; -webkit-font-smoothing:antialiased; }
body   { background:var(--bg); color:var(--t1); }

.main-content-wrapper { margin-left:272px; min-height:100vh; transition:margin-left .3s var(--ease); }
body.sb-collapsed .main-content-wrapper { margin-left:72px; }
@media(max-width:991px){ .main-content-wrapper{margin-left:0} }
.dash { padding:0 0 72px; }

/* ─────────────────────────────────────────────
   HERO
───────────────────────────────────────────── */
.hero {
    background:linear-gradient(135deg,var(--f) 0%,var(--fm) 55%,var(--fs) 100%);
    position:relative; overflow:hidden; color:#fff;
    animation:fadeUp .7s var(--ease) both;
}
.hero-mesh { position:absolute;inset:0;pointer-events:none;background:radial-gradient(ellipse 60% 80% at 108% -5%,rgba(163,230,53,.11) 0%,transparent 55%),radial-gradient(ellipse 40% 55% at -8% 110%,rgba(163,230,53,.07) 0%,transparent 55%); }
.hero-dots { position:absolute;inset:0;pointer-events:none;background-image:radial-gradient(rgba(255,255,255,.05) 1px,transparent 1px);background-size:20px 20px; }
.hero-ring { position:absolute;border-radius:50%;pointer-events:none;border:1px solid rgba(163,230,53,.07); }
.hero-ring.r1{width:480px;height:480px;top:-160px;right:-120px}
.hero-ring.r2{width:700px;height:700px;top:-260px;right:-230px}

.hero-top { position:relative;z-index:2; padding:44px 52px 32px; display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap; }
@media(max-width:767px){ .hero-top{padding:28px 20px 22px} }

.hero-eyebrow { display:inline-flex;align-items:center;gap:7px;background:rgba(163,230,53,.12);border:1px solid rgba(163,230,53,.2);border-radius:50px;padding:4px 14px;margin-bottom:14px;font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#bff060; }
.eyebrow-dot  { width:5px;height:5px;border-radius:50%;background:var(--lime);animation:pulse 1.8s ease-in-out infinite; }
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(1.8)}}

.hero h1 { font-size:clamp(1.8rem,3.8vw,2.6rem);font-weight:800;color:#fff;letter-spacing:-.6px;line-height:1.1;margin-bottom:6px; }
.hero-sub { font-size:.8rem;color:rgba(255,255,255,.45);font-weight:500; }

/* hero CTA buttons */
.hero-ctas { display:flex;gap:9px;flex-wrap:wrap; }
.btn-lime  { display:inline-flex;align-items:center;gap:8px;background:var(--lime);color:var(--f);font-size:.875rem;font-weight:800;padding:11px 24px;border-radius:50px;border:none;cursor:pointer;text-decoration:none;box-shadow:0 2px 14px rgba(163,230,53,.28);transition:all .25s var(--spring); }
.btn-lime:hover { transform:translateY(-2px) scale(1.03);box-shadow:0 10px 28px rgba(163,230,53,.4);color:var(--f); }
.btn-ghost { display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);font-size:.875rem;font-weight:700;padding:11px 20px;border-radius:50px;cursor:pointer;text-decoration:none;transition:all .22s ease; }
.btn-ghost:hover { background:rgba(255,255,255,.17);color:#fff;transform:translateY(-2px); }

/* export dropdown */
.exp-dd { border-radius:16px !important;padding:7px !important;border-color:var(--bdr) !important;box-shadow:var(--sh-lg) !important;background:var(--surf) !important;min-width:185px; }
.dd-item { display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;text-decoration:none;font-size:.82rem;font-weight:600;color:var(--t1);transition:background .14s ease; }
.dd-item:hover { background:var(--bg);color:var(--t1); }
.dd-ic { width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.88rem;flex-shrink:0; }

/* ─────────────────────────────────────────────
   HERO STAT BAND (bottom of hero)
───────────────────────────────────────────── */
.hero-band {
    display:grid; grid-template-columns:repeat(4,1fr);
    gap:1px; background:rgba(255,255,255,.07);
    position:relative; z-index:2;
}
@media(max-width:900px){ .hero-band{grid-template-columns:repeat(2,1fr)} }
@media(max-width:520px){ .hero-band{grid-template-columns:1fr} }

.hb-cell { background:rgba(255,255,255,.04); padding:24px 28px; transition:background .2s; }
.hb-cell:hover { background:rgba(255,255,255,.09); }
@media(max-width:767px){ .hb-cell{padding:18px 20px} }

.hbc-eyebrow { display:flex;align-items:center;gap:7px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.35);margin-bottom:10px; }
.hbc-ico { width:22px;height:22px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:.65rem; }
.hbc-val { font-size:1.6rem;font-weight:800;color:#fff;letter-spacing:-.5px;line-height:1;margin-bottom:6px; }
.hbc-meta { display:flex;align-items:center;gap:7px;font-size:.68rem;font-weight:600;color:rgba(255,255,255,.32); }
.hbc-pill { background:rgba(163,230,53,.18);color:var(--lime);font-size:.62rem;font-weight:800;padding:2px 8px;border-radius:50px; }

@keyframes fadeUp  { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes floatUp { from{opacity:0;transform:translateY(28px)} to{opacity:1;transform:translateY(0)} }

/* ─────────────────────────────────────────────
   FLOATING STAT CARDS
───────────────────────────────────────────── */
.stats-float { margin-top:-56px;position:relative;z-index:10;padding:0 52px;animation:floatUp .8s var(--ease) .4s both; }
@media(max-width:767px){ .stats-float{padding:0 16px} }

.sc { background:var(--surf);border-radius:var(--r);padding:22px 24px;border:1px solid var(--bdr);box-shadow:var(--sh-lg);height:100%;position:relative;overflow:hidden;transition:transform .28s var(--ease),box-shadow .28s ease; }
.sc:hover { transform:translateY(-5px);box-shadow:0 8px 20px rgba(11,36,25,.09),0 36px 70px rgba(11,36,25,.14); }
.sc::after { content:'';position:absolute;bottom:0;left:0;right:0;height:2.5px;border-radius:0 0 var(--r) var(--r);transform:scaleX(0);transform-origin:left;transition:transform .38s var(--ease); }
.sc:hover::after { transform:scaleX(1); }
.sc-g::after { background:linear-gradient(90deg,#16a34a,#4ade80); }
.sc-b::after { background:linear-gradient(90deg,#2563eb,#60a5fa); }
.sc-r::after { background:linear-gradient(90deg,#dc2626,#f87171); }
.sc-l::after { background:linear-gradient(90deg,var(--lime),#d4f98a); }
.sc-ico { width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;margin-bottom:16px;transition:transform .3s var(--spring); }
.sc:hover .sc-ico { transform:scale(1.12) rotate(7deg); }
.sc-lbl { font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--t3);margin-bottom:5px; }
.sc-val { font-size:1.5rem;font-weight:800;color:var(--t1);letter-spacing:-.8px;line-height:1.1;margin-bottom:14px; }
.sc-bar { height:4px;border-radius:99px;background:var(--bg);overflow:hidden;margin-bottom:9px; }
.sc-bar-fill { height:100%;border-radius:99px;width:0;transition:width 1.4s var(--ease); }
.sc-meta { font-size:.72rem;font-weight:600;color:var(--t3); }
.sa1{animation:floatUp .7s var(--ease) .45s both}
.sa2{animation:floatUp .7s var(--ease) .53s both}
.sa3{animation:floatUp .7s var(--ease) .61s both}
.sa4{animation:floatUp .7s var(--ease) .69s both}

/* ─────────────────────────────────────────────
   PAGE BODY
───────────────────────────────────────────── */
.pg-body { padding:32px 52px 0; }
@media(max-width:767px){ .pg-body{padding:24px 16px 0} }

/* ── DUAL PANEL ── */
.dual-panel { display:grid;grid-template-columns:1fr 320px;gap:18px;margin-bottom:20px; }
@media(max-width:1080px){ .dual-panel{grid-template-columns:1fr} }

/* Chart card */
.chart-card { background:var(--surf);border-radius:var(--r);padding:24px 26px;border:1px solid var(--bdr);box-shadow:var(--sh);display:flex;flex-direction:column;animation:floatUp .7s var(--ease) .72s both; }
.cc-head { display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:16px;flex-wrap:wrap; }
.cc-title { font-size:.9rem;font-weight:800;color:var(--t1);letter-spacing:-.2px; }
.cc-sub   { font-size:.7rem;font-weight:500;color:var(--t3);margin-top:2px; }
.chart-box { position:relative;flex:1;height:240px; }

.leg { display:flex;gap:12px;flex-wrap:wrap;margin-top:10px; }
.leg-i { display:flex;align-items:center;gap:5px;font-size:.68rem;font-weight:700;color:var(--t3); }
.leg-dot { width:8px;height:8px;border-radius:50%;flex-shrink:0; }

/* Ring card */
.ring-card { background:var(--surf);border-radius:var(--r);padding:24px 26px;border:1px solid var(--bdr);box-shadow:var(--sh);display:flex;flex-direction:column;animation:floatUp .7s var(--ease) .78s both; }
.ring-box { position:relative;width:160px;height:160px;margin:0 auto 14px; }
.ring-center { position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;pointer-events:none; }
.ring-center-val { font-size:.9rem;font-weight:800;color:var(--t1);letter-spacing:-.3px;line-height:1.1; }
.ring-center-sub { font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--t3);margin-top:2px; }

/* Ring breakdown bars */
.rb-list { display:flex;flex-direction:column;gap:12px; }
.rb-row  { display:grid;grid-template-columns:1fr auto;align-items:center;gap:8px; }
.rb-left { display:flex;align-items:center;gap:8px; }
.rb-ico  { width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.72rem;flex-shrink:0; }
.rb-name { font-size:.76rem;font-weight:700;color:var(--t2); }
.rb-val  { font-size:.8rem;font-weight:800;color:var(--t1); }
.rb-bar-wrap { height:3px;background:var(--bg);border-radius:99px;overflow:hidden;grid-column:1/-1;margin-top:-4px; }
.rb-bar-fill { height:100%;border-radius:99px;width:0;transition:width 1.4s var(--ease); }

/* ── FILTER / TAB ROW ── */
.filter-row { display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:14px;flex-wrap:wrap;animation:floatUp .7s var(--ease) .84s both; }

.type-tabs { display:flex;gap:6px;flex-wrap:wrap; }
.type-tab { display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:50px;font-size:.76rem;font-weight:800;text-decoration:none;border:1.5px solid var(--bdr);background:var(--surf);color:var(--t2);transition:all .2s var(--ease); }
.type-tab:hover { border-color:rgba(11,36,25,.18);color:var(--t1); }
.tc-badge { font-size:.6rem;background:var(--bg);color:var(--t3);padding:1px 7px;border-radius:50px;font-weight:800; }

.tt-all     .type-tab.act { background:var(--f);color:#fff;border-color:transparent;box-shadow:0 4px 14px rgba(11,36,25,.2); }
.tt-all     .type-tab.act .tc-badge { background:rgba(255,255,255,.18);color:rgba(255,255,255,.7); }
.tt-savings .type-tab.act { background:var(--grn);color:#fff;border-color:transparent;box-shadow:0 4px 14px rgba(22,163,74,.3); }
.tt-shares  .type-tab.act { background:var(--blu);color:#fff;border-color:transparent;box-shadow:0 4px 14px rgba(37,99,235,.3); }
.tt-welfare .type-tab.act { background:var(--red);color:#fff;border-color:transparent;box-shadow:0 4px 14px rgba(220,38,38,.3); }

/* date filter */
.date-form { display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap; }
.df-grp { display:flex;flex-direction:column;gap:4px; }
.df-lbl { font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.9px;color:var(--t3); }
.df-ctrl { background:var(--surf);border:1px solid var(--bdr);border-radius:var(--rsm);padding:8px 12px;font-size:.78rem;font-weight:600;color:var(--t1);outline:none;transition:border-color .18s ease; }
.df-ctrl:focus { border-color:rgba(11,36,25,.28); }
.btn-filter { display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:50px;background:var(--f);color:#fff;border:none;font-size:.78rem;font-weight:800;cursor:pointer;transition:all .22s var(--ease); }
.btn-filter:hover { background:var(--fm);transform:translateY(-1px);box-shadow:0 5px 14px rgba(11,36,25,.18); }
.btn-clear { display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50px;background:var(--surf);border:1px solid var(--bdr);color:var(--t3);text-decoration:none;transition:all .18s ease; }
.btn-clear:hover { border-color:rgba(220,38,38,.3);color:var(--red); }

/* active filter pills */
.active-filter-pills { display:flex;gap:7px;flex-wrap:wrap;align-items:center;margin-bottom:12px; }
.afp-lbl  { font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.9px;color:var(--t3); }
.afp-pill { display:inline-flex;align-items:center;gap:5px;background:var(--lg);border:1px solid rgba(163,230,53,.22);border-radius:50px;padding:3px 11px;font-size:.72rem;font-weight:700;color:var(--lt); }
[data-bs-theme="dark"] .afp-pill { color:var(--lime); }
.afp-pill span { opacity:.6; }
.afp-clear { font-size:.72rem;font-weight:700;color:var(--t3);text-decoration:none;margin-left:4px;transition:color .15s ease; }
.afp-clear:hover { color:var(--red); }

/* ── LEDGER TABLE CARD ── */
.ledger-card { background:var(--surf);border-radius:var(--r);border:1px solid var(--bdr);box-shadow:var(--sh);overflow:hidden;animation:floatUp .7s var(--ease) .88s both; }
.lc-head { display:flex;align-items:center;justify-content:space-between;padding:18px 26px;border-bottom:1px solid var(--bdr2);background:var(--surf2);flex-wrap:wrap;gap:12px; }
.lc-title { font-size:.88rem;font-weight:800;color:var(--t1); }
.lc-meta  { display:flex;align-items:center;gap:10px; }
.lc-badge { display:inline-flex;align-items:center;gap:4px;background:var(--lg);border:1px solid rgba(163,230,53,.25);border-radius:50px;padding:3px 10px;font-size:9.5px;font-weight:800;color:var(--lt); }
[data-bs-theme="dark"] .lc-badge { color:var(--lime); }
.lc-pg { font-size:.7rem;font-weight:600;color:var(--t3); }

/* Table */
.ct { width:100%;border-collapse:collapse; }
.ct thead th { background:var(--surf2);font-size:9.5px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:var(--t3);padding:11px 18px;border:none;border-bottom:1px solid var(--bdr2);white-space:nowrap; }

/* date separator */
.ct-sep td { padding:9px 26px 6px;background:var(--bg2);border-top:1px solid var(--bdr2);border-bottom:1px solid var(--bdr2); }
.ct-sep-inner { display:flex;align-items:center;gap:10px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--t3); }
.ct-sep-inner::after { content:'';flex:1;height:1px;background:var(--bdr); }
.ct-sep-today .ct-sep-inner { color:var(--grn); }
.ct-today-badge { background:var(--grn-bg);color:var(--grn);font-size:9px;font-weight:800;padding:2px 8px;border-radius:50px; }

/* data rows */
.ct-row { border-bottom:1px solid var(--bdr2);transition:background .13s ease; }
.ct-row:last-child { border-bottom:none; }
.ct-row:hover { background:rgba(11,36,25,.018); }
.ct-row td { padding:13px 18px;vertical-align:middle; }

/* type left stripe */
.ct-row.t-sav td:first-child { box-shadow:inset 3px 0 0 var(--c-sav); }
.ct-row.t-sha td:first-child { box-shadow:inset 3px 0 0 var(--c-sha); }
.ct-row.t-wel td:first-child { box-shadow:inset 3px 0 0 var(--c-wel); }

/* icon puck */
.ct-ico { width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;transition:transform .25s var(--spring); }
.ct-row:hover .ct-ico { transform:scale(1.1) rotate(5deg); }
.ico-sav { background:var(--grn-bg);color:var(--grn); }
.ico-sha { background:var(--blu-bg);color:var(--blu); }
.ico-wel { background:var(--red-bg);color:var(--red); }
.ico-def { background:var(--bg);color:var(--t2); }

.ct-name   { font-size:.85rem;font-weight:700;color:var(--t1);text-transform:capitalize; }
.ct-method { font-size:.68rem;font-weight:600;color:var(--t3);margin-top:2px;display:flex;align-items:center;gap:4px; }
.ct-method-dot { width:3px;height:3px;border-radius:50%;background:var(--t3); }

.ct-date { font-size:.82rem;font-weight:700;color:var(--t1); }
.ct-time { font-size:.65rem;font-weight:500;color:var(--t3);margin-top:2px; }

.ct-ref { font-family:monospace;font-size:.7rem;color:var(--t3);background:var(--bg);border:1px solid var(--bdr);border-radius:7px;padding:3px 9px;display:inline-block; }

/* status chips */
.sc-chip { display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:7px;font-size:9.5px;font-weight:800;letter-spacing:.3px; }
.sc-chip::before { content:'';width:4px;height:4px;border-radius:50%;background:currentColor; }
.chip-ok  { background:var(--grn-bg);color:var(--grn); }
.chip-pnd { background:var(--amb-bg);color:var(--amb); }
.chip-err { background:var(--red-bg);color:var(--red); }

.ct-amount     { font-size:.9rem;font-weight:800;color:var(--grn); }
.ct-amount-sub { font-size:.62rem;font-weight:600;color:var(--t3);text-align:right;margin-top:2px; }

/* row animation */
@keyframes rowIn { from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.ct-row { animation:rowIn .28s var(--ease) both; }
<?php for($ri=1;$ri<=10;$ri++) echo ".ct-row:nth-child($ri){animation-delay:".($ri*0.035)."s}"; ?>

/* empty state */
.empty-well { display:flex;flex-direction:column;align-items:center;padding:64px 24px;text-align:center; }
.ew-ico { width:72px;height:72px;border-radius:20px;background:var(--bg);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:var(--t3);margin-bottom:18px; }
.ew-title { font-size:.9rem;font-weight:800;color:var(--t1);margin-bottom:5px; }
.ew-sub   { font-size:.78rem;color:var(--t3);margin-bottom:18px; }
.btn-deposit { display:inline-flex;align-items:center;gap:7px;background:var(--f);color:#fff;font-size:.82rem;font-weight:800;padding:11px 22px;border-radius:50px;text-decoration:none;box-shadow:0 3px 14px rgba(11,36,25,.2);transition:all .22s var(--ease); }
.btn-deposit:hover { background:var(--fm);transform:translateY(-2px);box-shadow:0 8px 20px rgba(11,36,25,.25);color:#fff; }

/* ── PAGINATION ── */
.lc-footer { display:flex;align-items:center;justify-content:space-between;padding:14px 26px;border-top:1px solid var(--bdr2);flex-wrap:wrap;gap:10px; }
.pag-info  { font-size:.7rem;font-weight:600;color:var(--t3); }
.pag-btns  { display:flex;gap:4px; }
.pag-btn   { width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.76rem;font-weight:700;text-decoration:none;color:var(--t2);background:var(--bg);border:1px solid var(--bdr);transition:all .18s ease; }
.pag-btn:hover:not(.pag-active):not(.pag-dis) { border-color:rgba(11,36,25,.2);color:var(--t1); }
.pag-active { background:var(--f);color:#fff !important;border-color:transparent;box-shadow:0 3px 10px rgba(11,36,25,.2); }
.pag-dis    { opacity:.3;pointer-events:none; }

@media print { .no-print{display:none !important} body{background:#fff} }
::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--bdr);border-radius:99px}
</style>
</head>
<body>
<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
<?php $layout->topbar($pageTitle ?? ''); ?>
<div class="dash">

<!-- ══════════════════════════════
     HERO
══════════════════════════════ -->
<div class="hero">
    <div class="hero-mesh"></div>
    <div class="hero-dots"></div>
    <div class="hero-ring r1"></div>
    <div class="hero-ring r2"></div>

    <!-- Top row -->
    <div class="hero-top">
        <div>
            <div class="hero-eyebrow"><span class="eyebrow-dot"></span> Financial Record</div>
            <h1>My Contributions</h1>
            <p class="hero-sub">Complete history of your savings, shares &amp; welfare deposits</p>
        </div>
        <div class="hero-ctas no-print">
            <div class="dropdown">
                <button class="btn-ghost dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-cloud-download-fill"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end exp-dd mt-2">
                    <li><a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'export_pdf'])) ?>"><div class="dd-ic" style="background:rgba(220,38,38,.09);color:#dc2626"><i class="bi bi-file-pdf-fill"></i></div> Export PDF</a></li>
                    <li><a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'export_excel'])) ?>"><div class="dd-ic" style="background:rgba(5,150,105,.09);color:#059669"><i class="bi bi-file-earmark-spreadsheet-fill"></i></div> Export Excel</a></li>
                    <li><a class="dd-item" href="?<?= http_build_query(array_merge($_GET,['action'=>'print_report'])) ?>" target="_blank"><div class="dd-ic" style="background:rgba(99,102,241,.09);color:#6366f1"><i class="bi bi-printer-fill"></i></div> Print Statement</a></li>
                </ul>
            </div>
            <a href="<?= BASE_URL ?>/member/pages/mpesa_request.php" class="btn-lime">
                <i class="bi bi-plus-circle-fill"></i> New Deposit
            </a>
        </div>
    </div>

    <!-- Stat band -->
    <div class="hero-band">
        <div class="hb-cell">
            <div class="hbc-eyebrow">
                <div class="hbc-ico" style="background:rgba(163,230,53,.15);color:var(--lime)"><i class="bi bi-layers-fill"></i></div>
                Portfolio Total
            </div>
            <div class="hbc-val" id="ctr-total"><?= ks($grand_total) ?></div>
            <div class="hbc-meta"><?= number_format($total_count) ?> transactions <span class="hbc-pill">All time</span></div>
        </div>
        <div class="hb-cell">
            <div class="hbc-eyebrow">
                <div class="hbc-ico" style="background:rgba(163,230,53,.15);color:var(--lime)"><i class="bi bi-piggy-bank-fill"></i></div>
                Savings
            </div>
            <div class="hbc-val" id="ctr-sav"><?= ks($savings_val) ?></div>
            <div class="hbc-meta"><?= $cnt_savings ?> deposits <span class="hbc-pill"><?= $grand_total>0?round(($savings_val/$safe_gt)*100):0 ?>%</span></div>
        </div>
        <div class="hb-cell">
            <div class="hbc-eyebrow">
                <div class="hbc-ico" style="background:rgba(163,230,53,.15);color:var(--lime)"><i class="bi bi-pie-chart-fill"></i></div>
                Shares Capital
            </div>
            <div class="hbc-val" id="ctr-sha"><?= ks($shares_val) ?></div>
            <div class="hbc-meta"><?= $cnt_shares ?> deposits <span class="hbc-pill"><?= $grand_total>0?round(($shares_val/$safe_gt)*100):0 ?>%</span></div>
        </div>
        <div class="hb-cell">
            <div class="hbc-eyebrow">
                <div class="hbc-ico" style="background:rgba(163,230,53,.15);color:var(--lime)"><i class="bi bi-heart-pulse-fill"></i></div>
                Welfare Fund
            </div>
            <div class="hbc-val" id="ctr-wel"><?= ks($welfare_val) ?></div>
            <div class="hbc-meta"><?= $active_days ?> active days <span class="hbc-pill">30d</span></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════
     FLOATING STAT CARDS
══════════════════════════════ -->
<div class="stats-float">
    <div class="row g-3">
        <div class="col-md-3 sa1">
            <div class="sc sc-g">
                <div class="sc-ico" style="background:var(--grn-bg);color:var(--grn)"><i class="bi bi-piggy-bank-fill"></i></div>
                <div class="sc-lbl">Savings Balance</div>
                <div class="sc-val"><?= ks($ledger_savings) ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--grn)" data-w="100"></div></div>
                <div class="sc-meta"><?= $cnt_savings ?> deposits &middot; Ledger balance</div>
            </div>
        </div>
        <div class="col-md-3 sa2">
            <div class="sc sc-b">
                <div class="sc-ico" style="background:var(--blu-bg);color:var(--blu)"><i class="bi bi-pie-chart-fill"></i></div>
                <div class="sc-lbl">Shares Capital</div>
                <div class="sc-val"><?= ks($shares_val) ?></div>
                <?php $sPct = $grand_total>0?round(($shares_val/$safe_gt)*100):0; ?>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--blu)" data-w="<?= $sPct ?>"></div></div>
                <div class="sc-meta"><?= $sPct ?>% of total portfolio</div>
            </div>
        </div>
        <div class="col-md-3 sa3">
            <div class="sc sc-r">
                <div class="sc-ico" style="background:var(--red-bg);color:var(--red)"><i class="bi bi-heart-pulse-fill"></i></div>
                <div class="sc-lbl">Welfare Fund</div>
                <div class="sc-val"><?= ks($welfare_val) ?></div>
                <?php $wPct = $grand_total>0?round(($welfare_val/$safe_gt)*100):0; ?>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--red)" data-w="<?= $wPct ?>"></div></div>
                <div class="sc-meta"><?= $wPct ?>% of total portfolio</div>
            </div>
        </div>
        <div class="col-md-3 sa4">
            <div class="sc sc-l">
                <div class="sc-ico" style="background:var(--lg);color:var(--lt)"><i class="bi bi-calendar-check-fill"></i></div>
                <div class="sc-lbl">Active Days</div>
                <div class="sc-val"><?= $active_days ?></div>
                <div class="sc-bar"><div class="sc-bar-fill" style="background:var(--lime)" data-w="<?= min(100, $active_days/30*100) ?>"></div></div>
                <div class="sc-meta">In the last 30 days</div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════
     BODY
══════════════════════════════ -->
<div class="pg-body">

    <!-- Dual panel: trend chart + ring -->
    <div class="dual-panel">

        <!-- Trend Chart -->
        <div class="chart-card">
            <div class="cc-head">
                <div>
                    <div class="cc-title">Contribution Trend</div>
                    <div class="cc-sub">Monthly breakdown — last 7 months</div>
                </div>
            </div>
            <div class="leg">
                <div class="leg-i"><span class="leg-dot" style="background:var(--grn)"></span>Savings</div>
                <div class="leg-i"><span class="leg-dot" style="background:var(--blu)"></span>Shares</div>
                <div class="leg-i"><span class="leg-dot" style="background:var(--red)"></span>Welfare</div>
            </div>
            <div class="chart-box" style="margin-top:12px">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <!-- Ring card -->
        <div class="ring-card">
            <div class="cc-head">
                <div>
                    <div class="cc-title">Portfolio Mix</div>
                    <div class="cc-sub">Contribution breakdown</div>
                </div>
            </div>
            <div class="ring-box">
                <canvas id="ringChart" width="160" height="160"></canvas>
                <div class="ring-center">
                    <div class="ring-center-val"><?= ks($grand_total) ?></div>
                    <div class="ring-center-sub">Total</div>
                </div>
            </div>
            <div class="rb-list">
                <?php foreach ([
                    ['Savings', $savings_val, 'bi-piggy-bank-fill', 'var(--grn-bg)', 'var(--grn)', 'linear-gradient(90deg,#15803d,#4ade80)'],
                    ['Shares',  $shares_val,  'bi-pie-chart-fill',  'var(--blu-bg)', 'var(--blu)', 'linear-gradient(90deg,#1d4ed8,#93c5fd)'],
                    ['Welfare', $welfare_val, 'bi-heart-pulse-fill','var(--red-bg)', 'var(--red)', 'linear-gradient(90deg,#b91c1c,#fca5a5)'],
                ] as [$name,$val,$ico,$bg,$col,$grad]):
                    $pct = $grand_total > 0 ? round(($val/$safe_gt)*100) : 0;
                ?>
                <div>
                    <div class="rb-row">
                        <div class="rb-left">
                            <div class="rb-ico" style="background:<?= $bg ?>;color:<?= $col ?>"><i class="bi <?= $ico ?>"></i></div>
                            <span class="rb-name"><?= $name ?></span>
                        </div>
                        <span class="rb-val"><?= ks($val) ?></span>
                    </div>
                    <div class="rb-bar-wrap">
                        <div class="rb-bar-fill" style="background:<?= $grad ?>" data-w="<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div><!-- /dual-panel -->

    <!-- ── FILTER ROW ── -->
    <div class="filter-row no-print">
        <!-- Type tabs -->
        <div class="type-tabs">
            <?php
            $tabs = [
                ['','All','all',$total_count],
                ['savings','Savings','savings',$cnt_savings],
                ['shares','Shares','shares',$cnt_shares],
                ['welfare','Welfare','welfare',$cnt_welfare],
            ];
            foreach ($tabs as [$val,$lbl,$cls,$cnt]):
                $isAct = $filter_type === $val;
                $qp    = http_build_query(['type'=>$val,'from'=>$filter_from,'to'=>$filter_to,'page'=>1]);
            ?>
            <div class="tt-<?= $cls ?>">
                <a href="?<?= $qp ?>" class="type-tab<?= $isAct?' act':'' ?>">
                    <?= $lbl ?> <span class="tc-badge"><?= number_format($cnt) ?></span>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Date filter -->
        <form method="GET" class="date-form">
            <input type="hidden" name="type" value="<?= htmlspecialchars($filter_type) ?>">
            <div class="df-grp">
                <label class="df-lbl">From</label>
                <input type="date" name="from" value="<?= htmlspecialchars($filter_from) ?>" class="df-ctrl">
            </div>
            <div class="df-grp">
                <label class="df-lbl">To</label>
                <input type="date" name="to" value="<?= htmlspecialchars($filter_to) ?>" class="df-ctrl">
            </div>
            <button type="submit" class="btn-filter"><i class="bi bi-funnel"></i> Apply</button>
            <?php if (!empty($filter_from)): ?>
            <a href="?type=<?= urlencode($filter_type) ?>" class="btn-clear" title="Clear dates"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Active filter pills -->
    <?php if (!empty($filter_type) || !empty($filter_from)): ?>
    <div class="active-filter-pills no-print">
        <span class="afp-lbl">Filtering:</span>
        <?php if (!empty($filter_type)): ?><span class="afp-pill"><span>Type:</span> <?= ucfirst($filter_type) ?></span><?php endif; ?>
        <?php if (!empty($filter_from) && !empty($filter_to)): ?><span class="afp-pill"><span>Date:</span> <?= htmlspecialchars($filter_from) ?> → <?= htmlspecialchars($filter_to) ?></span><?php endif; ?>
        <a href="contributions.php" class="afp-clear">✕ Clear all</a>
    </div>
    <?php endif; ?>

    <!-- ── LEDGER TABLE ── -->
    <div class="ledger-card">
        <div class="lc-head">
            <div class="lc-title">Transaction History</div>
            <div class="lc-meta">
                <span class="lc-badge"><i class="bi bi-list-ul"></i> <?= number_format($total_rows) ?> records</span>
                <span class="lc-pg">Page <?= $page ?> / <?= max(1,$total_pages) ?></span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="ct">
                <thead>
                    <tr>
                        <th style="padding-left:26px;width:34%">Contribution</th>
                        <th style="width:14%">Date &amp; Time</th>
                        <th style="width:20%">Reference</th>
                        <th style="width:12%">Status</th>
                        <th style="text-align:right;padding-right:26px;width:20%">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($result->num_rows > 0):
                    $prev_date = null;
                    while ($row = $result->fetch_assoc()):
                        $type   = $row['contribution_type'];
                        $status = strtolower($row['status'] ?? 'completed');
                        $dt     = new DateTime($row['created_at']);
                        $dk     = $dt->format('Y-m-d');
                        $today  = date('Y-m-d');
                        $yest   = date('Y-m-d', strtotime('-1 day'));

                        if ($dk !== $prev_date):
                            $prev_date = $dk;
                            $isToday   = ($dk === $today);
                            $grpLabel  = match($dk) {
                                $today => 'Today',
                                $yest  => 'Yesterday',
                                default => $dt->format('l, d M Y'),
                            };
                ?>
                <tr class="ct-sep<?= $isToday?' ct-sep-today':'' ?>">
                    <td colspan="5">
                        <div class="ct-sep-inner">
                            <?= $grpLabel ?>
                            <?php if ($isToday): ?><span class="ct-today-badge"><?= $dt->format('d M Y') ?></span><?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endif;

                        $tcls  = match($type) { 'savings'=>'t-sav','shares'=>'t-sha','welfare'=>'t-wel',default=>'' };
                        $icls  = match($type) { 'savings'=>'ico-sav','shares'=>'ico-sha','welfare'=>'ico-wel',default=>'ico-def' };
                        $iname = match($type) { 'savings'=>'bi-piggy-bank-fill','shares'=>'bi-pie-chart-fill','welfare'=>'bi-heart-pulse-fill',default=>'bi-cash-stack' };
                        $stcls = match($status) { 'completed','active'=>'chip-ok','pending'=>'chip-pnd',default=>'chip-err' };
                ?>
                <tr class="ct-row <?= $tcls ?>">
                    <td style="padding-left:26px">
                        <div style="display:flex;align-items:center;gap:12px">
                            <div class="ct-ico <?= $icls ?>"><i class="bi <?= $iname ?>"></i></div>
                            <div>
                                <div class="ct-name"><?= ucfirst(str_replace('_',' ',$type)) ?></div>
                                <div class="ct-method">
                                    <span class="ct-method-dot"></span>
                                    <?= htmlspecialchars($row['payment_method'] ?? 'M-Pesa') ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="ct-date"><?= $dt->format('M d, Y') ?></div>
                        <div class="ct-time"><?= $dt->format('h:i A') ?></div>
                    </td>
                    <td><span class="ct-ref"><?= htmlspecialchars($row['reference_no'] ?? '—') ?></span></td>
                    <td><span class="sc-chip <?= $stcls ?>"><?= ucfirst($status) ?></span></td>
                    <td style="text-align:right;padding-right:26px">
                        <div class="ct-amount">+ KES <?= number_format((float)$row['amount'], 2) ?></div>
                        <div class="ct-amount-sub"><?= ucfirst($type) ?></div>
                    </td>
                </tr>
                <?php endwhile;
                else: ?>
                <tr><td colspan="5">
                    <div class="empty-well">
                        <div class="ew-ico"><i class="bi bi-receipt-cutoff"></i></div>
                        <div class="ew-title">No Contributions Found</div>
                        <div class="ew-sub"><?= (!empty($filter_type)||!empty($filter_from)) ? 'Try adjusting your filters.' : 'Make your first deposit to get started.' ?></div>
                        <?php if (!empty($filter_type)||!empty($filter_from)): ?>
                        <a href="contributions.php" class="btn-deposit"><i class="bi bi-x-circle-fill"></i> Clear Filters</a>
                        <?php else: ?>
                        <a href="<?= BASE_URL ?>/member/pages/mpesa_request.php" class="btn-deposit"><i class="bi bi-plus-circle-fill"></i> Make a Deposit</a>
                        <?php endif; ?>
                    </div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="lc-footer no-print">
            <span class="pag-info">Showing <?= $offset+1 ?>–<?= min($offset+$records_per_page,$total_rows) ?> of <?= number_format($total_rows) ?></span>
            <div class="pag-btns">
                <a class="pag-btn <?= $page<=1?'pag-dis':'' ?>" href="?page=<?= $page-1 ?>&type=<?= urlencode($filter_type) ?>&from=<?= $filter_from ?>&to=<?= $filter_to ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php
                $pstart = max(1,$page-2); $pend = min($total_pages,$page+2);
                if ($pstart>1) echo '<span class="pag-btn pag-dis" style="border:none;background:transparent">…</span>';
                for ($pi=$pstart;$pi<=$pend;$pi++):
                ?>
                <a class="pag-btn <?= $page==$pi?'pag-active':'' ?>"
                   href="?page=<?= $pi ?>&type=<?= urlencode($filter_type) ?>&from=<?= $filter_from ?>&to=<?= $filter_to ?>">
                    <?= $pi ?>
                </a>
                <?php endfor;
                if ($pend<$total_pages) echo '<span class="pag-btn pag-dis" style="border:none;background:transparent">…</span>';
                ?>
                <a class="pag-btn <?= $page>=$total_pages?'pag-dis':'' ?>" href="?page=<?= $page+1 ?>&type=<?= urlencode($filter_type) ?>&from=<?= $filter_from ?>&to=<?= $filter_to ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /ledger-card -->

</div><!-- /pg-body -->
</div><!-- /dash -->

<?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
/* ── PHP → JS ── */
const TREND_LABELS  = <?= json_encode($trend_labels) ?>;
const TREND_SAVINGS = <?= json_encode($trend_savings) ?>;
const TREND_SHARES  = <?= json_encode($trend_shares) ?>;
const TREND_WELFARE = <?= json_encode($trend_welfare) ?>;
const TOTAL_SAV = <?= $savings_val ?>;
const TOTAL_SHA = <?= $shares_val ?>;
const TOTAL_WEL = <?= $welfare_val ?>;

const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
const GRID = dark ? 'rgba(255,255,255,.05)' : 'rgba(11,36,25,.05)';
const TICK = dark ? '#3a6050' : '#8fada0';
const SURF = dark ? '#0d1d14' : '#ffffff';

const TT = {
    backgroundColor: dark?'#0d1d14':'#0b2419',
    titleColor:'#a3e635', bodyColor:'#fff',
    padding:12, cornerRadius:10,
    borderColor:'rgba(163,230,53,.2)', borderWidth:1,
    titleFont:{ family:"'Plus Jakarta Sans',sans-serif", weight:'800', size:12 },
    bodyFont: { family:"'Plus Jakarta Sans',sans-serif", size:11 },
};
const XS = { grid:{display:false}, ticks:{color:TICK,font:{family:"'Plus Jakarta Sans',sans-serif",size:10}} };
const YS = { grid:{color:GRID},    ticks:{color:TICK,font:{family:"'Plus Jakarta Sans',sans-serif",size:10},
    callback: v => v >= 1000 ? 'K'+(v/1000).toFixed(0) : v } };

/* Animate stat bars */
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.querySelectorAll('[data-w]').forEach(el => { el.style.width = el.dataset.w; });
        document.querySelectorAll('.rb-bar-fill').forEach(el => { el.style.width = el.dataset.w; });
    }, 480);
});

/* ── Trend Chart — stacked area ── */
(function() {
    const ctx = document.getElementById('trendChart').getContext('2d');

    const gSav = ctx.createLinearGradient(0,0,0,240);
    gSav.addColorStop(0,'rgba(22,163,74,.22)'); gSav.addColorStop(1,'rgba(22,163,74,0)');
    const gSha = ctx.createLinearGradient(0,0,0,240);
    gSha.addColorStop(0,'rgba(37,99,235,.18)'); gSha.addColorStop(1,'rgba(37,99,235,0)');
    const gWel = ctx.createLinearGradient(0,0,0,240);
    gWel.addColorStop(0,'rgba(220,38,38,.15)'); gWel.addColorStop(1,'rgba(220,38,38,0)');

    new Chart(ctx, {
        type:'line',
        data:{ labels:TREND_LABELS, datasets:[
            { label:'Savings',  data:TREND_SAVINGS, borderColor:'#16a34a', borderWidth:2.5, backgroundColor:gSav, fill:true, tension:.42, pointRadius:4, pointBackgroundColor:'#16a34a', pointBorderColor:SURF, pointBorderWidth:2 },
            { label:'Shares',   data:TREND_SHARES,  borderColor:'#2563eb', borderWidth:2.5, backgroundColor:gSha, fill:true, tension:.42, pointRadius:4, pointBackgroundColor:'#2563eb', pointBorderColor:SURF, pointBorderWidth:2 },
            { label:'Welfare',  data:TREND_WELFARE, borderColor:'#dc2626', borderWidth:2.5, backgroundColor:gWel, fill:true, tension:.42, pointRadius:4, pointBackgroundColor:'#dc2626', pointBorderColor:SURF, pointBorderWidth:2 },
        ]},
        options:{
            responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{display:false}, tooltip:{...TT, callbacks:{label:c=>' '+c.dataset.label+': KES '+c.parsed.y.toLocaleString()}} },
            scales:{ x:XS, y:YS }
        }
    });
})();

/* ── Ring Donut ── */
new Chart(document.getElementById('ringChart'), {
    type:'doughnut',
    data:{ labels:['Savings','Shares','Welfare'], datasets:[{
        data:[TOTAL_SAV,TOTAL_SHA,TOTAL_WEL],
        backgroundColor:['#16a34a','#2563eb','#dc2626'],
        borderWidth:4, borderColor: SURF, hoverOffset:7
    }]},
    options:{
        cutout:'72%', responsive:false,
        plugins:{ legend:{display:false}, tooltip:{...TT, callbacks:{label:c=>' KES '+c.parsed.toLocaleString()}} }
    }
});
</script>
</body>
</html>