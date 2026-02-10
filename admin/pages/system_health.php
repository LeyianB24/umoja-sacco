<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/FinancialIntegrityChecker.php';

require_admin();

$layout = LayoutManager::create('admin');
$checker = new FinancialIntegrityChecker($conn);

// Statistics
$stats = [
    'callback_rate' => 0,
    'pending_count' => 0,
    'mismatch_count' => 0,
    'email_success' => 0
];

// 1. Callback Success Rate
$res = $conn->query("SELECT status, COUNT(*) as cnt FROM mpesa_requests WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY status");
$total_mpesa = 0;
$completed_mpesa = 0;
while ($row = $res->fetch_assoc()) {
    $total_mpesa += $row['cnt'];
    if ($row['status'] === 'completed') $completed_mpesa += $row['cnt'];
}
$stats['callback_rate'] = $total_mpesa > 0 ? ($completed_mpesa / $total_mpesa) * 100 : 100;

// 2. Pending Contributions
$stats['pending_count'] = $conn->query("SELECT COUNT(*) FROM contributions WHERE status = 'pending'")->fetch_row()[0];

// 3. Ledger Mismatches (From latest check)
$stats['mismatch_count'] = $conn->query("SELECT COUNT(*) FROM integrity_checks WHERE status = 'failed' AND resolved = 0")->fetch_row()[0];

// 4. Email Success Rate
$res = $conn->query("SELECT status, COUNT(*) as cnt FROM email_queue WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY status");
$total_emails = 0;
$sent_emails = 0;
while ($row = $res->fetch_assoc()) {
    $total_emails += $row['cnt'];
    if ($row['status'] === 'sent') $sent_emails += $row['cnt'];
}
$stats['email_success'] = $total_emails > 0 ? ($sent_emails / $total_emails) * 100 : 100;

$env_label = strtoupper(APP_ENV);
$env_badge = APP_ENV === 'production' ? 'bg-success' : 'bg-warning';

// Run a manual audit if requested
if (isset($_GET['action']) && $_GET['action'] === 'run_audit') {
    $checker->runFullAudit();
    header("Location: system_health.php?audit_complete=1");
    exit;
}

$layout->header("System Health");
?>

<div class="container-fluid">
    <!-- Summary Cards -->
    <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
        <div class="col-md-6 col-lg-3">
            <div class="card bg-<?php echo $stats['callback_rate'] > 70 ? 'success' : 'warning'; ?> text-white text-center p-6">
                <div class="fs-2hx fw-bold mb-2"><?php echo round($stats['callback_rate']); ?>%</div>
                <div class="fs-6 fw-semibold opacity-75">MPesa Callback Rate (24h)</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card bg-<?php echo $stats['pending_count'] > 5 ? 'danger' : 'success'; ?> text-white text-center p-6">
                <div class="fs-2hx fw-bold mb-2"><?php echo $stats['pending_count']; ?></div>
                <div class="fs-6 fw-semibold opacity-75">Pending Transactions</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card bg-<?php echo $stats['mismatch_count'] > 0 ? 'danger' : 'success'; ?> text-white text-center p-6">
                <div class="fs-2hx fw-bold mb-2"><?php echo $stats['mismatch_count']; ?></div>
                <div class="fs-6 fw-semibold opacity-75">Ledger Mismatches</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card bg-<?php echo $stats['email_success'] > 90 ? 'info' : 'warning'; ?> text-white text-center p-6">
                <div class="fs-2hx fw-bold mb-2"><?php echo round($stats['email_success']); ?>%</div>
                <div class="fs-6 fw-semibold opacity-75">Email Success (24h)</div>
            </div>
        </div>
    </div>

                <div class="card-header border-0 pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1">System Health Overview</span>
                        <span class="text-muted mt-1 fw-semibold fs-7">Running in <span class="badge <?php echo $env_badge; ?>"><?php echo $env_label; ?></span> mode</span>
                    </h3>
                </div>
                <div class="card-body py-3">
                    <div class="card-toolbar">
                        <a href="?action=run_audit" class="btn btn-sm btn-primary">Run Full Audit</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                         <table class="table align-middle table-row-dashed fs-6 gy-5">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase">
                                    <th>Check Type</th>
                                    <th>Status</th>
                                    <th>Checked At</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $checks = $conn->query("SELECT * FROM integrity_checks ORDER BY checked_at DESC LIMIT 10");
                                if ($checks->num_rows === 0): ?>
                                    <tr><td colspan="4" class="text-center">No checks performed yet.</td></tr>
                                <?php else: while ($c = $checks->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo str_replace('_', ' ', ucfirst($c['check_type'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $c['status'] === 'passed' ? 'success' : 'danger'; ?>">
                                                <?php echo strtoupper($c['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M, H:i', strtotime($c['checked_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-light" onclick="alert('Details: <?php echo addslashes($c['details']); ?>')">View</button>
                                        </td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4">
            <div class="card card-flush shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">M-Pesa Callback Status (24h)</h3>
                </div>
                <div class="card-body d-flex flex-center">
                    <div style="width: 200px; height: 200px; border-radius: 50%; border: 15px solid #eee; position: relative;">
                        <!-- Placeholder for a chart -->
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                            <span class="fs-2hx fw-bold"><?php echo round($stats['callback_rate']); ?>%</span><br>
                            <span class="fs-7 text-muted">Success</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$layout->footer();
