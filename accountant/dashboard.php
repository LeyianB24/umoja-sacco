<?php
// usms/accountant/dashboard.php
// Accountant Dashboard — Umoja SACCO System

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

// Only Accountant access
require_accountant();

$admin_name = $_SESSION['admin_name'] ?? 'Accountant';
$admin_id = $_SESSION['admin_id'] ?? 0;

// ---------- KPIs ----------
$totalContributions = (float) ($conn->query("SELECT IFNULL(SUM(amount),0) AS total FROM contributions")->fetch_assoc()['total'] ?? 0.0);
$totalRepayments = (float) ($conn->query("SELECT IFNULL(SUM(amount_paid),0) AS total FROM loan_repayments")->fetch_assoc()['total'] ?? 0.0);
$pendingRepayments = (int) ($conn->query("SELECT COUNT(*) AS c FROM loan_repayments WHERE status='pending'")->fetch_assoc()['c'] ?? 0);
$totalDisbursedLoans = (int) ($conn->query("SELECT COUNT(*) AS c FROM loans WHERE status='disbursed'")->fetch_assoc()['c'] ?? 0);

// ---------- Monthly Contributions ----------
$months = [];
$labels = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-{$i} months"));
    $months[] = $m;
    $labels[] = date('M Y', strtotime($m . '-01'));
}
$contrib_map = array_fill_keys($months, 0);
$sql = "
  SELECT DATE_FORMAT(contribution_date, '%Y-%m') AS ym, SUM(amount) AS total
  FROM contributions
  WHERE contribution_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
  GROUP BY ym ORDER BY ym
";
$res = $conn->query($sql);
if ($res) while ($r = $res->fetch_assoc()) $contrib_map[$r['ym']] = (float)$r['total'];
$contrib_values = array_map(fn($m) => $contrib_map[$m] ?? 0, $months);

// ---------- Repayment Status ----------
$repayment_status = ['pending' => 0, 'confirmed' => 0, 'rejected' => 0];
$res = $conn->query("SELECT status, COUNT(*) AS c FROM loan_repayments GROUP BY status");
if ($res) while ($r = $res->fetch_assoc()) $repayment_status[strtolower($r['status'])] = (int)$r['c'];

// ---------- Notifications ----------
$notifications = [];
$notiQuery = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
if ($notiQuery) while ($n = $notiQuery->fetch_assoc()) $notifications[] = $n;

// ---------- Recent Activity (from audit_logs) ----------
$recent_activity = [];
$res = $conn->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 8");
if ($res) while ($r = $res->fetch_assoc()) $recent_activity[] = $r;

