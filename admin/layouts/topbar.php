<?php
// admin/layouts/topbar.php
// Canonical Admin Topbar - Sync with inc/topbar.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/topbar_styles.php';

$user_name = $member_name ?? $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$base = defined('BASE_URL') ? BASE_URL : '/usms';

global $conn;
$unread_msgs        = 0;
$unread_notif_count = 0;
$recent_messages    = [];
$recent_notifs      = [];

if (isset($conn) && isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];

    // MESSAGES
    $msg_sql = "SELECT m.message_id, m.body, m.subject, m.sent_at, m.is_read,
                       mem.full_name AS sender_name, m.from_member_id
                FROM messages m
                JOIN members mem ON m.from_member_id = mem.member_id
                WHERE m.to_admin_id = ?
                ORDER BY m.sent_at DESC LIMIT 5";
    if ($stmt = $conn->prepare($msg_sql)) {
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $recent_messages[] = $row;
        $stmt->close();
    }

    $res = $conn->query("SELECT COUNT(*) as cnt FROM messages WHERE to_admin_id = $admin_id AND is_read = 0");
    if ($row = $res->fetch_assoc()) $unread_msgs = $row['cnt'];

    // NOTIFICATIONS
    if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0) {
        $sql = "SELECT * FROM notifications
                WHERE user_type = 'admin'
                AND (user_id = ? OR to_role = ? OR to_role = 'all')
                ORDER BY created_at DESC LIMIT 5";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("is", $admin_id, $user_role);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($n = $res->fetch_assoc()) $recent_notifs[] = $n;
            $stmt->close();
        }

        $sql_cnt = "SELECT COUNT(*) as cnt FROM notifications
                    WHERE user_type = 'admin'
                    AND (user_id = ? OR to_role = ? OR to_role = 'all')
                    AND status = 'unread'";
        if ($stmt = $conn->prepare($sql_cnt)) {
            $stmt->bind_param("is", $admin_id, $user_role);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) $unread_notif_count = $row['cnt'];
            $stmt->close();
        }
    }

    // PROFILE PIC
    $profile_pic_db = null;
    $stmt = $conn->prepare("SELECT profile_pic FROM admins WHERE admin_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($u = $res->fetch_assoc()) $profile_pic_db = $u['profile_pic'];
        $stmt->close();
    }
}

$current_url  = urlencode($_SERVER['REQUEST_URI'] ?? '');
$msgs_link    = "{$base}/public/messages.php?return_to={$current_url}";
$notif_link   = "#";
$profile_link = "{$base}/public/admin_settings.php";
$pic_src      = !empty($profile_pic_db)
    ? "data:image/jpeg;base64," . base64_encode($profile_pic_db)
    : "{$base}/public/assets/uploads/male.jpg";

// Initials fallback
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(explode(' ', trim($user_name)), 0, 2))));
?>

