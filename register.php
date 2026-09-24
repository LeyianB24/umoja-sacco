<?php
include("config/db_connect.php");

$message = "";

if (isset($_POST['register'])) {
  // Sanitize and collect input
  $full_name = trim($_POST['full_name']);
  $email = trim($_POST['email']);
  $phone = trim($_POST['phone']);
  $password = $_POST['password'];

  // Basic validation
  if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
    $message = "<div class='alert alert-danger text-center'>All fields are required!</div>";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = "<div class='alert alert-danger text-center'>Invalid email format!</div>";
  } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
    $message = "<div class='alert alert-danger text-center'>Enter a valid phone number (10-15 digits)!</div>";
  } else {
    // Check if email already exists
    $check_email = $conn->prepare("SELECT id FROM members WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    $check_result = $check_email->get_result();

    if ($check_result->num_rows > 0) {
      $message = "<div class='alert alert-warning text-center'>Email already registered. Please login!</div>";
    } else {
      // Hash password securely
      $hashed_password = password_hash($password, PASSWORD_DEFAULT);

      // Insert member
      $stmt = $conn->prepare("INSERT INTO members (full_name, email, phone, password, date_joined) VALUES (?, ?, ?, ?, NOW())");
      $stmt->bind_param("ssss", $full_name, $email, $phone, $hashed_password);

      if ($stmt->execute()) {
        echo "<script>
          alert('🎉 Registration successful! You can now log in.');
          window.location='login.php';
        </script>";
        exit;
      } else {
        $message = "<div class='alert alert-danger text-center'>Error occurred while registering. Try again!</div>";
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register - Umoja Sacco Management System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #007bff, #28a745);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      color: #333;
    }
    .card {
      border-radius: 15px;
      padding: 25px;
      background: #fff;
    }
    .form-control:focus {
      box-shadow: 0 0 5px rgba(40, 167, 69, 0.7);
      border-color: #28a745;
    }
    .btn-success {
      border-radius: 25px;
      font-weight: bold;
    }
    h3 {
      color: #28a745;
      font-weight: 700;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="col-md-6 col-lg-5 mx-auto">
    <div class="card shadow-lg">
      <h3 class="text-center mb-4">Create Member Account</h3>

      <?php if (!empty($message)) echo $message; ?>

      <form method="POST" novalidate>
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-control" placeholder="Enter your full name" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="example@email.com" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-control" placeholder="07XXXXXXXX" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" placeholder="Create a password" minlength="6" required>
        </div>
        <button type="submit" name="register" class="btn btn-success w-100 mt-2">Register</button>
      </form>

      <p class="mt-3 text-center">
        Already have an account? <a href="login.php" class="text-decoration-none">Login here</a>
      </p>
    </div>
  </div>
</div>

</body>
</html>
