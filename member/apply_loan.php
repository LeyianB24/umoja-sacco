<?php
session_start();
include('../config/db_connect.php');

// Redirect if not logged in
if (!isset($_SESSION['member_id'])) {
    header("Location: ../login.php");
    exit;
}

$member_id = $_SESSION['member_id'];
$success_message = $error_message = "";

// Handle loan application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = trim($_POST['amount']);
    $default_interest_rate = 10; // Default interest rate (you can change this)

    if (empty($amount)) {
        $error_message = "Please enter a loan amount.";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error_message = "Invalid loan amount.";
    } else {
        $sql = "INSERT INTO loans (member_id, amount, interest_rate, status, date_applied)
                VALUES (?, ?, ?, 'Pending', NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("idd", $member_id, $amount, $default_interest_rate);

        if ($stmt->execute()) {
            // Notify admin
            $notify_sql = "INSERT INTO notifications (member_id, message, is_read, date_sent)
                           VALUES (?, 'A new loan application has been submitted.', 0, NOW())";
            $notify_stmt = $conn->prepare($notify_sql);
            $notify_stmt->bind_param("i", $member_id);
            $notify_stmt->execute();

            $success_message = "✅ Loan application submitted successfully! Await approval.";
        } else {
            $error_message = "❌ Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Apply for Loan - USMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #e0f2fe 0%, #ffffff 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Poppins', sans-serif;
    }
    .card {
      border: none;
      border-radius: 20px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.1);
      overflow: hidden;
      width: 100%;
      max-width: 450px;
    }
    .card-header {
      background: linear-gradient(90deg, #1d4ed8, #3b82f6);
      font-weight: 600;
      letter-spacing: 0.5px;
    }
    .btn-dashboard {
      background: #fff;
      color: #1d4ed8;
      font-weight: 500;
      transition: 0.3s;
    }
    .btn-dashboard:hover {
      background: #e0f2fe;
      color: #1e40af;
    }
    .alert {
      border-radius: 10px;
      animation: fadeIn 0.6s ease-in-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="card shadow-lg mx-auto">
      <div class="card-header text-white d-flex justify-content-between align-items-center p-3">
        <h5 class="mb-0"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Apply for a Loan</h5>
        <a href="dashboard.php" class="btn btn-dashboard btn-sm rounded-pill">
          <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
      </div>

      <div class="card-body p-4">
        <?php if (!empty($success_message)): ?>
          <div class="alert alert-success text-center fw-semibold"><?= $success_message ?></div>
        <?php elseif (!empty($error_message)): ?>
          <div class="alert alert-danger text-center fw-semibold"><?= $error_message ?></div>
        <?php endif; ?>

        <form method="POST" action="">
          <div class="mb-4">
            <label class="form-label fw-semibold">
              <i class="fa-solid fa-money-bill-wave me-2 text-primary"></i> Loan Amount (Ksh)
            </label>
            <input type="number" name="amount" class="form-control" placeholder="Enter loan amount" required min="1">
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold">
              <i class="fa-solid fa-percent me-2 text-primary"></i> Interest Rate (%)
            </label>
            <input type="text" class="form-control" value="10%" disabled>
            <small class="text-muted">Default interest rate applied automatically</small>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-success rounded-pill py-2 fw-semibold shadow-sm">
              <i class="fa-solid fa-paper-plane me-2"></i> Submit Application
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
