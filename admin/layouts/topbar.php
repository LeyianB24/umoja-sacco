<?php
// admin/layouts/topbar.php
// Canonical Admin Topbar - Sync with inc/topbar.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/topbar_styles.php';

$user_name = $member_name ?? $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$base = defined('BASE_URL') ? BASE_URL : '/usms';

// Notification & Message Counts
global $conn;
$unread_msgs  = 0;
$unread_notif_count = 0;
$recent_messages = [];
$recent_notifs = [];

if (isset($conn) && isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    
    // MESSAGES
    $msg_sql = "SELECT m.message_id, m.body, m.subject, m.sent_at, m.is_read,
                       mem.full_name AS sender_name,
                       m.from_member_id
                FROM messages m
                JOIN members mem ON m.from_member_id=mem.member_id
                WHERE m.to_admin_id=?
                ORDER BY m.sent_at DESC LIMIT 5";
    if($stmt=$conn->prepare($msg_sql)){
        $stmt->bind_param("i",$admin_id);
        $stmt->execute();
        $res=$stmt->get_result();
        while($row=$res->fetch_assoc()) $recent_messages[]=$row;
        $stmt->close();
    }

    $res = $conn->query("SELECT COUNT(*) as cnt FROM messages WHERE to_admin_id = $admin_id AND is_read = 0");
    if ($row = $res->fetch_assoc()) $unread_msgs = $row['cnt'];

    // NOTIFICATIONS
    if($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0) {
        $sql = "SELECT * FROM notifications 
                WHERE user_type = 'admin' 
                AND (user_id = ? OR to_role = ? OR to_role = 'all') 
                ORDER BY created_at DESC LIMIT 5";
        if($stmt=$conn->prepare($sql)){
            $stmt->bind_param("is", $admin_id, $user_role);
            $stmt->execute();
            $res=$stmt->get_result();
            while($n=$res->fetch_assoc()) $recent_notifs[]=$n;
            $stmt->close();
        }

        $sql_cnt = "SELECT COUNT(*) as cnt FROM notifications 
                    WHERE user_type = 'admin' 
                    AND (user_id = ? OR to_role = ? OR to_role = 'all') 
                    AND status = 'unread'";
        if($stmt=$conn->prepare($sql_cnt)){
            $stmt->bind_param("is", $admin_id, $user_role);
            $stmt->execute();
            $res=$stmt->get_result();
            if ($row = $res->fetch_assoc()) $unread_notif_count = $row['cnt'];
            $stmt->close();
        }
    }
}

// Ensure return_to captures the current page for context-aware back navigation
$current_url = urlencode($_SERVER['REQUEST_URI'] ?? '');
$msgs_link  = "{$base}/public/messages.php?return_to={$current_url}";
$notif_link = "#"; // Admin notifications hub if exists
$profile_link = "{$base}/public/admin_settings.php";
$pic_src = "{$base}/public/assets/uploads/male.jpg"; // Admin default
?>

