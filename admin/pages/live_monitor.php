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
<?php $layout->header($pageTitle); ?>
<body>

    <?php $layout->sidebar(); ?>
    <div class="main-wrapper">
        <?php $layout->topbar($pageTitle); ?>
        <main class="main-content">

    <div class="container-fluid">
        <!-- Hero Section -->
        <div class="monitor-hero">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center mb-3">
                        <span class="live-dot"></span>
                        <span class="text-lime fw-bold small text-uppercase tracking-wider">Live System Feed</span>
                    </div>
                    <h1 class="display-5 fw-800 mb-3 text-white">Operations Monitor</h1>
                    <p class="lead opacity-75 mb-0 text-white">Tracking real-time payment callbacks, notification delivery, and system activity logs.</p>
                </div>
                <div class="col-md-4 text-end d-none d-md-block">
                    <div class="d-flex flex-column gap-2">
                        <button onclick="location.reload()" class="btn btn-lime rounded-pill px-4 py-2 fw-bold">
                            <i class="bi bi-arrow-clockwise me-2"></i> REFRESH FEED
                        </button>
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
                                <div class="fw-bold"><?= htmlspecialchars((string)($log['action'] ?? '')) ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;">TYPE: <?= htmlspecialchars((string)($log['user_type'] ?? '')) ?></div>
                            </td>
                            <td>
                                <span class="severity-badge severity-<?= strtolower((string)($log['severity'] ?? 'info')) ?>">
                                    <?= htmlspecialchars((string)($log['severity'] ?? 'INFO')) ?>
                                </span>
                            </td>
                            <td class="small"><?= htmlspecialchars((string)($log['details'] ?? '')) ?></td>
                            <td class="pe-4 text-end font-monospace small text-muted">
                                <?= htmlspecialchars((string)($log['ip_address'] ?? '')) ?>
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

        </main>
        <?php $layout->footer(); ?>
    </div>
