<?php
// usms/superadmin/notifications.php
// Superadmin Notifications Center

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

require_superadmin();

$admin_id   = $_SESSION['admin_id'] ?? 0;
$admin_name = $_SESSION['admin_name'] ?? 'Superadmin';

$db = $conn ?? null;
if (!$db) die("DB connection unavailable");


// Fetch notifications for superadmin using correct table structure
// Notification uses: to_role, user_type, user_id
$sql = "
    SELECT 
        n.*,
        CASE 
            WHEN n.user_type = 'admin' THEN a.full_name
            WHEN n.user_type = 'member' THEN m.full_name
            ELSE 'System'
        END AS sender_name
    FROM notifications n
    LEFT JOIN admins a ON (n.user_type='admin' AND n.user_id = a.admin_id)
    LEFT JOIN members m ON (n.user_type='member' AND n.user_id = m.member_id)
    WHERE n.to_role = 'superadmin'
    ORDER BY n.created_at DESC
";

$res = $db->query($sql);
$notifications = [];
while ($row = $res->fetch_assoc()) $notifications[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notifications — Superadmin | <?= SITE_NAME ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSET_BASE ?>/css/style.css?v=1.3">

<style>
:root {
  --sacco-green: <?= $theme['primary'] ?? '#16a34a' ?>;
  --sacco-dark: <?= $theme['primary_dark'] ?? '#0b6623' ?>;
  --sacco-gold: <?= $theme['accent'] ?? '#f4c430' ?>;
}
body { background:#f6f8f9; }
.sidebar {
    width:260px;background:linear-gradient(180deg,var(--sacco-dark),#0a3d1d);
    color:#fff;min-height:100vh;position:fixed;
}
.sidebar nav a { color:#fff;padding:12px 18px;display:block;text-decoration:none; }
.sidebar nav a.active, .sidebar nav a:hover { background:rgba(255,255,255,0.08); }
.main { margin-left:260px;padding:28px; }
.card { border-radius:12px; }
.unread { background:#fff9d8; }
</style>

</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand p-3 d-flex align-items-center gap-2">
        <img src="<?= ASSET_BASE ?>/images/people_logo.png" width="55"
             style="border-radius:50%;border:2px solid var(--sacco-gold)">
        <div>
            <div class="fw-bold"><?= SITE_NAME ?></div>
            <small><?= TAGLINE ?></small>
        </div>
    </div>

    <nav>
        <a href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
        <a href="manage_admins.php"><i class="bi bi-person-gear me-2"></i> Admins</a>
        <a href="manage_members.php"><i class="bi bi-people me-2"></i> Members</a>
        <a href="manage_loans.php"><i class="bi bi-cash-coin me-2"></i> Loans</a>
        <a href="notifications.php" class="active"><i class="bi bi-bell me-2"></i> Notifications</a>
        <a href="support.php"><i class="bi bi-life-preserver me-2"></i> Support</a>
        <a href="audit_logs.php"><i class="bi bi-activity me-2"></i> Logs</a>
        <a href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a>

        <div class="p-3">
            <a href="<?= BASE_URL ?>/public/logout.php" class="btn btn-warning w-100">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </nav>
</aside>

<!-- MAIN -->
<main class="main">

<h4 class="mb-3">Notifications</h4>
<p class="text-muted">System messages, support alerts, loan updates, and user actions.</p>

<div class="card">
    <div class="card-header bg-white">
        <h6 class="mb-0">Recent Notifications</h6>
    </div>

    <ul class="list-group list-group-flush">

        <?php if (!$notifications): ?>
            <li class="list-group-item text-center text-muted py-3">
                No notifications found.
            </li>

        <?php else: foreach ($notifications as $nt): ?>
            <li class="list-group-item <?= $nt['status'] === 'unread' ? 'unread' : '' ?>">
                <div class="d-flex justify-content-between">

                    <div>
                        <div class="fw-bold"><?= htmlspecialchars($nt['title']) ?></div>
                        <div><?= htmlspecialchars($nt['message']) ?></div>

                        <small class="text-muted">
                            From: <?= htmlspecialchars($nt['sender_name']) ?>
                            • <?= date('d M Y H:i', strtotime($nt['created_at'])) ?>
                        </small>
                    </div>

                    <div class="text-end ms-2">

                        <?php if ($nt['status'] === 'unread'): ?>
                            <a href="notifications_update.php?action=mark_read&id=<?= $nt['notification_id'] ?>"
                               class="btn btn-sm btn-success mb-1">
                                <i class="bi bi-check2"></i> Mark Read
                            </a>
                        <?php endif; ?>

                        <a href="notifications_update.php?action=delete&id=<?= $nt['notification_id'] ?>"
                           onclick="return confirm('Delete this notification?')"
                           class="btn btn-sm btn-danger">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>

                </div>
            </li>
        <?php endforeach; endif; ?>

    </ul>
</div>

</main>
</body>
</html>