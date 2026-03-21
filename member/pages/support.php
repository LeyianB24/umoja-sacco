<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

require_member();
$layout    = LayoutManager::create('member');
$member_id = $_SESSION['member_id'];
$success   = "";
$error     = "";

// ── Handle Submission ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category     = $_POST['category']     ?? 'general';
    $subject      = trim($_POST['subject']  ?? '');
    $message      = trim($_POST['message']  ?? '');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $priority     = $_POST['priority'] ?? 'normal';

    if ($subject === '' || $message === '') {
        $error = "Please fill in the subject and description fields.";
    } else {
        $attachmentPath = null;
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg','jpeg','png','pdf','doc','docx'];
            $ext     = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $error = "Invalid file type. Allowed: JPG, PNG, PDF, DOC.";
            } elseif ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
                $error = "File exceeds 5 MB limit.";
            } else {
                $fileName = time().'_'.preg_replace("/[^a-zA-Z0-9.]/","_",basename($_FILES['attachment']['name']));
                $dir = __DIR__."/../uploads/support/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir.$fileName))
                    $attachmentPath = "uploads/support/".$fileName;
                else
                    $error = "Failed to upload attachment.";
            }
        }

        if (empty($error)) {
            $role_mapping     = SUPPORT_ROUTING_MAP;
            $target_name      = $role_mapping[$category] ?? 'Superadmin';
            $assigned_role_id = 1;

            $stmt_role = $conn->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
            $stmt_role->bind_param("s", $target_name); $stmt_role->execute();
            if ($row_role = $stmt_role->get_result()->fetch_assoc())
                $assigned_role_id = (int)$row_role['id'];
            $stmt_role->close();

            // Prepend priority to subject
            $priority_prefix = strtoupper($priority) === 'NORMAL' ? '' : "[".strtoupper($priority)."] ";
            $full_subject    = $priority_prefix . $subject;

            $sql  = "INSERT INTO support_tickets (member_id, category, assigned_role_id, subject, message, status, attachment, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isisss", $member_id, $category, $assigned_role_id, $full_subject, $message, $attachmentPath);

            if ($stmt->execute()) {
                $ticket_id = $stmt->insert_id;

                if ($reference_no) {
                    $stmt_ref = $conn->prepare("UPDATE support_tickets SET message = CONCAT(message, '\n\nReference: ', ?) WHERE support_id = ?");
                    $stmt_ref->bind_param("si", $reference_no, $ticket_id);
                    $stmt_ref->execute(); $stmt_ref->close();
                }

                $notif_msg   = "Ticket #$ticket_id ($category) submitted. Our team will review it shortly.";
                $admin_notif = "New ticket #$ticket_id ($category) from Member ID: $member_id. Priority: $priority.";
                $conn->query("INSERT INTO notifications (member_id,title,message,status,user_type,user_id,created_at) VALUES ($member_id,'Ticket #$ticket_id','$notif_msg','unread','member',$member_id,NOW())");
                $conn->query("INSERT INTO notifications (title,message,status,user_type,user_id,created_at) SELECT 'New Ticket #$ticket_id','$admin_notif','unread','admin',admin_id,NOW() FROM admins WHERE role_id=$assigned_role_id OR role_id=1");

                $success = "Ticket #$ticket_id has been submitted. We'll respond within 24 hours.";
            } else {
                $error = "System error — please try again later.";
            }
            if (isset($stmt)) $stmt->close();
        }
    }
}

// ── Fetch member's ticket history ────────────────────────────
$tickets = [];
$t_stmt  = $conn->prepare("SELECT support_id, category, subject, status, created_at FROM support_tickets WHERE member_id = ? ORDER BY created_at DESC LIMIT 10");
$t_stmt->bind_param("i", $member_id); $t_stmt->execute();
$t_res = $t_stmt->get_result();
while ($tr = $t_res->fetch_assoc()) $tickets[] = $tr;
$t_stmt->close();

$open_count    = count(array_filter($tickets, fn($t) => strtolower($t['status']) === 'pending'));
$resolved_count= count(array_filter($tickets, fn($t) => in_array(strtolower($t['status']),['resolved','closed'])));

$pageTitle = "Support Center";

