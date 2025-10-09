<?php
session_start();
include("../config/db_connect.php");

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: ../login.php");
  exit;
}

// Fetch members
$members_sql = "SELECT id, full_name, email, phone, join_date FROM members ORDER BY join_date DESC";
$members_result = $conn->query($members_sql);
$member_count = $members_result->num_rows;

// Fetch contributions
$contributions_sql = "SELECT c.id, m.full_name, c.amount, c.contribution_date 
                      FROM contributions c
                      JOIN members m ON c.member_id = m.id
                      ORDER BY c.contribution_date DESC LIMIT 6";
$contributions_result = $conn->query($contributions_sql);

// Total contributions
$total_sql = "SELECT SUM(amount) AS total_amount FROM contributions";
$total_result = $conn->query($total_sql);
$total_amount = $total_result->fetch_assoc()['total_amount'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - Umoja Sacco</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

  <style>
    body {
      background-color: #f0f4f8;
      font-family: 'Poppins', sans-serif;
    }
    .navbar {
      background: linear-gradient(135deg, #14532d, #22c55e);
      box-shadow: 0 3px 12px rgba(0,0,0,0.15);
    }
    .navbar-brand {
      font-weight: 700;
      color: #fff !important;
      display: flex;
      align-items: center;
    }
    .navbar-brand i {
      margin-right: 8px;
      color: #dcfce7;
    }
    .nav-link, .dropdown-toggle {
      color: #fff !important;
      transition: 0.3s;
    }
    .nav-link:hover, .dropdown-toggle:hover {
      color: #dcfce7 !important;
    }
    .card {
      border: none;
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 4px 10px rgba(0,0,0,0.05);
      transition: all 0.3s ease;
    }
    .card:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 14px rgba(0,0,0,0.08);
    }
    .stat-card {
      background: linear-gradient(135deg, #22c55e, #16a34a);
      color: white;
      border: none;
    }
    .stat-card i {
      font-size: 32px;
      margin-bottom: 10px;
    }
    h4 {
      color: #166534;
      font-weight: 600;
    }
    table thead {
      background-color: #dcfce7;
    }
    footer {
      text-align: center;
      margin-top: 50px;
      color: #6b7280;
      font-size: 0.9rem;
    }
    .btn-primary, .btn-success {
      border-radius: 25px;
      font-weight: 500;
    }
    .btn-primary {
      background-color: #14532d;
      border: none;
    }
    .btn-primary:hover {
      background-color: #16a34a;
    }
    @keyframes fadeUp {
      from {opacity: 0; transform: translateY(15px);}
      to {opacity: 1; transform: translateY(0);}
    }
    .animate {
      animation: fadeUp 0.7s ease forwards;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark py-3">
  <div class="container-fluid px-4">
    <a class="navbar-brand" href="#">
      <i class="fa-solid fa-seedling"></i> Umoja Sacco Admin
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav align-items-center">
        <li class="nav-item me-3"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item me-3"><a class="nav-link" href="view_members.php">Members</a></li>
        <li class="nav-item me-3"><a class="nav-link" href="view_contributions.php">Contributions</a></li>
        <li><a href="manage_loans.php" class="btn btn-primary"><i class="fa-solid fa-money-check-dollar"></i> Manage Loans</a></li>
        <li class="nav-item dropdown ms-3">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="fa-solid fa-user-circle me-1"></i><?php echo $_SESSION['admin_name']; ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><a class="dropdown-item" href="../logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-5 animate">
  <div class="text-center mb-4">
    <h2 class="fw-bold text-success">Welcome, <?php echo $_SESSION['admin_name']; ?> 👋</h2>
    <p class="text-muted">Manage your SACCO members, loans, and contributions effortlessly.</p>
  </div>

  <!-- Quick Stats -->
  <div class="row text-center mb-5">
    <div class="col-md-4 mb-3">
      <div class="card stat-card py-4">
        <i class="fa-solid fa-users"></i>
        <h5>Total Members</h5>
        <h2><?php echo $member_count; ?></h2>
      </div>
    </div>
    <div class="col-md-4 mb-3">
      <div class="card stat-card py-4">
        <i class="fa-solid fa-hand-holding-dollar"></i>
        <h5>Total Contributions</h5>
        <h2>KES <?php echo number_format($total_amount, 2); ?></h2>
      </div>
    </div>
    <div class="col-md-4 mb-3">
      <div class="card stat-card py-4">
        <i class="fa-solid fa-bell"></i>
        <h5>Recent Updates</h5>
        <h2><i class="fa-regular fa-clock"></i></h2>
      </div>
    </div>
  </div>

  <!-- Members Table -->
  <div class="card mb-5">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="fa-solid fa-users me-2"></i>Recent Members</h4>
        <a href="add_member.php" class="btn btn-success btn-sm"><i class="fa-solid fa-user-plus me-1"></i> Add Member</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Full Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Join Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($members_result->num_rows > 0): ?>
              <?php while ($row = $members_result->fetch_assoc()): ?>
              <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                <td><?php echo $row['join_date']; ?></td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="5" class="text-center text-muted">No members found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Contributions Table -->
  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="fa-solid fa-hand-holding-dollar me-2"></i>Recent Contributions</h4>
        <div>
          <a href="view_contributions.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-eye me-1"></i> View All</a>
          <a href="add_contribution.php" class="btn btn-success btn-sm"><i class="fa-solid fa-plus me-1"></i> Record New</a>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Member</th>
              <th>Amount (KES)</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($contributions_result->num_rows > 0): ?>
              <?php while ($row = $contributions_result->fetch_assoc()): ?>
              <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo number_format($row['amount'], 2); ?></td>
                <td><?php echo $row['contribution_date']; ?></td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="4" class="text-center text-muted">No recent contributions found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<footer class="mt-5">
  <p>© <?php echo date('Y'); ?> Umoja Sacco Management System | Designed by <strong>Bezalel Leyian</strong></p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
