<?php
// usms/superadmin/dashboard.php
// Superadmin Dashboard — Full System Overview + Notifications + Support View

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

// Must be logged in as superadmin
require_superadmin();

$admin_id   = $_SESSION['admin_id'] ?? 0;
$admin_name = $_SESSION['admin_name'] ?? 'Superadmin';

$db = $conn ?? null;
if (!$db) die('Database connection not available.');


// -------------------------------------------------------
// 1. KPIs
// -------------------------------------------------------
$totalAdmins        = (int) ($db->query("SELECT COUNT(*) AS c FROM admins")->fetch_assoc()['c'] ?? 0);
$totalMembers       = (int) ($db->query("SELECT COUNT(*) AS c FROM members")->fetch_assoc()['c'] ?? 0);
$totalLoans         = (int) ($db->query("SELECT COUNT(*) AS c FROM loans")->fetch_assoc()['c'] ?? 0);
$totalContributions = (float) ($db->query("SELECT IFNULL(SUM(amount),0) AS s FROM contributions")->fetch_assoc()['s'] ?? 0);


// -------------------------------------------------------
// 2. Loan statistics
// -------------------------------------------------------
$pendingLoans   = (int) ($db->query("SELECT COUNT(*) AS c FROM loans WHERE status='pending'")->fetch_assoc()['c']);
$approvedLoans  = (int) ($db->query("SELECT COUNT(*) AS c FROM loans WHERE status='approved'")->fetch_assoc()['c']);
$disbursedLoans = (int) ($db->query("SELECT COUNT(*) AS c FROM loans WHERE status='disbursed'")->fetch_assoc()['c']);


// -------------------------------------------------------
// 3. Member growth (12 months)
// -------------------------------------------------------
$months = [];
$labels = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-{$i} months"));
    $months[] = $m;
    $labels[] = date('M Y', strtotime($m . "-01"));
}

$member_map = array_fill_keys($months, 0);

$res = $db->query("
    SELECT DATE_FORMAT(join_date, '%Y-%m') AS ym, COUNT(*) AS cnt
    FROM members
    WHERE join_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH),'%Y-%m-01')
    GROUP BY ym ORDER BY ym
");

if ($res) {
    while ($r = $res->fetch_assoc()) {
        $member_map[$r['ym']] = (int)$r['cnt'];
    }
}

$member_values = array_map(fn($m) => $member_map[$m], $months);


