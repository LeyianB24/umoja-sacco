<?php
session_start();
include('../config/db_connect.php');

// Send new notification
if (isset($_POST['send'])) {
  $member_id = $_POST['member_id'];
  $message = $_POST['message'];
  $stmt = $conn->prepare("INSERT INTO notifications (member_id, message, is_read, date_sent) VALUES (?, ?, 0, NOW())");
  $stmt->bind_param("is", $member_id, $message);
  $stmt->execute();
  $stmt->close();
}

// Fetch notifications with member names
$query = "SELECT notifications.*, members.full_name 
          FROM notifications 
          INNER JOIN members ON notifications.member_id = members.id 
          ORDER BY notifications.id DESC";
$result = $conn->query($query);

// Fetch members for dropdown
$members = $conn->query("SELECT id, full_name FROM members");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Notifications</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container mt-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="text-primary fw-bold">Manage Notifications</h3>
    <a href="../admin_dashboard.php" class="btn btn-primary rounded-pill px-4">
      <i class="fa-solid fa-arrow-left me-2"></i> Back to Dashboard
    </a>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h5 class="mb-3 text-secondary">Send New Notification</h5>
      <form method="POST" class="row g-3">
        <div class="col-md-4">
          <select name="member_id" class="form-select" required>
            <option value="">Select Member</option>
            <?php while($m = $members->fetch_assoc()): ?>
              <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-6">
          <input type="text" name="message" class="form-control" placeholder="Enter notification message" required>
        </div>
        <div class="col-md-2">
          <button name="send" class="btn btn-success w-100">
            <i class="fa-solid fa-paper-plane me-2"></i> Send
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <table class="table table-bordered table-striped text-center">
        <thead class="table-primary">
          <tr>
            <th>ID</th>
            <th>Member</th>
            <th>Message</th>
            <th>Status</th>
            <th>Date Sent</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['full_name']) ?></td>
              <td><?= htmlspecialchars($row['message']) ?></td>
              <td><?= $row['is_read'] ? '<span class="badge bg-success">Read</span>' : '<span class="badge bg-secondary">Unread</span>' ?></td>
              <td><?= htmlspecialchars($row['date_sent']) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</body>
</html>
