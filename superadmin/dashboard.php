<?php
// superadmin/dashboard.php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../inc/auth.php';

// 1. Auth Check (Superadmin Only)
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

$admin_name = htmlspecialchars($_SESSION['full_name'] ?? 'Super Admin');

// 2. HIGH LEVEL METRICS
$members_cnt = $conn->query("SELECT COUNT(*) as c FROM members")->fetch_assoc()['c'] ?? 0;
$staff_cnt   = $conn->query("SELECT COUNT(*) as c FROM admins")->fetch_assoc()['c'] ?? 0;

$res_sav = $conn->query("SELECT 
    COALESCE(SUM(CASE WHEN transaction_type IN ('deposit','repayment','income','share_capital') THEN amount ELSE 0 END),0) - 
    COALESCE(SUM(CASE WHEN transaction_type IN ('withdrawal','loan_disbursement','expense') THEN amount ELSE 0 END),0) 
    as net_liquidity FROM transactions");
$net_liquidity = $res_sav->fetch_assoc()['net_liquidity'] ?? 0;

$res_loans = $conn->query("SELECT COALESCE(SUM(amount),0) as val FROM loans WHERE status IN ('disbursed','active')");
$total_loans = $res_loans->fetch_assoc()['val'] ?? 0;

$liquidity_ratio = ($net_liquidity > 0) ? ($total_loans / $net_liquidity) * 100 : 0;

$error_count = 0;
$log_file = __DIR__ . '/../member/mpesa_error.log';
if (file_exists($log_file)) {
    $lines = file($log_file);
    foreach(array_slice($lines, -100) as $line) {
        if (stripos($line,'ERROR') !== false || stripos($line,'FAILED') !== false) $error_count++;
    }
}

// 3. RECENT SYSTEM AUDITS
$audit_logs = [];
$chk = $conn->query("SHOW TABLES LIKE 'audit_logs'");
if ($chk->num_rows > 0) {
    $sql = "SELECT al.*, a.full_name as user_name 
            FROM audit_logs al 
            LEFT JOIN admins a ON al.admin_id = a.admin_id 
            ORDER BY al.created_at DESC LIMIT 6"; 
    $res = $conn->query($sql);
    while($row = $res->fetch_assoc()) $audit_logs[] = $row;
}

$pageTitle = "System Command Center";

// Define Stat Array with new Color Logic
$stats_data = [
    [
        'label' => 'Total Users',
        'value' => $members_cnt + $staff_cnt,
        'sub'   => "$members_cnt Members • $staff_cnt Staff",
        'icon'  => 'bi-people-fill',
        'type'  => 'primary-command' // Custom Deep Green Card
    ],
    [
        'label' => 'Liquidity Health',
        'value' => number_format($liquidity_ratio, 1) . '%',
        'sub'   => $liquidity_ratio > 85 ? 'Risk: Low Reserves' : 'Healthy Reserves',
        'icon'  => 'bi-activity',
        'status'=> $liquidity_ratio > 85 ? 'text-danger' : 'text-lime',
        'type'  => 'standard'
    ],
    [
        'label' => 'System Status',
        'value' => $error_count > 0 ? 'Review' : 'Optimal',
        'sub'   => $error_count > 0 ? "$error_count Critical Errors" : 'All Services Online',
        'icon'  => $error_count > 0 ? 'bi-exclamation-triangle' : 'bi-shield-check',
        'status'=> $error_count > 0 ? 'text-warning' : 'text-lime',
        'type'  => 'standard'
    ],
    [
        'label' => 'Loan Portfolio',
        'value' => 'KES ' . number_format($total_loans / 1000000, 1) . 'M',
        'sub'   => 'Active Disbursed Capital',
        'icon'  => 'bi-bank2',
        'status'=> 'text-dark',
        'type'  => 'standard'
    ]
];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --forest-green: #062d1d;
            --lime-neon: #d1ff33;
            --bg-body: #f4f7f6;
            --card-radius: 24px;
            --shadow-soft: 0 10px 40px rgba(0,0,0,0.04);
        }

        [data-bs-theme="dark"] {
            --bg-body: #0a0c0b;
            --forest-green: #0b1a14;
        }

        body { 
            background-color: var(--bg-body); 
            font-family: 'Inter', system-ui, -apple-system, sans-serif; 
            color: #1a1a1a;
        }

        /* Command Center Cards */
        .cmd-card {
            background: #ffffff;
            border-radius: var(--card-radius);
            border: none;
            padding: 1.75rem;
            box-shadow: var(--shadow-soft);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .cmd-card:hover { transform: translateY(-5px); }

        /* Primary Deep Green Styling */
        .cmd-card.primary-command {
            background: var(--forest-green);
            color: #ffffff;
        }

        .primary-command .icon-box {
            background: var(--lime-neon);
            color: var(--forest-green);
        }

        .icon-box {
            width: 48px; height: 48px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            background: rgba(0,0,0,0.04);
            font-size: 1.25rem;
        }

        /* Lime Enhancements */
        .text-lime { color: #28a745 !important; }
        .primary-command .text-lime { color: var(--lime-neon) !important; }

        /* Table Design */
        .cmd-table { border-collapse: separate; border-spacing: 0 10px; }
        .cmd-table thead th { 
            border: none; color: #888; font-size: 0.75rem; 
            text-transform: uppercase; letter-spacing: 1px; padding-left: 1.5rem;
        }
        .cmd-table tbody tr { 
            background: #ffffff; box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border-radius: 15px;
        }
        .cmd-table tbody td { border: none; padding: 1.25rem 1.5rem; }
        .cmd-table tbody tr td:first-child { border-radius: 15px 0 0 15px; }
        .cmd-table tbody tr td:last-child { border-radius: 0 15px 15px 0; }

        /* Tools & Sidebar Layout */
        .main-content { margin-left: 260px; transition: 0.3s; }
        @media (max-width: 991px) { .main-content { margin-left: 0; } }

        .btn-lime {
            background: var(--lime-neon);
            color: var(--forest-green);
            border: none;
            font-weight: 700;
            border-radius: 12px;
            padding: 10px 20px;
        }
        .btn-lime:hover { background: #beeb22; }
    </style>
</head>
<body>

<div class="d-flex">
        <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php require_once __DIR__ . '/../inc/topbar.php'; ?>
            
            <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-end mb-5">
                <div>
                    <h2 class="fw-bold mb-1">Command Center</h2>
                    <p class="text-muted mb-0">System Infrastructure & Critical Metrics</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="../admin/backups.php" class="btn btn-white border px-4 py-2 rounded-4 fw-semibold shadow-sm">
                        <i class="bi bi-shield-lock me-2"></i>Security Backup
                    </a>
                </div>
            </div>

            <div class="row g-4 mb-5">
                <?php foreach($stats_data as $s): ?>
                <div class="col-xl-3 col-md-6">
                    <div class="cmd-card <?= $s['type'] ?>">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div class="icon-box">
                                <i class="bi <?= $s['icon'] ?>"></i>
                            </div>
                            <?php if($s['type'] === 'primary-command'): ?>
                                <span class="badge rounded-pill bg-success bg-opacity-25 text-lime">Live</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="<?= $s['type'] === 'primary-command' ? 'text-white-50' : 'text-muted' ?> small fw-bold text-uppercase mb-1"><?= $s['label'] ?></p>
                            <h2 class="fw-bold mb-2 <?= $s['status'] ?? '' ?>"><?= $s['value'] ?></h2>
                            <div class="small <?= $s['type'] === 'primary-command' ? 'text-white-50' : 'text-muted' ?>">
                                <?= $s['sub'] ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Recent Audit Activity</h5>
                        <a href="../admin/audit_logs.php" class="btn btn-sm text-primary fw-bold">View Full Log <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table cmd-table align-middle">
                            <thead>
                                <tr>
                                    <th>Administrator</th>
                                    <th>Operation</th>
                                    <th class="text-end">Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($audit_logs)): ?>
                                    <tr><td colspan="3" class="text-center py-5 text-muted">No recent activity detected.</td></tr>
                                <?php else: foreach($audit_logs as $log): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light rounded-circle p-2 me-3">
                                                    <i class="bi bi-person text-dark"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></div>
                                                    <small class="text-muted"><?= $log['ip_address'] ?? 'Internal' ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill bg-light text-dark px-3 py-2 border">
                                                <?= strtoupper(str_replace('_', ' ', $log['action'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-end text-muted small">
                                            <?= date('M d, H:i', strtotime($log['created_at'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="cmd-card primary-command h-100">
                        <h5 class="fw-bold mb-4">Command Tools</h5>
                        
                        <div class="list-group list-group-flush bg-transparent">
                            <a href="../superadmin/manage_admins.php" class="list-group-item bg-transparent text-white border-white border-opacity-10 py-3 px-0 d-flex align-items-center">
                                <i class="bi bi-people me-3 fs-5 text-lime"></i>
                                <div>
                                    <div class="fw-bold">Staff Directory</div>
                                    <div class="small text-white-50">Manage administrative roles</div>
                                </div>
                            </a>
                            <a href="../admin/settings.php" class="list-group-item bg-transparent text-white border-white border-opacity-10 py-3 px-0 d-flex align-items-center">
                                <i class="bi bi-gear me-3 fs-5 text-lime"></i>
                                <div>
                                    <div class="fw-bold">System Parameters</div>
                                    <div class="small text-white-50">Global config & rates</div>
                                </div>
                            </a>
                        </div>

                        <div class="mt-5 pt-4 border-top border-white border-opacity-10">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="bi bi-megaphone-fill text-lime"></i>
                                <span class="fw-bold small text-uppercase letter-spacing-1">Broadcast</span>
                            </div>
                            <p class="small text-white-50 mb-4">Send a high-priority system alert to all active sessions.</p>
                            <a href="../public/messages.php?action=compose&type=broadcast" class="btn btn-lime w-100">Create Announcement</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <?php require_once __DIR__ . '/../inc/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
        window.addEventListener('themeChanged', (e) => {
            document.documentElement.setAttribute('data-bs-theme', e.detail);
        });
    });
</script>
</body>
</html>