// -------------------------------------------------------
// 4. Notifications (superadmin sees notifications where to_role='superadmin')
// -------------------------------------------------------
$notifications = [];
$res = $db->query("
    SELECT n.*, 
           COALESCE(a.full_name, m.full_name) AS sender_name
    FROM notifications n
    LEFT JOIN admins a ON n.user_type='admin' AND n.user_id = a.admin_id
    LEFT JOIN members m ON n.user_type='member' AND n.user_id = m.member_id
    WHERE n.to_role = 'superadmin'
    ORDER BY n.created_at DESC
    LIMIT 8
");

while ($row = $res->fetch_assoc()) $notifications[] = $row;


// -------------------------------------------------------
// 5. Support Tickets (Pending Only)
// -------------------------------------------------------
$support_tickets = [];
$res = $db->query("
    SELECT s.*, 
           a.full_name AS admin_name,
           m.full_name AS member_name
    FROM support_tickets s
    LEFT JOIN admins a ON s.admin_id = a.admin_id
    LEFT JOIN members m ON s.member_id = m.member_id
    WHERE s.status = 'Pending'
    ORDER BY s.created_at DESC
    LIMIT 6
");

while ($row = $res->fetch_assoc()) $support_tickets[] = $row;


// -------------------------------------------------------
// 6. Audit Logs
// -------------------------------------------------------
$recent_activity = [];
$res = $db->query("
    SELECT a.*, ad.full_name AS admin_name 
    FROM audit_logs a 
    LEFT JOIN admins ad ON a.admin_id = ad.admin_id
    ORDER BY a.created_at DESC 
    LIMIT 7
");

while ($row = $res->fetch_assoc()) $recent_activity[] = $row;


function ksh($num) { return number_format((float)$num, 2); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Superadmin Dashboard — <?= SITE_NAME ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="<?= ASSET_BASE ?>/css/style.css?v=1.3">

<style>
    :root {
        --sacco-green: <?= $theme['primary'] ?? '#16a34a' ?>;
        --sacco-dark: <?= $theme['primary_dark'] ?? '#0b6623' ?>;
        --sacco-gold: <?= $theme['accent'] ?? '#f4c430' ?>;
    }
    body { background:#f6f8f9; }
    .sidebar { width:260px;background:linear-gradient(180deg,var(--sacco-dark),#0a3d1d);color:#fff;min-height:100vh;position:fixed; }
    .sidebar nav a { color:#fff;padding:12px 18px;display:block;text-decoration:none; }
    .sidebar nav a.active, .sidebar nav a:hover { background:rgba(255,255,255,0.08); }
    .main { margin-left:260px;padding:28px; }
    .kpi-card { background:#fff;border-radius:12px;padding:18px;box-shadow:0 8px 18px rgba(0,0,0,0.05); }
</style>

</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand p-3 d-flex align-items-center gap-2">
        <img src="<?= ASSET_BASE ?>/images/people_logo.png" width="55" style="border-radius:50%;border:2px solid var(--sacco-gold)">
        <div>
            <div class="fw-bold"><?= SITE_NAME ?></div>
            <small><?= TAGLINE ?></small>
        </div>
    </div>

    <nav>
        <a href="dashboard.php" class="active"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
        <a href="manage_admins.php"><i class="bi bi-person-gear me-2"></i>Admins</a>
        <a href="manage_members.php"><i class="bi bi-people me-2"></i>Members</a>
        <a href="manage_loans.php"><i class="bi bi-cash-coin me-2"></i>Loans</a>
        <a href="notifications.php"><i class="bi bi-bell me-2"></i>Notifications</a>
        <a href="support.php"><i class="bi bi-life-preserver me-2"></i>Support</a>
        <a href="audit_logs.php"><i class="bi bi-activity me-2"></i>System Logs</a>
        <a href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a>

        <div class="p-3">
            <a href="<?= BASE_URL ?>/public/logout.php" class="btn btn-warning w-100">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </nav>
</aside>

<!-- MAIN -->
<main class="main">

    <!-- Header -->
    <div class="d-flex justify-content-between mb-4">
        <div>
            <h4>Superadmin Dashboard</h4>
            <small class="text-muted">Welcome, <?= htmlspecialchars($admin_name) ?></small>
        </div>
        <strong><?= date('d M Y') ?></strong>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="kpi-card">Admins <div class="fw-bold"><?= $totalAdmins ?></div></div></div>
        <div class="col-md-3"><div class="kpi-card">Members <div class="fw-bold"><?= $totalMembers ?></div></div></div>
        <div class="col-md-3"><div class="kpi-card">Loans <div class="fw-bold"><?= $totalLoans ?></div></div></div>
        <div class="col-md-3"><div class="kpi-card">Total Contributions <div class="fw-bold text-success">Ksh <?= ksh($totalContributions) ?></div></div></div>
    </div>

    <!-- Charts -->
    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card p-3">
                <h6>Member Growth</h6>
                <canvas id="chartMembers" height="120"></canvas>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card p-3">
                <h6>Loan Summary</h6>
                <canvas id="chartLoans" height="160"></canvas>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <div class="card mb-4">
        <div class="card-header"><h6>Recent Notifications</h6></div>
        <ul class="list-group list-group-flush">
            <?php if (!$notifications): ?>
                <li class="list-group-item text-muted text-center">No notifications</li>
            <?php else: foreach ($notifications as $n): ?>
                <li class="list-group-item">
                    <div class="small text-muted"><?= $n['created_at'] ?></div>
                    <div><strong><?= htmlspecialchars($n['title'] ?? 'Notification') ?></strong></div>
                    <div><?= htmlspecialchars($n['message']) ?></div>
                </li>
            <?php endforeach; endif; ?>
        </ul>
    </div>

    <!-- Support Tickets -->
    <div class="card mb-4">
        <div class="card-header"><h6>Pending Support Tickets</h6></div>
        <ul class="list-group list-group-flush">
        <?php if (!$support_tickets): ?>
            <li class="list-group-item text-center text-muted">No pending tickets</li>
        <?php else: foreach ($support_tickets as $t): ?>
            <li class="list-group-item">
                <div class="fw-bold"><?= htmlspecialchars($t['subject']) ?></div>
                <div><?= htmlspecialchars($t['message']) ?></div>
                <small class="text-muted">
                    From: <?= $t['member_name'] ?: $t['admin_name'] ?>
                </small>
            </li>
        <?php endforeach; endif; ?>
        </ul>
    </div>

    <!-- Activity Log -->
    <div class="card">
        <div class="card-header"><h6>Recent System Activity</h6></div>
        <ul class="list-group list-group-flush">
            <?php if (!$recent_activity): ?>
                <li class="list-group-item text-center text-muted">No recent logs</li>
            <?php else: foreach ($recent_activity as $log): ?>
                <li class="list-group-item">
                    <small class="text-muted"><?= $log['created_at'] ?></small>
                    <div>
                        <?= htmlspecialchars($log['action']) ?>
                        <small class="text-muted"> — <?= htmlspecialchars($log['admin_name']) ?></small>
                    </div>
                </li>
            <?php endforeach; endif; ?>
        </ul>
    </div>

</main>

<script>
// Chart data
new Chart(document.getElementById('chartMembers'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'New Members',
            data: <?= json_encode($member_values) ?>,
            fill: true,
            borderColor: '#16a34a',
            backgroundColor: 'rgba(22,163,74,0.1)'
        }]
    },
    options: { plugins: { legend: { display:false } } }
});

new Chart(document.getElementById('chartLoans'), {
    type: 'bar',
    data: {
        labels: ['Pending', 'Approved', 'Disbursed'],
        datasets: [{
            data: [<?= $pendingLoans ?>, <?= $approvedLoans ?>, <?= $disbursedLoans ?>],
            backgroundColor: ['#f59e0b','#16a34a','#0ea5e9']
        }]
    },
    options: { plugins: { legend: { display:false } }, scales: { y: { beginAtZero:true } } }
});
</script>

</body>
</html>