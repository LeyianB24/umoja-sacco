<?php
session_start();
include("../config/db_connect.php");

// Redirect if not logged in as admin
if (!isset($_SESSION['admin_id'])) {
  header("Location: ../login.php");
  exit;
}

// Filters
$filter_from = $_GET['from'] ?? '';
$filter_to = $_GET['to'] ?? '';
$filter_member = $_GET['member'] ?? '';

// Build query
$query = "
  SELECT c.id, m.full_name, c.amount, c.contribution_date
  FROM contributions c
  JOIN members m ON c.member_id = m.id
  WHERE 1
";

if (!empty($filter_from) && !empty($filter_to)) {
  $query .= " AND DATE(c.contribution_date) BETWEEN '$filter_from' AND '$filter_to'";
}
if (!empty($filter_member)) {
  $query .= " AND m.id = '$filter_member'";
}
$query .= " ORDER BY c.contribution_date DESC";
$result = $conn->query($query);

// Total contributions
$total_query = "SELECT SUM(amount) AS total_amount FROM contributions WHERE 1";
if (!empty($filter_from) && !empty($filter_to)) {
  $total_query .= " AND DATE(contribution_date) BETWEEN '$filter_from' AND '$filter_to'";
}
$total_result = $conn->query($total_query);
$total_amount = $total_result->fetch_assoc()['total_amount'] ?? 0;

// Fetch members for dropdown
$members = $conn->query("SELECT id, full_name FROM members ORDER BY full_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Contributions - Umoja Sacco Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f3f8f4;
      font-family: 'Segoe UI', sans-serif;
    }
    .navbar {
      background: linear-gradient(90deg, #016e38, #03a84e);
    }
    .navbar-brand {
      font-weight: 700;
      letter-spacing: 0.5px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .navbar-brand img {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: white;
      padding: 4px;
    }
    .card {
      border-radius: 14px;
      border: none;
      box-shadow: 0 4px 15px rgba(0,0,0,0.06);
      background-color: #fff;
    }
    .card-header {
      background: linear-gradient(90deg, #03a84e, #016e38);
      color: white;
      font-weight: 600;
    }
    .filter-section label {
      font-weight: 600;
      color: #026a37;
    }
    .btn {
      border-radius: 25px;
      font-weight: 500;
    }
    .btn-custom {
      min-width: 160px;
    }
    .table thead {
      background-color: #e9f7ec;
      color: #056b2b;
    }
    .table tbody tr:hover {
      background-color: #f0faf2;
      transform: scale(1.01);
      transition: 0.15s ease-in-out;
    }
    .back-btn {
      background: #026a37;
      border: none;
      color: white;
      padding: 8px 20px;
      border-radius: 25px;
      transition: 0.3s;
    }
    .back-btn:hover {
      background: #03a84e;
    }
    .alert-success {
      background: #d1f0da;
      border: 1px solid #03a84e;
      color: #026a37;
    }
    footer {
      text-align: center;
      margin-top: 50px;
      color: #666;
      font-size: 0.9rem;
    }
    @media print {
      body { background: white; color: #000; }
      .navbar, .btn, form, .back-btn { display: none !important; }
      table { border-collapse: collapse; width: 100%; font-size: 13px; }
      th, td { border: 1px solid #ccc; padding: 8px; }
      th { background-color: #f2f2f2; }
      .print-header { text-align: center; margin-bottom: 20px; }
      .print-footer { text-align: center; margin-top: 30px; font-size: 12px; border-top: 1px solid #ccc; padding-top: 5px; }
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark shadow-sm">
  <div class="container-fluid px-4">
    <a class="navbar-brand" href="dashboard.php">
      <img src="https://cdn-icons-png.flaticon.com/512/2922/2922510.png" alt="Umoja Logo">
      Umoja Sacco Admin
    </a>
    <div class="collapse navbar-collapse justify-content-end">
      <ul class="navbar-nav align-items-center">
        <li class="nav-item me-3"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item me-3"><a class="nav-link" href="view_members.php">Members</a></li>
        <li class="nav-item me-3"><a class="nav-link active" href="view_contributions.php">Contributions</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="fa-solid fa-user-circle me-1"></i><?= $_SESSION['admin_name']; ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="../logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Page Content -->
<div class="container mt-4">
  <div class="d-flex justify-content-end mb-3">
    <a href="dashboard.php" class="back-btn shadow-sm"><i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard</a>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="fa-solid fa-coins me-2"></i> Member Contributions</h5>
    </div>

    <div class="card-body">

      <!-- Filter Section -->
      <form class="row g-3 mb-4 filter-section" method="GET">
        <div class="col-md-3">
          <label class="form-label">From:</label>
          <input type="date" name="from" class="form-control" value="<?= $filter_from; ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">To:</label>
          <input type="date" name="to" class="form-control" value="<?= $filter_to; ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Member:</label>
          <select name="member" class="form-select">
            <option value="">All Members</option>
            <?php while ($m = $members->fetch_assoc()): ?>
              <option value="<?= $m['id']; ?>" <?= ($filter_member == $m['id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($m['full_name']); ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-success w-50 me-2" type="submit"><i class="fa-solid fa-filter me-1"></i> Filter</button>
          <a href="view_contributions.php" class="btn btn-outline-secondary w-50"><i class="fa-solid fa-rotate-right me-1"></i> Reset</a>
        </div>
      </form>

      <!-- Export Buttons -->
      <div class="d-flex justify-content-end gap-2 mb-3">
        <a href="export_contributions.php?<?= http_build_query($_GET) ?>" class="btn btn-outline-success btn-custom">
          <i class="fa-solid fa-file-excel"></i> Export Excel
        </a>
        <a href="export_contributions_pdf.php?from=<?= $filter_from; ?>&to=<?= $filter_to; ?>&member=<?= $filter_member; ?>" class="btn btn-outline-danger btn-custom">
          <i class="fa-solid fa-file-pdf"></i> Export PDF
        </a>
        <button onclick="window.print()" class="btn btn-outline-primary btn-custom">
          <i class="fa-solid fa-print"></i> Print Report
        </button>
      </div>

      <!-- Table -->
      <div class="table-responsive">
        <div class="d-none d-print-block print-header">
          <h2>Umoja Sacco Management System</h2>
          <h4>Contributions Report</h4>
          <p><strong>Generated on:</strong> <?= date('d M Y, h:i A'); ?></p>
          <?php if ($filter_from && $filter_to): ?>
            <p><strong>Period:</strong> <?= $filter_from . ' to ' . $filter_to; ?></p>
          <?php endif; ?>
        </div>

        <table class="table table-hover table-bordered align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Member Name</th>
              <th>Amount (KSh)</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result->num_rows > 0): ?>
              <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><?= $i++; ?></td>
                  <td><?= htmlspecialchars($row['full_name']); ?></td>
                  <td><?= number_format($row['amount'], 2); ?></td>
                  <td><?= $row['contribution_date']; ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="4" class="text-center text-muted">No contributions found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="alert alert-success text-center fw-bold mt-3">
        Total Contributions: KSh <?= number_format($total_amount, 2); ?>
      </div>

      <div class="d-none d-print-block print-footer">
        <p>Umoja Sacco © <?= date('Y'); ?> | Generated by Admin</p>
      </div>

    </div>
  </div>
</div>

<footer>
  <p>© <?= date('Y'); ?> Umoja Sacco | Designed by Bezalel Leyian</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
