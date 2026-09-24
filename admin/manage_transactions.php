<?php
session_start();
include('../config/db_connect.php');

$type_filter = $_GET['type'] ?? '';
$date_filter = $_GET['date'] ?? '';

$query = "SELECT transactions.*, members.full_name 
          FROM transactions 
          INNER JOIN members ON transactions.member_id = members.id 
          WHERE 1=1";

if ($type_filter != '') $query .= " AND transactions.transaction_type = '$type_filter'";
if ($date_filter != '') $query .= " AND DATE(transactions.transaction_date) = '$date_filter'";

$query .= " ORDER BY transactions.id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Transactions</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container mt-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="text-primary fw-bold">Manage Transactions</h3>
    <a href="../admin_dashboard.php" class="btn btn-primary rounded-pill px-4">
      <i class="fa-solid fa-arrow-left me-2"></i> Back to Dashboard
    </a>
  </div>

  <form method="GET" class="row mb-3">
    <div class="col-md-4">
      <select name="type" class="form-select">
        <option value="">All Types</option>
        <option value="Deposit" <?= $type_filter=='Deposit'?'selected':'' ?>>Deposit</option>
        <option value="Withdrawal" <?= $type_filter=='Withdrawal'?'selected':'' ?>>Withdrawal</option>
      </select>
    </div>
    <div class="col-md-4">
      <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
    </div>
    <div class="col-md-4">
      <button class="btn btn-success w-100"><i class="fa-solid fa-filter me-2"></i> Filter</button>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="card-body">
      <table class="table table-bordered table-striped text-center">
        <thead class="table-primary">
          <tr>
            <th>ID</th>
            <th>Member</th>
            <th>Type</th>
            <th>Amount (KSh)</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['full_name']) ?></td>
              <td><?= htmlspecialchars($row['transaction_type']) ?></td>
              <td><?= number_format($row['amount'], 2) ?></td>
              <td><?= htmlspecialchars($row['transaction_date']) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</body>
</html>
