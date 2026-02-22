<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
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
    
    <style>
        :root {
            /* --- DASHBOARD BRAND COLORS --- */
            --brand-dark: #064420;       /* Deep Forest Green */
            --brand-emerald: #10B981;    /* Bright Emerald */
            --brand-lime: #D1FAE5;       /* Light Mint/Lime */
            --brand-text: #1F2937;       /* Dark Grey Text */
            --brand-muted: #6B7280;      /* Muted Text */
            
            /* UI Vars */
            --bg-app: #f8f9fa;
            --card-bg: #ffffff;
            --border-color: #e5e7eb;
        }

        [data-bs-theme="dark"] {
            --bg-app: #111827;
            --card-bg: #1f2937;
            --brand-dark: #34D399;
            --brand-text: #F3F4F6;
            --brand-muted: #9CA3AF;
            --border-color: #374151;
            --brand-lime: rgba(16, 185, 129, 0.1);
        }

        body {
            background-color: var(--bg-app);
            color: var(--brand-text);
            font-family: 'Segoe UI', Inter, sans-serif;
            overflow-x: hidden;
        }

        /* Container Styling */
        .page-header h3 { color: var(--brand-dark); font-weight: 800; }

        /* Notification Card */
        .notif-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            transition: all 0.2s ease-in-out;
            position: relative;
            overflow: hidden;
        }

        .notif-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }

        /* Unread State */
        .notif-card.unread {
            background-color: var(--brand-lime);
            border-color: transparent;
        }
        .notif-card.unread::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
            background-color: var(--brand-emerald);
        }

        /* Icons Styles */
        .icon-box {
            width: 42px; height: 42px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        /* Theme Mapping */
        .theme-finance .icon-box { background-color: var(--brand-dark); color: #fff; }
        .theme-success .icon-box { background-color: var(--brand-emerald); color: #fff; }
        .theme-alert .icon-box { background-color: #fee2e2; color: #ef4444; }
        .theme-general .icon-box { background-color: #f3f4f6; color: var(--brand-muted); }

        /* Typography */
        .notif-title { font-weight: 700; color: var(--brand-text); }
        .notif-time { font-size: 0.75rem; color: var(--brand-muted); }
        .notif-msg { font-size: 0.9rem; color: var(--brand-muted); line-height: 1.5; }

        /* Button Override */
        .btn-brand-outline {
            border: 2px solid var(--border-color);
            color: var(--brand-muted);
            font-weight: 600;
            border-radius: 8px;
        }
        .btn-brand-outline:hover {
            border-color: var(--brand-dark);
            color: var(--brand-dark);
            background: transparent;
        }

        /* Layout */
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>

 <div class="d-flex">
        <?php $layout->sidebar(); ?>

        <div class="flex-fill main-content-wrapper p-0 border-start">
            
            <?php $layout->topbar($pageTitle ?? ''); ?>
            
            <div class="container-fluid p-4 p-md-5">
               
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-5 page-header">
                <div>
                    <h3 class="mb-1 display-6 fw-bold">Admin Notifications</h3>
                    <p class="small mb-0 text-muted fs-6">Stay up-to-date with system alerts and member actions.</p>
                </div>
                <!-- Controls if needed -->
            </div>

            <div class="row justify-content-center">
                <div class="col-xl-9 col-lg-10">
                    
                    <?php if ($result->num_rows > 0): ?>
                        <div class="d-flex flex-column gap-3">
                            <?php while ($row = $result->fetch_assoc()): 
                                $title = htmlspecialchars($row['title'] ?: 'Notification');
                                $message = htmlspecialchars($row['message'] ?: '');
                                $created_raw = $row['created_at'] ?? null;
                                
                                $time_diff = time() - strtotime($created_raw);
                                if ($time_diff < 3600) {
                                    $time = floor($time_diff / 60) . ' mins ago';
                                } elseif ($time_diff < 86400) {
                                    $time = floor($time_diff / 3600) . ' hours ago';
                                } else {
                                    $time = date('M d, Y', strtotime($created_raw));
                                }
                                
                                $is_new = ((int)$row['is_read'] === 0);
                                $style = getNotificationStyle($title, $message);
                            ?>
                            
                            <div class="notif-card p-4 d-flex align-items-start gap-4 <?= $is_new ? 'unread' : '' ?> <?= $style['class'] ?>">
                                <div class="icon-box shadow-sm">
                                    <i class="bi <?= $style['icon'] ?>"></i>
                                </div>
                                
                                <div class="grow pt-1 w-100">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="notif-title mb-0 fs-5">
                                            <?= $title ?>
                                            <?php if($is_new): ?>
                                                <span class="d-inline-block bg-success rounded-circle ms-2 shadow-sm" style="width:10px; height:10px; vertical-align:middle;"></span>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="notif-time fw-bold text-uppercase mt-1"><?= $time ?></small>
                                    </div>
                                    <p class="notif-msg mb-0 fs-6 mt-2 opacity-75">
                                        <?= nl2br($message) ?>
                                    </p>
                                </div>
                            </div>

                            <?php endwhile; ?>
                        </div>
                        
                        <div class="text-center mt-5 text-muted small opacity-50 fw-bold text-uppercase">
                            &mdash; End of Notifications &mdash;
                        </div>

                    <?php else: ?>
                        <div class="text-center py-5 my-5">
                            <div class="mb-4 mx-auto d-flex align-items-center justify-content-center rounded-circle" style="width:100px; height:100px; background: rgba(16, 185, 129, 0.1); color: var(--brand-dark);">
                                <i class="bi bi-bell-slash" style="font-size: 2.5rem;"></i>
                            </div>
                            <h4 class="fw-bold mt-3" style="color: var(--brand-dark);">No new notifications</h4>
                            <p class="text-muted fs-5">You're all caught up! System alerts will appear here.</p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

            </div>
            <?php $layout->footer(); ?>
        </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', savedTheme);
</script>
</body>
</html>

<?php 
// Mark Read Logic
if ($result->num_rows > 0) {
    if($stmt2 = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_type = 'admin' AND (user_id = ? OR to_role = ? OR to_role = 'all') AND is_read = 0")){
        $stmt2->bind_param("is", $admin_id, $user_role);
        $stmt2->execute();
        $stmt2->close();
    }
}
?>
