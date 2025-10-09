<?php
session_start();
include('../config/db_connect.php');

// Redirect if not logged in as admin
if (!isset($_SESSION['admin_id'])) {
  header("Location: ../login.php");
  exit;
}

// Fetch all members
$query = "SELECT * FROM members ORDER BY id DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Members - Umoja Sacco</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      background-color: #f4f6f9;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }
    .navbar {
      background-color: #004085;
    }
    .navbar-brand, .nav-link, .navbar-text {
      color: #fff !important;
    }
    .card {
      border-radius: 12px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    .btn-custom {
      min-width: 150px;
    }
    .table thead {
      background-color: #004085;
      color: #fff;
    }
  </style>
</head>
<body>

<!-- ✅ Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="admin_dashboard.php">
      <i class="fa-solid fa-people-group me-2"></i> Umoja Sacco Admin
    </a>
    <div class="d-flex ms-auto align-items-center">
      <span class="navbar-text me-3">Welcome, <?php echo $_SESSION['admin_name']; ?></span>
      <a href="../logout.php" class="btn btn-outline-light btn-sm">
        <i class="fa-solid fa-right-from-bracket"></i> Logout
      </a>
    </div>
  </div>
</nav>

<!-- ✅ Page Content -->
<div class="container mt-4">
<div class="d-flex justify-content-end mb-3">
  <a href="dashboard.php" class="btn btn-primary px-4 shadow-sm rounded-pill">
  <i class="fa-solid fa-arrow-left me-2"></i> Back to Dashboard
</a>
</div>


  <!-- Members Management -->
  <div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="fa-solid fa-users me-2"></i> Manage Members</h5>
      <a href="add_member.php" class="btn btn-success btn-sm shadow-sm">
        <i class="fa-solid fa-user-plus me-1"></i> Add Member
      </a>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle text-center">
          <thead>
            <tr>
              <th>ID</th>
              <th>Full Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Date Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><?= $row['id'] ?></td>
                  <td><?= htmlspecialchars($row['full_name']) ?></td>
                  <td><?= htmlspecialchars($row['email']) ?></td>
                  <td><?= htmlspecialchars($row['phone']) ?></td>
                  <td><?= htmlspecialchars($row['date_joined']) ?></td>
                  <td>
                    <a href="edit_member.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning me-1">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </a>
                    <a href="delete_member.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger"
                       onclick="return confirm('Are you sure you want to delete this member?');">
                      <i class="fa-solid fa-trash"></i>
                    </a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-muted">No members found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</body>
</html>
