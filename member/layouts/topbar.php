<?php
// inc/topbar.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../inc/Auth.php';

$my_user_id  = $_SESSION['member_id'] ?? $_SESSION['admin_id'] ?? 0;
$user_role   = $_SESSION['role']      ?? 'member';
$member_name = $_SESSION['member_name'] ?? $_SESSION['full_name'] ?? 'User';
$profile_pic_db = null;
$user_gender = 'male';

$recent_messages    = [];
$recent_notifs      = [];
$unread_msgs_count  = 0;
$unread_notif_count = 0;

if (!function_exists('time_elapsed_string_topbar')) {
    function time_elapsed_string_topbar($datetime) {
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        if ($diff->i < 1 && $diff->h < 1 && $diff->d < 1) return "just now";
        foreach (['y'=>'yr','m'=>'mo','d'=>'day','h'=>'hr','i'=>'min','s'=>'sec'] as $k=>$v){
            if ($diff->$k > 0) return $diff->$k . " {$v}" . ($diff->$k>1?'s':'') . " ago";
        }
        return "just now";
    }
}

if ($my_user_id > 0 && isset($conn)) {
    $stmt = ($user_role==='member')
        ? $conn->prepare("SELECT full_name, profile_pic, gender FROM members WHERE member_id=?")
        : $conn->prepare("SELECT full_name, profile_pic, 'male' AS gender FROM admins WHERE admin_id=?");
    if ($stmt) {
        $stmt->bind_param("i", $my_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($u = $res->fetch_assoc()) {
            $member_name    = $u['full_name'];
            $profile_pic_db = $u['profile_pic'];
            $user_gender    = $u['gender'];
        }
        $stmt->close();
    }

    $msg_sql = ($user_role==='member')
        ? "SELECT m.message_id, m.body, m.subject, m.sent_at, m.is_read,
                   COALESCE(a.full_name, mem.full_name, 'System') AS sender_name,
                   m.from_admin_id, m.from_member_id
           FROM messages m
           LEFT JOIN admins a ON m.from_admin_id=a.admin_id
           LEFT JOIN members mem ON m.from_member_id=mem.member_id
           WHERE m.to_member_id=? ORDER BY m.sent_at DESC LIMIT 5"
        : "SELECT m.message_id, m.body, m.subject, m.sent_at, m.is_read,
                   mem.full_name AS sender_name, m.from_member_id
           FROM messages m
           JOIN members mem ON m.from_member_id=mem.member_id
           WHERE m.to_admin_id=? ORDER BY m.sent_at DESC LIMIT 5";

    if ($stmt = $conn->prepare($msg_sql)) {
        $stmt->bind_param("i", $my_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $recent_messages[] = $row;
        $stmt->close();
    }

    $cnt_sql = ($user_role==='member')
        ? "SELECT COUNT(*) AS cnt FROM messages WHERE to_member_id=? AND is_read=0"
        : "SELECT COUNT(*) AS cnt FROM messages WHERE to_admin_id=? AND is_read=0";
    if ($stmt = $conn->prepare($cnt_sql)) {
        $stmt->bind_param("i", $my_user_id);
        $stmt->execute();
        $unread_msgs_count = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
    }

    if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0) {
        $sql = "SELECT * FROM notifications WHERE user_type=? AND user_id=? ORDER BY created_at DESC LIMIT 5";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("si", $user_role, $my_user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($n = $res->fetch_assoc()) $recent_notifs[] = $n;
            $stmt->close();
        }
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_type=? AND user_id=? AND status='unread'");
        $stmt->bind_param("si", $user_role, $my_user_id);
        $stmt->execute();
        $unread_notif_count = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
    }
}

$base = defined("BASE_URL") ? BASE_URL : '..';
$default_avatar = strtolower($user_gender ?? 'male') === 'female' ? 'female.jpg' : 'male.jpg';
$pic_src = !empty($profile_pic_db)
    ? "data:image/jpeg;base64," . base64_encode($profile_pic_db)
    : "{$base}/public/assets/uploads/{$default_avatar}";
$current_url  = urlencode($_SERVER['REQUEST_URI'] ?? '');
$msgs_link    = "{$base}/public/messages.php?return_to={$current_url}";
$notif_link   = ($user_role==='member') ? "{$base}/member/pages/notifications.php" : "#";
$profile_link = ($user_role==='member') ? "{$base}/member/pages/profile.php" : "{$base}/public/admin_settings.php";

$first_name = htmlspecialchars(explode(' ', $member_name)[0]);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

<style>
/* ─── Topbar Variables ─── */
:root {
    --tb-height: 68px;
    --tb-bg: #ffffff;
    --tb-border: #F0F7F4;
    --tb-text: #7a9e8e;
    --tb-text-dark: #0F392B;
    --tb-icon-bg: #F7FBF9;
    --tb-icon-border: #E8F0ED;
    --tb-icon-hover-bg: #0F392B;
    --tb-icon-hover-color: #A3E635;
    --tb-lime: #A3E635;
    --tb-forest: #0F392B;
    --tb-radius: 12px;
}
[data-bs-theme="dark"] {
    --tb-bg: #0c1a14;
    --tb-border: rgba(255,255,255,0.05);
    --tb-text: #7a9e8e;
    --tb-text-dark: #e0f0ea;
    --tb-icon-bg: rgba(255,255,255,0.04);
    --tb-icon-border: rgba(255,255,255,0.07);
}

/* ─── Bar ─── */
.top-navbar {
    height: var(--tb-height);
    background: var(--tb-bg);
    border-bottom: 1px solid var(--tb-border);
    position: sticky;
    top: 0;
    z-index: 1020;
    font-family: 'Plus Jakarta Sans', sans-serif;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    gap: 16px;
    box-shadow: 0 1px 12px rgba(15,57,43,0.05);
    transition: background 0.3s;
}

/* ─── Page Title ─── */
.tb-title-wrap h5 {
    font-size: 1rem;
    font-weight: 800;
    color: var(--tb-text-dark);
    letter-spacing: -0.3px;
    margin: 0 0 2px;
    line-height: 1.2;
}
.tb-welcome {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--tb-text);
}
.tb-welcome-dot {
    width: 5px; height: 5px;
    border-radius: 50%;
    background: #39B54A;
    animation: tbDotPulse 2s ease-in-out infinite;
    flex-shrink: 0;
}
@keyframes tbDotPulse { 0%,100%{opacity:1} 50%{opacity:0.35} }

