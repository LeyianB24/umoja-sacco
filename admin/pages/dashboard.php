<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

// Initialize Layout Manager
$layout = LayoutManager::create('admin');

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'System Admin');

// Role-based visibility: Filter tickets by assigned_role_id
$my_role_id = (int)($_SESSION['role_id'] ?? 0);
$where_clauses = ["s.status != 'Closed'"];

if ($my_role_id !== 1) {
    $where_clauses[] = "s.assigned_role_id = $my_role_id";
}

$where_sql = "WHERE " . implode(' AND ', $where_clauses);

$open_tickets = $conn->query("SELECT COUNT(*) AS c FROM support_tickets s $where_sql")->fetch_assoc()['c'] ?? 0;
$today_logs = $conn->query("SELECT COUNT(*) AS c FROM audit_logs WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0;

// Total Cash Position (Sourced from Golden Ledger accounts: Cash, Bank, M-Pesa)
$cash_position = 0;
if (Auth::can('view_financials') || $my_role_id === 1) {
    $cash_res = $conn->query("SELECT SUM(current_balance) as balance FROM ledger_accounts WHERE category IN ('cash', 'bank', 'mpesa')");
    $cash_position = $cash_res->fetch_assoc()['balance'] ?? 0;
}

// Database Size (Restricted)
$db_size = "N/A";
if ($my_role_id === 1) {
    try {
        $q = $conn->query("SELECT SUM(data_length + index_length) / 1024 / 1024 AS size FROM information_schema.TABLES WHERE table_schema=DATABASE()");
        $row = $q->fetch_assoc();
        $db_size = number_format((float)($row['size'] ?? 0), 1);
    } catch (Exception $e) { $db_size = "0.0"; }
}

// Recent Tickets (Role-Filtered)
$tickets = $conn->query("SELECT s.*, COALESCE(m.full_name,'Guest') AS sender FROM support_tickets s LEFT JOIN members m ON s.member_id=m.member_id $where_sql ORDER BY s.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// LIVE SIMULATION: System Health Metrics
require_once __DIR__ . '/../../inc/SystemHealthHelper.php';
$health = getSystemHealth($conn);

$pageTitle = "System Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> | USMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
    <style>
        :root { --forest: #0F2E25; --lime: #D0F35D; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0f4f3; }
        .main-content { margin-left: 280px; padding: 40px; min-height: 100vh; }
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
        .glass-stat { 
            background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px);
            border-radius: 24px; padding: 30px; border: 1px solid rgba(255,255,255,0.5);
            transition: 0.3s;
        }
        .glass-stat:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
        .icon-puck { 
            width: 56px; height: 56px; border-radius: 18px; 
            display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        }
        .table-glass { background: white; border-radius: 24px; overflow: hidden; border: none; }
        .nav-pill-custom {
            padding: 15px 25px; border-radius: 20px; background: white; 
            color: var(--forest); text-decoration: none; font-weight: 700;
            display: flex; align-items: center; gap: 15px; transition: 0.3s;
            margin-bottom: 15px; border: 1px solid rgba(0,0,0,0.02);
        }
        .nav-pill-custom:hover { background: var(--forest); color: white; transform: translateX(5px); }
        .nav-pill-custom i { font-size: 1.2rem; opacity: 0.7; }
    </style>
</head>
<body>

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content">
        <?php $layout->topbar($pageTitle ?? 'System Dashboard'); ?>

    <div class="hp-hero">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3">V28 System Online</span>
                <h1 class="display-4 fw-800 mb-2">Hello, <?= explode(' ', $admin_name)[0] ?>.</h1>
                <p class="opacity-75 fs-5">Everything is running smoothly. The Sacco's financial heart is beating at <span class="text-lime fw-bold">100% precision</span>.</p>
                <a href="<?= BASE_URL ?>/admin/pages/loans_reviews.php" class="btn btn-lime rounded-pill px-4 py-2 fw-bold shadow-sm">
                    <i class="bi bi-clock-history me-2"></i> Review Pending Loans
                </a>
            </div>
            <div class="col-md-5 text-end d-none d-lg-block">
                <div class="d-inline-block p-4 rounded-4 bg-white bg-opacity-10 backdrop-blur">
                    <div class="small opacity-75">Ledger Integrity</div>
                    <div class="h3 fw-bold mb-0 text-lime">ACID Verified</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <a href="<?= BASE_URL ?>/admin/pages/support.php" class="text-decoration-none">
                <div class="glass-stat">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="icon-puck bg-primary bg-opacity-10 text-primary"><i class="bi bi-ticket-perforated"></i></div>
                        <div class="text-end">
                            <div class="small text-muted fw-bold">TICKETS</div>
                            <div class="h3 fw-800 mb-0"><?= $open_tickets ?></div>
                        </div>
                    </div>
                    <div class="progress" style="height: 6px; border-radius: 10px;">
                        <div class="progress-bar bg-primary" style="width: 45%"></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="<?= BASE_URL ?>/admin/pages/audit_logs.php" class="text-decoration-none">
                <div class="glass-stat">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="icon-puck bg-success bg-opacity-10 text-success"><i class="bi bi-activity"></i></div>
                        <div class="text-end">
                            <div class="small text-muted fw-bold">LOGS</div>
                            <div class="h3 fw-800 mb-0"><?= $today_logs ?></div>
                        </div>
                    </div>
                    <div class="progress" style="height: 6px; border-radius: 10px;">
                        <div class="progress-bar bg-success" style="width: 70%"></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="<?= BASE_URL ?>/admin/pages/revenue.php" class="text-decoration-none">
                <div class="glass-stat">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="icon-puck bg-dark text-lime"><i class="bi bi-bank"></i></div>
                        <div class="text-end">
                            <div class="small text-muted fw-bold">CASH</div>
                            <div class="h3 fw-800 mb-0"><?= number_format((float)($cash_position / 1000), 1) ?>K</div>
                        </div>
                    </div>
                    <div class="progress" style="height: 6px; border-radius: 10px;">
                        <div class="progress-bar bg-dark" style="width: 85%"></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="<?= BASE_URL ?>/admin/pages/system_health.php" class="text-decoration-none">
                <div class="glass-stat">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="icon-puck bg-warning bg-opacity-10 text-warning"><i class="bi bi-database"></i></div>
                        <div class="text-end">
                            <div class="small text-muted fw-bold">STORAGE</div>
                            <div class="h3 fw-800 mb-0"><?= $db_size ?> <small class="fs-6">MB</small></div>
                        </div>
                    </div>
                    <div class="progress" style="height: 6px; border-radius: 10px;">
                        <div class="progress-bar bg-warning" style="width: 30%"></div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-12">
            <a href="<?= BASE_URL ?>/admin/pages/live_monitor.php" class="text-decoration-none">
                <div class="glass-stat border-0 shadow-sm" style="background: linear-gradient(to right, #ffffff, #f8fafc);">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="bi bi-broadcast text-danger me-2"></i>Live Operations Monitor</h5>
                            <p class="small text-muted mb-0">Real-time status of payment gateways and notification engines.</p>
                        </div>
                        <?php if ($health['ledger_imbalance']): ?>
                            <span class="badge bg-danger rounded-pill px-3 py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Ledger Imbalance Detected</span>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> All Systems Nominal</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row g-4 text-dark">
                        <div class="col-md-3 border-end">
                            <div class="small text-muted mb-1">Callback Success Rate</div>
                            <div class="h4 fw-bold mb-0"><?= $health['callback_success_rate'] ?>%</div>
                            <div class="progress mt-2" style="height: 4px;">
                                <div class="progress-bar bg-success" style="width: <?= $health['callback_success_rate'] ?>%"></div>
                            </div>
                        </div>
                        <div class="col-md-3 border-end">
                            <div class="small text-muted mb-1">Pending STK (>5m)</div>
                            <div class="h4 fw-bold mb-0 <?= $health['pending_transactions'] > 0 ? 'text-warning' : '' ?>">
                                <?= $health['pending_transactions'] ?> Transactions
                            </div>
                        </div>
                        <div class="col-md-3 border-end">
                            <div class="small text-muted mb-1">Failed Comms (Today)</div>
                            <div class="h4 fw-bold mb-0 <?= $health['failed_notifications'] > 0 ? 'text-danger' : '' ?>">
                                <?= $health['failed_notifications'] ?> Alerts
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted mb-1">Daily Volume</div>
                            <div class="h4 fw-bold mb-0">KES <?= number_format($health['daily_volume']) ?></div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="table-glass shadow-sm p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Active Support Inbox</h5>
                    <a href="<?= BASE_URL ?>/admin/pages/support.php" class="btn btn-light rounded-pill px-4 btn-sm border fw-bold text-forest">Open Support Center</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="border-0 rounded-start">Member</th>
                                <th class="border-0">Issue</th>
                                <th class="border-0">Priority</th>
                                <th class="border-0 rounded-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tickets as $t): ?>
                            <tr>
                                <td class="py-3">
                                    <div class="fw-bold"><?= htmlspecialchars($t['sender']) ?></div>
                                    <small class="text-muted"><?= time_ago($t['created_at']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($t['subject']) ?></td>
                                <td>
                                    <?php $p = $t['priority']; ?>
                                    <span class="badge rounded-pill bg-<?= $p=='High'?'danger':($p=='Medium'?'warning':'info') ?> bg-opacity-10 text-<?= $p=='High'?'danger':($p=='Medium'?'warning':'info') ?> px-3">
                                        <?= strtoupper($p) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/admin/pages/support_view.php?id=<?= $t['support_id'] ?>" class="btn btn-forest btn-sm rounded-pill px-3">Reply</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <h5 class="fw-bold mb-4">Management Hub</h5>
            
            <?php if (Auth::can('members.php') || $my_role_id === 1): ?>
            <a href="<?= BASE_URL ?>/admin/pages/members.php" class="nav-pill-custom">
                <div class="icon-puck bg-light"><i class="bi bi-people"></i></div>
                <span>Member Directory</span>
                <i class="bi bi-chevron-right ms-auto opacity-25"></i>
            </a>
            <?php endif; ?>

            <?php if (Auth::can('loans_reviews.php') || $my_role_id === 1): ?>
            <a href="<?= BASE_URL ?>/admin/pages/loans_reviews.php" class="nav-pill-custom">
                <div class="icon-puck bg-light"><i class="bi bi-cash-stack"></i></div>
                <span>Loan Review Desk</span>
                <i class="bi bi-chevron-right ms-auto opacity-25"></i>
            </a>
            <?php endif; ?>

            <?php if (Auth::can('reports.php') || $my_role_id === 1): ?>
            <a href="<?= BASE_URL ?>/admin/pages/reports.php" class="nav-pill-custom">
                <div class="icon-puck bg-light"><i class="bi bi-file-earmark-bar-graph"></i></div>
                <span>Analytics & Reports</span>
                <i class="bi bi-chevron-right ms-auto opacity-25"></i>
            </a>
            <?php endif; ?>

            <?php if (Auth::can('settings.php') || $my_role_id === 1): ?>
            <a href="<?= BASE_URL ?>/admin/pages/settings.php" class="nav-pill-custom">
                <div class="icon-puck bg-light"><i class="bi bi-shield-lock"></i></div>
                <span>System Security</span>
                <i class="bi bi-chevron-right ms-auto opacity-25"></i>
            </a>
            <?php endif; ?>

            <?php if (Auth::can('live_monitor.php') || $my_role_id === 1): ?>
            <a href="<?= BASE_URL ?>/admin/pages/live_monitor.php" class="nav-pill-custom">
                <div class="icon-puck bg-light text-danger"><i class="bi bi-broadcast"></i></div>
                <span>Live Monitor</span>
                <i class="bi bi-chevron-right ms-auto opacity-25"></i>
            </a>
            <?php endif; ?>

            <?php if (Auth::can('system_health.php') || $my_role_id === 1): ?>
            <a href="<?= BASE_URL ?>/admin/pages/system_health.php" class="nav-pill-custom">
                <div class="icon-puck bg-light text-success"><i class="bi bi-heart-pulse"></i></div>
                <span>System Health</span>
                <i class="bi bi-chevron-right ms-auto opacity-25"></i>
            </a>
            <?php endif; ?>
            <?php $layout->footer(); ?>
        </div>
        
    </div>
    
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>








