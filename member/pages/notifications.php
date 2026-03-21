<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');
$layout = LayoutManager::create('member');

if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = (int)$_SESSION['member_id'];

$stmt = $conn->prepare("SELECT notification_id, title, message, is_read, created_at FROM notifications WHERE member_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

function getNotificationStyle(string $title, string $msg): array {
    $t = strtolower($title . ' ' . $msg);
    if (str_contains($t,'loan')||str_contains($t,'credit')||str_contains($t,'pay'))
        return ['icon'=>'bi-wallet2','bg'=>'var(--grn-bg)','color'=>'var(--grn)'];
    if (str_contains($t,'approv')||str_contains($t,'success'))
        return ['icon'=>'bi-check-circle-fill','bg'=>'var(--grn-bg)','color'=>'var(--grn)'];
    if (str_contains($t,'reject')||str_contains($t,'fail')||str_contains($t,'error'))
        return ['icon'=>'bi-exclamation-triangle-fill','bg'=>'var(--red-bg)','color'=>'var(--red)'];
    if (str_contains($t,'warn'))
        return ['icon'=>'bi-exclamation-circle-fill','bg'=>'var(--amb-bg)','color'=>'var(--amb)'];
    if (str_contains($t,'welfare')||str_contains($t,'heart'))
        return ['icon'=>'bi-heart-pulse-fill','bg'=>'rgba(13,148,136,.08)','color'=>'#0d9488'];
    return ['icon'=>'bi-bell-fill','bg'=>'rgba(11,36,25,.06)','color'=>'var(--t2)'];
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60).'m ago';
    if ($diff < 86400)  return floor($diff/3600).'h ago';
    if ($diff < 604800) return floor($diff/86400).'d ago';
    return date('d M Y', strtotime($datetime));
}

// Split into unread and read
$unread_rows = [];
$read_rows   = [];
$finance_count = 0;

$result->data_seek(0);
while ($r = $result->fetch_assoc()) {
    $t = strtolower($r['title'].' '.$r['message']);
    if (preg_match('/loan|pay|credit/i', $t)) $finance_count++;
    if ((int)$r['is_read'] === 0) $unread_rows[] = $r;
    else                           $read_rows[]   = $r;
}

$total_count  = count($unread_rows) + count($read_rows);
$unread_count = count($unread_rows);

