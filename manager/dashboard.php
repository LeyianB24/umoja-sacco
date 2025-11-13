<?php
// usms/manager/dashboard.php
// Manager Dashboard — Loan & Member Overview

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

// Ensure only manager can access
require_manager();

$manager_name = $_SESSION['username'] ?? 'manager';

// Database connection
$db = $conn ?? null;
if (!$db) die('Database connection not available.');

// ---------- STATS ----------
$totalMembers = (int) ($db->query("SELECT COUNT(*) AS c FROM members")->fetch_assoc()['c'] ?? 0);
$totalLoans = (int) ($db->query("SELECT COUNT(*) AS c FROM loans")->fetch_assoc()['c'] ?? 0);
$pendingLoans = (int) ($db->query("SELECT COUNT(*) AS c FROM loans WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0);
$approvedLoans = (int) ($db->query("SELECT COUNT(*) AS c FROM loans WHERE status = 'approved'")->fetch_assoc()['c'] ?? 0);
$disbursedLoans = (int) ($db->query("SELECT COUNT(*) AS c FROM loans WHERE status = 'disbursed'")->fetch_assoc()['c'] ?? 0);

// ---------- Loan amounts ----------
$totalLoanAmount = (float) ($db->query("SELECT IFNULL(SUM(amount),0) AS total FROM loans")->fetch_assoc()['total'] ?? 0);
$totalDisbursed = (float) ($db->query("SELECT IFNULL(SUM(amount),0) AS total FROM loans WHERE status = 'disbursed'")->fetch_assoc()['total'] ?? 0);
$totalPending = (float) ($db->query("SELECT IFNULL(SUM(amount),0) AS total FROM loans WHERE status = 'pending'")->fetch_assoc()['total'] ?? 0);

function ksh($n) { return number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Manager Dashboard — <?= htmlspecialchars(SITE_NAME) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="<?= htmlspecialchars(ASSET_BASE) ?>/css/style.css?v=1.1">

  <style>
    :root {
      --sacco-green: <?= $theme['primary'] ?? '#16a34a' ?>;
      --sacco-dark: <?= $theme['primary_dark'] ?? '#0b6623' ?>;
      --sacco-gold: <?= $theme['accent'] ?? '#f4c430' ?>;
    }
    body { background:#f6f8f9; }
    .sidebar { width:260px; background: linear-gradient(180deg,var(--sacco-dark), #0a3d1d); color:#fff; min-height:100vh; position:fixed; transition:all .28s ease; }
    .sidebar .brand { padding:18px; display:flex; gap:12px; align-items:center; }
    .sidebar nav a { color: rgba(255,255,255,0.9); padding:12px 18px; display:block; text-decoration:none; transition: .15s; }
    .sidebar nav a:hover, .sidebar nav a.active { background: rgba(255,255,255,0.06); }
    .main { margin-left:260px; padding:28px; }
    .kpi-card { border-radius:12px; box-shadow:0 8px 26px rgba(11,54,2,0.04); padding:18px; background:#fff; }
    .kpi-value { font-size:1.5rem; font-weight:700; color:var(--sacco-dark); }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="brand">
    <img src="<?= htmlspecialchars(ASSET_BASE) ?>/images/people_logo.png" alt="logo" width="56" height="56" style="border-radius:50%;border:3px solid var(--sacco-gold)">
    <div>
      <div style="font-weight:700;"><?= htmlspecialchars(SITE_NAME) ?></div>
      <small style="color:rgba(255,255,255,0.8)">Manager Portal</small>
    </div>
  </div>

  <nav class="mt-3">
    <a href="dashboard.php" class="active"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a href="manage_loans.php"><i class="bi bi-cash-stack me-2"></i> Manage Loans</a>
    <a href="members_list.php"><i class="bi bi-people me-2"></i> Members</a>
    <a href="reports.php"><i class="bi bi-graph-up me-2"></i> Reports</a>
    <a class="nav-link" href="<?= BASE_URL ?>/manager/notifications.php"><i class="bi bi-bell me-2"></i> Notifications
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
      <h4 class="mb-0" style="color:var(--sacco-dark)">Manager Dashboard</h4>
      <small class="text-muted">Welcome back, <?= htmlspecialchars($manager_name) ?></small>
    </div>
    <div>
      <strong><?= date('d M Y') ?></strong>
    </div>
  </div>

  <!-- KPI -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="kpi-card"><small>Total Members</small><div class="kpi-value"><?= number_format($totalMembers) ?></div></div>
    </div>
    <div class="col-md-3">
      <div class="kpi-card"><small>Total Loans</small><div class="kpi-value"><?= number_format($totalLoans) ?></div></div>
    </div>
    <div class="col-md-3">
      <div class="kpi-card"><small>Pending Loans</small><div class="kpi-value text-warning"><?= number_format($pendingLoans) ?></div></div>
    </div>
    <div class="col-md-3">
      <div class="kpi-card"><small>Disbursed Loans</small><div class="kpi-value text-success"><?= number_format($disbursedLoans) ?></div></div>
    </div>
  </div>

  <!-- Loan Amounts Summary -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="kpi-card"><small>Total Loan Amount</small><div class="kpi-value">Ksh <?= ksh($totalLoanAmount) ?></div></div>
    </div>
    <div class="col-md-4">
      <div class="kpi-card"><small>Pending Loan Amount</small><div class="kpi-value text-warning">Ksh <?= ksh($totalPending) ?></div></div>
    </div>
    <div class="col-md-4">
      <div class="kpi-card"><small>Disbursed Amount</small><div class="kpi-value text-success">Ksh <?= ksh($totalDisbursed) ?></div></div>
    </div>
  </div>

  <!-- Loan Status Chart -->
  <div class="card chart-card p-3 mb-4">
    <h6>Loan Distribution</h6>
    <canvas id="loanChart" height="140"></canvas>
  </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const ctx = document.getElementById('loanChart');
new Chart(ctx, {
  type: 'doughnut',
  data: {
    labels: ['Pending', 'Approved', 'Disbursed'],
    datasets: [{
      data: [<?= $pendingLoans ?>, <?= $approvedLoans ?>, <?= $disbursedLoans ?>],
      backgroundColor: ['#f59e0b','#16a34a','#0ea5e9']
    }]
  },
  options: { plugins: { legend: { position:'bottom' } } }
});
</script>
</body>
</html>