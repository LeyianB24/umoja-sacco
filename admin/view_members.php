<?php
session_start();
include("../config/db_connect.php");

// ✅ Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: ../login.php");
  exit;
}

// ✅ Delete member if requested
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $conn->query("DELETE FROM members WHERE id = $id");
  header("Location: view_members.php?msg=deleted");
  exit;
}

// ✅ Fetch all members
$result = $conn->query("SELECT * FROM members ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Members - Umoja Sacco Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">

  <style>
    body {
      background-color: #f5f7fa;
      font-family: 'Segoe UI', sans-serif;
    }
    .navbar {
      background: linear-gradient(90deg, #006400, #228B22);
    }
    .navbar-brand {
      font-weight: 700;
      color: white !important;
    }
    .navbar-nav .nav-link {
      color: white !important;
      font-weight: 500;
    }
    .card {
      border: none;
      border-radius: 14px;
      box-shadow: 0 4px 14px rgba(0,0,0,0.07);
      transition: 0.3s ease;
    }
    .card:hover {
      transform: translateY(-2px);
    }
    .table thead {
      background-color: #d1e7dd;
    }
    .btn {
      border-radius: 25px;
      transition: all 0.2s ease;
    }
    .btn:hover {
      transform: translateY(-1px);
    }
    .alert {
      border-radius: 10px;
    }
    .dataTables_wrapper .dataTables_filter input {
      border-radius: 20px;
      padding: 6px 12px;
      border: 1px solid #ccc;
    }
    .footer {
      margin-top: 40px;
      text-align: center;
      font-size: 0.9rem;
      color: #666;
    }
  </style>
</head>
<body>

<!-- ✅ Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark shadow-sm">
  <div class="container-fluid px-4">
    <a class="navbar-brand" href="dashboard.php">
      <i class="fa-solid fa-users me-2"></i>Umoja Sacco Admin
    </a>
    <div class="collapse navbar-collapse justify-content-end">
      <ul class="navbar-nav align-items-center">
        <li class="nav-item me-3"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item me-3"><a class="nav-link active" href="#">Members</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="fa-solid fa-user-circle me-1"></i> <?= $_SESSION['admin_name']; ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="../logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- ✅ Page Content -->
<div class="container mt-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-success">
      <i class="fa-solid fa-users me-2"></i>Manage Members
    </h3>
    <a href="dashboard.php" class="btn btn-outline-success shadow-sm">
      <i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard
    </a>
  </div>

  <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
    <div class="alert alert-danger text-center fw-semibold">Member deleted successfully.</div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="fa-solid fa-users me-2"></i>Member List</h5>
      <a href="add_member.php" class="btn btn-light btn-sm rounded-pill">
        <i class="fa-solid fa-plus me-1"></i> Add Member
      </a>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table id="membersTable" class="table table-hover table-bordered align-middle">
          <thead class="table-success">
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
                  <td><?= $row['id']; ?></td>
                  <td><?= htmlspecialchars($row['full_name']); ?></td>
                  <td><?= htmlspecialchars($row['email']); ?></td>
                  <td><?= htmlspecialchars($row['phone']); ?></td>
                  <td><?= htmlspecialchars($row['join_date']); ?></td>
                  <td class="d-flex justify-content-center flex-wrap gap-1">
                    <a href="edit_member.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">
                      <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="view_members.php?delete=<?= $row['id']; ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Are you sure you want to delete this member?');">
                      <i class="fa-solid fa-trash"></i>
                    </a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No members found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="footer mt-4">
    <p>Umoja Sacco © <?= date('Y'); ?> | Powered by Admin Dashboard</p>
  </div>
</div>

<!-- ✅ Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
  $(document).ready(function () {
    $('#membersTable').DataTable({
      "pageLength": 10,
      "ordering": true,
      "language": {
        "search": "🔍 Search:",
        "lengthMenu": "Show _MENU_ entries"
      }
    });
  });
</script>

</body>
</html>
