<?php
include('../config/db_connect.php');


// Fetch member details by ID
if (isset($_GET['id'])) {
  $id = intval($_GET['id']);
  $query = "SELECT * FROM members WHERE id = $id";
  $result = $conn->query($query);
  $member = $result->fetch_assoc();
}

// Handle form submission
if (isset($_POST['update'])) {
  $id = $_POST['id'];
  $full_name = $_POST['full_name'];
  $email = $_POST['email'];
  $phone = $_POST['phone'];

  $update_query = "UPDATE members SET 
      full_name = '$full_name', 
      email = '$email', 
      phone = '$phone'
      WHERE id = $id";

  if ($conn->query($update_query)) {
    header('Location: manage_members.php');
    exit;
  } else {
    echo "Error updating record: " . $conn->error;
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Member</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
  <div class="card shadow-sm col-md-6 mx-auto">
    <div class="card-body">
      <h4 class="text-center text-primary mb-3 fw-bold">Edit Member</h4>

      <form method="POST">
        <input type="hidden" name="id" value="<?= $member['id'] ?>">

        <div class="mb-3">
          <label>Full Name</label>
          <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($member['full_name']) ?>" required>
        </div>

        <div class="mb-3">
          <label>Email</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($member['email']) ?>" required>
        </div>

        <div class="mb-3">
          <label>Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($member['phone']) ?>" required>
        </div>

        <button type="submit" name="update" class="btn btn-primary w-100">Update Member</button>
      </form>

      <a href="manage_members.php" class="btn btn-outline-secondary mt-3 w-100">Cancel</a>
    </div>
  </div>
</div>

</body>
</html>
