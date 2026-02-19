<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

require_admin();
require_permission();

$layout = LayoutManager::create('admin');
$pageTitle = "Transaction Monitor";

// Fetch recent callback logs
$callbacks = $conn->query("SELECT cl.*, m.full_name, m.phone 
    FROM callback_logs cl 
    LEFT JOIN members m ON cl.member_id = m.member_id 
    ORDER BY cl.created_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);

// Fetch recent mpesa requests
$requests = $conn->query("SELECT r.*, m.full_name, m.phone 
    FROM mpesa_requests r 
    JOIN members m ON r.member_id = m.member_id 
    ORDER BY r.created_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> | USMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
    <style>
        .table-premium { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .callback-status-success { color: #059669; background: #ecfdf5; padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 600; }
        .callback-status-failed { color: #dc2626; background: #fef2f2; padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 600; }
        .raw-payload { max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: monospace; font-size: 11px; color: #64748b; }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body class="bg-light">
    <div class="d-flex">
        <?php $layout->sidebar(); ?>
        <div class="flex-fill p-4" style="margin-left: 280px;">
            <?php $layout->topbar($pageTitle); ?>

            <div class="mb-4 d-flex justify-content-between align-items-center">
                <h4 class="fw-bold m-0 text-forest">Live Operations Monitor</h4>
                <div class="btn-group">
                    <button class="btn btn-outline-forest active" id="btnCallbacks">Callback Logs</button>
                    <button class="btn btn-outline-forest" id="btnRequests">Outbound Requests</button>
                </div>
            </div>

            <!-- Callback Logs -->
            <div id="sectionCallbacks" class="table-premium">
                <div class="p-4 border-bottom bg-white">
                    <h5 class="fw-bold mb-0">Incoming Payment Callbacks</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>Type</th>
                                <th>Member / Phone</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>M-Pesa Ref</th>
                                <th>Raw Payload</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($callbacks as $cl): ?>
                            <tr>
                                <td class="small"><?= date('M j, H:i', strtotime($cl['created_at'])) ?></td>
                                <td><span class="badge bg-secondary-subtle text-secondary small"><?= $cl['callback_type'] ?></span></td>
                                <td>
                                    <div class="fw-bold"><?= $cl['full_name'] ?? 'Inbound' ?></div>
                                    <div class="small text-muted"><?= $cl['phone'] ?? 'Unknown' ?></div>
                                </td>
                                <td class="fw-bold">KES <?= number_format((float)($cl['amount'] ?? 0)) ?></td>
                                <td>
                                    <span class="<?= ((int)$cl['result_code'] === 0) ? 'callback_status-success' : 'callback_status-failed' ?>">
                                        <?= ((int)$cl['result_code'] === 0) ? 'SUCCESS' : 'FAILED (' . $cl['result_code'] . ')' ?>
                                    </span>
                                </td>
                                <td class="small font-monospace"><?= $cl['mpesa_receipt_number'] ?: 'N/A' ?></td>
                                <td class="raw-payload"><?= htmlspecialchars($cl['raw_payload']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Outbound Requests -->
            <div id="sectionRequests" class="table-premium d-none">
                <div class="p-4 border-bottom bg-white">
                    <h5 class="fw-bold mb-0">Initiated Payment Requests</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>Member</th>
                                <th>Amount</th>
                                <th>Ref No</th>
                                <th>Checkout ID</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $r): ?>
                            <tr>
                                <td class="small"><?= date('M j, H:i', strtotime($r['created_at'])) ?></td>
                                <td>
                                    <div class="fw-bold"><?= $r['full_name'] ?></div>
                                    <div class="small text-muted"><?= $r['phone'] ?></div>
                                </td>
                                <td class="fw-bold">KES <?= number_format((float)($r['amount'] ?? 0)) ?></td>
                                <td class="small"><?= $r['reference_no'] ?></td>
                                <td class="small font-monospace"><?= $r['checkout_request_id'] ?></td>
                                <td>
                                    <?php if ($r['status'] === 'completed'): ?>
                                        <span class="badge bg-success-subtle text-success">Completed</span>
                                    <?php elseif ($r['status'] === 'pending'): ?>
                                        <span class="badge bg-warning-subtle text-warning">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger">Failed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('btnCallbacks').onclick = function() {
            this.classList.add('active');
            document.getElementById('btnRequests').classList.remove('active');
            document.getElementById('sectionCallbacks').classList.remove('d-none');
            document.getElementById('sectionRequests').classList.add('d-none');
        };
        document.getElementById('btnRequests').onclick = function() {
            this.classList.add('active');
            document.getElementById('btnCallbacks').classList.remove('active');
            document.getElementById('sectionRequests').classList.remove('d-none');
            document.getElementById('sectionCallbacks').classList.add('d-none');
        };
    </script>
</body>
</html>
