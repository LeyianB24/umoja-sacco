<?php
// inc/topbar.php — HD Edition · Forest & Lime · Plus Jakarta Sans
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../inc/Auth.php';

$my_user_id     = $_SESSION['member_id'] ?? $_SESSION['admin_id'] ?? 0;
$user_role      = $_SESSION['role']      ?? 'member';
$member_name    = $_SESSION['member_name'] ?? $_SESSION['full_name'] ?? 'User';
$profile_pic_db = null;
$user_gender    = 'male';

$recent_messages    = [];
$recent_notifs      = [];
$unread_msgs_count  = 0;
$unread_notif_count = 0;

if (!function_exists('time_elapsed_string_topbar')) {
    function time_elapsed_string_topbar($datetime) {
        $now = new DateTime(); $ago = new DateTime($datetime); $diff = $now->diff($ago);
        if ($diff->i < 1 && $diff->h < 1 && $diff->d < 1) return "just now";
        foreach (['y'=>'yr','m'=>'mo','d'=>'day','h'=>'hr','i'=>'min','s'=>'sec'] as $k=>$v){
            if ($diff->$k > 0) return $diff->$k." {$v}".($diff->$k>1?'s':'')." ago";
        }
        return "just now";
    }
}

if ($my_user_id > 0 && isset($conn)) {
    $stmt = ($user_role==='member')
        ? $conn->prepare("SELECT full_name, profile_pic, gender FROM members WHERE member_id=?")
        : $conn->prepare("SELECT full_name, profile_pic, 'male' AS gender FROM admins WHERE admin_id=?");
    if ($stmt) {
        $stmt->bind_param("i", $my_user_id); $stmt->execute();
        if ($u = $stmt->get_result()->fetch_assoc()) {
            $member_name = $u['full_name']; $profile_pic_db = $u['profile_pic']; $user_gender = $u['gender'];
        }
        $stmt->close();
    }

    $msg_sql = ($user_role==='member')
        ? "SELECT m.message_id,m.body,m.subject,m.sent_at,m.is_read,COALESCE(a.full_name,mem.full_name,'System') AS sender_name,m.from_admin_id,m.from_member_id FROM messages m LEFT JOIN admins a ON m.from_admin_id=a.admin_id LEFT JOIN members mem ON m.from_member_id=mem.member_id WHERE m.to_member_id=? ORDER BY m.sent_at DESC LIMIT 5"
        : "SELECT m.message_id,m.body,m.subject,m.sent_at,m.is_read,mem.full_name AS sender_name,m.from_member_id FROM messages m JOIN members mem ON m.from_member_id=mem.member_id WHERE m.to_admin_id=? ORDER BY m.sent_at DESC LIMIT 5";
    if ($stmt = $conn->prepare($msg_sql)) {
        $stmt->bind_param("i", $my_user_id); $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $recent_messages[] = $row;
        $stmt->close();
    }

    $cnt_sql = ($user_role==='member')
        ? "SELECT COUNT(*) AS cnt FROM messages WHERE to_member_id=? AND is_read=0"
        : "SELECT COUNT(*) AS cnt FROM messages WHERE to_admin_id=? AND is_read=0";
    if ($stmt = $conn->prepare($cnt_sql)) {
        $stmt->bind_param("i", $my_user_id); $stmt->execute();
        $unread_msgs_count = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
    }

    if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0) {
        if ($stmt = $conn->prepare("SELECT * FROM notifications WHERE user_type=? AND user_id=? ORDER BY created_at DESC LIMIT 5")) {
            $stmt->bind_param("si", $user_role, $my_user_id); $stmt->execute();
            $res = $stmt->get_result();
            while ($n = $res->fetch_assoc()) $recent_notifs[] = $n;
            $stmt->close();
        }
        if ($stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_type=? AND user_id=? AND status='unread'")) {
            $stmt->bind_param("si", $user_role, $my_user_id); $stmt->execute();
            $unread_notif_count = $stmt->get_result()->fetch_assoc()['cnt'];
            $stmt->close();
        }
    }
}

$base           = defined("BASE_URL") ? BASE_URL : '..';
$default_avatar = strtolower($user_gender ?? 'male') === 'female' ? 'female.jpg' : 'male.jpg';
$pic_src        = !empty($profile_pic_db)
    ? "data:image/jpeg;base64,".base64_encode($profile_pic_db)
    : "{$base}/public/assets/uploads/{$default_avatar}";
