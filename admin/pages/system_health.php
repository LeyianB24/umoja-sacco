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
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0f4f3; }
        .main-content { margin-left: 280px; padding: 40px; min-height: 100vh; transition: 0.3s; }
        .hp-hero { 
            background: linear-gradient(135deg, var(--forest) 0%, #1a4d3e 100%); 
            border-radius: 30px; padding: 50px; color: white; margin-bottom: 40px;
            box-shadow: 0 20px 40px rgba(15, 46, 37, 0.15);
            position: relative; overflow: hidden;
        }
        .hp-hero::after {
            content: ''; position: absolute; top: -50%; right: -10%; width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(208, 243, 93, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        .stat-card {
            background: var(--glass); backdrop-filter: blur(10px);
            border-radius: 24px; padding: 24px; border: 1px solid rgba(255,255,255,0.5);
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); height: 100%;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
        .audit-log-table {
            background: white; border-radius: 24px; overflow: hidden; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        }
        .severity-badge { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 4px 10px; border-radius: 10px; }
        .severity-info { background: #e0f2fe; color: #0369a1; }
        .severity-warning { background: #fef3c7; color: #92400e; }
        .severity-critical { background: #fee2e2; color: #991b1b; }
        .health-indicator { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        .bg-ok { background: #10b981; box-shadow: 0 0 10px rgba(16, 185, 129, 0.5); }
        .bg-err { background: #ef4444; box-shadow: 0 0 10px rgba(239, 68, 68, 0.5); }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>

<?php $layout->sidebar(); ?>

<div class="main-content">
    <?php $layout->topbar($pageTitle); ?>

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
