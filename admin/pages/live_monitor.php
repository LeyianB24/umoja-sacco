<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/SystemHealthHelper.php';
require_once __DIR__ . '/../../inc/AuditHelper.php';

require_admin();
require_permission();

$layout = LayoutManager::create('admin');
$health = getSystemHealth($conn);

// Fetch latest audit logs (Live Feed)
$recent_logs_q = $conn->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50");
$recent_logs = $recent_logs_q->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Live Operations Monitor";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> | USMS Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ASSET_BASE ?>/css/style.css">
    <style>
        :root { --forest: #0F2E25; --lime: #D0F35D; --glass: rgba(255, 255, 255, 0.9); }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
        .main-content { margin-left: 280px; padding: 40px; min-height: 100vh; transition: 0.3s; }
        .monitor-hero { 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            border-radius: 30px; padding: 40px; color: white; margin-bottom: 40px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.1);
            position: relative; overflow: hidden;
        }
        .monitor-hero::after {
            content: ''; position: absolute; top: -50%; right: -10%; width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(208, 243, 93, 0.05) 0%, transparent 70%);
            border-radius: 50%;
        }
        .stat-card {
            background: white; border-radius: 24px; padding: 24px; border: 1px solid #edf2f7;
            transition: 0.3s; height: 100%; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .log-table-card {
            background: white; border-radius: 24px; border: 1px solid #edf2f7; overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .severity-badge { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 4px 10px; border-radius: 8px; }
        .severity-info { background: #f0f9ff; color: #0369a1; }
        .severity-warning { background: #fffbeb; color: #92400e; }
        .severity-critical { background: #fef2f2; color: #991b1b; }
        .live-dot {
            width: 10px; height: 10px; background: #10b981; border-radius: 50%;
            display: inline-block; margin-right: 8px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>

<?php $layout->sidebar(); ?>

<div class="main-content">
    <?php $layout->topbar($pageTitle); ?>

    <div class="container-fluid">
        <!-- Hero Section -->
        <div class="monitor-hero">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center mb-3">
                        <span class="live-dot"></span>
                        <span class="text-lime fw-bold small text-uppercase tracking-wider">Live System Feed</span>
                    </div>
                    <h1 class="display-5 fw-800 mb-3">Operations Monitor</h1>
                    <p class="lead opacity-75 mb-0">Tracking real-time payment callbacks, notification delivery, and system activity logs.</p>
                </div>
                <div class="col-md-4 text-end d-none d-md-block">
                    <div class="d-flex flex-column gap-2">
                        <button onclick="location.reload()" class="btn btn-lime rounded-pill px-4 py-2 fw-bold">
                            <i class="bi bi-arrow-clockwise me-2"></i> REFRESH FEED
                        </button>
                        <a href="monitor.php" class="btn btn-outline-light rounded-pill px-4 py-2 small fw-600">
                            <i class="bi bi-list-check me-2"></i> TRANSACTION LOGS
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Operational Metrics -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="small text-muted fw-bold text-uppercase mb-2">Callback Success</div>
                    <div class="h2 fw-800 mb-0"><?= $health['callback_success_rate'] ?>%</div>
                    <div class="progress mt-3" style="height: 6px; border-radius: 10px;">
                        <div class="progress-bar bg-success" style="width: <?= $health['callback_success_rate'] ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="small text-muted fw-bold text-uppercase mb-2">Pending STK</div>
                    <div class="h2 fw-800 mb-0"><?= $health['pending_transactions'] ?></div>
                    <div class="text-<?= $health['pending_transactions'] > 5 ? 'warning' : 'success' ?> small mt-2 fw-bold">
                        <i class="bi bi-clock me-1"></i> Stuck > 5 mins
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="small text-muted fw-bold text-uppercase mb-2">Failed Comms</div>
                    <div class="h2 fw-800 mb-0"><?= $health['failed_notifications'] ?></div>
                    <div class="text-<?= $health['failed_notifications'] > 0 ? 'danger' : 'success' ?> small mt-2 fw-bold">
                        <i class="bi bi-envelope-exclamation me-1"></i> Delivery errors today
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="small text-muted fw-bold text-uppercase mb-2">Daily Volume</div>
                    <div class="h2 fw-800 mb-0">KES <?= number_format($health['daily_volume'], 0) ?></div>
                    <div class="text-muted small mt-2 fw-bold">
                        <i class="bi bi-lightning-charge me-1"></i> Successful processed
                    </div>
                </div>
            </div>
        </div>

        <!-- Real-time Audit Feed -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-800 mb-0">Operation Audit Feed</h4>
            <div class="small text-muted fw-bold">Showing last 50 events</div>
        </div>
        
        <div class="log-table-card mb-5">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 border-0">Time</th>
                            <th class="border-0">Action</th>
                            <th class="border-0">Severity</th>
                            <th class="border-0">Details</th>
                            <th class="border-0 pe-4 text-end">Origin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td class="ps-4 small text-muted">
                                <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                <span class="d-block text-uppercase" style="font-size: 0.6rem;"><?= date('M d', strtotime($log['created_at'])) ?></span>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($log['action']) ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;">TYPE: <?= htmlspecialchars($log['user_type']) ?></div>
                            </td>
                            <td>
                                <span class="severity-badge severity-<?= strtolower($log['severity']) ?>">
                                    <?= htmlspecialchars($log['severity']) ?>
                                </span>
                            </td>
                            <td class="small"><?= htmlspecialchars($log['details']) ?></td>
                            <td class="pe-4 text-end font-monospace small text-muted">
                                <?= htmlspecialchars($log['ip_address']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent_logs)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No operational logs streaming yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php $layout->footer(); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