<!-- ═══════════════════════════════════════════════════ TOPBAR STYLES -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ── Topbar tokens (scoped, don't conflict with page tokens) ── */
.tb-root {
    --tb-forest:      #1a3a2a;
    --tb-forest-mid:  #234d38;
    --tb-lime:        #a8e063;
    --tb-lime-glow:   rgba(168,224,99,.18);
    --tb-ink:         #111c14;
    --tb-muted:       #6b7f72;
    --tb-surface:     #ffffff;
    --tb-surface-2:   #f5f8f5;
    --tb-border:      #e3ebe5;
    --tb-shadow:      0 4px 20px rgba(26,58,42,.08);
    --tb-shadow-lg:   0 8px 32px rgba(26,58,42,.14);
    --tb-radius:      12px;
    --tb-transition:  all .2s cubic-bezier(.4,0,.2,1);

    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* Dark mode overrides */
[data-bs-theme="dark"] .tb-root {
    --tb-surface:     #1a2520;
    --tb-surface-2:   #1f2e28;
    --tb-border:      #2e4438;
    --tb-ink:         #e8f0ea;
    --tb-muted:       #7a9485;
    --tb-shadow:      0 4px 20px rgba(0,0,0,.25);
    --tb-shadow-lg:   0 8px 32px rgba(0,0,0,.35);
}

/* ── Topbar shell ────────────────────────────────────── */
.top-navbar {
    background: var(--tb-surface);
    border-bottom: 1px solid var(--tb-border);
    height: 64px;
    padding: 0 24px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    box-shadow: var(--tb-shadow);
    position: sticky; top: 0; z-index: 1020;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    transition: var(--tb-transition);
}

/* ── Left: hamburger + page title ────────────────────── */
.tb-left { display: flex; align-items: center; gap: 14px; }

.tb-hamburger {
    width: 36px; height: 36px; border-radius: 10px;
    border: 1.5px solid var(--tb-border); background: var(--tb-surface-2);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: var(--tb-muted); cursor: pointer;
    transition: var(--tb-transition);
}
.tb-hamburger:hover { background: var(--tb-lime-glow); border-color: rgba(168,224,99,.4); color: var(--tb-forest); }

.tb-page-title  { font-size: .95rem; font-weight: 800; color: var(--tb-ink); letter-spacing: -.3px; line-height: 1; margin: 0; }
.tb-page-sub    { display: flex; align-items: center; gap: 5px; margin-top: 3px; }
.tb-sub-dot     { width: 5px; height: 5px; border-radius: 50%; background: var(--tb-lime); }
.tb-sub-text    { font-size: .72rem; font-weight: 600; color: var(--tb-muted); }

/* ── Right action cluster ────────────────────────────── */
.tb-right { display: flex; align-items: center; gap: 4px; }

/* Icon buttons */
.tb-icon-btn {
    position: relative;
    width: 38px; height: 38px; border-radius: 10px;
    border: 1.5px solid transparent; background: transparent;
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; color: var(--tb-muted); cursor: pointer;
    transition: var(--tb-transition);
}
.tb-icon-btn:hover { background: var(--tb-surface-2); border-color: var(--tb-border); color: var(--tb-ink); }
.tb-icon-btn:active { transform: scale(.94); }

/* Unread dot */
.tb-badge-dot {
    position: absolute; top: 6px; right: 6px;
    width: 8px; height: 8px; border-radius: 50%;
    background: #ef4444; border: 2px solid var(--tb-surface);
    animation: tb-pulse 2s infinite;
}
@keyframes tb-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,.4); }
    50%       { box-shadow: 0 0 0 5px rgba(239,68,68,0); }
}

/* Divider */
.tb-divider { width: 1px; height: 28px; background: var(--tb-border); margin: 0 6px; }

/* ── Dropdown panel shared ───────────────────────────── */
.tb-dropdown {
    background: var(--tb-surface) !important;
    border: 1px solid var(--tb-border) !important;
    border-radius: var(--tb-radius) !important;
    box-shadow: var(--tb-shadow-lg) !important;
    padding: 0 !important; overflow: hidden;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    animation: tb-fadeDown .18s ease both;
}
@keyframes tb-fadeDown { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }

.tb-dropdown-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-bottom: 1px solid var(--tb-border);
}
.tb-dropdown-header-title { font-size: .72rem; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: var(--tb-muted); }
.tb-dropdown-header-title span { color: var(--tb-forest); font-weight: 800; }
.tb-view-all { font-size: .72rem; font-weight: 700; color: var(--tb-forest); text-decoration: none; display: flex; align-items: center; gap: 4px; transition: var(--tb-transition); }
.tb-view-all:hover { color: var(--tb-lime); }

.tb-scroll-body { max-height: 340px; overflow-y: auto; }
.tb-scroll-body::-webkit-scrollbar { width: 4px; }
.tb-scroll-body::-webkit-scrollbar-track { background: transparent; }
.tb-scroll-body::-webkit-scrollbar-thumb { background: var(--tb-border); border-radius: 4px; }