$pageTitle = "Notifications";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<script>(function(){var s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $pageTitle ?> &middot; <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* NOTIFICATIONS · HD · Forest & Lime · Plus Jakarta Sans */
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
.hero-inner{position:relative;z-index:2;display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap}
.hero-eyebrow{display:inline-flex;align-items:center;gap:7px;background:rgba(163,230,53,.12);border:1px solid rgba(163,230,53,.2);border-radius:50px;padding:4px 14px;margin-bottom:12px;font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#bff060}
.hero h1{font-size:clamp(1.7rem,3.5vw,2.5rem);font-weight:800;color:#fff;letter-spacing:-.5px;line-height:1.1;margin-bottom:6px}
.hero-sub{font-size:.8rem;color:rgba(255,255,255,.45);font-weight:500}
.btn-back{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);font-size:.82rem;font-weight:700;padding:10px 20px;border-radius:50px;text-decoration:none;transition:all .22s ease;margin-bottom:4px}
.btn-back:hover{background:rgba(255,255,255,.17);color:#fff;transform:translateY(-2px)}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes floatUp{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}

/* ── STAT CARDS ── */
.stats-float{margin-top:-60px;position:relative;z-index:10;padding:0 28px;animation:floatUp .8s var(--ease) .4s both}
@media(max-width:767px){.stats-float{padding:0 14px}}
.sc{background:var(--surf);border-radius:var(--r);padding:22px 24px;border:1px solid var(--bdr);box-shadow:var(--sh-lg);height:100%;position:relative;overflow:hidden;transition:transform .28s var(--ease),box-shadow .28s ease}
.sc:hover{transform:translateY(-4px);box-shadow:0 8px 20px rgba(11,36,25,.09),0 32px 64px rgba(11,36,25,.14)}
.sc::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2.5px;border-radius:0 0 var(--r) var(--r);transform:scaleX(0);transform-origin:left;transition:transform .38s var(--ease)}
.sc:hover::after{transform:scaleX(1)}
.sc-g::after{background:linear-gradient(90deg,#16a34a,#4ade80)}
.sc-r::after{background:linear-gradient(90deg,#dc2626,#f87171)}
.sc-l::after{background:linear-gradient(90deg,var(--lime),#d4f98a)}
.sc-ico{width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:16px;transition:transform .3s var(--spring)}
.sc:hover .sc-ico{transform:scale(1.12) rotate(7deg)}
.sc-lbl{font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--t3);margin-bottom:5px}
.sc-val{font-size:1.5rem;font-weight:800;color:var(--t1);letter-spacing:-.8px;line-height:1.1;margin-bottom:9px}
.sc-meta{font-size:.72rem;font-weight:600;color:var(--t3)}
.sa1{animation:floatUp .7s var(--ease) .45s both}
.sa2{animation:floatUp .7s var(--ease) .53s both}
.sa3{animation:floatUp .7s var(--ease) .61s both}

/* ── PAGE BODY ── */
.pg-body{padding:32px 28px 0}
@media(max-width:767px){.pg-body{padding:24px 14px 0}}

/* ── SECTION DIVIDER ── */
.notif-section-label {
    display:flex;align-items:center;gap:12px;
    font-size:9.5px;font-weight:800;letter-spacing:1.4px;
    text-transform:uppercase;margin-bottom:14px;
}
.notif-section-label::after{content:'';flex:1;height:1px;background:var(--bdr)}
.nsl-unread{color:var(--red)}
.nsl-unread::after{background:rgba(220,38,38,.18)}
.nsl-read{color:var(--t3)}

/* ── NOTIFICATION SHELL ── */
.notif-shell{background:var(--surf);border-radius:22px;border:1px solid var(--bdr);box-shadow:var(--sh);overflow:hidden;margin-bottom:16px}

/* Unread shell gets a red-tinted top border */
.notif-shell.shell-unread{border-color:rgba(220,38,38,.18)}
.notif-shell.shell-unread .ns-head{background:rgba(220,38,38,.04)}
[data-bs-theme="dark"] .notif-shell.shell-unread .ns-head{background:rgba(220,38,38,.06)}

/* Read shell is normal */
.notif-shell.shell-read{border-color:var(--bdr)}

.ns-head{display:flex;align-items:center;justify-content:space-between;padding:16px 26px;border-bottom:1px solid var(--bdr2);flex-wrap:wrap;gap:10px}
.ns-title{font-size:.85rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:9px}
.ns-badge{display:inline-flex;align-items:center;gap:4px;border-radius:50px;padding:3px 10px;font-size:9.5px;font-weight:800}
.ns-badge-red{background:var(--red-bg);border:1px solid rgba(220,38,38,.2);color:var(--red)}
.ns-badge-grey{background:rgba(11,36,25,.05);border:1px solid var(--bdr);color:var(--t3)}
[data-bs-theme="dark"] .ns-badge-grey{background:rgba(255,255,255,.05)}

/* mark all button */
.btn-mark-all{display:inline-flex;align-items:center;gap:6px;background:var(--red-bg);border:1px solid rgba(220,38,38,.2);color:var(--red);font-size:.72rem;font-weight:800;padding:5px 14px;border-radius:50px;cursor:pointer;border:none;transition:all .18s ease}
.btn-mark-all:hover{background:rgba(220,38,38,.14);transform:translateY(-1px)}

/* ── NOTIF ROWS ── */
.notif-row{display:flex;align-items:flex-start;gap:14px;padding:16px 26px;border-bottom:1px solid var(--bdr2);position:relative;transition:background .13s ease}
.notif-row:last-child{border-bottom:none}
.notif-row:hover{background:rgba(11,36,25,.016)}

/* unread row treatment */
.notif-row.unread{background:rgba(220,38,38,.022)}
[data-bs-theme="dark"] .notif-row.unread{background:rgba(220,38,38,.04)}
.notif-row.unread::before{content:'';position:absolute;left:0;top:14px;bottom:14px;width:3px;border-radius:0 3px 3px 0;background:var(--red)}

/* read row — subtle tint gone, just normal */
.notif-row.read{background:transparent}

.n-ico{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:.92rem;flex-shrink:0;transition:transform .25s var(--spring)}
.notif-row:hover .n-ico{transform:scale(1.1) rotate(5deg)}

/* read rows: icon is muted */
.notif-row.read .n-ico{opacity:.6}
.notif-row.read:hover .n-ico{opacity:1}

.n-body{flex:1;min-width:0}
.n-head-row{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:5px;flex-wrap:wrap}
.n-title{font-size:.875rem;font-weight:800;color:var(--t1);line-height:1.3}
.notif-row.read .n-title{font-weight:600;color:var(--t2)}

.unread-dot{display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--red);margin-left:7px;vertical-align:middle;animation:udot 2s ease-in-out infinite}
@keyframes udot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(1.7)}}

.n-time{font-size:.68rem;font-weight:700;color:var(--t3);white-space:nowrap;flex-shrink:0;display:flex;align-items:center;gap:3px}
.n-time i{font-size:.55rem}

.n-msg{font-size:.82rem;font-weight:500;color:var(--t2);line-height:1.55}
.notif-row.read .n-msg{color:var(--t3)}

/* ── EMPTY STATE ── */
.empty-well{display:flex;flex-direction:column;align-items:center;padding:56px 24px;text-align:center}
.ew-ico{width:70px;height:70px;border-radius:20px;background:var(--lg);border:1px solid rgba(163,230,53,.2);display:flex;align-items:center;justify-content:center;font-size:1.7rem;color:var(--lt);margin-bottom:16px}
.ew-ico.ew-red{background:var(--grn-bg);border-color:rgba(22,163,74,.2);color:var(--grn)}
.ew-title{font-size:.88rem;font-weight:800;color:var(--t1);margin-bottom:4px}
.ew-sub{font-size:.76rem;font-weight:500;color:var(--t3)}

/* ── ALL-READ BANNER ── */
.all-read-banner{display:flex;align-items:center;gap:12px;background:var(--grn-bg);border:1px solid rgba(22,163,74,.2);border-radius:var(--rsm);padding:14px 18px;margin-bottom:16px;font-size:.82rem;font-weight:600;color:var(--grn);animation:floatUp .5s var(--ease) .72s both}
.all-read-banner i{font-size:1.1rem;flex-shrink:0}

/* ── COLLAPSED READ SECTION ── */
.read-toggle{display:flex;align-items:center;justify-content:space-between;padding:14px 26px;cursor:pointer;user-select:none;border-top:1px solid var(--bdr2);transition:background .13s ease}
.read-toggle:hover{background:rgba(11,36,25,.018)}
.rt-left{display:flex;align-items:center;gap:9px;font-size:.82rem;font-weight:700;color:var(--t2)}
.rt-count{background:rgba(11,36,25,.06);border-radius:50px;padding:2px 9px;font-size:.68rem;font-weight:800;color:var(--t3)}
.rt-chevron{font-size:.8rem;color:var(--t3);transition:transform .25s var(--ease)}
.rt-chevron.open{transform:rotate(180deg)}

.read-section{overflow:hidden;max-height:0;transition:max-height .45s var(--ease)}
.read-section.open{max-height:9999px}

/* ── FOOTER ── */
.ns-footer{text-align:center;padding:14px 26px;border-top:1px solid var(--bdr2);font-size:.72rem;font-weight:700;color:var(--t3);letter-spacing:.3px}
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
        <div>
            <div class="hero-eyebrow"><i class="bi bi-bell-fill" style="font-size:.75rem"></i> Activity Feed</div>
            <h1>Notifications</h1>
            <p class="hero-sub">Recent alerts and updates for your account</p>
        </div>
        <a href="dashboard.php" class="btn-back"><i class="bi bi-arrow-left" style="font-size:.72rem"></i> Dashboard</a>
    </div>
</div>

<!-- STAT CARDS -->
<div class="stats-float">
    <div class="row g-3">
        <div class="col-md-4 sa1">
            <div class="sc sc-g">
                <div class="sc-ico" style="background:var(--grn-bg);color:var(--grn)"><i class="bi bi-bell-fill"></i></div>
                <div class="sc-lbl">Total</div>
                <div class="sc-val"><?= $total_count ?></div>
                <div class="sc-meta">Notifications on record</div>
            </div>
        </div>
        <div class="col-md-4 sa2">
            <div class="sc sc-<?= $unread_count>0?'r':'g' ?>">
                <div class="sc-ico" style="background:<?= $unread_count>0?'var(--red-bg)':'var(--grn-bg)' ?>;color:<?= $unread_count>0?'var(--red)':'var(--grn)' ?>">
                    <i class="bi bi-bell-<?= $unread_count>0?'exclamation-fill':'fill' ?>"></i>
                </div>
                <div class="sc-lbl">Unread</div>
                <div class="sc-val" style="color:<?= $unread_count>0?'var(--red)':'var(--grn)' ?>"><?= $unread_count ?></div>
                <div class="sc-meta"><?= $unread_count>0 ? 'Awaiting your attention' : 'All caught up!' ?></div>
            </div>
        </div>
        <div class="col-md-4 sa3">
            <div class="sc sc-l">
                <div class="sc-ico" style="background:var(--lg);color:var(--lt)"><i class="bi bi-wallet2"></i></div>
                <div class="sc-lbl">Financial</div>
                <div class="sc-val"><?= $finance_count ?></div>
                <div class="sc-meta">Loan &amp; payment alerts</div>
            </div>
        </div>
    </div>
</div>

<!-- NOTIFICATION LIST -->
<div class="pg-body">
    <div class="row justify-content-center">
        <div class="col-xl-9 col-lg-11">

            <?php if ($total_count === 0): ?>
            <!-- Totally empty -->
            <div class="notif-shell shell-read">
                <div class="empty-well">
                    <div class="ew-ico"><i class="bi bi-bell-slash"></i></div>
                    <div class="ew-title">No Notifications Yet</div>
                    <div class="ew-sub">You're all caught up! New alerts will appear here.</div>
                </div>
            </div>

            <?php else: ?>

                <!-- ══ UNREAD SECTION ══ -->
                <?php if (!empty($unread_rows)): ?>
                <div class="notif-section-label nsl-unread" style="animation:floatUp .5s var(--ease) .72s both">
                    <i class="bi bi-circle-fill" style="font-size:.5rem"></i>
                    Unread (<?= $unread_count ?>)
                </div>
                <div class="notif-shell shell-unread" style="animation:floatUp .6s var(--ease) .76s both">
                    <div class="ns-head">
                        <span class="ns-title">
                            <i class="bi bi-bell-exclamation-fill" style="color:var(--red);font-size:.9rem"></i>
                            New Notifications
                            <span class="ns-badge ns-badge-red"><?= $unread_count ?> new</span>
                        </span>
                        <button class="btn-mark-all" onclick="markAllRead()">
                            <i class="bi bi-check2-all"></i> Mark all read
                        </button>
                    </div>
                    <?php foreach ($unread_rows as $i => $row):
                        $title   = htmlspecialchars($row['title']   ?: 'Notification');
                        $message = htmlspecialchars($row['message'] ?: '');
                        $style   = getNotificationStyle($title, $message);
                        $time    = timeAgo($row['created_at'] ?? 'now');
                    ?>
                    <div class="notif-row unread"
                         style="animation:floatUp .45s var(--ease) <?= round(.8 + $i * 0.04, 2) ?>s both">
                        <div class="n-ico" style="background:<?= $style['bg'] ?>;color:<?= $style['color'] ?>">
                            <i class="bi <?= $style['icon'] ?>"></i>
                        </div>
                        <div class="n-body">
                            <div class="n-head-row">
                                <div class="n-title">
                                    <?= $title ?><span class="unread-dot"></span>
                                </div>
                                <span class="n-time"><i class="bi bi-clock"></i><?= $time ?></span>
                            </div>
                            <div class="n-msg"><?= nl2br($message) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php else: ?>
                <!-- All read — show success banner -->
                <div class="all-read-banner">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>You're all caught up! No unread notifications.</span>
                </div>
                <?php endif; ?>


                <!-- ══ READ SECTION ══ -->
                <?php if (!empty($read_rows)): ?>
                <div class="notif-section-label nsl-read" style="animation:floatUp .5s var(--ease) .9s both">
                    <i class="bi bi-check2" style="font-size:.75rem"></i>
                    Previously Read (<?= count($read_rows) ?>)
                </div>

                <div class="notif-shell shell-read" style="animation:floatUp .6s var(--ease) .94s both">
                    <div class="ns-head">
                        <span class="ns-title">
                            <i class="bi bi-bell-fill" style="color:var(--t3);font-size:.85rem"></i>
                            Read Notifications
                            <span class="ns-badge ns-badge-grey"><?= count($read_rows) ?> items</span>
                        </span>
                        <button class="btn-mark-all" style="background:rgba(11,36,25,.06);color:var(--t3);border:1px solid var(--bdr)" onclick="toggleRead()">
                            <i class="bi bi-chevron-down" id="readChevron"></i>
                            <span id="readToggleTxt">Show all</span>
                        </button>
                    </div>

                    <!-- First 3 always visible -->
                    <?php foreach (array_slice($read_rows, 0, 3) as $i => $row):
                        $title   = htmlspecialchars($row['title']   ?: 'Notification');
                        $message = htmlspecialchars($row['message'] ?: '');
                        $style   = getNotificationStyle($title, $message);
                        $time    = timeAgo($row['created_at'] ?? 'now');
                    ?>
                    <div class="notif-row read"
                         style="animation:floatUp .45s var(--ease) <?= round(.96 + $i * 0.04, 2) ?>s both">
                        <div class="n-ico" style="background:<?= $style['bg'] ?>;color:<?= $style['color'] ?>">
                            <i class="bi <?= $style['icon'] ?>"></i>
                        </div>
                        <div class="n-body">
                            <div class="n-head-row">
                                <div class="n-title"><?= $title ?></div>
                                <span class="n-time"><i class="bi bi-clock"></i><?= $time ?></span>
                            </div>
                            <div class="n-msg"><?= nl2br($message) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Remaining rows — hidden until toggled -->
                    <?php if (count($read_rows) > 3): ?>
                    <div class="read-section" id="readMore">
                        <?php foreach (array_slice($read_rows, 3) as $i => $row):
                            $title   = htmlspecialchars($row['title']   ?: 'Notification');
                            $message = htmlspecialchars($row['message'] ?: '');
                            $style   = getNotificationStyle($title, $message);
                            $time    = timeAgo($row['created_at'] ?? 'now');
                        ?>
                        <div class="notif-row read">
                            <div class="n-ico" style="background:<?= $style['bg'] ?>;color:<?= $style['color'] ?>">
                                <i class="bi <?= $style['icon'] ?>"></i>
                            </div>
                            <div class="n-body">
                                <div class="n-head-row">
                                    <div class="n-title"><?= $title ?></div>
                                    <span class="n-time"><i class="bi bi-clock"></i><?= $time ?></span>
                                </div>
                                <div class="n-msg"><?= nl2br($message) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="ns-footer">&mdash; End of Notifications &mdash;</div>
                </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>
</div>

</div><!-- /dash -->
<?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Expand/collapse read section
function toggleRead() {
    const section = document.getElementById('readMore');
    const chevron = document.getElementById('readChevron');
    const txt     = document.getElementById('readToggleTxt');
    if (!section) return;
    const isOpen = section.classList.toggle('open');
    chevron.style.transform = isOpen ? 'rotate(180deg)' : '';
    txt.textContent = isOpen ? 'Show less' : 'Show all';
}

// Mark all unread as read via AJAX + remove unread styling
function markAllRead() {
    // Visual: remove unread treatment from all rows immediately
    document.querySelectorAll('.notif-row.unread').forEach(row => {
        row.classList.remove('unread');
        row.classList.add('read');
        const dot = row.querySelector('.unread-dot');
        if (dot) dot.remove();
        const ico = row.querySelector('.n-ico');
        if (ico) ico.style.opacity = '.6';
    });

    // Hide the unread badge count
    document.querySelectorAll('.ns-badge-red').forEach(b => b.textContent = '0 new');

    // Update stat card
    const valEl = document.querySelector('.sa2 .sc-val');
    if (valEl) { valEl.textContent = '0'; valEl.style.color = 'var(--grn)'; }
    const metaEl = document.querySelector('.sa2 .sc-meta');
    if (metaEl) metaEl.textContent = 'All caught up!';

    // Server-side mark read
    fetch('<?= BASE_URL ?>/public/ajax_mark_read.php', {
        method:'POST',
        body: new URLSearchParams({ type:'all_notifications', member_id:'<?= $member_id ?>' }),
        keepalive: true
    }).catch(()=>{});
}
</script>

<?php
// Mark all as read after rendering (server-side fallback)
if (!empty($unread_rows)) {
    $update = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE member_id = ? AND is_read = 0");
    if ($update) { $update->bind_param("i", $member_id); $update->execute(); $update->close(); }
}
?>
</body>
</html>