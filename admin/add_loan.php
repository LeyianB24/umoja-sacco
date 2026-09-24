<?php
session_start();
include('../config/db_connect.php');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $member_id = $_POST['member_id'];
  $amount = $_POST['amount'];
  $interest_rate = $_POST['interest_rate'];
  $status = 'Pending';
  $date_applied = date('Y-m-d');

  $stmt = $conn->prepare("INSERT INTO loans (member_id, amount, interest_rate, status, date_applied) VALUES (?, ?, ?, ?, ?)");
  $stmt->bind_param("iddss", $member_id, $amount, $interest_rate, $status, $date_applied);

  if ($stmt->execute()) {
    $success = "Loan successfully recorded!";
  } else {
    $error = "Error: " . $conn->error;
  }
}

// Fetch members for dropdown
$members = $conn->query("SELECT id, full_name FROM members ORDER BY full_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add New Loan</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container mt-5">
  <!-- Header & Back Button -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-success fw-bold"><i class="fa-solid fa-plus-circle me-2"></i> Add New Loan</h3>
    <a href="manage_loans.php" class="btn btn-outline-primary rounded-pill shadow-sm">
      <i class="fa-solid fa-arrow-left me-2"></i> Back to Loans
    </a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
      <?php elseif (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="mb-3">
          <label for="member_id" class="form-label fw-bold">Select Member</label>
          <select name="member_id" id="member_id" class="form-select" required>
            <option value="">-- Choose Member --</option>
            <?php while($row = $members->fetch_assoc()): ?>
              <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['full_name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="mb-3">
          <label for="amount" class="form-label fw-bold">Loan Amount (Ksh)</label>
          <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
        </div>

        <div class="mb-3">
          <label for="interest_rate" class="form-label fw-bold">Interest Rate (%)</label>
          <input type="number" step="0.01" name="interest_rate" id="interest_rate" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-success px-4 rounded-pill">
          <i class="fa-solid fa-save me-2"></i> Save Loan
        </button>
      </form>
    </div>
  </div>
</div>

</body>
</html>