/* ── Message items ───────────────────────────────────── */
.tb-msg-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 13px 18px; border-bottom: 1px solid var(--tb-border);
    text-decoration: none; transition: var(--tb-transition); cursor: pointer;
}
.tb-msg-item:last-child { border-bottom: none; }
.tb-msg-item:hover { background: var(--tb-surface-2); }
.tb-msg-item.unread { background: rgba(168,224,99,.06); }
.tb-msg-item.unread .tb-msg-name { color: var(--tb-forest); }

.tb-msg-avatar {
    width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--tb-forest), #2e6347);
    color: #fff; font-size: .8rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
}
.tb-msg-name { font-size: .84rem; font-weight: 700; color: var(--tb-ink); }
.tb-msg-time { font-size: .68rem; color: var(--tb-muted); font-weight: 500; white-space: nowrap; }
.tb-msg-body { font-size: .78rem; color: var(--tb-muted); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 230px; margin-top: 2px; }

/* ── Notification items ──────────────────────────────── */
.tb-notif-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 13px 18px; border-bottom: 1px solid var(--tb-border);
    transition: var(--tb-transition);
}
.tb-notif-item:last-child { border-bottom: none; }
.tb-notif-item:hover { background: var(--tb-surface-2); }
.tb-notif-item.unread { background: rgba(168,224,99,.06); }

.tb-notif-icon {
    width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
    background: var(--tb-lime-glow); color: var(--tb-forest);
    display: flex; align-items: center; justify-content: center; font-size: .85rem;
}
.tb-notif-msg  { font-size: .8rem; font-weight: 600; color: var(--tb-ink); line-height: 1.4; }
.tb-notif-time { font-size: .69rem; color: var(--tb-muted); margin-top: 3px; }

/* ── Empty states ────────────────────────────────────── */
.tb-empty { text-align: center; padding: 32px 18px; color: var(--tb-muted); }
.tb-empty i { font-size: 1.8rem; opacity: .2; display: block; margin-bottom: 8px; }
.tb-empty p { font-size: .78rem; margin: 0; }

/* ── User pill ───────────────────────────────────────── */
.tb-user-pill {
    display: flex; align-items: center; gap: 10px;
    padding: 5px 10px 5px 5px;
    border-radius: 100px; border: 1.5px solid var(--tb-border);
    background: var(--tb-surface-2); cursor: pointer;
    transition: var(--tb-transition);
}
.tb-user-pill:hover { background: var(--tb-lime-glow); border-color: rgba(168,224,99,.5); }

.tb-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    object-fit: cover; flex-shrink: 0;
    border: 2px solid var(--tb-lime-glow);
}
.tb-avatar-fallback {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, var(--tb-forest), #2e6347);
    color: #fff; font-size: .7rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid var(--tb-lime-glow);
}
.tb-user-name { font-size: .8rem; font-weight: 700; color: var(--tb-ink); line-height: 1; }
.tb-user-role { font-size: .62rem; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; color: var(--tb-muted); margin-top: 2px; }

/* Profile dropdown */
.tb-profile-section { padding: 16px 18px 12px; border-bottom: 1px solid var(--tb-border); }
.tb-profile-name { font-size: .9rem; font-weight: 800; color: var(--tb-ink); }
.tb-profile-role { font-size: .7rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; background: var(--tb-lime-glow); color: var(--tb-forest); border-radius: 100px; padding: 2px 10px; display: inline-block; margin-top: 4px; }

