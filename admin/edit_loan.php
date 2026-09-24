<?php
session_start();
include('../config/db_connect.php');

// ✅ Check if loan ID is provided
if (!isset($_GET['id'])) {
  die("Loan ID not provided.");
}

$loan_id = $_GET['id'];

// ✅ Fetch loan details
$query = "SELECT * FROM loans WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
  die("Loan not found.");
}

$loan = $result->fetch_assoc();

// ✅ Handle form submission
if (isset($_POST['update'])) {
  $member_id = $_POST['member_id'];
  $amount = $_POST['amount'];
  $interest_rate = $_POST['interest_rate'];
  $status = $_POST['status'];
  $date_applied = $_POST['date_applied'];

  $update_query = "UPDATE loans SET member_id=?, amount=?, interest_rate=?, status=?, date_applied=? WHERE id=?";
  $update_stmt = $conn->prepare($update_query);
  $update_stmt->bind_param("idsssi", $member_id, $amount, $interest_rate, $status, $date_applied, $loan_id);

  if ($update_stmt->execute()) {
    echo "<script>alert('Loan updated successfully!'); window.location='manage_loans.php';</script>";
  } else {
    echo "<script>alert('Error updating loan.');</script>";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Loan</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container mt-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-primary fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> Edit Loan</h3>
    <a href="manage_loans.php" class="btn btn-outline-primary rounded-pill shadow-sm">
      <i class="fa-solid fa-arrow-left me-2"></i> Back to Loans
    </a>
  </div>

  <div class="card shadow-sm p-4">
    <form method="POST">
      <div class="mb-3">
        <label class="form-label fw-semibold">Member ID</label>
        <input type="number" name="member_id" class="form-control" value="<?= htmlspecialchars($loan['member_id']) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Amount</label>
        <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($loan['amount']) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Interest Rate (%)</label>
        <input type="text" name="interest_rate" class="form-control" value="<?= htmlspecialchars($loan['interest_rate']) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Status</label>
        <select name="status" class="form-select" required>
          <option value="Pending" <?= $loan['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
          <option value="Approved" <?= $loan['status'] == 'Approved' ? 'selected' : '' ?>>Approved</option>
          <option value="Rejected" <?= $loan['status'] == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Date Applied</label>
        <input type="date" name="date_applied" class="form-control" value="<?= htmlspecialchars($loan['date_applied']) ?>" required>
      </div>

      <button type="submit" name="update" class="btn btn-success px-4 rounded-pill">
        <i class="fa-solid fa-save me-2"></i> Update Loan
      </button>
    </form>
  </div>
</div>

</body>
</html>