/* ─── Icon Buttons ─── */
.tb-icon-btn {
    width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
    border-radius: var(--tb-radius);
    background: var(--tb-icon-bg);
    border: 1px solid var(--tb-icon-border);
    color: var(--tb-text);
    cursor: pointer;
    position: relative;
    transition: all 0.2s cubic-bezier(0.4,0,0.2,1);
    font-size: 0.95rem;
    flex-shrink: 0;
}
.tb-icon-btn:hover,
.tb-icon-btn[aria-expanded="true"] {
    background: var(--tb-icon-hover-bg);
    color: var(--tb-icon-hover-color);
    border-color: transparent;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(15,57,43,0.2);
}

/* Badge Dot */
.tb-badge {
    position: absolute;
    top: 8px; right: 8px;
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #dc2626;
    border: 2px solid var(--tb-bg);
}
.tb-icon-btn:hover .tb-badge,
.tb-icon-btn[aria-expanded="true"] .tb-badge {
    background: var(--tb-lime);
    border-color: var(--tb-forest);
}

/* ─── Divider ─── */
.tb-divider {
    width: 1px;
    height: 28px;
    background: var(--tb-border);
    flex-shrink: 0;
}

/* ─── User Pill ─── */
.tb-user-pill {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 6px 5px 12px;
    background: var(--tb-icon-bg);
    border: 1px solid var(--tb-icon-border);
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.2s;
}
.tb-user-pill:hover { border-color: var(--tb-forest); }
.tb-user-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--tb-text-dark);
    white-space: nowrap;
}
.tb-user-role {
    display: inline-flex;
    background: var(--tb-forest);
    color: var(--tb-lime);
    font-size: 0.55rem;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 6px;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    line-height: 1.4;
}
.tb-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--tb-border);
    flex-shrink: 0;
}

/* ─── Dropdowns ─── */
.tb-dropdown {
    border: 1px solid var(--tb-border) !important;
    border-radius: 18px !important;
    box-shadow: 0 12px 44px rgba(15,57,43,0.1) !important;
    padding: 6px !important;
    margin-top: 10px !important;
    background: var(--tb-bg) !important;
    font-family: 'Plus Jakarta Sans', sans-serif;
    animation: tbDropIn 0.2s cubic-bezier(0.16,1,0.3,1) both;
}
@keyframes tbDropIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

.tb-dd-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px 8px;
    border-bottom: 1px solid var(--tb-border);
    margin-bottom: 4px;
}
.tb-dd-head-title {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.9px;
    color: var(--tb-text-dark);
}
.tb-dd-head-count {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--tb-text);
}
.tb-dd-view-all {
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--tb-forest);
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1.5px solid var(--tb-lime);
    padding-bottom: 1px;
    transition: opacity 0.2s;
}
.tb-dd-view-all:hover { opacity: 0.65; }

