<?php
session_start();
include("config/db_connect.php");

$error = "";

if (isset($_POST['login'])) {
  $email = trim($_POST['email']);
  $password = trim($_POST['password']);

  // --- ADMIN LOGIN ---
  $stmt = $conn->prepare("SELECT * FROM admin WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $admin_res = $stmt->get_result();

  if ($admin_res->num_rows > 0) {
    $admin = $admin_res->fetch_assoc();
    $hashed_input = hash('sha256', $password);

    if ($hashed_input === $admin['password']) {
      $_SESSION['admin_id'] = $admin['id'];
      $_SESSION['admin_name'] = $admin['username'];
      echo "<script>
              setTimeout(()=>{ window.location='admin/dashboard.php'; }, 800);
            </script>";
      exit;
    } else {
      $error = "Incorrect admin password!";
    }
  }

  // --- MEMBER LOGIN ---
  $stmt = $conn->prepare("SELECT * FROM members WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $member_res = $stmt->get_result();

  if ($member_res->num_rows > 0) {
    $member = $member_res->fetch_assoc();

    if (password_verify($password, $member['password'])) {
      $_SESSION['member_id'] = $member['id'];
      $_SESSION['member_name'] = $member['full_name'];
      echo "<script>
              setTimeout(()=>{ window.location='member/dashboard.php'; }, 800);
            </script>";
      exit;
    } else {
      $error = "Incorrect member password!";
    }
  }

  if (empty($error)) {
    $error = "Invalid email or password!";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login - Umoja Sacco Management System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://kit.fontawesome.com/a2d9d6d66c.js" crossorigin="anonymous"></script>
  <style>
    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #006400, #00b894);
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Poppins', sans-serif;
    }
    .login-card {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 2rem;
      width: 100%;
      max-width: 420px;
      color: #fff;
      box-shadow: 0 8px 25px rgba(0,0,0,0.3);
      animation: fadeIn 0.8s ease;
    }
    .form-control {
      border-radius: 50px;
      padding: 0.75rem 1rem;
      border: none;
      outline: none;
    }
    .btn-login {
      border-radius: 50px;
      padding: 0.7rem;
      background-color: #006400;
      border: none;
      font-weight: 600;
      transition: background 0.3s ease;
    }
    .btn-login:hover {
      background-color: #004d00;
    }
    .toggle-password {
      cursor: pointer;
      position: absolute;
      right: 15px;
      top: 10px;
      color: #555;
    }
    .alert {
      border-radius: 50px;
    }
    a { color: #ffeaa7; text-decoration: none; }
    a:hover { text-decoration: underline; }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>

<body>
  <div class="login-card">
    <div class="text-center mb-4">
      <i class="fa-solid fa-piggy-bank fa-3x mb-2"></i>
      <h3 class="fw-bold">Umoja Sacco</h3>
      <p class="text-light mb-0">Secure Member & Admin Access</p>
    </div>

    <?php if (!empty($error)) : ?>
      <div class="alert alert-danger text-center py-2"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3 position-relative">
        <label class="form-label text-light">Email</label>
        <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
      </div>

      <div class="mb-3 position-relative">
        <label class="form-label text-light">Password</label>
        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
        <i class="fa-solid fa-eye toggle-password" id="togglePassword"></i>
      </div>

      <button type="submit" name="login" class="btn btn-login w-100 shadow-sm">
        <i class="fa-solid fa-right-to-bracket me-2"></i> Login
      </button>
    </form>

    <p class="mt-4 text-center">
      Not registered? <a href="register.php">Create an account</a>
    </p>
  </div>

  <script>
    // Password toggle
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    togglePassword.addEventListener('click', () => {
      const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
      password.setAttribute('type', type);
      togglePassword.classList.toggle('fa-eye-slash');
    });
  </script>
</body>
</html>
