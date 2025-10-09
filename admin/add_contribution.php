<?php
session_start();
include("../config/db_connect.php");

// Redirect if admin not logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: ../login.php");
  exit;
}

$message = "";

// Handle form submission
if (isset($_POST['save'])) {
  $member_id = $_POST['member_id'];
  $amount = $_POST['amount'];

  if (!empty($member_id) && !empty($amount) && is_numeric($amount)) {
    $stmt = $conn->prepare("INSERT INTO contributions (member_id, amount, contribution_date) VALUES (?, ?, NOW())");
    $stmt->bind_param("id", $member_id, $amount);
    if ($stmt->execute()) {
      $message = "<div class='alert alert-success text-center'>Contribution recorded successfully!</div>";
    } else {
      $message = "<div class='alert alert-danger text-center'>Error saving contribution.</div>";
    }
  } else {
    $message = "<div class='alert alert-warning text-center'>Please select a member and enter a valid amount.</div>";
  }
}

// Fetch members for dropdown
$members = $conn->query("SELECT id, full_name FROM members ORDER BY full_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Contribution - USMS Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Record a Contribution</h3>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">← Back to Dashboard</a>
  </div>

  <?php echo $message; ?>

  <div class="card shadow p-4">
    <form method="POST">
      <div class="mb-3">
        <label for="member" class="form-label">Select Member</label>
        <select name="member_id" id="member" class="form-select" required>
          <option value="">-- Choose Member --</option>
          <?php while ($m = $members->fetch_assoc()) : ?>
            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['full_name']); ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="mb-3">
        <label for="amount" class="form-label">Amount (KSh)</label>
        <input type="number" name="amount" id="amount" class="form-control" min="1" required>
      </div>

      <button type="submit" name="save" class="btn btn-primary w-100">Save Contribution</button>
    </form>
  </div>
</div>

</body>
</html>
