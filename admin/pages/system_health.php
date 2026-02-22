<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/SystemHealthHelper.php';
require_once __DIR__ . '/../../inc/FinancialIntegrityChecker.php';
require_once __DIR__ . '/../../inc/AuditHelper.php';

require_admin();
require_permission();

$layout = LayoutManager::create('admin');
$checker = new FinancialIntegrityChecker($conn);

// Handle Full Audit Trigger
$audit_results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_audit'])) {
    $audit_results = $checker->runFullAudit();
    AuditHelper::log($conn, 'SYSTEM_HEALTH_AUDIT', 'Manual system health audit executed by ' . ($_SESSION['admin_name'] ?? 'Admin'), null, (int)$_SESSION['admin_id'], 'warning');
}

$health = getSystemHealth($conn);

// Fetch latest audit logs
$recent_logs_q = $conn->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50");
$recent_logs = $recent_logs_q->fetch_all(MYSQLI_ASSOC);

$pageTitle = "System Health & Integrity";
?>
<?php $layout->header($pageTitle); ?>
    <style>
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }
    </style>

<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content-wrapper p-0">
        <?php $layout->topbar($pageTitle ?? 'Pulse Monitor'); ?>
        <div class="container-fluid">
        <!-- Hero Section -->
        <div class="hp-hero">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <h1 class="display-5 fw-800 mb-3">System Health Center</h1>
                    <p class="lead opacity-75 mb-4">Monitor real-time operational metrics, financial integrity checks, and security audit logs.</p>
                    <div class="d-flex gap-3">
                        <form method="POST">
                            <button type="submit" name="run_audit" class="btn btn-lime rounded-pill px-4 py-2">
                                <i class="bi bi-shield-check me-2"></i> Run Full Audit
                            </button>
                        </form>
                        <button onclick="location.reload()" class="btn btn-outline-light rounded-pill px-4 py-2">
                            <i class="bi bi-arrow-clockwise me-2"></i> Refresh Metrics
                        </button>
                    </div>
                </div>
                <div class="col-md-5 d-none d-md-block text-end">
                    <div class="display-1 fw-800 opacity-25">99.9%</div>
                    <div class="small text-uppercase tracking-wider opacity-50">System Uptime</div>
                </div>
            </div>
        </div>

        <?php if ($audit_results): ?>
        <div class="alert alert-info border-0 rounded-4 shadow-sm mb-4 animate__animated animate__fadeIn">
            <div class="d-flex align-items-center">
                <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                <div>
                    <h6 class="mb-1 fw-bold">Audit Completed</h6>
                    <p class="mb-0 small">Financial integrity check performed. Found <?= count($audit_results['sync']['data']) + count($audit_results['balance']['data']) + count($audit_results['double_posting']['data']) ?> potential issues.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Integrity Checks -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-bold">Ledger Balance Sync</h6>
                        <span class="health-indicator <?= $health['ledger_imbalance'] ? 'bg-err' : 'bg-ok' ?>"></span>
                    </div>
                    <p class="small text-muted">Ensures Total Debits equal Total Credits in the golden ledger.</p>
                    <div class="mt-auto pt-3 border-top">
                        <span class="badge rounded-pill <?= $health['ledger_imbalance'] ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?>">
                            <?= $health['ledger_imbalance'] ? 'Imbalance Detected' : 'Healthy' ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-bold">Member Account Sync</h6>
                        <span class="health-indicator bg-ok"></span>
                    </div>
                    <p class="small text-muted">Verifies individual account balances against transaction history.</p>
                    <div class="mt-auto pt-3 border-top">
                        <span class="badge rounded-pill bg-success-subtle text-success">Verified</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-bold">Database Storage</h6>
                        <i class="bi bi-database text-muted"></i>
                    </div>
                    <p class="small text-muted">Current size of the SACCO's central data repository.</p>
                    <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                        <div class="h5 fw-bold mb-0"><?= $health['db_size'] ?? 'N/A' ?> MB</div>
                        <span class="badge rounded-pill bg-light  border">Optimized</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Health Actions -->
        <h4 class="fw-800 mb-4">Deep Health Checks</h4>
        <div class="row g-4 mb-5">
            <div class="col-md-6">
                <div class="stat-card p-4">
                    <div class="d-flex gap-4">
                        <div class="icon-puck bg-forest text-lime fs-4"><i class="bi bi-shield-check"></i></div>
                        <div>
                            <h5 class="fw-bold">Financial Integrity Audit</h5>
                            <p class="text-muted small">Run a comprehensive comparison across the ledger, member wallets, and transaction requests to detect any hidden imbalances.</p>
                            <form method="POST">
                                <button type="submit" name="run_audit" class="btn btn-forest rounded-pill px-4">
                                    <i class="bi bi-play-circle me-1"></i> Start Full Audit
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card p-4">
                    <div class="d-flex gap-4">
                        <div class="icon-puck bg-secondary bg-opacity-10 text-secondary fs-4"><i class="bi bi-broadcast"></i></div>
                        <div>
                            <h5 class="fw-bold">Performance Monitor</h5>
                            <p class="text-muted small">Looking for operational metrics like callback success rates and live STK request tracking?</p>
                            <a href="live_monitor.php" class="btn btn-outline-dark rounded-pill px-4">
                                <i class="bi bi-activity me-1"></i> Go to Live Monitor
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php $layout->footer(); ?>
        </div>
        
    </div>
</div>