$current_url    = urlencode($_SERVER['REQUEST_URI'] ?? '');
$msgs_link      = "{$base}/public/messages.php?return_to={$current_url}";
$notif_link     = ($user_role==='member') ? "{$base}/member/pages/notifications.php" : "#";
$profile_link   = ($user_role==='member') ? "{$base}/member/pages/profile.php" : "{$base}/public/admin_settings.php";
$first_name     = htmlspecialchars(explode(' ', $member_name)[0]);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════════════════════
   TOPBAR · HD EDITION · Plus Jakarta Sans · Forest & Lime
   Token-for-token match with savings / shares / welfare /
   sidebar pages — uses same --f / --lime / --bdr variables
═══════════════════════════════════════════════════════════ */
:root {
    /* ── Core palette (exact match) ── */
    --f:      #0b2419;
    --fm:     #154330;
    --lime:   #a3e635;
    --lt:     #6a9a1a;

    /* ── Topbar tokens ── */
    --tb-h:      68px;
    --tb-bg:     #ffffff;
    --tb-bdr:    rgba(11,36,25,0.07);
    --tb-bdr2:   rgba(11,36,25,0.04);
    --tb-t1:     #0b2419;
    --tb-t2:     #6b8a7a;
    --tb-t3:     #8fada0;
    --tb-bg2:    #f7fbf8;
    --tb-r:      10px;
    --tb-ease:   cubic-bezier(0.16,1,0.3,1);
    --tb-spring: cubic-bezier(0.34,1.56,0.64,1);
    --tb-sh:     0 1px 16px rgba(11,36,25,0.06);

    --grn:    #16a34a;
    --red:    #dc2626;
    --grn-bg: rgba(22,163,74,0.08);
    --red-bg: rgba(220,38,38,0.08);
    --amb-bg: rgba(217,119,6,0.08);
    --amb:    #d97706;
}

[data-bs-theme="dark"] {
    --tb-bg:  #0d1d14;
    --tb-bdr: rgba(255,255,255,0.07);
    --tb-bdr2:rgba(255,255,255,0.04);
    --tb-t1:  #d8eee2;
    --tb-t2:  #5a8a6e;
    --tb-t3:  #3a6050;
    --tb-bg2: #0a1810;
}

/* Font everywhere in topbar */
.top-navbar, .top-navbar *, .tb-dropdown, .tb-dropdown * {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    -webkit-font-smoothing: antialiased;
}

/* ─────────────────────────────────────────────
   BAR
───────────────────────────────────────────── */
.top-navbar {
    height: var(--tb-h);
    background: var(--tb-bg);
    border-bottom: 1px solid var(--tb-bdr);
    position: sticky; top: 0; z-index: 1020;
    display: flex; align-items: center;
    justify-content: space-between;
    padding: 0 26px;
    gap: 16px;
    box-shadow: var(--tb-sh);
    transition: background .28s ease;
}

/* ─────────────────────────────────────────────
   PAGE TITLE BLOCK
───────────────────────────────────────────── */
.tb-page-title {
    font-size: .95rem; font-weight: 800;
    color: var(--tb-t1); letter-spacing: -.3px;
    line-height: 1.2; margin: 0 0 2px;
}

.tb-welcome {
    display: flex; align-items: center; gap: 5px;
    font-size: .7rem; font-weight: 600; color: var(--tb-t2);
}

.tb-online-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: var(--grn); flex-shrink: 0;
    animation: tb-blink 2s ease-in-out infinite;
}

@keyframes tb-blink { 0%,100%{opacity:1} 50%{opacity:.25} }

/* ─────────────────────────────────────────────
   ICON BUTTONS
───────────────────────────────────────────── */
.tb-btn {
    width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
    border-radius: var(--tb-r);
    background: var(--tb-bg2);
    border: 1px solid var(--tb-bdr);
    color: var(--tb-t2);
    cursor: pointer; font-size: .92rem; flex-shrink: 0;
    position: relative;
    transition: all .22s var(--tb-spring);
    outline: none;
}

.tb-btn:hover, .tb-btn[aria-expanded="true"] {
    background: var(--f);
    color: var(--lime);
    border-color: transparent;
    transform: translateY(-2px) scale(1.04);
    box-shadow: 0 6px 18px rgba(11,36,25,.2);
}

/* Unread dot */
.tb-dot {
    position: absolute; top: 8px; right: 8px;
    width: 7px; height: 7px; border-radius: 50%;
    background: var(--red); border: 2px solid var(--tb-bg);
    transition: all .2s ease;
}

