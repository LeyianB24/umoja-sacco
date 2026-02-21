<?php
// admin/layouts/topbar.php
// Canonical Admin Topbar - Matches layout.css structure

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../inc/Auth.php';

$user_name = $member_name ?? $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$base = defined('BASE_URL') ? BASE_URL : '/usms';

// Notification & Message Counts (Optional optimization: move to LayoutManager or a Helper)
global $conn;
$unread_msgs  = 0;
$unread_notifs = 0;

if (isset($conn) && isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    $res = $conn->query("SELECT COUNT(*) as cnt FROM messages WHERE to_admin_id = $admin_id AND is_read = 0");
    if ($row = $res->fetch_assoc()) $unread_msgs = $row['cnt'];

    if($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0) {
        $res = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_type = 'admin' AND user_id = $admin_id AND status = 'unread'");
        if ($row = $res->fetch_assoc()) $unread_notifs = $row['cnt'];
    }
}
?>

<header class="admin-topbar">
    <button class="sidebar-toggle-btn d-lg-none me-3" id="mobileToggle">
        <i class="bi bi-list"></i>
    </button>

    <div class="topbar-page-title">
        <?= $pageTitle ?? 'Admin Console' ?>
    </div>

    <div class="topbar-actions">
        <!-- Messages Dropdown -->
        <div class="dropdown">
            <button class="sidebar-toggle-btn position-relative" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-chat-dots"></i>
                <?php if($unread_msgs > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                        <?= $unread_msgs ?>
                    </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 mt-3" style="width: 280px;">
                <li class="px-3 py-2 border-bottom">
                    <h6 class="mb-0 fw-bold">Recent Messages</h6>
                </li>
                <li class="text-center py-4 text-muted">
                    <small>Check communications hub</small>
                </li>
            </ul>
        </div>

        <!-- Notifications Dropdown -->
        <div class="dropdown">
            <button class="sidebar-toggle-btn position-relative" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell"></i>
                <?php if($unread_notifs > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark" style="font-size: 0.6rem;">
                        <?= $unread_notifs ?>
                    </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 mt-3" style="width: 300px;">
                <li class="px-3 py-2 border-bottom">
                    <h6 class="mb-0 fw-bold">Alerts & Notifications</h6>
                </li>
                <li class="text-center py-4 text-muted">
                    <small>Stay updated on system events</small>
                </li>
            </ul>
        </div>

        <div class="vr mx-2 opacity-10" style="height: 24px;"></div>

        <!-- Theme Toggle -->
        <button class="sidebar-toggle-btn" id="themeToggleBtn">
            <i class="bi bi-moon-stars" id="themeIcon"></i>
        </button>

        <!-- User Profile -->
        <div class="dropdown ms-2">
            <div class="d-flex align-items-center gap-2 cursor-pointer" data-bs-toggle="dropdown" style="cursor: pointer;">
                <div class="d-none d-md-block text-end lh-1">
                    <div class="fw-bold small"><?= htmlspecialchars(explode(' ', $user_name)[0]) ?></div>
                    <small class="text-muted" style="font-size: 0.65rem;"><?= strtoupper($user_role) ?></small>
                </div>
                <div class="avatar-initial">
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                </div>
            </div>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 mt-3" style="min-width: 180px;">
                <li><a class="dropdown-item py-2 px-3 small rounded-3 mx-2 w-auto mb-1" href="<?= $base ?>/public/admin_settings.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                <li><a class="dropdown-item py-2 px-3 small rounded-3 mx-2 w-auto mb-1" href="<?= $base ?>/admin/pages/settings.php"><i class="bi bi-gear me-2"></i>Global Config</a></li>
                <li><hr class="dropdown-divider opacity-5"></li>
                <li><a class="dropdown-item py-2 px-3 small rounded-3 mx-2 w-auto text-danger" href="<?= $base ?>/public/logout.php"><i class="bi bi-power me-2"></i>Sign Out</a></li>
            </ul>
        </div>
    </div>
</header>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const themeBtn = document.getElementById('themeToggleBtn');
    const themeIcon = document.getElementById('themeIcon');
    const html = document.documentElement;

    const updateIcon = (theme) => {
        if (theme === 'dark') {
            themeIcon.classList.replace('bi-moon-stars', 'bi-sun');
        } else {
            themeIcon.classList.replace('bi-sun', 'bi-moon-stars');
        }
    };

    updateIcon(html.getAttribute('data-bs-theme'));

    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            const current = html.getAttribute('data-bs-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', next);
            localStorage.setItem('theme', next);
            updateIcon(next);
        });
    }
});
</script>
