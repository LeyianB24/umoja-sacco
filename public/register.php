<?php
// usms/public/register.php
session_start();

// Includes
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

// 1. Generate CSRF Token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 2. Initialize ALL variables to empty strings to prevent "Undefined variable" errors
$errors = [];
$full_name   = '';
$national_id = '';
$phone       = ''; 
$email       = '';
$phone_raw   = ''; // <--- Fixed: Initialized here

/**
 * Normalize phone to +254XXXXXXXXX
 */
function normalize_phone($raw) {
    $p = preg_replace('/[^\d\+]/', '', $raw);
    if ($p === '') return '';
    if (strpos($p, '+') === 0) return $p; 
    if (preg_match('/^0(\d{8,9})$/', $p, $m)) return '+254' . $m[1];
    if (preg_match('/^7(\d{8})$/', $p, $m)) return '+254' . $p;
    return $p;
}

// 3. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid security token. Please reload the page.');
    }

    // Collect & Sanitize
    $full_name   = trim($_POST['full_name'] ?? '');
    $national_id = trim($_POST['national_id'] ?? '');
    $phone_raw   = trim($_POST['phone'] ?? ''); // Assign posted value
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    $phone = normalize_phone($phone_raw);

    // Validation
    if ($full_name === '') $errors[] = "Full name is required.";
    if ($national_id === '') $errors[] = "National ID / Passport is required.";
    if ($phone === '') $errors[] = "Phone number is required.";
    if ($email === '') $errors[] = "Email is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Enter a valid email address.";
    if ($password === '') $errors[] = "Password is required.";
    if ($password !== $confirm) $errors[] = "Passwords do not match.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";

    // Uniqueness Checks
    if (empty($errors)) {
        $checkSql = "SELECT member_id FROM members WHERE email = ? OR phone = ? OR national_id = ? LIMIT 1";
        if ($stmt = $conn->prepare($checkSql)) {
            $stmt->bind_param("sss", $email, $phone, $national_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = "A member with that email, phone, or national ID already exists.";
            }
            $stmt->close();
        } else {
            $errors[] = "Database error. Please try again.";
        }
    }

    // Insert + Auto Login
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $status = 'active'; 
        
        $insertSql = "INSERT INTO members (full_name, national_id, phone, email, password, join_date, status) VALUES (?, ?, ?, ?, ?, NOW(), ?)";
        
        if ($ins = $conn->prepare($insertSql)) {
            $ins->bind_param("ssssss", $full_name, $national_id, $phone, $email, $hashed, $status);
            
            if ($ins->execute()) {
                $newMemberId = $ins->insert_id;
                
                // Prevent Session Fixation
                session_regenerate_id(true);

                // Set Session
                $_SESSION['member_id'] = $newMemberId;
                $_SESSION['member_name'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 'member';
                $_SESSION['status'] = $status;

                header("Location: ../member/dashboard.php");
                exit;
            } else {
                $errors[] = "Registration failed: " . htmlspecialchars($ins->error);
            }
            $ins->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Register — <?= htmlspecialchars(SITE_NAME) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  
  <link href="<?= ASSET_BASE ?>/css/style.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root {
      --umoja-green: #0A6B3A;
      --umoja-dark-green: #085a30;
      --umoja-golden: #FFC107;
    }

    body {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #E6F3EB;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .bg-image-holder {
        background-image: url('<?= defined('BACKGROUND_IMAGE') ? BACKGROUND_IMAGE : "" ?>');
        background-size: cover;
        background-position: center;
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        z-index: -1;
        opacity: 0.15;
        filter: grayscale(20%);
    }

    .reg-card {
        border: none;
        border-radius: 1.5rem;
        box-shadow: 0 20px 40px rgba(10, 107, 58, 0.15);
        background: #fff;
        overflow: hidden;
        width: 100%;
        max-width: 1000px;
    }

    .brand-panel {
        background: linear-gradient(135deg, var(--umoja-dark-green) 0%, var(--umoja-green) 100%);
        color: #fff;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 3rem;
        position: relative;
    }

    .form-panel {
        padding: 3rem;
    }

    .form-control {
        padding: 0.75rem 1rem;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
    }
    .form-control:focus {
        border-color: var(--umoja-green);
        box-shadow: 0 0 0 0.25rem rgba(10, 107, 58, 0.15);
    }

    .input-group-text {
        background-color: #f8f9fa;
        color: var(--umoja-green);
        border-right: none;
    }
    .form-control { border-left: none; }

    .btn-register {
        background-color: var(--umoja-green);
        color: white;
        font-weight: 600;
        padding: 0.8rem;
        border-radius: 0.5rem;
        transition: all 0.3s;
        border: none;
    }
    .btn-register:hover {
        background-color: var(--umoja-dark-green);
        transform: translateY(-2px);
        color: white;
    }

    .toggle-password {
        cursor: pointer;
        background: white;
        border-left: none;
    }

    @media (max-width: 991px) {
        .brand-panel { padding: 2rem; text-align: center; }
        .form-panel { padding: 2rem; }
    }
  </style>
</head>
<body>

<div class="bg-image-holder"></div>

<div class="container py-4">
  <div class="reg-card row g-0">
    
    <div class="col-lg-5 brand-panel">
      <div style="z-index: 2;">
        <div class="mb-4">
            <img src="<?= ASSET_BASE ?>/images/people_logo.png" 
                 alt="Logo" 
                 class="rounded-circle border border-3 border-warning shadow-sm"
                 style="width: 80px; height: 80px; object-fit: cover;">
        </div>
        <h2 class="fw-bold text-white mb-3">Join <?= htmlspecialchars(SITE_NAME) ?></h2>
        <p class="text-white-50 lead mb-4"><?= htmlspecialchars(TAGLINE) ?></p>
        
        <div class="d-none d-lg-block">
            <ul class="list-unstyled mt-4 space-y-3">
                <li class="d-flex align-items-center mb-3">
                    <i class="bi bi-check-circle-fill text-warning me-3 fs-5"></i>
                    <span>Quick & Secure Registration</span>
                </li>
                <li class="d-flex align-items-center mb-3">
                    <i class="bi bi-check-circle-fill text-warning me-3 fs-5"></i>
                    <span>Access Instant Loans</span>
                </li>
                <li class="d-flex align-items-center">
                    <i class="bi bi-check-circle-fill text-warning me-3 fs-5"></i>
                    <span>Track Your Savings</span>
                </li>
            </ul>
        </div>
        
        <div class="mt-5 text-white-50">
            Already a member? <br>
            <a href="login.php" class="btn btn-outline-light fw-bold rounded-pill px-4 mt-2">Sign In</a>
        </div>
      </div>
    </div>

    <div class="col-lg-7 form-panel">
      <h3 class="fw-bold text-dark mb-4">Create Account</h3>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2 shadow-sm border-0 border-start border-danger border-5">
          <ul class="mb-0 ps-3 small">
            <?php foreach ($errors as $e) echo "<li>$e</li>"; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="regForm">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label small fw-bold text-muted">Full Name</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                    <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($full_name) ?>" placeholder="Your official name">
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label small fw-bold text-muted">National ID</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-card-heading"></i></span>
                    <input type="text" name="national_id" class="form-control" required value="<?= htmlspecialchars($national_id) ?>" placeholder="ID Number">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-bold text-muted">Phone Number</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-telephone-fill"></i></span>
                    <input type="text" name="phone" class="form-control" required value="<?= htmlspecialchars($phone_raw) ?>" placeholder="07...">
                </div>
            </div>

            <div class="col-12">
                <label class="form-label small fw-bold text-muted">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                    <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($email) ?>" placeholder="name@example.com">
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label small fw-bold text-muted">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" name="password" id="pass1" class="form-control border-end-0" required placeholder="Min 6 chars">
                    <span class="input-group-text toggle-password border-start-0" onclick="togglePass('pass1', 'icon1')">
                        <i class="bi bi-eye-slash" id="icon1"></i>
                    </span>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-bold text-muted">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-check-lg"></i></span>
                    <input type="password" name="confirm_password" id="pass2" class="form-control border-end-0" required placeholder="Repeat password">
                    <span class="input-group-text toggle-password border-start-0" onclick="togglePass('pass2', 'icon2')">
                        <i class="bi bi-eye-slash" id="icon2"></i>
                    </span>
                </div>
            </div>

            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-register w-100" id="submitBtn">
                    <span class="spinner-border spinner-border-sm d-none me-2"></span>
                    <span class="btn-text">Create My Account</span>
                </button>
            </div>
            
            <div class="col-12 d-lg-none text-center mt-3">
                <a href="login.php" class="text-success fw-bold text-decoration-none">Already a member? Log In</a>
            </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // 1. Toggle Password Visibility
    function togglePass(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === "password") {
            input.type = "text";
            icon.classList.replace("bi-eye-slash", "bi-eye");
        } else {
            input.type = "password";
            icon.classList.replace("bi-eye", "bi-eye-slash");
        }
    }

    // 2. Loading State
    document.getElementById('regForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.querySelector('.spinner-border').classList.remove('d-none');
        btn.querySelector('.btn-text').textContent = "Creating Account...";
    });
</script>
</body>
</html>