.tb-btn:hover .tb-dot,
.tb-btn[aria-expanded="true"] .tb-dot {
    background: var(--lime);
    border-color: var(--f);
}

/* ─────────────────────────────────────────────
   VERTICAL DIVIDER
───────────────────────────────────────────── */
.tb-sep {
    width: 1px; height: 26px;
    background: var(--tb-bdr); flex-shrink: 0;
}

/* ─────────────────────────────────────────────
   USER PILL
───────────────────────────────────────────── */
.tb-user-pill {
    display: flex; align-items: center; gap: 10px;
    padding: 4px 5px 4px 12px;
    background: var(--tb-bg2); border: 1px solid var(--tb-bdr);
    border-radius: 50px; cursor: pointer;
    transition: all .22s var(--tb-ease);
    outline: none;
}

.tb-user-pill:hover { border-color: rgba(11,36,25,.18); }
[data-bs-theme="dark"] .tb-user-pill:hover { border-color: rgba(163,230,53,.3); }

.tb-user-name {
    font-size: .82rem; font-weight: 700;
    color: var(--tb-t1); white-space: nowrap;
}

.tb-user-role-chip {
    display: inline-flex;
    background: var(--f); color: var(--lime);
    font-size: .52rem; font-weight: 800;
    padding: 2px 7px; border-radius: 6px;
    letter-spacing: .7px; text-transform: uppercase;
    line-height: 1.5;
}

.tb-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    object-fit: cover; flex-shrink: 0;
    border: 2px solid var(--tb-bdr);
    transition: border-color .2s ease;
}

.tb-user-pill:hover .tb-avatar { border-color: rgba(11,36,25,.2); }

/* ─────────────────────────────────────────────
   DROPDOWNS
───────────────────────────────────────────── */
.tb-dropdown {
    border: 1px solid var(--tb-bdr) !important;
    border-radius: 18px !important;
    box-shadow: 0 12px 48px rgba(11,36,25,.12) !important;
    padding: 6px !important;
    margin-top: 10px !important;
    background: var(--tb-bg) !important;
    animation: tb-drop .22s var(--tb-ease) both;
}

@keyframes tb-drop { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

.tb-dd-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 12px 9px;
    border-bottom: 1px solid var(--tb-bdr2); margin-bottom: 4px;
}

.tb-dd-title {
    font-size: .7rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 1px;
    color: var(--tb-t1);
}

.tb-dd-count { font-size: .65rem; font-weight: 700; color: var(--tb-t3); margin-left: 4px; }

.tb-dd-link {
    font-size: .65rem; font-weight: 800;
    color: var(--f); text-decoration: none;
    text-transform: uppercase; letter-spacing: .5px;
    border-bottom: 1.5px solid var(--lime);
    padding-bottom: 1px; transition: opacity .18s ease;
}

.tb-dd-link:hover { opacity: .6; }

.tb-dd-scroll {
    max-height: 320px; overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--tb-bdr) transparent;
}

.tb-dd-scroll::-webkit-scrollbar       { width:3px; }
.tb-dd-scroll::-webkit-scrollbar-track { background:transparent; }
.tb-dd-scroll::-webkit-scrollbar-thumb { background:var(--tb-bdr); border-radius:99px; }

/* ─────────────────────────────────────────────
   MESSAGE ITEMS
───────────────────────────────────────────── */
.tb-msg-row {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px 10px; border-radius: 12px;
    cursor: pointer; position: relative;
    margin-bottom: 1px;
    transition: background .14s ease;
}

.tb-msg-row:hover { background: var(--tb-bg2); }

.tb-msg-row.unread::before {
    content: '';
    position: absolute; left:0; top:14px;
    width: 2.5px; height: calc(100% - 28px);
    background: linear-gradient(to bottom, var(--grn), var(--lime));
    border-radius: 0 3px 3px 0;
}

.tb-msg-av {
    width: 36px; height: 36px; border-radius: 10px;
    background: linear-gradient(135deg, var(--f), var(--fm));
    color: var(--lime);
    display: flex; align-items: center; justify-content: center;
    font-size: .78rem; font-weight: 800; flex-shrink: 0;
}

