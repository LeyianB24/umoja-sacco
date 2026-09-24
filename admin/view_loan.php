<?php
session_start();
include('../config/db_connect.php');

if (!isset($_GET['id'])) {
    header("Location: manage_loans.php");
    exit;
}

$loan_id = intval($_GET['id']);
$sql = "
  SELECT l.*, m.full_name, m.email 
  FROM loans l 
  JOIN members m ON l.member_id = m.id 
  WHERE l.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$result = $stmt->get_result();
$loan = $result->fetch_assoc();

if (!$loan) {
    header("Location: manage_loans.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Loan Details</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="text-primary mb-3">
        <i class="fa-solid fa-file-invoice-dollar me-2"></i> Loan Details
      </h4>
      <p><strong>Member:</strong> <?= htmlspecialchars($loan['full_name']); ?> (<?= htmlspecialchars($loan['email']); ?>)</p>
      <p><strong>Amount:</strong> KES <?= number_format($loan['amount'], 2); ?></p>
      <p><strong>Interest Rate:</strong> <?= htmlspecialchars($loan['interest_rate']); ?>%</p>
      <p><strong>Status:</strong> <span class="badge bg-<?= ($loan['status'] == 'Approved' ? 'success' : ($loan['status'] == 'Rejected' ? 'danger' : 'warning')) ?>"><?= htmlspecialchars($loan['status']); ?></span></p>
      <p><strong>Date Applied:</strong> <?= htmlspecialchars($loan['date_applied']); ?></p>
      <a href="manage_loans.php" class="btn btn-outline-primary rounded-pill">
        <i class="fa-solid fa-arrow-left me-2"></i> Back
      </a>
    </div>
  </div>
</div>
</body>
</html>
