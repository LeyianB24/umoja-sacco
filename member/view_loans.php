<?php
session_start();
include('../config/db_connect.php');

// ✅ Redirect if not logged in
if (!isset($_SESSION['member_id'])) {
    header("Location: ../login.php");
    exit;
}

$member_id = $_SESSION['member_id'];
$member_name = $_SESSION['member_name'];

// ✅ Fetch all loans for the logged-in member
$query = "SELECT * FROM loans WHERE member_id = ? ORDER BY date_applied DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Loan Applications - USMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #eef2ff, #dbeafe);
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
    }

    .dashboard-container {
      max-width: 1000px;
      margin: 60px auto;
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.08);
      padding: 40px;
      transition: 0.3s;
    }

    h3 {
      color: #1e3a8a;
      font-weight: 700;
      letter-spacing: 0.5px;
    }

    .btn-rounded {
      border-radius: 50px;
      transition: 0.3s;
    }

    .btn-rounded:hover {
      transform: scale(1.05);
    }

    .table thead {
      background-color: #1e3a8a;
      color: #fff;
    }

    .table-hover tbody tr:hover {
      background-color: #f3f4f6;
      transition: 0.2s;
    }

    .badge {
      font-size: 0.9rem;
      padding: 0.6em 1em;
      border-radius: 30px;
      text-transform: capitalize;
    }

    .no-loan {
      background-color: #eff6ff;
      border-left: 5px solid #3b82f6;
      color: #1e40af;
      border-radius: 12px;
    }

    .footer-text {
      text-align: center;
      font-size: 0.9rem;
      color: #6b7280;
      margin-top: 30px;
    }

    .fade-in {
      animation: fadeIn 0.8s ease-in-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

<div class="dashboard-container fade-in">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3>
      <i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i> My Loan Applications
    </h3>
    <a href="dashboard.php" class="btn btn-outline-primary btn-rounded shadow-sm">
      <i class="fa-solid fa-arrow-left me-2"></i> Back to Dashboard
    </a>
  </div>

  <!-- Loan Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <?php if ($result->num_rows > 0): ?>
        <div class="table-responsive">
          <table class="table table-bordered table-hover text-center align-middle mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Amount (KES)</th>
                <th>Interest Rate (%)</th>
                <th>Status</th>
                <th>Date Applied</th>
              </tr>
            </thead>
            <tbody>
              <?php 
                $count = 1;
                while ($row = $result->fetch_assoc()):
              ?>
                <tr>
                  <td><?= $count++; ?></td>
                  <td><?= number_format($row['amount'], 2); ?></td>
                  <td><?= htmlspecialchars($row['interest_rate']); ?></td>
                  <td>
                    <span class="badge 
                      <?= $row['status'] == 'Approved' ? 'bg-success' : 
                          ($row['status'] == 'Rejected' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                      <i class="fa-solid 
                        <?= $row['status'] == 'Approved' ? 'fa-circle-check' : 
                            ($row['status'] == 'Rejected' ? 'fa-circle-xmark' : 'fa-hourglass-half'); ?> me-1"></i>
                      <?= htmlspecialchars($row['status']); ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($row['date_applied']); ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="alert no-loan text-center p-4">
          <i class="fa-solid fa-circle-info me-2"></i> You haven’t applied for any loans yet.
          <br>
          <a href="apply_loan.php" class="btn btn-primary btn-sm mt-3">
            <i class="fa-solid fa-plus me-1"></i> Apply for a Loan
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer-text">
    <p>© <?= date('Y'); ?> <strong>USMS</strong> | Member Loan Applications</p>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