.tb-dd-scroll { max-height: 320px; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #E0EDE7 transparent; }

/* Message Item */
.tb-msg-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 10px;
    border-radius: 12px;
    text-decoration: none;
    color: inherit;
    transition: background 0.18s;
    position: relative;
    margin-bottom: 1px;
    cursor: pointer;
}
.tb-msg-item:hover { background: #F7FBF9; }
.tb-msg-item.unread::before {
    content: '';
    position: absolute;
    left: 0; top: 14px;
    width: 3px; height: calc(100% - 28px);
    background: linear-gradient(to bottom, #39B54A, #A3E635);
    border-radius: 0 3px 3px 0;
}
.tb-msg-avatar {
    width: 36px; height: 36px;
    border-radius: 11px;
    background: linear-gradient(135deg, #0F392B, #1a5c43);
    color: #A3E635;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem;
    font-weight: 800;
    flex-shrink: 0;
}
.tb-msg-sender {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--tb-text-dark);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 160px;
}
.tb-msg-preview {
    font-size: 0.73rem;
    color: var(--tb-text);
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.tb-msg-time {
    font-size: 0.63rem;
    color: var(--tb-text);
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
}

/* Notification Item */
.tb-notif-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px;
    border-radius: 12px;
    text-decoration: none;
    color: inherit;
    transition: background 0.18s;
    position: relative;
    margin-bottom: 1px;
    cursor: pointer;
}
.tb-notif-item:hover { background: #F7FBF9; }
.tb-notif-item.unread::before {
    content: '';
    position: absolute;
    left: 0; top: 12px;
    width: 3px; height: calc(100% - 24px);
    background: linear-gradient(to bottom, #39B54A, #A3E635);
    border-radius: 0 3px 3px 0;
}
.tb-notif-icon {
    width: 34px; height: 34px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.88rem;
    flex-shrink: 0;
}
.tb-notif-icon-success { background: #D1FAE5; color: #059669; }
.tb-notif-icon-warn    { background: #FEF3C7; color: #d97706; }
.tb-notif-icon-danger  { background: #FEE2E2; color: #dc2626; }
.tb-notif-icon-default { background: #E8F5E9; color: #1a6b35; }
.tb-notif-msg  { font-size: 0.78rem; font-weight: 600; color: var(--tb-text-dark); line-height: 1.5; margin-bottom: 3px; }
.tb-notif-time { font-size: 0.62rem; font-weight: 600; color: var(--tb-text); display: flex; align-items: center; gap: 3px; }

/* Empty States */
.tb-empty {
    padding: 32px 20px;
    text-align: center;
    color: var(--tb-text);
}
.tb-empty i { font-size: 2rem; display: block; margin-bottom: 8px; opacity: 0.3; }
.tb-empty p { font-size: 0.78rem; font-weight: 600; margin: 0; }

/* User Dropdown Items */
.tb-user-dd-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 9px 12px;
    border-radius: 10px;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--tb-text-dark);
    text-decoration: none;
    transition: all 0.18s;
}
.tb-user-dd-item:hover { background: #F7FBF9; color: var(--tb-forest); }
.tb-user-dd-item.danger { color: #dc2626; }
.tb-user-dd-item.danger:hover { background: #FEE2E2; color: #991b1b; }
.tb-user-dd-icon {
    width: 28px; height: 28px;
    border-radius: 8px;
    background: #F0F7F4;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem;
    flex-shrink: 0;
}
.tb-user-dd-item.danger .tb-user-dd-icon { background: #FEE2E2; color: #dc2626; }
.tb-user-dd-divider { height: 1px; background: var(--tb-border); margin: 5px 4px; }

/* Mobile toggle */
.tb-mobile-toggle {
    width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
    border-radius: var(--tb-radius);
    background: var(--tb-icon-bg);
    border: 1px solid var(--tb-icon-border);
    color: var(--tb-text);
    cursor: pointer;
    font-size: 1.1rem;
    transition: all 0.2s;
    flex-shrink: 0;
}
.tb-mobile-toggle:hover { background: var(--tb-forest); color: var(--tb-lime); border-color: transparent; }
</style>

<nav class="top-navbar">

    <!-- Left: Mobile Toggle + Page Title -->
    <div class="d-flex align-items-center gap-3">
        <button class="tb-mobile-toggle d-lg-none" id="mobileSidebarToggle" title="Menu">
            <i class="bi bi-list"></i>
        </button>
        <div class="tb-title-wrap">
            <h5><?= $pageTitle ?? 'Dashboard' ?></h5>
            <div class="tb-welcome d-none d-md-flex">
                <span class="tb-welcome-dot"></span>
                Welcome back, <?= $first_name ?>
            </div>
        </div>
    </div>

    <!-- Right: Actions -->
    <div class="d-flex align-items-center gap-2">

        <!-- Theme Toggle -->
        <button class="tb-icon-btn" id="themeToggle" title="Toggle Theme">
            <i class="bi bi-moon-stars" id="themeIcon"></i>
        </button>

        <!-- Messages -->
        <div class="dropdown">
            <button class="tb-icon-btn" data-bs-toggle="dropdown" aria-expanded="false" title="Messages">
                <i class="bi bi-chat-dots-fill"></i>
                <?php if ($unread_msgs_count > 0): ?><span class="tb-badge"></span><?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end tb-dropdown" style="width:360px;">
                <div class="tb-dd-head">
                    <span class="tb-dd-head-title">Messages
                        <span class="tb-dd-head-count ms-1"><?= $unread_msgs_count > 0 ? "($unread_msgs_count new)" : '' ?></span>
                    </span>
                    <a href="<?= $msgs_link ?>" class="tb-dd-view-all">View All</a>
                </div>
                <div class="tb-dd-scroll">
                    <?php if (!empty($recent_messages)):
                        foreach ($recent_messages as $msg):
                            $chat_id = $user_role==='member'
                                ? ($msg['from_admin_id'] ?: $msg['from_member_id'])
                                : $msg['from_member_id'];
                            $initial = strtoupper(substr($msg['sender_name'] ?? 'U', 0, 1));
                            $parts = explode(' ', trim($msg['sender_name'] ?? 'U'));
                            if (count($parts) > 1) $initial = strtoupper(substr($parts[0],0,1) . substr(end($parts),0,1));
                            $is_unread = $msg['is_read'] == 0;
                    ?>
                    <div class="tb-msg-item <?= $is_unread ? 'unread' : '' ?>"
                         onclick="markReadAndRedirect('message', <?= $msg['message_id'] ?>, '<?= $msgs_link ?>&chat_with=<?= $chat_id ?>')">
                        <div class="tb-msg-avatar"><?= $initial ?></div>
                        <div style="flex:1;min-width:0;">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                <span class="tb-msg-sender"><?= htmlspecialchars($msg['sender_name'] ?? 'User') ?></span>
                                <span class="tb-msg-time"><?= time_elapsed_string_topbar($msg['sent_at']) ?></span>
                            </div>
                            <div class="tb-msg-preview"><?= htmlspecialchars($msg['body'] ?: 'Sent an attachment') ?></div>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                    <div class="tb-empty">
                        <i class="bi bi-chat-square-dots"></i>
                        <p>No recent messages</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div class="dropdown">
            <button class="tb-icon-btn" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="bi bi-bell-fill"></i>
                <?php if ($unread_notif_count > 0): ?><span class="tb-badge"></span><?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end tb-dropdown" style="width:360px;">
                <div class="tb-dd-head">
                    <span class="tb-dd-head-title">Notifications
                        <span class="tb-dd-head-count ms-1"><?= $unread_notif_count > 0 ? "($unread_notif_count unread)" : '' ?></span>
                    </span>
                    <a href="<?= $notif_link ?>" class="tb-dd-view-all">Mark All Read</a>
                </div>
                <div class="tb-dd-scroll">
                    <?php if (!empty($recent_notifs)):
                        foreach ($recent_notifs as $n):
                            $icon_class = 'tb-notif-icon-default';
                            $icon = 'bi-bell-fill';
                            if (stripos($n['message'], 'payment') !== false)  { $icon='bi-credit-card-fill'; $icon_class='tb-notif-icon-success'; }
                            if (stripos($n['message'], 'approved') !== false) { $icon='bi-check-circle-fill'; $icon_class='tb-notif-icon-success'; }
                            if (stripos($n['message'], 'reject') !== false || stripos($n['message'], 'alert') !== false) { $icon='bi-exclamation-circle-fill'; $icon_class='tb-notif-icon-danger'; }
                            if (stripos($n['message'], 'warn') !== false)     { $icon='bi-exclamation-triangle-fill'; $icon_class='tb-notif-icon-warn'; }
                            $is_unread = $n['status'] === 'unread';
                    ?>
                    <div class="tb-notif-item <?= $is_unread ? 'unread' : '' ?>"
                         onclick="markReadAndRedirect('notification', <?= $n['notification_id'] ?>, '<?= $notif_link ?>')">
                        <div class="tb-notif-icon <?= $icon_class ?>"><i class="bi <?= $icon ?>"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div class="tb-notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                            <div class="tb-notif-time"><i class="bi bi-clock" style="font-size:0.58rem"></i><?= time_elapsed_string_topbar($n['created_at']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                    <div class="tb-empty">
                        <i class="bi bi-bell-slash"></i>
                        <p>No notifications</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tb-divider d-none d-md-block"></div>

        <!-- User Profile -->
        <div class="dropdown">
            <div class="tb-user-pill d-flex" data-bs-toggle="dropdown" style="cursor:pointer;">
                <div class="d-none d-md-flex flex-column align-items-end" style="gap:2px;">
                    <span class="tb-user-name"><?= htmlspecialchars($member_name) ?></span>
                    <span class="tb-user-role"><?= strtoupper($user_role) ?></span>
                </div>
                <img src="<?= $pic_src ?>" alt="User" class="tb-avatar">
            </div>
            <ul class="dropdown-menu dropdown-menu-end tb-dropdown" style="min-width:200px; padding:6px;">
                <li>
                    <div style="padding:8px 12px 10px; border-bottom:1px solid var(--tb-border); margin-bottom:4px;">
                        <div style="font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.9px;color:var(--tb-text);">My Account</div>
                    </div>
                </li>
                <li>
                    <a href="<?= $profile_link ?>" class="tb-user-dd-item">
                        <span class="tb-user-dd-icon"><i class="bi bi-person-fill"></i></span> Profile
                    </a>
                </li>
                <?php if ($user_role === 'member'): ?>
                <li>
                    <a href="<?= $base ?>/member/pages/settings.php" class="tb-user-dd-item">
                        <span class="tb-user-dd-icon"><i class="bi bi-gear-wide-connected"></i></span> Settings
                    </a>
                </li>
                <?php else: ?>
                <li>
                    <a href="<?= $base ?>/public/admin_settings.php" class="tb-user-dd-item">
                        <span class="tb-user-dd-icon"><i class="bi bi-sliders2"></i></span> Config
                    </a>
                </li>
                <?php endif; ?>
                <li><div class="tb-user-dd-divider"></div></li>
                <li>
                    <a href="<?= $base ?>/public/logout.php" class="tb-user-dd-item danger">
                        <span class="tb-user-dd-icon"><i class="bi bi-power"></i></span> Sign Out
                    </a>
                </li>
            </ul>
        </div>

    </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {

    // Theme Toggle
    const html = document.documentElement;
    const themeIcon = document.getElementById('themeIcon');
    const updateIcon = (theme) => {
        if (!themeIcon) return;
        if (theme === 'dark') { themeIcon.classList.replace('bi-moon-stars', 'bi-sun'); }
        else { themeIcon.classList.replace('bi-sun', 'bi-moon-stars'); }
    };
    const saved = localStorage.getItem('theme');
    if (saved) { html.setAttribute('data-bs-theme', saved); updateIcon(saved); }
    document.getElementById('themeToggle')?.addEventListener('click', () => {
        const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', next);
        localStorage.setItem('theme', next);
        updateIcon(next);
    });

    // Mobile Sidebar Toggle
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('mobile-open');
            if (backdrop) backdrop.classList.toggle('show');
        });
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 992 &&
                sidebar.classList.contains('mobile-open') &&
                !sidebar.contains(e.target) &&
                !mobileToggle.contains(e.target)) {
                sidebar.classList.remove('mobile-open');
                if (backdrop) backdrop.classList.remove('show');
            }
        });
        if (backdrop) {
            backdrop.addEventListener('click', () => {
                sidebar.classList.remove('mobile-open');
                backdrop.classList.remove('show');
            });
        }
    }

    // Desktop Sidebar Collapse
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        const saved_sb = localStorage.getItem('sb_collapsed');
        if (saved_sb === 'true') document.body.classList.add('sb-collapsed');
        sidebarToggle.addEventListener('click', () => {
            document.body.classList.toggle('sb-collapsed');
            localStorage.setItem('sb_collapsed', document.body.classList.contains('sb-collapsed'));
        });
    }
});

function markReadAndRedirect(type, id, redirectUrl) {
    const formData = new FormData();
    formData.append('type', type);
    formData.append('id', id);
    fetch('<?= BASE_URL ?>/public/ajax_mark_read.php', {
        method: 'POST', body: formData, keepalive: true
    }).catch(err => console.error('Mark read error:', err));
    window.location.href = redirectUrl;
}
</script>