<div class="top-navbar d-flex align-items-center justify-content-between px-3 px-lg-4">
    
    <div class="d-flex align-items-center gap-3">
        <button id="mobileSidebarToggle" class="btn-icon-nav d-lg-none border-0 shadow-none bg-transparent mobile-nav-toggle">
            <i class="bi bi-list-nested"></i>
        </button>

        <div>
            <h5 class="fw-bold mb-0" style="color: var(--nav-text-dark) !important; letter-spacing: -0.5px;">
                <?= $pageTitle ?? 'Admin Console' ?>
            </h5>
            <div class="d-none d-md-flex align-items-center gap-2">
                <span style="width:6px; height:6px; background:var(--accent-lime); border-radius:50%;"></span>
                <small style="color: var(--nav-text); font-size: 0.8rem;">
                    Backend Management
                </small>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 gap-md-3">
        
        <button id="themeToggle" class="btn-icon-nav" title="Switch Theme">
            <i class="bi bi-moon-stars" id="themeIcon"></i>
        </button>

        <!-- Messages Dropdown -->
        <div class="dropdown">
            <button class="btn-icon-nav" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-chat-dots"></i>
                <?php if($unread_msgs > 0): ?><span class="badge-dot"></span><?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end custom-dropdown animate__animated animate__fadeInUp" style="width: 360px;">
                <div class="dropdown-header-custom d-flex justify-content-between align-items-center">
                    <span>Messages <small class="text-muted fw-normal ms-1">(<?= $unread_msgs ?> new)</small></span>
                    <a href="<?= $msgs_link ?>" class="text-decoration-none fw-bold" style="font-size:0.75rem; color: var(--accent-green)">VIEW ALL</a>
                </div>
                
                <div style="max-height: 350px; overflow-y: auto;">
                <?php if(!empty($recent_messages)): foreach($recent_messages as $msg): ?>
                    <a href="<?= $msgs_link ?>&chat_with=<?= $msg['from_member_id'] ?>" class="list-item-custom <?= $msg['is_read']==0?'unread':'' ?>">
                        <div class="d-flex align-items-center gap-3">
                            <div class="msg-avatar bg-primary text-white d-flex align-items-center justify-content-center fw-bold">
                                <?= strtoupper(substr($msg['sender_name'] ?? 'M', 0, 1)) ?>
                            </div>
                            <div class="grow overflow-hidden">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-truncate" style="font-size:0.9rem; max-width: 160px;"><?= htmlspecialchars($msg['sender_name'] ?? 'Member') ?></strong>
                                    <span class="small text-muted" style="font-size: 0.7rem;"><?= date('H:i', strtotime($msg['sent_at'])) ?></span>
                                </div>
                                <div class="text-truncate small text-secondary"><?= htmlspecialchars($msg['body']) ?></div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; else: ?>
                    <div class="py-5 text-center text-muted">
                        <i class="bi bi-chat-square-dots display-6 opacity-25"></i>
                        <p class="small mt-2 mb-0">No recent messages</p>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notifications Dropdown -->
        <div class="dropdown ms-2">
            <button class="btn-icon-nav" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell"></i>
                <?php if($unread_notif_count > 0): ?><span class="badge-dot"></span><?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end custom-dropdown animate__animated animate__fadeInUp" style="width: 300px;">
                <div class="dropdown-header-custom d-flex justify-content-between align-items-center">
                    <span>Notifications</span>
                </div>
                <div style="max-height: 350px; overflow-y: auto;">
                <?php if(!empty($recent_notifs)): foreach($recent_notifs as $n): ?>
                    <div class="list-item-custom <?= $n['status'] === 'unread' ? 'unread' : '' ?>">
                        <div class="d-flex gap-3">
                            <div class="notif-icon-box shrink-0">
                                <i class="bi bi-bell"></i>
                            </div>
                            <div class="grow">
                                <p class="mb-1 small fw-medium lh-sm"><?= htmlspecialchars($n['message']) ?></p>
                                <div class="small opacity-50"><?= date('d M, H:i', strtotime($n['created_at'])) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="py-4 text-center text-muted">
                        <small>No recent notifications</small>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="d-none d-md-block" style="width: 1px; height: 30px; background: var(--nav-border);"></div>

        <div class="dropdown">
            <div class="user-info-pill d-flex align-items-center gap-3" data-bs-toggle="dropdown">
                <div class="d-none d-md-block text-end lh-1">
                    <div class="fw-bold" style="font-size: 0.85rem; color: var(--nav-text-dark);"><?= htmlspecialchars($user_name) ?></div>
                    <span class="role-badge"><?= strtoupper($user_role) ?></span>
                </div>
                <div class="msg-avatar bg-success text-white d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px;">
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                </div>
            </div>

            <ul class="dropdown-menu dropdown-menu-end custom-dropdown" style="min-width: 200px;">
                <li class="px-3 py-2 border-bottom border-light mb-2">
                    <span class="small text-muted fw-bold">ADMIN ACCOUNT</span>
                </li>
                <li><a class="dropdown-item list-item-custom small" href="<?= $profile_link ?>"><i class="bi bi-person me-2"></i>Profile Settings</a></li>
                <li><a class="dropdown-item list-item-custom small" href="<?= $base ?>/admin/pages/settings.php"><i class="bi bi-gear me-2"></i>Global Config</a></li>
                <li><hr class="dropdown-divider opacity-10"></li>
                <li><a class="dropdown-item list-item-custom small text-danger" href="<?= $base ?>/public/logout.php"><i class="bi bi-power me-2"></i>Sign Out</a></li>
            </ul>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const html = document.documentElement;
    const themeIcon = document.getElementById('themeIcon');
    const themeToggle = document.getElementById('themeToggle');

    const updateIcon = (theme) => {
        if(theme === 'dark'){
            if(themeIcon) themeIcon.classList.replace('bi-moon-stars', 'bi-sun');
        } else {
            if(themeIcon) themeIcon.classList.replace('bi-sun', 'bi-moon-stars');
        }
    };

    if(themeToggle) {
        themeToggle.addEventListener('click', () => {
            const current = html.getAttribute('data-bs-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', next);
            localStorage.setItem('theme', next);
            updateIcon(next);
        });
    }
});
</script>