.tb-msg-sender { font-size: .82rem; font-weight: 700; color: var(--tb-t1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px; }
.tb-msg-body   { font-size: .72rem; font-weight: 500; color: var(--tb-t2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
.tb-msg-time   { font-size: .62rem; font-weight: 600; color: var(--tb-t3); white-space: nowrap; flex-shrink: 0; }

/* ─────────────────────────────────────────────
   NOTIFICATION ITEMS
───────────────────────────────────────────── */
.tb-notif-row {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px; border-radius: 12px;
    cursor: pointer; position: relative;
    margin-bottom: 1px;
    transition: background .14s ease;
}

.tb-notif-row:hover { background: var(--tb-bg2); }

.tb-notif-row.unread::before {
    content: '';
    position: absolute; left:0; top:12px;
    width: 2.5px; height: calc(100% - 24px);
    background: linear-gradient(to bottom, var(--grn), var(--lime));
    border-radius: 0 3px 3px 0;
}

.tb-notif-ico {
    width: 34px; height: 34px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: .85rem; flex-shrink: 0;
}

.nico-grn  { background: var(--grn-bg); color: var(--grn); }
.nico-red  { background: var(--red-bg); color: var(--red); }
.nico-amb  { background: var(--amb-bg); color: var(--amb); }
.nico-def  { background: rgba(11,36,25,.06); color: var(--tb-t2); }

.tb-notif-msg  { font-size: .78rem; font-weight: 600; color: var(--tb-t1); line-height: 1.45; margin-bottom: 3px; }
.tb-notif-time { font-size: .62rem; font-weight: 600; color: var(--tb-t3); display: flex; align-items: center; gap: 3px; }

/* ─────────────────────────────────────────────
   EMPTY STATES
───────────────────────────────────────────── */
.tb-empty {
    padding: 32px 20px; text-align: center; color: var(--tb-t3);
}
.tb-empty i { font-size: 2rem; display: block; margin-bottom: 8px; opacity: .25; }
.tb-empty p { font-size: .78rem; font-weight: 600; margin: 0; }

/* ─────────────────────────────────────────────
   USER DROPDOWN ITEMS
───────────────────────────────────────────── */
.tb-profile-header {
    padding: 8px 12px 10px;
    border-bottom: 1px solid var(--tb-bdr2);
    margin-bottom: 4px;
}

.tb-profile-name {
    font-size: .88rem; font-weight: 800; color: var(--tb-t1);
    margin-bottom: 2px;
}

.tb-profile-role {
    font-size: .62rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .7px;
    color: var(--tb-t3);
    display: flex; align-items: center; gap: 5px;
}

.tb-profile-role::before {
    content: ''; width: 5px; height: 5px; border-radius: 50%;
    background: var(--grn); flex-shrink: 0;
}

.tb-menu-item {
    display: flex; align-items: center; gap: 9px;
    padding: 9px 12px; border-radius: 10px;
    font-size: .82rem; font-weight: 600;
    color: var(--tb-t1); text-decoration: none;
    transition: all .15s ease;
}

.tb-menu-item:hover { background: var(--tb-bg2); color: var(--tb-t1); }
.tb-menu-item.logout { color: var(--red); }
.tb-menu-item.logout:hover { background: var(--red-bg); color: var(--red); }

.tb-menu-ico {
    width: 28px; height: 28px; border-radius: 8px;
    background: var(--tb-bg2); border: 1px solid var(--tb-bdr2);
    display: flex; align-items: center; justify-content: center;
    font-size: .78rem; flex-shrink: 0;
    transition: all .15s ease;
}

.tb-menu-item:hover .tb-menu-ico { background: rgba(11,36,25,.07); border-color: var(--tb-bdr); }
.tb-menu-item.logout .tb-menu-ico { background: var(--red-bg); border-color: transparent; color: var(--red); }
.tb-menu-item.logout:hover .tb-menu-ico { background: var(--red); color: #fff; }

.tb-menu-divider { height: 1px; background: var(--tb-bdr2); margin: 5px 4px; }

/* Mobile menu toggle */
.tb-mobile-btn {
    width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
    border-radius: var(--tb-r);
    background: var(--tb-bg2); border: 1px solid var(--tb-bdr);
    color: var(--tb-t2); cursor: pointer; font-size: 1.1rem;
    transition: all .22s var(--tb-spring);
}

.tb-mobile-btn:hover {
    background: var(--f); color: var(--lime);
    border-color: transparent;
    transform: scale(1.06);
}
</style>

<nav class="top-navbar">

    <!-- ── Left: Mobile toggle + Page title ── -->
    <div class="d-flex align-items-center gap-3">
        <button class="tb-mobile-btn d-lg-none" id="mobileSidebarToggle" title="Menu">
            <i class="bi bi-list"></i>
        </button>
        <div>
            <div class="tb-page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
            <div class="tb-welcome d-none d-md-flex">
                <span class="tb-online-dot"></span>
                Welcome back, <?= $first_name ?>
            </div>
        </div>
    </div>

    <!-- ── Right: Action cluster ── -->
    <div class="d-flex align-items-center gap-2">

        <!-- Theme toggle -->
        <button class="tb-btn" id="themeToggle" title="Toggle Theme">
            <i class="bi bi-moon-stars" id="themeIcon"></i>
        </button>

        <!-- Messages -->
        <div class="dropdown">
            <button class="tb-btn" data-bs-toggle="dropdown" aria-expanded="false" title="Messages">
                <i class="bi bi-chat-dots-fill"></i>
                <?php if ($unread_msgs_count > 0): ?><span class="tb-dot"></span><?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end tb-dropdown" style="width:360px;">
                <div class="tb-dd-head">
                    <span class="tb-dd-title">Messages<span class="tb-dd-count"><?= $unread_msgs_count > 0 ? "({$unread_msgs_count} new)" : '' ?></span></span>
                    <a href="<?= $msgs_link ?>" class="tb-dd-link">View All</a>
                </div>
                <div class="tb-dd-scroll">
                    <?php if (!empty($recent_messages)): foreach ($recent_messages as $msg):
                        $chat_id = $user_role==='member'
                            ? ($msg['from_admin_id'] ?: $msg['from_member_id'])
                            : $msg['from_member_id'];
                        $parts   = explode(' ', trim($msg['sender_name'] ?? 'U'));
                        $initial = count($parts) > 1
                            ? strtoupper(substr($parts[0],0,1).substr(end($parts),0,1))
                            : strtoupper(substr($msg['sender_name'] ?? 'U', 0, 1));
                        $unread  = $msg['is_read'] == 0;
                    ?>
                    <div class="tb-msg-row <?= $unread ? 'unread' : '' ?>"
                         onclick="markReadAndRedirect('message', <?= $msg['message_id'] ?>, '<?= $msgs_link ?>&chat_with=<?= $chat_id ?>')">
                        <div class="tb-msg-av"><?= $initial ?></div>
                        <div style="flex:1;min-width:0;">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                <span class="tb-msg-sender"><?= htmlspecialchars($msg['sender_name'] ?? 'User') ?></span>
                                <span class="tb-msg-time"><?= time_elapsed_string_topbar($msg['sent_at']) ?></span>
                            </div>
                            <div class="tb-msg-body"><?= htmlspecialchars($msg['body'] ?: 'Sent an attachment') ?></div>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                    <div class="tb-empty"><i class="bi bi-chat-square-dots"></i><p>No recent messages</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div class="dropdown">
            <button class="tb-btn" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="bi bi-bell-fill"></i>
                <?php if ($unread_notif_count > 0): ?><span class="tb-dot"></span><?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end tb-dropdown" style="width:360px;">
                <div class="tb-dd-head">
                    <span class="tb-dd-title">Notifications<span class="tb-dd-count"><?= $unread_notif_count > 0 ? "({$unread_notif_count} unread)" : '' ?></span></span>
                    <a href="<?= $notif_link ?>" class="tb-dd-link">Mark All Read</a>
                </div>
                <div class="tb-dd-scroll">
                    <?php if (!empty($recent_notifs)): foreach ($recent_notifs as $n):
                        $ico_cls = 'nico-def'; $ico = 'bi-bell-fill';
                        $msg_lc  = strtolower($n['message'] ?? '');
                        if (str_contains($msg_lc,'payment')||str_contains($msg_lc,'approved')) { $ico='bi-check-circle-fill'; $ico_cls='nico-grn'; }
                        if (str_contains($msg_lc,'reject')||str_contains($msg_lc,'alert'))     { $ico='bi-exclamation-circle-fill'; $ico_cls='nico-red'; }
                        if (str_contains($msg_lc,'warn'))  { $ico='bi-exclamation-triangle-fill'; $ico_cls='nico-amb'; }
                        $unread = $n['status'] === 'unread';
                    ?>
                    <div class="tb-notif-row <?= $unread ? 'unread' : '' ?>"
                         onclick="markReadAndRedirect('notification', <?= $n['notification_id'] ?>, '<?= $notif_link ?>')">
                        <div class="tb-notif-ico <?= $ico_cls ?>"><i class="bi <?= $ico ?>"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div class="tb-notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                            <div class="tb-notif-time"><i class="bi bi-clock" style="font-size:.55rem;"></i><?= time_elapsed_string_topbar($n['created_at']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                    <div class="tb-empty"><i class="bi bi-bell-slash"></i><p>No notifications</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tb-sep d-none d-md-block"></div>

        <!-- User profile -->
        <div class="dropdown">
            <div class="tb-user-pill" data-bs-toggle="dropdown" aria-expanded="false" tabindex="0">
                <div class="d-none d-md-flex flex-column align-items-end" style="gap:2px;">
                    <span class="tb-user-name"><?= htmlspecialchars($member_name) ?></span>
                    <span class="tb-user-role-chip"><?= strtoupper($user_role) ?></span>
                </div>
                <img src="<?= $pic_src ?>" alt="Avatar" class="tb-avatar">
            </div>
            <ul class="dropdown-menu dropdown-menu-end tb-dropdown" style="min-width:210px;">
                <!-- Profile header -->
                <li>
                    <div class="tb-profile-header">
                        <div class="tb-profile-name"><?= htmlspecialchars($member_name) ?></div>
                        <div class="tb-profile-role"><?= strtoupper($user_role) ?></div>
                    </div>
                </li>
                <li>
                    <a href="<?= $profile_link ?>" class="tb-menu-item">
                        <span class="tb-menu-ico"><i class="bi bi-person-fill"></i></span> My Profile
                    </a>
                </li>
                <?php if ($user_role === 'member'): ?>
                <li>
                    <a href="<?= $base ?>/member/pages/settings.php" class="tb-menu-item">
                        <span class="tb-menu-ico"><i class="bi bi-gear-wide-connected"></i></span> Settings
                    </a>
                </li>
                <?php else: ?>
                <li>
                    <a href="<?= $base ?>/public/admin_settings.php" class="tb-menu-item">
                        <span class="tb-menu-ico"><i class="bi bi-sliders2"></i></span> Config
                    </a>
                </li>
                <?php endif; ?>
                <li><div class="tb-menu-divider"></div></li>
                <li>
                    <a href="<?= $base ?>/public/logout.php" class="tb-menu-item logout">
                        <span class="tb-menu-ico"><i class="bi bi-box-arrow-right"></i></span> Sign Out
                    </a>
                </li>
            </ul>
        </div>

    </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {

    /* ── Theme toggle ── */
    const html      = document.documentElement;
    const themeIcon = document.getElementById('themeIcon');

    const applyTheme = (t) => {
        html.setAttribute('data-bs-theme', t);
        if (!themeIcon) return;
        themeIcon.className = t === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    };

    const saved = localStorage.getItem('theme');
    if (saved) applyTheme(saved);

    document.getElementById('themeToggle')?.addEventListener('click', () => {
        const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        localStorage.setItem('theme', next);
    });

    /* ── Mobile sidebar ── */
    const sidebar   = document.getElementById('sidebar');
    const backdrop  = document.getElementById('sidebarBackdrop');
    const mobileBtn = document.getElementById('mobileSidebarToggle');

    if (mobileBtn && sidebar) {
        mobileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('mobile-open');
            backdrop?.classList.toggle('show');
        });
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 992 &&
                sidebar.classList.contains('mobile-open') &&
                !sidebar.contains(e.target) &&
                !mobileBtn.contains(e.target)) {
                sidebar.classList.remove('mobile-open');
                backdrop?.classList.remove('show');
            }
        });
        backdrop?.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            backdrop.classList.remove('show');
        });
    }

    /* ── Desktop sidebar collapse (toggle button in sidebar.php) ── */
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        if (localStorage.getItem('sb_collapsed') === '1') document.body.classList.add('sb-collapsed');
        sidebarToggle.addEventListener('click', () => {
            document.body.classList.toggle('sb-collapsed');
            localStorage.setItem('sb_collapsed', document.body.classList.contains('sb-collapsed') ? '1' : '0');
        });
    }
});

function markReadAndRedirect(type, id, url) {
    const fd = new FormData();
    fd.append('type', type); fd.append('id', id);
    fetch('<?= BASE_URL ?>/public/ajax_mark_read.php', { method:'POST', body:fd, keepalive:true })
        .catch(e => console.error('mark read:', e));
    window.location.href = url;
}
</script>