// Category meta for the picker
$categories = [
    ['loans',        'Loans & Repayments',     'bi-bank2',                 'var(--amb-bg)', 'var(--amb)'],
    ['savings',      'Savings & Deposits',      'bi-piggy-bank-fill',       'var(--grn-bg)', 'var(--grn)'],
    ['shares',       'Shares & Equity',         'bi-pie-chart-fill',        'var(--blu-bg)', 'var(--blu)'],
    ['welfare',      'Welfare & Benefits',      'bi-heart-pulse-fill',      'var(--red-bg)', 'var(--red)'],
    ['withdrawals',  'Withdrawals & M-Pesa',    'bi-phone-vibrate-fill',    'var(--grn-bg)', 'var(--grn)'],
    ['technical',    'Technical Issue',         'bi-tools',                 'rgba(124,77,255,.08)', '#7c4dff'],
    ['profile',      'Account / Profile',       'bi-person-badge-fill',     'var(--blu-bg)', 'var(--blu)'],
    ['investments',  'Investments',             'bi-buildings-fill',        'var(--amb-bg)', 'var(--amb)'],
    ['general',      'General Inquiry',         'bi-chat-dots-fill',        'rgba(11,36,25,.07)', 'var(--t2)'],
];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<script>(function(){var s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $pageTitle ?> | <?= SITE_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════
   SUPPORT CENTER · HD EDITION · Forest & Lime
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
    --ease:cubic-bezier(.16,1,.3,1);
    --spring:cubic-bezier(.34,1.56,.64,1);
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
.dash{padding:0 0 72px}

/* ═══════════════════════════════
   HERO
═══════════════════════════════ */
.hero{
    background:linear-gradient(135deg,var(--f) 0%,var(--fm) 55%,var(--fs) 100%);
    padding:44px 52px 110px;position:relative;overflow:hidden;color:#fff;
    animation:fadeUp .7s var(--ease) both;
}
@media(max-width:767px){.hero{padding:30px 20px 96px}}

.hero-mesh{position:absolute;inset:0;pointer-events:none;
    background:radial-gradient(ellipse 65% 90% at 110% -10%,rgba(163,230,53,.12) 0%,transparent 55%),
               radial-gradient(ellipse 40% 50% at -10% 110%,rgba(163,230,53,.08) 0%,transparent 55%)}
.hero-dots{position:absolute;inset:0;pointer-events:none;
    background-image:radial-gradient(rgba(255,255,255,.05) 1px,transparent 1px);
    background-size:20px 20px}
.hero-ring{position:absolute;border-radius:50%;pointer-events:none;border:1px solid rgba(163,230,53,.07)}
.hero-ring.r1{width:500px;height:500px;top:-160px;right:-120px}
.hero-ring.r2{width:720px;height:720px;top:-250px;right:-220px}
.hero-ring.r3{width:280px;height:280px;bottom:-120px;left:-80px;opacity:.5}

.hero-inner{position:relative;z-index:2;max-width:680px}
.hero-eyebrow{display:inline-flex;align-items:center;gap:8px;
    background:rgba(163,230,53,.12);border:1px solid rgba(163,230,53,.22);
    border-radius:50px;padding:5px 16px;margin-bottom:16px;
    font-size:10px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:#bff060}
.eyebrow-dot{width:5px;height:5px;border-radius:50%;background:var(--lime);
    animation:pulse 1.8s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(1.8)}}

.hero h1{font-size:clamp(2rem,5vw,3rem);font-weight:800;color:#fff;
    letter-spacing:-.8px;line-height:1.1;margin-bottom:10px}
.hero-sub{font-size:.88rem;color:rgba(255,255,255,.5);font-weight:500;
    line-height:1.6;max-width:480px;margin-bottom:28px}
.hero-sub strong{color:rgba(255,255,255,.75)}

.hero-pills{display:flex;gap:8px;flex-wrap:wrap}
.hero-pill{display:inline-flex;align-items:center;gap:6px;
    background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.14);
    border-radius:50px;padding:6px 14px;font-size:.75rem;font-weight:700;color:rgba(255,255,255,.7)}
.hero-pill i{font-size:.72rem}

/* hero right */
.hero-right{position:absolute;top:0;right:52px;bottom:0;display:flex;align-items:center;z-index:2}
@media(max-width:991px){.hero-right{display:none}}

.hero-stat-block{display:flex;flex-direction:column;gap:12px;align-items:flex-end}
.hstat{background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.14);
    border-radius:14px;padding:14px 20px;text-align:right;min-width:130px;
    transition:all .22s var(--spring)}
.hstat:hover{background:rgba(255,255,255,.16);transform:translateX(-3px)}
.hstat-val{font-size:1.4rem;font-weight:800;color:#fff;letter-spacing:-.5px;line-height:1}
.hstat-lbl{font-size:.62rem;font-weight:700;text-transform:uppercase;
    letter-spacing:.8px;color:rgba(255,255,255,.4);margin-top:4px}

.btn-back{position:absolute;top:28px;right:52px;z-index:3;
    display:inline-flex;align-items:center;gap:7px;
    background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);
    color:rgba(255,255,255,.75);font-size:.78rem;font-weight:700;
    padding:8px 18px;border-radius:50px;text-decoration:none;
    transition:all .22s ease}
.btn-back:hover{background:rgba(255,255,255,.18);color:#fff;transform:translateY(-1px)}
@media(max-width:767px){.btn-back{top:18px;right:18px}}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes floatUp{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}

/* ═══════════════════════════════
   FLOATING STAT CARDS
═══════════════════════════════ */
.stats-float{margin-top:-62px;position:relative;z-index:10;padding:0 52px;
    animation:floatUp .8s var(--ease) .38s both}
@media(max-width:767px){.stats-float{padding:0 16px}}

.sc{background:var(--surf);border-radius:var(--r);padding:22px 24px;
    border:1px solid var(--bdr);box-shadow:var(--sh-lg);height:100%;
    position:relative;overflow:hidden;
    transition:transform .28s var(--ease),box-shadow .28s ease}
.sc:hover{transform:translateY(-5px);
    box-shadow:0 8px 20px rgba(11,36,25,.09),0 36px 70px rgba(11,36,25,.14)}
.sc::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2.5px;
    border-radius:0 0 var(--r) var(--r);transform:scaleX(0);
    transform-origin:left;transition:transform .38s var(--ease)}