.tb-menu-item {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 18px; font-size: .83rem; font-weight: 600; color: var(--tb-ink);
    text-decoration: none; transition: var(--tb-transition); border: none; background: none; width: 100%; cursor: pointer;
}
.tb-menu-item:hover { background: var(--tb-surface-2); color: var(--tb-forest); }
.tb-menu-item i { width: 18px; text-align: center; opacity: .6; }
.tb-menu-item:hover i { opacity: 1; }
.tb-menu-item.danger { color: #c0392b; }
.tb-menu-item.danger:hover { background: #fef0f0; }
.tb-menu-divider { height: 1px; background: var(--tb-border); margin: 4px 0; }

/* Theme toggle active state */
.tb-icon-btn.theme-active { background: var(--tb-lime-glow); border-color: rgba(168,224,99,.4); color: var(--tb-forest); }

/* Count badge on icon */
.tb-count-badge {
    position: absolute; top: 4px; right: 4px;
    min-width: 16px; height: 16px; border-radius: 100px;
    background: var(--tb-forest); color: var(--tb-lime);
    font-size: .58rem; font-weight: 800; line-height: 16px; text-align: center;
    padding: 0 4px; border: 2px solid var(--tb-surface);
}
</style>

<!-- ═════════════════════════════════════════════════════ TOPBAR HTML -->
<div class="top-navbar tb-root">

    <!-- LEFT: Hamburger + Page Title -->
    <div class="tb-left">
        <button id="mobileSidebarToggle" class="tb-hamburger d-lg-none mobile-nav-toggle" aria-label="Toggle sidebar">
            <i class="bi bi-list-nested"></i>
        </button>

        <div>
            <div class="tb-page-title"><?= htmlspecialchars($pageTitle ?? 'Admin Console') ?></div>
            <div class="tb-page-sub d-none d-md-flex">
                <div class="tb-sub-dot"></div>
                <span class="tb-sub-text">Backend Management</span>
            </div>
        </div>
    </div>

    <!-- RIGHT: Action cluster -->
    <div class="tb-right">

        <!-- Theme toggle -->
        <button id="themeToggle" class="tb-icon-btn" title="Switch Theme" aria-label="Toggle dark mode">
            <i class="bi bi-moon-stars" id="themeIcon"></i>
        </button>

        <!-- ── Messages ─────────────────────────────────────── -->
        <div class="dropdown">
            <button class="tb-icon-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Messages">
                <i class="bi bi-chat-dots"></i>
                <?php if ($unread_msgs > 0): ?>
                    <span class="tb-count-badge"><?= $unread_msgs > 9 ? '9+' : $unread_msgs ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end tb-dropdown" style="width:360px">
                <div class="tb-dropdown-header">
                    <div class="tb-dropdown-header-title">
                        Messages <span>(<?= $unread_msgs ?>)</span>
                    </div>
                    <a href="<?= $msgs_link ?>" class="tb-view-all">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="tb-scroll-body">
                    <?php if (!empty($recent_messages)): foreach ($recent_messages as $msg): ?>
                        <a href="<?= $msgs_link ?>&chat_with=<?= $msg['from_member_id'] ?>"
                           class="tb-msg-item <?= $msg['is_read'] == 0 ? 'unread' : '' ?>">
                            <div class="tb-msg-avatar">
                                <?= strtoupper(substr($msg['sender_name'] ?? 'M', 0, 1)) ?>
                            </div>
                            <div style="flex:1;min-width:0">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                                    <div class="tb-msg-name"><?= htmlspecialchars($msg['sender_name'] ?? 'Member') ?></div>
                                    <div class="tb-msg-time"><?= date('H:i', strtotime($msg['sent_at'])) ?></div>
                                </div>
                                <div class="tb-msg-body"><?= htmlspecialchars($msg['body']) ?></div>
                            </div>
                        </a>
                    <?php endforeach; else: ?>
                        <div class="tb-empty">
                            <i class="bi bi-chat-square-dots"></i>
                            <p>No recent messages</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Notifications ────────────────────────────────── -->
        <div class="dropdown">
            <button class="tb-icon-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                <i class="bi bi-bell"></i>
                <?php if ($unread_notif_count > 0): ?>
                    <span class="tb-badge-dot"></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end tb-dropdown" style="width:300px">
                <div class="tb-dropdown-header">
                    <div class="tb-dropdown-header-title">
                        Notifications
                        <?php if ($unread_notif_count > 0): ?>
                            <span style="background:var(--tb-lime-glow);color:var(--tb-forest);border-radius:100px;padding:1px 8px;font-size:.65rem;font-weight:800;margin-left:6px"><?= $unread_notif_count ?> new</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="tb-scroll-body">
                    <?php if (!empty($recent_notifs)): foreach ($recent_notifs as $n): ?>
                        <div class="tb-notif-item <?= $n['status'] === 'unread' ? 'unread' : '' ?>">
                            <div class="tb-notif-icon"><i class="bi bi-bell"></i></div>
                            <div>
                                <div class="tb-notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                                <div class="tb-notif-time"><?= date('d M, H:i', strtotime($n['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="tb-empty">
                            <i class="bi bi-bell-slash"></i>
                            <p>No recent notifications</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tb-divider d-none d-md-block"></div>

        <!-- ── User profile pill ────────────────────────────── -->
        <div class="dropdown">
            <div class="tb-user-pill d-none d-md-flex" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                <?php if (!empty($profile_pic_db)): ?>
                    <img src="<?= $pic_src ?>" alt="Profile" class="tb-avatar">
                <?php else: ?>
                    <div class="tb-avatar-fallback"><?= $initials ?></div>
                <?php endif; ?>
                <div>
                    <div class="tb-user-name"><?= htmlspecialchars($user_name) ?></div>
                    <div class="tb-user-role"><?= htmlspecialchars($user_role) ?></div>
                </div>
            </div>

            <!-- Mobile: avatar only -->
            <button class="tb-icon-btn d-md-none" data-bs-toggle="dropdown" aria-label="Account">
                <?php if (!empty($profile_pic_db)): ?>
                    <img src="<?= $pic_src ?>" alt="Profile" class="tb-avatar" style="width:26px;height:26px">
                <?php else: ?>
                    <i class="bi bi-person-circle"></i>
                <?php endif; ?>
            </button>

            <ul class="dropdown-menu dropdown-menu-end tb-dropdown" style="min-width:220px">
                <!-- Profile header -->
                <li>
                    <div class="tb-profile-section">
                        <div class="tb-profile-name"><?= htmlspecialchars($user_name) ?></div>
                        <div class="tb-profile-role"><?= htmlspecialchars($user_role) ?></div>
                    </div>
                </li>
                <li>
                    <a class="tb-menu-item" href="<?= $profile_link ?>">
                        <i class="bi bi-person-gear"></i>Profile Settings
                    </a>
                </li>
                <li>
                    <a class="tb-menu-item" href="<?= $base ?>/admin/pages/settings.php">
                        <i class="bi bi-sliders2"></i>Global Config
                    </a>
                </li>
                <li><div class="tb-menu-divider"></div></li>
                <li>
                    <a class="tb-menu-item danger" href="<?= $base ?>/public/logout.php">
                        <i class="bi bi-box-arrow-right"></i>Sign Out
                    </a>
                </li>
            </ul>
        </div>

    </div><!-- /tb-right -->
</div>

<!-- ═══════════════════════════════════════════════════ TOPBAR SCRIPT -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const html        = document.documentElement;
    const themeIcon   = document.getElementById('themeIcon');
    const themeToggle = document.getElementById('themeToggle');

    const applyTheme = (theme) => {
        html.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
        if (theme === 'dark') {
            themeIcon?.classList.replace('bi-moon-stars', 'bi-sun');
            themeToggle?.classList.add('theme-active');
        } else {
            themeIcon?.classList.replace('bi-sun', 'bi-moon-stars');
            themeToggle?.classList.remove('theme-active');
        }
    };

    // Restore saved theme
    const saved = localStorage.getItem('theme');
    if (saved) applyTheme(saved);

    themeToggle?.addEventListener('click', () => {
        applyTheme(html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark');
    });
});
</script>