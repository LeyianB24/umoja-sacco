<?php
include('../config/db_connect.php');

if (isset($_POST['save'])) {
  $full_name = trim($_POST['full_name']);
  $email = trim($_POST['email']);
  $phone = trim($_POST['phone']);
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];
  $date_joined = date('Y-m-d');

  // ✅ Basic validation
  if ($password !== $confirm_password) {
    $error = "Passwords do not match!";
  } else {
    // ✅ Check if email already exists
    $check = $conn->prepare("SELECT id FROM members WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
      $error = "A member with this email already exists!";
    } else {
      // ✅ Hash password securely
      $hashed_password = password_hash($password, PASSWORD_BCRYPT);

      // ✅ Insert new member
      $query = $conn->prepare("
        INSERT INTO members (full_name, email, phone, password, date_joined)
        VALUES (?, ?, ?, ?, ?)
      ");
      $query->bind_param("sssss", $full_name, $email, $phone, $hashed_password, $date_joined);

      if ($query->execute()) {
        header('Location: manage_members.php?success=Member+added+successfully');
        exit;
      } else {
        $error = "Error saving member: " . $conn->error;
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Member</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container mt-5">
  <div class="card shadow col-md-6 mx-auto border-0 rounded-4">
    <div class="card-body p-4">
      <h4 class="text-center mb-4 text-success fw-bold">
        <i class="fa-solid fa-user-plus me-2"></i> Add New Member
      </h4>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-control" placeholder="Enter full name" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" placeholder="Enter email address" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" placeholder="Enter phone number" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" placeholder="Create password" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required>
        </div>

        <button type="submit" name="save" class="btn btn-success w-100 rounded-pill">
          <i class="fa-solid fa-save me-2"></i> Save Member
        </button>
      </form>

      <a href="manage_members.php" class="btn btn-outline-secondary mt-3 w-100 rounded-pill">
        <i class="fa-solid fa-arrow-left me-2"></i> Back
      </a>
    </div>
  </div>
</div>

</body>
</html>
