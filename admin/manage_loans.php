<?php
session_start();
include('../config/db_connect.php');

// ✅ Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: ../login.php");
  exit;
}

// ✅ Fetch all loans and member names
$query = "
  SELECT l.id, l.member_id, l.amount, l.interest_rate, l.status, l.date_applied, 
         m.full_name 
  FROM loans l
  JOIN members m ON l.member_id = m.id
  ORDER BY l.date_applied DESC
";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Loans - Umoja Sacco Admin</title>
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
    .badge {
      font-size: 0.85rem;
      padding: 6px 10px;
      border-radius: 8px;
    }
    .btn {
      border-radius: 25px;
      transition: all 0.2s ease;
    }
    .btn:hover {
      transform: translateY(-1px);
    }
    .dataTables_wrapper .dataTables_filter input {
      border-radius: 20px;
      padding: 5px 12px;
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
      <i class="fa-solid fa-hand-holding-dollar me-2"></i>Umoja Sacco Admin
    </a>
    <div class="collapse navbar-collapse justify-content-end">
      <ul class="navbar-nav align-items-center">
        <li class="nav-item me-3"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item me-3"><a class="nav-link active" href="#">Loans</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="fa-solid fa-user-circle me-1"></i> <?php echo $_SESSION['admin_name']; ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="../logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- ✅ Main Container -->
<div class="container mt-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-success"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Manage Loans</h3>
    <a href="dashboard.php" class="btn btn-outline-success shadow-sm">
      <i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard
    </a>
  </div>

  <div class="card">
    <div class="card-body">
      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($_GET['success']); ?></div>
      <?php endif; ?>

      <div class="table-responsive">
        <table id="loanTable" class="table table-hover table-bordered align-middle">
          <thead class="table-success">
            <tr>
              <th>#</th>
              <th>Member Name</th>
              <th>Loan Amount (KES)</th>
              <th>Interest Rate (%)</th>
              <th>Status</th>
              <th>Date Applied</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            if ($result->num_rows > 0):
              $count = 1;
              while ($row = $result->fetch_assoc()):
            ?>
            <tr>
              <td><?= $count++ ?></td>
              <td><?= htmlspecialchars($row['full_name']) ?></td>
              <td><?= number_format($row['amount'], 2) ?></td>
              <td><?= htmlspecialchars($row['interest_rate']) ?></td>
              <td>
                <span class="badge bg-<?=
                  ($row['status'] == 'Approved' ? 'success' :
                  ($row['status'] == 'Rejected' ? 'danger' : 'warning'))
                ?>">
                  <?= htmlspecialchars($row['status']); ?>
                </span>
              </td>
              <td><?= htmlspecialchars($row['date_applied']) ?></td>
              <td class="d-flex justify-content-center flex-wrap gap-1">
                <a href="view_loan.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info text-white">
                  <i class="fa-solid fa-eye"></i>
                </a>
                <a href="edit_loan.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">
                  <i class="fa-solid fa-pen"></i>
                </a>
                <a href="approve_loan.php?id=<?= $row['id']; ?>&action=approve" class="btn btn-sm btn-success"
                   onclick="return confirm('Approve this loan?');">
                  <i class="fa-solid fa-check"></i>
                </a>
                <a href="approve_loan.php?id=<?= $row['id']; ?>&action=reject" class="btn btn-sm btn-danger"
                   onclick="return confirm('Reject this loan?');">
                  <i class="fa-solid fa-xmark"></i>
                </a>
                <a href="delete_loan.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Are you sure you want to delete this loan?');">
                  <i class="fa-solid fa-trash"></i>
                </a>
              </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="7" class="text-center text-muted py-3">No loans found.</td></tr>
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
    $('#loanTable').DataTable({
      "pageLength": 10,
      "ordering": true,
      "columnDefs": [{ "orderable": false, "targets": 6 }],
      "language": {
        "search": "🔍 Search:",
        "lengthMenu": "Show _MENU_ entries"
      }
    });
  });
</script>

</body>
</html>