function ksh($n) { return number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Accountant Dashboard — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="<?= htmlspecialchars(ASSET_BASE) ?>/css/style.css?v=1.2">
  <style>
    :root {
      --sacco-green: <?= $theme['primary'] ?? '#16a34a' ?>;
      --sacco-dark: <?= $theme['primary_dark'] ?? '#0b6623' ?>;
      --sacco-gold: <?= $theme['accent'] ?? '#f4c430' ?>;
    }
    body { background:#f6f8f9; }
    .sidebar { width:260px; background: linear-gradient(180deg,var(--sacco-dark), #0a3d1d); color:#fff; min-height:100vh; position:fixed; }
    .sidebar .brand { padding:18px; display:flex; gap:12px; align-items:center; }
    .sidebar nav a { color: rgba(255,255,255,0.9); padding:12px 18px; display:block; text-decoration:none; }
    .sidebar nav a:hover, .sidebar nav a.active { background: rgba(255,255,255,0.08); }
    .main { margin-left:260px; padding:28px; }
    .kpi-card { border-radius:12px; box-shadow:0 8px 26px rgba(11,54,2,0.04); padding:18px; background:#fff; }
    .profile-dropdown { position: relative; }
    .profile-menu { display: none; position: absolute; right: 0; top: 120%; background: white; box-shadow: 0 3px 10px rgba(0,0,0,0.1); border-radius:8px; z-index:10; }
    .profile-menu a { display: block; padding:8px 16px; color:#333; text-decoration:none; }
    .profile-menu a:hover { background:#f1f1f1; }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="brand">
    <img src="<?= htmlspecialchars(ASSET_BASE) ?>/images/people_logo.png" alt="logo" width="56" height="56" style="border-radius:50%;border:3px solid var(--sacco-gold)">
    <div>
      <div style="font-weight:700;"><?= htmlspecialchars(SITE_NAME) ?></div>
      <small style="color:rgba(255,255,255,0.8)"><?= htmlspecialchars(TAGLINE) ?></small>
    </div>
  </div>
  <nav class="mt-3">
    <a href="dashboard.php" class="active"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a href="manage_contributions.php"><i class="bi bi-cash me-2"></i> Manage Contributions</a>
    <a href="manage_repayments.php"><i class="bi bi-wallet2 me-2"></i> Manage Repayments</a>
    <a href="generate_statements.php"><i class="bi bi-file-earmark-text me-2"></i> Generate Statements</a>
    <a href="notifications.php"><i class="bi bi-bell me-2"></i> Notifications</a>
    <a href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/support.php"><i class="bi bi-life-preserver me-2"></i> Support / Help</a>
    <div class="mt-4 px-3">
      <a href="<?= htmlspecialchars(BASE_URL) ?>/public/logout.php" class="btn btn-sm w-100" style="background:var(--sacco-gold); color:var(--sacco-dark); font-weight:600;">
        <i class="bi bi-box-arrow-right me-2"></i> Logout
      </a>
    </div>
  </nav>
</aside>

<!-- MAIN -->
<main class="main">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-0" style="color:var(--sacco-dark)">Accountant Dashboard</h4>
      <small class="text-muted">Welcome back, <?= htmlspecialchars($admin_name) ?></small>
    </div>
    <div class="profile-dropdown">
      <button id="profileBtn" class="btn btn-outline-success btn-sm rounded-pill">
        <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($admin_name) ?>
      </button>
      <div id="profileMenu" class="profile-menu">
        <a href="profile.php"><i class="bi bi-person me-2"></i> View Profile</a>
        <a href="../public/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
      </div>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3 mb-4">
    <div class="col-md-3"><div class="kpi-card"><small>Total Contributions</small><div class="kpi-value text-success">Ksh <?= ksh($totalContributions) ?></div></div></div>
    <div class="col-md-3"><div class="kpi-card"><small>Total Repayments</small><div class="kpi-value text-primary">Ksh <?= ksh($totalRepayments) ?></div></div></div>
    <div class="col-md-3"><div class="kpi-card"><small>Pending Repayments</small><div class="kpi-value text-warning"><?= $pendingRepayments ?></div></div></div>
    <div class="col-md-3"><div class="kpi-card"><small>Disbursed Loans</small><div class="kpi-value"><?= $totalDisbursedLoans ?></div></div></div>
  </div>

  <!-- Charts -->
  <div class="row g-3 mb-4">
    <div class="col-lg-7">
      <div class="card chart-card p-3">
        <h6>Monthly Contributions (12 months)</h6>
        <canvas id="chartContributions" height="120"></canvas>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card chart-card p-3">
        <h6>Repayment Status Summary</h6>
        <canvas id="chartRepayments" height="160"></canvas>
      </div>
    </div>
  </div>

  <!-- Notifications -->
  <div class="card mb-4">
    <div class="card-header bg-transparent"><h6><i class="bi bi-bell me-2"></i>Recent Notifications</h6></div>
    <div class="card-body">
      <ul class="list-group list-group-flush">
        <?php if (empty($notifications)): ?>
          <li class="list-group-item text-muted text-center py-3">No notifications available</li>
        <?php else: foreach ($notifications as $n): ?>
          <li class="list-group-item">
            <strong><?= htmlspecialchars($n['title'] ?? 'Notification') ?></strong><br>
            <small class="text-muted"><?= htmlspecialchars($n['message'] ?? '') ?></small><br>
            <small class="text-secondary"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></small>
          </li>
        <?php endforeach; endif; ?>
      </ul>
    </div>
  </div>

  <!-- Recent Activity -->
  <div class="card">
    <div class="card-header bg-transparent"><h6><i class="bi bi-clock-history me-2"></i>Recent Activity</h6></div>
    <div class="card-body">
      <ul class="list-group list-group-flush">
        <?php if (empty($recent_activity)): ?>
          <li class="list-group-item text-muted text-center py-3">No recent activity recorded</li>
        <?php else: foreach ($recent_activity as $act): ?>
          <li class="list-group-item">
            <div class="small text-muted"><?= date('d M Y, H:i', strtotime($act['created_at'])) ?></div>
            <div><?= htmlspecialchars($act['action']) ?></div>
          </li>
        <?php endforeach; endif; ?>
      </ul>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.getElementById('profileBtn').addEventListener('click', () => {
  document.getElementById('profileMenu').style.display =
    document.getElementById('profileMenu').style.display === 'block' ? 'none' : 'block';
});

const contribLabels = <?= json_encode($labels) ?>;
const contribData = <?= json_encode($contrib_values) ?>;
const repayStatus = {
  labels: ['Pending', 'Confirmed', 'Rejected'],
  datasets: [{ data: [<?= $repayment_status['pending'] ?>, <?= $repayment_status['confirmed'] ?>, <?= $repayment_status['rejected'] ?>], backgroundColor: ['#f59e0b','#16a34a','#dc2626'] }]
};

new Chart(document.getElementById('chartContributions'), {
  type: 'line',
  data: { labels: contribLabels, datasets: [{ label: 'Contributions', data: contribData, fill:true, backgroundColor:'rgba(22,163,74,0.1)', borderColor:'#16a34a' }] },
  options: { plugins: { legend: { display:false } } }
});

new Chart(document.getElementById('chartRepayments'), {
  type: 'pie', data: repayStatus, options: { plugins: { legend: { position:'bottom' } } }
});
</script>
</body>
</html>