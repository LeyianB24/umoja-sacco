<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

// Auth Check
require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');

$admin_id = (int) $_SESSION['admin_id'];
$user_role = $_SESSION['role'] ?? 'admin';

// Fetch Notifications
$query = "SELECT notification_id, title, message, is_read, created_at 
          FROM notifications 
          WHERE user_type = 'admin' 
          AND (user_id = ? OR to_role = ? OR to_role = 'all') 
          ORDER BY created_at DESC LIMIT 50";

$stmt = $conn->prepare($query);
$stmt->bind_param("is", $admin_id, $user_role);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close(); 

// Helper for Icon/Color logic based on the Dashboard Theme
function getNotificationStyle($title, $msg) {
    $t = strtolower($title . ' ' . $msg);
    
    // Finance/Money
    if (strpos($t, 'loan') !== false || strpos($t, 'credit') !== false || strpos($t, 'pay') !== false || strpos($t, 'disburs') !== false) {
        return ['icon' => 'bi-wallet2', 'class' => 'theme-finance'];
    }
    // Success/Approval
    if (strpos($t, 'approv') !== false || strpos($t, 'success') !== false || strpos($t, 'verify') !== false) {
        return ['icon' => 'bi-check-lg', 'class' => 'theme-success'];
    }
    // Alerts/Rejection
    if (strpos($t, 'reject') !== false || strpos($t, 'fail') !== false || strpos($t, 'error') !== false) {
        return ['icon' => 'bi-exclamation-lg', 'class' => 'theme-alert'];
    }
    // System/General
    return ['icon' => 'bi-bell', 'class' => 'theme-general'];
}