.sc:hover::after{transform:scaleX(1)}
.sc-g::after{background:linear-gradient(90deg,#16a34a,#4ade80)}
.sc-b::after{background:linear-gradient(90deg,#2563eb,#60a5fa)}
.sc-a::after{background:linear-gradient(90deg,#d97706,#fbbf24)}
.sc-r::after{background:linear-gradient(90deg,#dc2626,#f87171)}
.sc-l::after{background:linear-gradient(90deg,var(--lime),#d4f98a)}

.sc-ico{width:46px;height:46px;border-radius:13px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.15rem;margin-bottom:16px;
    transition:transform .3s var(--spring)}
.sc:hover .sc-ico{transform:scale(1.12) rotate(7deg)}
.sc-lbl{font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--t3);margin-bottom:5px}
.sc-val{font-size:1.55rem;font-weight:800;color:var(--t1);letter-spacing:-.8px;line-height:1.1;margin-bottom:10px}
.sc-meta{font-size:.72rem;font-weight:600;color:var(--t3)}
.sa1{animation:floatUp .7s var(--ease) .44s both}
.sa2{animation:floatUp .7s var(--ease) .52s both}
.sa3{animation:floatUp .7s var(--ease) .60s both}
.sa4{animation:floatUp .7s var(--ease) .68s both}

/* ═══════════════════════════════
   PAGE BODY
═══════════════════════════════ */
.pg-body{padding:32px 52px 0;display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start}
@media(max-width:1199px){.pg-body{grid-template-columns:1fr}}
@media(max-width:767px){.pg-body{padding:24px 16px 0}}

/* ── SIDEBAR COLUMN ── */
.sidebar-col{display:flex;flex-direction:column;gap:18px}

/* ── CARD BASE ── */
.card{background:var(--surf);border-radius:var(--r);border:1px solid var(--bdr);
    box-shadow:var(--sh);overflow:hidden;animation:floatUp .7s var(--ease) both}
.card-head{display:flex;align-items:center;gap:12px;
    padding:18px 22px;border-bottom:1px solid var(--bdr2);background:var(--surf2)}
.ch-ico{width:34px;height:34px;border-radius:9px;
    display:flex;align-items:center;justify-content:center;
    font-size:.85rem;flex-shrink:0}
.ch-title{font-size:.875rem;font-weight:800;color:var(--t1);letter-spacing:-.2px}
.ch-sub{font-size:.68rem;font-weight:500;color:var(--t3);margin-top:1px}
.card-body{padding:20px 22px}

/* ── FLASH MESSAGES ── */
.flash{display:flex;align-items:flex-start;gap:13px;border-radius:var(--rsm);
    padding:15px 20px;margin:0 52px 20px;font-size:.85rem;font-weight:600;
    animation:fadeUp .4s var(--ease) both}
@media(max-width:767px){.flash{margin:0 16px 16px}}
.flash-ico{width:38px;height:38px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0}
.flash-title{font-size:.88rem;font-weight:800;margin-bottom:2px}
.flash-sub{font-weight:500;line-height:1.5}
.flash.ok{background:var(--grn-bg);border:1px solid rgba(22,163,74,.2)}
.flash.ok .flash-ico{background:#d1fae5;color:#065f46}
.flash.ok .flash-title,.flash.ok .flash-sub{color:var(--grn)}
.flash.err{background:var(--red-bg);border:1px solid rgba(220,38,38,.2)}
.flash.err .flash-ico{background:#fee2e2;color:#991b1b}
.flash.err .flash-title,.flash.err .flash-sub{color:var(--red)}
.flash-close{margin-left:auto;background:none;border:none;opacity:.5;cursor:pointer;font-size:1rem;color:inherit;padding:0;line-height:1;flex-shrink:0}

/* ── CONTACT ITEMS ── */
.contact-item{display:flex;align-items:center;gap:14px;
    padding:13px 14px;background:var(--surf2);border:1px solid var(--bdr);
    border-radius:13px;margin-bottom:9px;transition:all .2s var(--ease)}
.contact-item:last-child{margin-bottom:0}
.contact-item:hover{border-color:rgba(11,36,25,.16);transform:translateY(-2px);
    box-shadow:0 5px 16px rgba(11,36,25,.06)}
.ci-ico{width:44px;height:44px;border-radius:12px;background:var(--f);
    color:var(--lime);display:flex;align-items:center;justify-content:center;
    font-size:1rem;flex-shrink:0;transition:transform .25s var(--spring)}
.contact-item:hover .ci-ico{transform:scale(1.1) rotate(6deg)}
.ci-label{font-size:9px;font-weight:800;letter-spacing:.9px;text-transform:uppercase;color:var(--t3);margin-bottom:3px}
.ci-value{font-size:.82rem;font-weight:800;color:var(--t1)}

/* Hours block */
.hours-block{background:var(--f);border-radius:14px;padding:20px;margin-top:14px;
    position:relative;overflow:hidden;text-align:center}
.hours-block::before{content:'';position:absolute;inset:0;
    background-image:radial-gradient(rgba(255,255,255,.04) 1px,transparent 1px);
    background-size:16px 16px;pointer-events:none}
.hours-block::after{content:'';position:absolute;top:-40px;right:-40px;
    width:130px;height:130px;border-radius:50%;
    background:radial-gradient(circle,rgba(163,230,53,.14) 0%,transparent 65%);
    pointer-events:none}
.hb-inner{position:relative;z-index:2}
.hb-ico-wrap{width:48px;height:48px;border-radius:13px;
    background:rgba(163,230,53,.14);border:1px solid rgba(163,230,53,.25);
    display:flex;align-items:center;justify-content:center;
    font-size:1.3rem;color:var(--lime);margin:0 auto 12px}
.hb-title{font-size:.85rem;font-weight:800;color:#fff;margin-bottom:12px}
.hb-row{display:flex;align-items:center;justify-content:space-between;
    padding:6px 0;border-bottom:1px solid rgba(255,255,255,.07)}
.hb-row:last-child{border-bottom:none;padding-bottom:0}
.hb-day{font-size:.72rem;font-weight:600;color:rgba(255,255,255,.45)}
.hb-time{font-size:.72rem;font-weight:800;color:rgba(255,255,255,.8)}
.hb-time.closed{color:rgba(255,255,255,.25)}

/* Quick links */
.quick-link{display:flex;align-items:center;gap:11px;
    padding:11px 13px;background:var(--surf2);border:1px solid var(--bdr);
    border-radius:11px;text-decoration:none;color:var(--t1);
    font-size:.8rem;font-weight:700;transition:all .18s ease;margin-bottom:7px}
.quick-link:last-child{margin-bottom:0}
.quick-link:hover{border-color:rgba(11,36,25,.16);color:var(--t1);
    background:var(--surf);transform:translateX(3px)}
.ql-ico{width:28px;height:28px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0}
.ql-arrow{margin-left:auto;color:var(--t3);font-size:.72rem;transition:transform .18s ease}
.quick-link:hover .ql-arrow{transform:translateX(3px)}

/* ═══════════════════════════════
   MAIN COLUMN TABS
═══════════════════════════════ */
.main-col{display:flex;flex-direction:column;gap:20px}

.tab-shell{background:var(--surf);border-radius:var(--r);border:1px solid var(--bdr);
    box-shadow:var(--sh);overflow:hidden;animation:floatUp .7s var(--ease) .72s both}
.tab-shell-head{padding:6px;background:var(--bg);border-bottom:1px solid var(--bdr2);
    display:flex;gap:4px;align-items:center}
.stab{display:flex;align-items:center;gap:7px;padding:9px 18px;border-radius:10px;
    font-size:.82rem;font-weight:700;color:var(--t3);
    border:none;background:transparent;cursor:pointer;
    transition:all .18s var(--ease);white-space:nowrap}
.stab i{font-size:.85rem}
.stab:hover{color:var(--t2)}
.stab.active{background:var(--f);color:#fff;box-shadow:0 3px 12px rgba(11,36,25,.2)}
.stab-badge{background:var(--red-bg);color:var(--red);
    border-radius:50px;padding:1px 7px;font-size:9px;font-weight:800;
    margin-left:2px}
.stab.active .stab-badge{background:rgba(255,255,255,.15);color:rgba(255,255,255,.7)}

.stab-pane{display:none}
.stab-pane.show{display:block}

/* ═══════════════════════════════
   CATEGORY PICKER
═══════════════════════════════ */
.cat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:6px}
@media(max-width:600px){.cat-grid{grid-template-columns:repeat(2,1fr)}}

.cat-tile{display:flex;flex-direction:column;align-items:center;gap:7px;
    padding:14px 8px;background:var(--surf2);border:2px solid var(--bdr);
    border-radius:13px;cursor:pointer;text-align:center;
    font-size:.72rem;font-weight:700;color:var(--t2);
    transition:all .18s var(--ease);user-select:none;position:relative}
.cat-tile:hover{border-color:rgba(11,36,25,.18);background:var(--surf);transform:translateY(-2px)}
.cat-tile.selected{border-color:var(--f);background:rgba(11,36,25,.04);color:var(--f)}
.cat-tile-ico{width:38px;height:38px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;font-size:.95rem;
    transition:transform .2s var(--spring)}
.cat-tile:hover .cat-tile-ico,.cat-tile.selected .cat-tile-ico{transform:scale(1.12) rotate(5deg)}
.cat-tile-check{position:absolute;top:6px;right:6px;width:16px;height:16px;
    border-radius:50%;background:var(--f);color:var(--lime);
    display:none;align-items:center;justify-content:center;font-size:.55rem}
.cat-tile.selected .cat-tile-check{display:flex}
.cat-tile-lbl{line-height:1.3}

/* Hidden real select synced with tile */
#hiddenCategory{display:none}

/* ═══════════════════════════════
   PRIORITY PILLS
═══════════════════════════════ */
.priority-row{display:flex;gap:8px;flex-wrap:wrap}
.pri-pill{display:inline-flex;align-items:center;gap:7px;
    padding:8px 16px;border-radius:50px;
    border:2px solid var(--bdr);background:var(--surf2);
    cursor:pointer;font-size:.78rem;font-weight:700;color:var(--t2);
    transition:all .18s ease;user-select:none}
.pri-pill input{display:none}
.pri-pill:hover{border-color:rgba(11,36,25,.2)}
.pri-pill.selected-low    {border-color:var(--grn);background:var(--grn-bg);color:var(--grn)}
.pri-pill.selected-normal {border-color:var(--blu);background:var(--blu-bg);color:var(--blu)}
.pri-pill.selected-high   {border-color:var(--amb);background:var(--amb-bg);color:var(--amb)}
.pri-pill.selected-urgent {border-color:var(--red);background:var(--red-bg);color:var(--red)}
.pri-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}

/* ═══════════════════════════════
   FORM FIELDS
═══════════════════════════════ */
.field-group{margin-bottom:18px}
.field-lbl{display:flex;align-items:center;gap:7px;
    font-size:10px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;
    color:var(--t3);margin-bottom:7px}
.opt-badge{background:var(--bg);border:1px solid var(--bdr);border-radius:5px;
    padding:1px 7px;font-size:9px;font-weight:700;color:var(--t3);letter-spacing:.3px}
.field-ctrl{width:100%;padding:11px 14px;background:var(--surf2);
    border:1px solid var(--bdr);border-radius:var(--rsm);
    color:var(--t1);font-size:.875rem;font-weight:500;outline:none;
    transition:border-color .18s ease,box-shadow .18s ease}
.field-ctrl:focus{border-color:rgba(11,36,25,.3);box-shadow:0 0 0 3px rgba(11,36,25,.07)}
textarea.field-ctrl{resize:vertical;min-height:140px;line-height:1.6}

/* prefix input group */
.input-with-icon{position:relative}
.input-with-icon i{position:absolute;left:12px;top:50%;transform:translateY(-50%);
    color:var(--t3);font-size:.82rem;pointer-events:none}
.input-with-icon .field-ctrl{padding-left:36px}

/* file input */
.file-ctrl{width:100%;font-size:.82rem;color:var(--t2);cursor:pointer}
.file-ctrl::file-selector-button{background:var(--f);color:var(--lime);
    border:none;border-radius:8px;padding:7px 14px;font-size:.75rem;font-weight:800;
    cursor:pointer;margin-right:10px;transition:all .18s ease;
    font-family:'Plus Jakarta Sans',sans-serif!important}
.file-ctrl::file-selector-button:hover{background:var(--fm)}
.file-note{font-size:.68rem;font-weight:600;color:var(--t3);margin-top:5px;
    display:flex;align-items:center;gap:4px}

/* form divider */
.form-divider{display:flex;align-items:center;gap:12px;
    font-size:9.5px;font-weight:800;letter-spacing:1px;text-transform:uppercase;
    color:var(--t3);margin:22px 0 18px}
.form-divider::before,.form-divider::after{content:'';flex:1;height:1px;background:var(--bdr)}

/* ── SUBMIT BUTTON ── */
.btn-submit{display:flex;align-items:center;justify-content:center;gap:9px;
    width:100%;padding:14px 28px;border-radius:50px;
    background:var(--f);color:#fff;border:none;
    font-size:.9rem;font-weight:800;cursor:pointer;
    box-shadow:0 3px 16px rgba(11,36,25,.22);
    transition:all .25s var(--spring)}
.btn-submit:hover{background:var(--fm);transform:translateY(-2px);
    box-shadow:0 10px 28px rgba(11,36,25,.28);color:var(--lime)}
.btn-submit:active{transform:translateY(0);box-shadow:none}

/* ═══════════════════════════════
   TICKET HISTORY TABLE
═══════════════════════════════ */
.ticket-table{width:100%;border-collapse:collapse}
.ticket-table thead th{background:var(--surf2);font-size:10px;font-weight:800;
    letter-spacing:.8px;text-transform:uppercase;color:var(--t3);
    padding:11px 18px;border:none;border-bottom:1px solid var(--bdr2);white-space:nowrap}
.ticket-table tbody tr{border-bottom:1px solid var(--bdr2);transition:background .13s ease}
.ticket-table tbody tr:last-child{border-bottom:none}
.ticket-table tbody tr:hover{background:rgba(11,36,25,.018)}
.ticket-table tbody td{padding:13px 18px;vertical-align:middle;font-size:.82rem}

/* ticket id */
.tid{font-family:monospace;font-size:.8rem;font-weight:800;
    background:var(--bg);border:1px solid var(--bdr);border-radius:6px;
    padding:2px 8px;color:var(--t2)}

/* status chips */
.tstatus{display:inline-flex;align-items:center;gap:4px;
    padding:3px 10px;border-radius:7px;font-size:9.5px;font-weight:800;letter-spacing:.3px}
.tstatus::before{content:'';width:4px;height:4px;border-radius:50%;background:currentColor}
.ts-pending {background:var(--amb-bg);color:var(--amb)}
.ts-open    {background:var(--blu-bg);color:var(--blu)}
.ts-resolved{background:var(--grn-bg);color:var(--grn)}
.ts-closed  {background:rgba(11,36,25,.06);color:var(--t3)}

/* category chip */
.tcat{font-size:.72rem;font-weight:700;color:var(--t2);
    background:var(--surf2);border:1px solid var(--bdr);
    border-radius:6px;padding:2px 8px;text-transform:capitalize}

/* date */
.tdate{font-size:.78rem;font-weight:600;color:var(--t3)}

/* empty state */
.empty-well{display:flex;flex-direction:column;align-items:center;
    padding:52px 24px;text-align:center}
.ew-ico{width:64px;height:64px;border-radius:18px;background:var(--bg);
    border:1px solid var(--bdr);display:flex;align-items:center;
    justify-content:center;font-size:1.6rem;color:var(--t3);margin-bottom:14px}
.ew-title{font-size:.88rem;font-weight:800;color:var(--t1);margin-bottom:4px}
.ew-sub{font-size:.76rem;color:var(--t3)}

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
    <div class="hero-ring r3"></div>

    <a href="dashboard.php" class="btn-back">
        <i class="bi bi-arrow-left" style="font-size:.7rem"></i> Dashboard
    </a>

    <div class="hero-inner">
        <div class="hero-eyebrow">
            <span class="eyebrow-dot"></span>
            Member Support
        </div>
        <h1>How can we<br>help you today?</h1>
        <p class="hero-sub">
            Our specialized support team handles everything from
            <strong>loan queries</strong> to <strong>technical issues</strong>.
            Submit a ticket and we'll respond within 24 hours.
        </p>
        <div class="hero-pills">
            <span class="hero-pill"><i class="bi bi-lightning-charge-fill"></i> &lt; 24hr response</span>
            <span class="hero-pill"><i class="bi bi-shield-check-fill"></i> Secure &amp; confidential</span>
            <span class="hero-pill"><i class="bi bi-headset"></i> 9 support desks</span>
        </div>
    </div>

    <!-- Right: Live stats -->
    <div class="hero-right">
        <div class="hero-stat-block">
            <div class="hstat">
                <div class="hstat-val"><?= $open_count ?></div>
                <div class="hstat-lbl">Open Tickets</div>
            </div>
            <div class="hstat">
                <div class="hstat-val"><?= $resolved_count ?></div>
                <div class="hstat-lbl">Resolved</div>
            </div>
            <div class="hstat">
                <div class="hstat-val"><?= count($tickets) ?></div>
                <div class="hstat-lbl">Total Submitted</div>
            </div>
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
                <div class="sc-ico" style="background:var(--grn-bg);color:var(--grn)"><i class="bi bi-lightning-charge-fill"></i></div>
                <div class="sc-lbl">Avg Response</div>
                <div class="sc-val">&lt; 24 hrs</div>
                <div class="sc-meta">Business days</div>
            </div>
        </div>
        <div class="col-md-3 sa2">
            <div class="sc sc-a">
                <div class="sc-ico" style="background:var(--amb-bg);color:var(--amb)"><i class="bi bi-ticket-detailed-fill"></i></div>
                <div class="sc-lbl">Open Tickets</div>
                <div class="sc-val"><?= $open_count ?></div>
                <div class="sc-meta"><?= $open_count > 0 ? 'Awaiting response' : 'All clear!' ?></div>
            </div>
        </div>
        <div class="col-md-3 sa3">
            <div class="sc sc-b">
                <div class="sc-ico" style="background:var(--blu-bg);color:var(--blu)"><i class="bi bi-check2-circle"></i></div>
                <div class="sc-lbl">Resolved</div>
                <div class="sc-val"><?= $resolved_count ?></div>
                <div class="sc-meta">Successfully closed</div>
            </div>
        </div>
        <div class="col-md-3 sa4">
            <div class="sc sc-l">
                <div class="sc-ico" style="background:var(--lg);color:var(--lt)"><i class="bi bi-clock-history"></i></div>
                <div class="sc-lbl">Service Hours</div>
                <div class="sc-val">Mon–Sat</div>
                <div class="sc-meta">08:00 – 17:00 EAT</div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════
     FLASH MESSAGES
══════════════════════════════ -->
<div style="padding-top:28px">
    <?php if ($success): ?>
    <div class="flash ok">
        <div class="flash-ico"><i class="bi bi-check2-circle"></i></div>
        <div><div class="flash-title">Ticket Submitted</div><div class="flash-sub"><?= htmlspecialchars($success) ?></div></div>
        <button class="flash-close" onclick="this.closest('.flash').remove()">&times;</button>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="flash err">
        <div class="flash-ico"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div><div class="flash-title">Submission Failed</div><div class="flash-sub"><?= htmlspecialchars($error) ?></div></div>
        <button class="flash-close" onclick="this.closest('.flash').remove()">&times;</button>
    </div>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════
     BODY GRID
══════════════════════════════ -->
<div class="pg-body">

    <!-- ── Sidebar column ── -->
    <div class="sidebar-col">

        <!-- Contact Info -->
        <div class="card" style="animation-delay:.74s">
            <div class="card-head">
                <div class="ch-ico" style="background:var(--lg);color:var(--lt)"><i class="bi bi-telephone-fill"></i></div>
                <div><div class="ch-title">Direct Contact</div><div class="ch-sub">Reach us outside tickets</div></div>
            </div>
            <div class="card-body">
                <div class="contact-item">
                    <div class="ci-ico"><i class="bi bi-geo-alt-fill"></i></div>
                    <div>
                        <div class="ci-label">Headquarters</div>
                        <div class="ci-value"><?= defined('OFFICE_LOCATION') ? htmlspecialchars(OFFICE_LOCATION) : 'Nairobi, Kenya' ?></div>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="ci-ico"><i class="bi bi-telephone-outbound-fill"></i></div>
                    <div>
                        <div class="ci-label">Hotline</div>
                        <div class="ci-value"><?= defined('OFFICE_PHONE') ? htmlspecialchars(OFFICE_PHONE) : '+254 700 000000' ?></div>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="ci-ico"><i class="bi bi-envelope-paper-fill"></i></div>
                    <div>
                        <div class="ci-label">Official Email</div>
                        <div class="ci-value"><?= defined('OFFICE_EMAIL') ? htmlspecialchars(OFFICE_EMAIL) : 'support@umoja.com' ?></div>
                    </div>
                </div>

                <!-- Hours -->
                <div class="hours-block">
                    <div class="hb-inner">
                        <div class="hb-ico-wrap"><i class="bi bi-clock-history"></i></div>
                        <div class="hb-title">Service Hours</div>
                        <div class="hb-row">
                            <span class="hb-day">Mon – Fri</span>
                            <span class="hb-time">08:00 – 17:00</span>
                        </div>
                        <div class="hb-row">
                            <span class="hb-day">Saturday</span>
                            <span class="hb-time">09:00 – 13:00</span>
                        </div>
                        <div class="hb-row">
                            <span class="hb-day">Sunday</span>
                            <span class="hb-time closed">Closed</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card" style="animation-delay:.80s">
            <div class="card-head">
                <div class="ch-ico" style="background:var(--blu-bg);color:var(--blu)"><i class="bi bi-grid-fill"></i></div>
                <div><div class="ch-title">Quick Access</div><div class="ch-sub">Jump to your accounts</div></div>
            </div>
            <div class="card-body" style="padding-bottom:14px">
                <?php foreach ([
                    ['bi-piggy-bank-fill',   'var(--grn-bg)','var(--grn)', 'Savings Account', 'savings.php'],
                    ['bi-bank2',             'var(--amb-bg)','var(--amb)', 'My Loans',         'loans.php'],
                    ['bi-heart-pulse-fill',  'var(--red-bg)','var(--red)', 'Welfare Hub',       'welfare.php'],
                    ['bi-arrow-left-right',  'var(--blu-bg)','var(--blu)', 'All Transactions',  'transactions.php'],
                    ['bi-gear-wide-connected','rgba(11,36,25,.07)','var(--t2)', 'Settings',     'settings.php'],
                ] as [$ico,$bg,$col,$lbl,$href]): ?>
                <a href="<?= $href ?>" class="quick-link">
                    <div class="ql-ico" style="background:<?= $bg ?>;color:<?= $col ?>"><i class="bi <?= $ico ?>"></i></div>
                    <?= $lbl ?>
                    <i class="bi bi-arrow-right ql-arrow"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

    </div><!-- /sidebar-col -->

    <!-- ── Main column ── -->
    <div class="main-col">

        <!-- Tab shell -->
        <div class="tab-shell">

            <div class="tab-shell-head">
                <button class="stab active" data-tab="new-ticket" id="tab-new">
                    <i class="bi bi-plus-circle-fill"></i> New Ticket
                </button>
                <button class="stab" data-tab="my-tickets" id="tab-hist">
                    <i class="bi bi-ticket-detailed-fill"></i> My Tickets
                    <?php if ($open_count > 0): ?><span class="stab-badge"><?= $open_count ?></span><?php endif; ?>
                </button>
            </div>

            <!-- ══ NEW TICKET PANE ══ -->
            <div class="stab-pane show" id="pane-new-ticket">
                <div style="padding:26px">

                    <!-- Step 1: Pick category -->
                    <div class="form-divider">Step 1 &mdash; Choose a Category</div>

                    <div class="cat-grid" id="catGrid">
                        <?php foreach ($categories as [$val,$lbl,$ico,$bg,$col]): ?>
                        <div class="cat-tile" data-cat="<?= $val ?>"
                             onclick="selectCat('<?= $val ?>', this)">
                            <div class="cat-tile-check"><i class="bi bi-check"></i></div>
                            <div class="cat-tile-ico" style="background:<?= $bg ?>;color:<?= $col ?>">
                                <i class="bi <?= $ico ?>"></i>
                            </div>
                            <span class="cat-tile-lbl"><?= $lbl ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="font-size:.7rem;font-weight:600;color:var(--red);margin-top:4px;display:none" id="catErr">
                        <i class="bi bi-exclamation-circle me-1"></i>Please select a category.
                    </div>

                    <!-- Step 2: Fill details -->
                    <form method="POST" enctype="multipart/form-data" id="ticketForm">
                        <input type="hidden" name="category" id="hiddenCategory">

                        <div class="form-divider">Step 2 &mdash; Ticket Details</div>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <div class="field-group">
                                    <label class="field-lbl">Subject</label>
                                    <div class="input-with-icon">
                                        <i class="bi bi-tag-fill"></i>
                                        <input type="text" name="subject" class="field-ctrl"
                                               placeholder="Briefly describe the issue" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="field-group">
                                    <label class="field-lbl">Ref / Transaction ID <span class="opt-badge">Optional</span></label>
                                    <div class="input-with-icon">
                                        <i class="bi bi-hash"></i>
                                        <input type="text" name="reference_no" class="field-ctrl"
                                               placeholder="e.g. S9K1234567">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="field-group">
                            <label class="field-lbl">Detailed Description</label>
                            <textarea name="message" class="field-ctrl" rows="6"
                                placeholder="Describe your issue clearly — include dates, amounts, error messages, and any steps you already tried…" required></textarea>
                        </div>

                        <!-- Priority -->
                        <div class="field-group">
                            <label class="field-lbl">Priority</label>
                            <div class="priority-row" id="priorityRow">
                                <?php foreach ([
                                    ['low',    'Low',    '#16a34a', 'var(--grn)'],
                                    ['normal', 'Normal', '#2563eb', 'var(--blu)'],
                                    ['high',   'High',   '#d97706', 'var(--amb)'],
                                    ['urgent', 'Urgent', '#dc2626', 'var(--red)'],
                                ] as [$val,$lbl,$dotcol,$txtcol]): ?>
                                <label class="pri-pill <?= $val==='normal'?'selected-normal':'' ?>" data-pri="<?= $val ?>">
                                    <input type="radio" name="priority" value="<?= $val ?>" <?= $val==='normal'?'checked':'' ?>>
                                    <span class="pri-dot" style="background:<?= $dotcol ?>"></span>
                                    <?= $lbl ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Attachment -->
                        <div class="field-group">
                            <label class="field-lbl">Attachment <span class="opt-badge">Optional</span></label>
                            <input type="file" name="attachment" class="file-ctrl"
                                   accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                            <div class="file-note">
                                <i class="bi bi-info-circle"></i>
                                Max 5 MB &nbsp;&middot;&nbsp; JPG, PNG, PDF, DOC accepted
                            </div>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="btn-submit">
                            <i class="bi bi-send-check-fill"></i> Submit Support Request
                        </button>

                    </form>
                </div>
            </div><!-- /pane-new-ticket -->

            <!-- ══ MY TICKETS PANE ══ -->
            <div class="stab-pane" id="pane-my-tickets">
                <?php if (empty($tickets)): ?>
                <div class="empty-well">
                    <div class="ew-ico"><i class="bi bi-ticket-detailed"></i></div>
                    <div class="ew-title">No Tickets Yet</div>
                    <div class="ew-sub">Submit your first ticket using the New Ticket tab.</div>
                </div>
                <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="ticket-table">
                        <thead>
                            <tr>
                                <th style="padding-left:24px">Ticket #</th>
                                <th>Subject</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th style="padding-right:24px">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $i => $tk):
                                $st  = strtolower($tk['status'] ?? 'pending');
                                $sc  = match($st) { 'pending'=>'ts-pending','open'=>'ts-open','resolved'=>'ts-resolved','closed'=>'ts-closed', default=>'ts-pending' };
                            ?>
                            <tr style="animation:floatUp .4s var(--ease) <?= round(.05 + $i * 0.04, 2) ?>s both">
                                <td style="padding-left:24px"><span class="tid">#<?= $tk['support_id'] ?></span></td>
                                <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600;color:var(--t1)"><?= htmlspecialchars($tk['subject']) ?></td>
                                <td><span class="tcat"><?= htmlspecialchars($tk['category']) ?></span></td>
                                <td><span class="tstatus <?= $sc ?>"><?= ucfirst($st) ?></span></td>
                                <td style="padding-right:24px"><span class="tdate"><?= date('d M Y', strtotime($tk['created_at'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align:center;padding:14px;border-top:1px solid var(--bdr2);font-size:.72rem;font-weight:700;color:var(--t3)">
                    Showing last <?= count($tickets) ?> tickets
                </div>
                <?php endif; ?>
            </div><!-- /pane-my-tickets -->

        </div><!-- /tab-shell -->

    </div><!-- /main-col -->

</div><!-- /pg-body -->
</div><!-- /dash -->

<?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Tab switcher ── */
document.querySelectorAll('.stab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.stab').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.stab-pane').forEach(p => p.classList.remove('show'));
        btn.classList.add('active');
        document.getElementById('pane-' + btn.dataset.tab).classList.add('show');
    });
});

/* ── Category tile picker ── */
function selectCat(val, tile) {
    document.querySelectorAll('.cat-tile').forEach(t => t.classList.remove('selected'));
    tile.classList.add('selected');
    document.getElementById('hiddenCategory').value = val;
    document.getElementById('catErr').style.display = 'none';
}

/* ── Form validation: require category ── */
document.getElementById('ticketForm').addEventListener('submit', function(e) {
    if (!document.getElementById('hiddenCategory').value) {
        e.preventDefault();
        document.getElementById('catErr').style.display = 'flex';
        document.getElementById('catGrid').scrollIntoView({ behavior:'smooth', block:'center' });
    }
});

/* ── Priority pills ── */
document.querySelectorAll('.pri-pill').forEach(pill => {
    const radio = pill.querySelector('input');
    const applyActive = () => {
        document.querySelectorAll('.pri-pill').forEach(p => {
            p.className = 'pri-pill';
        });
        pill.classList.add('selected-' + radio.value);
    };
    if (radio.checked) applyActive();
    pill.addEventListener('click', () => { radio.checked = true; applyActive(); });
});

/* ── If form was just submitted with error, show ticket pane ── */
<?php if ($error && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
// keep new ticket tab active (already default)
<?php endif; ?>

/* ── If success, auto-switch to My Tickets ── */
<?php if ($success): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('tab-hist').click();
});
<?php endif; ?>
</script>
</body>
</html>