$pageTitle = "My Notifications";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --brand-dark:    #064420;
            --brand-emerald: #10B981;
            --brand-lime:    #D1FAE5;
            --brand-text:    #111827;
            --brand-muted:   #6B7280;
            --bg-app:        #f4f6f8;
            --card-bg:       #ffffff;
            --border-color:  #e5e7eb;
            --ease-expo:     cubic-bezier(0.16, 1, 0.3, 1);
            --ease-spring:   cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        [data-bs-theme="dark"] {
            --bg-app:        #0d1117;
            --card-bg:       #161b22;
            --brand-dark:    #34D399;
            --brand-text:    #f0f6fc;
            --brand-muted:   #8b949e;
            --border-color:  #21262d;
            --brand-lime:    rgba(16, 185, 129, 0.08);
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg-app);
            color: var(--brand-text);
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow-x: hidden;
        }

        /* ── Page Shell ── */
        .main-content-wrapper {
            margin-left: 280px;
            transition: margin 0.3s ease;
            min-height: 100vh;
            padding: 2.5rem;
        }
        @media (max-width: 991px) {
            .main-content-wrapper { margin-left: 0; padding: 1.5rem; }
        }

        /* ── Page Header ── */
        .notif-page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 32px;
            animation: fadeUp 0.55s var(--ease-expo) both;
        }

        .notif-page-header .header-eyebrow {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--brand-emerald);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .notif-page-header h3 {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--brand-text);
            margin: 0 0 6px;
            letter-spacing: -0.4px;
            line-height: 1.15;
        }

        .notif-page-header p {
            font-size: 0.9rem;
            color: var(--brand-muted);
            margin: 0;
            font-weight: 400;
        }

        .unread-counter {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--brand-dark);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 50px;
            letter-spacing: 0.3px;
        }

        [data-bs-theme="dark"] .unread-counter {
            background: rgba(52, 211, 153, 0.15);
            color: #34D399;
            border: 1px solid rgba(52, 211, 153, 0.2);
        }

        /* ── Notification List ── */
        .notif-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* ── Notification Card ── */
        .notif-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px 22px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            position: relative;
            overflow: hidden;
            transition: transform 0.22s var(--ease-expo), box-shadow 0.22s var(--ease-expo), border-color 0.22s ease;
            animation: fadeUp 0.5s var(--ease-expo) both;
            cursor: default;
        }

        .notif-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(0,0,0,0.07);
            border-color: rgba(16,185,129,0.2);
        }

        /* Unread accent bar */
        .notif-card.unread {
            background: linear-gradient(to right, rgba(16,185,129,0.04), var(--card-bg) 60%);
            border-color: rgba(16,185,129,0.18);
        }

        [data-bs-theme="dark"] .notif-card.unread {
            background: linear-gradient(to right, rgba(16,185,129,0.07), var(--card-bg) 60%);
        }

        .notif-card.unread::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 3px;
            background: var(--brand-emerald);
            border-radius: 0 2px 2px 0;
        }

        /* Stagger delays */
        .notif-card:nth-child(1)  { animation-delay: 0.04s; }
        .notif-card:nth-child(2)  { animation-delay: 0.08s; }
        .notif-card:nth-child(3)  { animation-delay: 0.12s; }
        .notif-card:nth-child(4)  { animation-delay: 0.16s; }
        .notif-card:nth-child(5)  { animation-delay: 0.20s; }
        .notif-card:nth-child(6)  { animation-delay: 0.24s; }
        .notif-card:nth-child(7)  { animation-delay: 0.28s; }
        .notif-card:nth-child(8)  { animation-delay: 0.32s; }
        .notif-card:nth-child(n+9){ animation-delay: 0.35s; }

        /* ── Icon Box ── */
        .icon-box {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .theme-finance .icon-box  { background: var(--brand-dark); color: #a7f3d0; }
        .theme-success .icon-box  { background: rgba(16,185,129,0.12); color: var(--brand-emerald); }
        .theme-alert   .icon-box  { background: #fef2f2; color: #dc2626; }
        .theme-general .icon-box  { background: #f3f4f6; color: var(--brand-muted); }

        [data-bs-theme="dark"] .theme-finance .icon-box { background: rgba(52,211,153,0.12); color: #34D399; }
        [data-bs-theme="dark"] .theme-alert   .icon-box { background: rgba(239,68,68,0.1); color: #f87171; }
        [data-bs-theme="dark"] .theme-general .icon-box { background: rgba(255,255,255,0.06); color: var(--brand-muted); }

        /* ── Card Body ── */
        .notif-body { flex: 1; min-width: 0; }

        .notif-top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 5px;
        }

        .notif-title {
            font-size: 0.93rem;
            font-weight: 700;
            color: var(--brand-text);
            margin: 0;
            line-height: 1.3;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .new-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--brand-emerald);
            flex-shrink: 0;
            box-shadow: 0 0 0 2px rgba(16,185,129,0.2);
            animation: pulseGreen 2s infinite;
        }

        .notif-time {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--brand-muted);
            white-space: nowrap;
            letter-spacing: 0.2px;
        }

        .notif-msg {
            font-size: 0.865rem;
            color: var(--brand-muted);
            line-height: 1.55;
            margin: 0;
            font-weight: 400;
        }

        /* ── End Divider ── */
        .notif-end-label {
            text-align: center;
            margin-top: 32px;
            margin-bottom: 8px;
        }

        .notif-end-label span {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--brand-muted);
            opacity: 0.45;
        }

        .notif-end-label span::before,
        .notif-end-label span::after {
            content: '';
            display: block;
            width: 48px;
            height: 1px;
            background: var(--border-color);
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 80px 24px;
            animation: fadeUp 0.6s var(--ease-expo) both;
        }

        .empty-icon-wrap {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: rgba(16,185,129,0.07);
            border: 1px solid rgba(16,185,129,0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: var(--brand-emerald);
            font-size: 2.2rem;
        }

        .empty-state h4 {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--brand-text);
            margin-bottom: 8px;
            letter-spacing: -0.2px;
        }

        .empty-state p {
            font-size: 0.9rem;
            color: var(--brand-muted);
            margin: 0;
        }

        /* ── Animations ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulseGreen {
            0%   { box-shadow: 0 0 0 0   rgba(16,185,129,0.4); }
            70%  { box-shadow: 0 0 0 6px rgba(16,185,129,0); }
            100% { box-shadow: 0 0 0 0   rgba(16,185,129,0); }
        }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>

<?php $layout->sidebar(); ?>

<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>

    <div class="container-fluid px-2 py-2">

        <?php
            // Pre-count unread
            $unread_count = 0;
            $all_rows = [];
            while ($row = $result->fetch_assoc()) {
                if ((int)$row['is_read'] === 0) $unread_count++;
                $all_rows[] = $row;
            }
        ?>

        <!-- ─── PAGE HEADER ─────────────────────────────── -->
        <div class="notif-page-header">
            <div>
                <div class="header-eyebrow">
                    <i class="bi bi-bell-fill"></i> Notifications
                </div>
                <h3>Admin Notifications</h3>
                <p>Stay up-to-date with system alerts and member actions.</p>
            </div>
            <?php if ($unread_count > 0): ?>
            <div class="pt-1">
                <span class="unread-counter">
                    <i class="bi bi-dot" style="font-size:1.1rem;margin:-2px;"></i>
                    <?= $unread_count ?> Unread
                </span>
            </div>
            <?php endif; ?>
        </div>

        <!-- ─── NOTIFICATION LIST ────────────────────────── -->
        <div class="row justify-content-center">
            <div class="col-xl-9 col-lg-10">

                <?php if (!empty($all_rows)): ?>
                <div class="notif-list">
                    <?php foreach ($all_rows as $row):
                        $title       = htmlspecialchars($row['title'] ?: 'Notification');
                        $message     = htmlspecialchars($row['message'] ?: '');
                        $created_raw = $row['created_at'] ?? null;
                        $time_diff   = time() - strtotime($created_raw);

                        if ($time_diff < 3600) {
                            $time = floor($time_diff / 60) . ' mins ago';
                        } elseif ($time_diff < 86400) {
                            $time = floor($time_diff / 3600) . ' hrs ago';
                        } else {
                            $time = date('M d, Y', strtotime($created_raw));
                        }

                        $is_new = ((int)$row['is_read'] === 0);
                        $style  = getNotificationStyle($title, $message);
                    ?>

                    <div class="notif-card <?= $is_new ? 'unread' : '' ?> <?= $style['class'] ?>">
                        <div class="icon-box">
                            <i class="bi <?= $style['icon'] ?>"></i>
                        </div>

                        <div class="notif-body">
                            <div class="notif-top-row">
                                <h6 class="notif-title">
                                    <?= $title ?>
                                    <?php if ($is_new): ?>
                                        <span class="new-dot" title="Unread"></span>
                                    <?php endif; ?>
                                </h6>
                                <span class="notif-time">
                                    <i class="bi bi-clock me-1 opacity-50"></i><?= $time ?>
                                </span>
                            </div>
                            <p class="notif-msg"><?= nl2br($message) ?></p>
                        </div>
                    </div>

                    <?php endforeach; ?>
                </div>

                <div class="notif-end-label">
                    <span>End of Notifications</span>
                </div>

                <?php else: ?>

                <div class="empty-state">
                    <div class="empty-icon-wrap">
                        <i class="bi bi-bell-slash"></i>
                    </div>
                    <h4>You're all caught up</h4>
                    <p>No notifications right now. System alerts will appear here.</p>
                </div>

                <?php endif; ?>

            </div>
        </div>

    </div><!-- /container-fluid -->

    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', savedTheme);
</script>

<?php 
// Mark Read Logic
if (!empty($all_rows)) {
    if ($stmt2 = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_type = 'admin' AND (user_id = ? OR to_role = ? OR to_role = 'all') AND is_read = 0")) {
        $stmt2->bind_param("is", $admin_id, $user_role);
        $stmt2->execute();
    }
}
?>

</body>
</html>