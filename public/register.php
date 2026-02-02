<?php
// usms/public/register.php
session_start();

// Includes
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/functions.php';

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
        $reg_no = generate_member_no($conn);
        $status = 'inactive'; // Set to inactive until registration fee is paid
        
        $insertSql = "INSERT INTO members (member_reg_no, full_name, national_id, phone, email, password, join_date, status, reg_fee_paid) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 0)";
        
        if ($ins = $conn->prepare($insertSql)) {
            $ins->bind_param("sssssss", $reg_no, $full_name, $national_id, $phone, $email, $hashed, $status);
            
            if ($ins->execute()) {
                $newMemberId = $ins->insert_id;
                
                // Prevent Session Fixation
                session_regenerate_id(true);

                // Set Session
                $_SESSION['member_id'] = $newMemberId;
                $_SESSION['member_name'] = $full_name;
                $_SESSION['reg_no'] = $reg_no;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 'member';
                $_SESSION['status'] = $status;

                header("Location: ../member/pages/pay_registration.php");
                exit;
            } else {
                $errors[] = "Registration failed: " . htmlspecialchars($ins->error);
            }
            $ins->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Register — <?= htmlspecialchars(SITE_NAME) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    :root {
        --forest-green: #0F392B;
        --forest-mid: #134e3b;
        --lime: #D0F35D;
        --glass-bg: rgba(255, 255, 255, 0.9);
        --glass-border: rgba(255, 255, 255, 0.2);
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: linear-gradient(135deg, rgba(15, 57, 43, 0.85) 0%, rgba(19, 78, 59, 0.90) 100%),
                    url('<?= BACKGROUND_IMAGE ?>') center/cover no-repeat fixed;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
    }

    .reg-container {
        width: 100%;
        max-width: 1000px;
        position: relative;
        animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(40px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .reg-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 40px;
        overflow: hidden;
        box-shadow: 0 40px 80px -20px rgba(15, 57, 43, 0.2);
        display: flex;
    }

    .brand-side {
        background: var(--forest-green);
        width: 40%;
        padding: 60px;
        color: white;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }

    .brand-side::after {
        content: '';
        position: absolute;
        top: -50px;
        left: -50px;
        width: 200px;
        height: 200px;
        background: var(--lime);
        filter: blur(100px);
        opacity: 0.1;
    }

    .form-side {
        width: 60%;
        padding: 60px;
        background: rgba(255,255,255,0.4);
    }

    .brand-logo-small {
        width: 64px;
        height: 64px;
        background: white;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--forest-green);
        font-size: 1.8rem;
        margin-bottom: 30px;
        box-shadow: 0 10px 20px rgba(208, 243, 93, 0.2);
        border: 2px solid var(--lime);
    }

    .step-item {
        display: flex;
        gap: 15px;
        margin-bottom: 25px;
        opacity: 0.8;
    }

    .step-icon {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        background: rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.9rem;
    }

    .form-label {
        font-weight: 700;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--forest-mid);
        margin-bottom: 8px;
        opacity: 0.6;
    }

    .form-control-modern {
        background: #f1f3f5 !important;
        border: 2px solid transparent !important;
        border-radius: 16px !important;
        padding: 12px 18px !important;
        transition: all 0.3s !important;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .form-control-modern:focus {
        background: white !important;
        border-color: var(--forest-green) !important;
        box-shadow: 0 0 0 5px rgba(15, 57, 43, 0.05) !important;
    }

    .btn-register {
        background: var(--forest-green);
        color: white;
        border: none;
        border-radius: 16px;
        padding: 16px;
        font-weight: 700;
        transition: all 0.3s;
        box-shadow: 0 10px 20px rgba(15, 57, 43, 0.1);
    }

    .btn-register:hover {
        background: var(--forest-mid);
        transform: translateY(-2px);
        box-shadow: 0 15px 30px rgba(15, 57, 43, 0.2);
        color: white;
    }

    .toggle-pass {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: var(--forest-green);
        opacity: 0.4;
        z-index: 10;
        transition: 0.2s;
    }
    .toggle-pass:hover { opacity: 0.8; }

    .input-wrapper { position: relative; }

    @media (max-width: 991px) {
        .reg-card { flex-direction: column; }
        .brand-side, .form-side { width: 100%; padding: 40px; }
        .brand-side { text-align: center; align-items: center; }
        .brand-logo-small { margin-left: auto; margin-right: auto; }
        .step-item { text-align: left; }
    }
  </style>
</head>
<body>

<div class="reg-container">
    <div class="reg-card">
        <!-- Branding Side -->
        <div class="brand-side">
            <div>
                <div class="brand-logo-small">
                    <img src="<?= SITE_LOGO ?>" alt="<?= SITE_NAME ?>" style="width: 100%; height: 100%; object-fit: contain; padding: 8px;">
                </div>
                <h1 class="fw-800 display-5 mb-3" style="letter-spacing:-2px">Join the Community</h1>
                <p class="opacity-75 mb-5 lead">Start your journey toward financial freedom with Umoja Drivers Sacco.</p>
                
                <div class="step-list d-none d-lg-block">
                    <div class="step-item">
                        <div class="step-icon">1</div>
                        <div>
                            <h6 class="mb-0 fw-bold">Instant Account</h6>
                            <small class="opacity-50">Simple registration in under 2 minutes.</small>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-icon">2</div>
                        <div>
                            <h6 class="mb-0 fw-bold">Secure Access</h6>
                            <small class="opacity-50">State-of-the-art encryption for your data.</small>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-icon">3</div>
                        <div>
                            <h6 class="mb-0 fw-bold">Easy Savings</h6>
                            <small class="opacity-50">Track every shilling from your dashboard.</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <p class="small opacity-50 mb-0">Owned & Operated by</p>
                <h6 class="fw-bold mb-0 text-lime">Umoja Drivers Sacco Ltd.</h6>
            </div>
        </div>

        <!-- Form Side -->
        <div class="form-side">
            <div class="d-flex justify-content-between align-items-center mb-5">
                <h3 class="fw-800 mb-0 text-forest">Create Account</h3>
                <a href="login.php" class="text-decoration-none fw-800 small text-forest-mid opacity-50">LOGIN INSTEAD</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger border-0 rounded-4 shadow-sm p-3 mb-4 small fw-bold">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $e) echo "<li>$e</li>"; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="regForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control form-control-modern" required value="<?= htmlspecialchars($full_name) ?>" placeholder="As per ID Card">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">National ID</label>
                        <input type="text" name="national_id" class="form-control form-control-modern" required value="<?= htmlspecialchars($national_id) ?>" placeholder="8 Digits">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control form-control-modern" required value="<?= htmlspecialchars($phone_raw) ?>" placeholder="07xxxxxxxx">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control form-control-modern" required value="<?= htmlspecialchars($email) ?>" placeholder="your@email.com">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <div class="input-wrapper">
                            <input type="password" name="password" id="pass1" class="form-control form-control-modern" required placeholder="••••••••">
                            <div class="toggle-pass" onclick="togglePass('pass1', 'icon1')">
                                <i class="bi bi-eye-slash" id="icon1"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-wrapper">
                            <input type="password" name="confirm_password" id="pass2" class="form-control form-control-modern" required placeholder="••••••••">
                            <div class="toggle-pass" onclick="togglePass('pass2', 'icon2')">
                                <i class="bi bi-eye-slash" id="icon2"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-4 pt-2">
                        <button type="submit" class="btn btn-register w-100" id="submitBtn">
                            <span class="btn-text">Register Account</span>
                            <div class="spinner-border spinner-border-sm d-none ms-2"></div>
                        </button>
                    </div>

                    <p class="text-center small text-muted mt-4">
                        By registering, you agree to our <a href="#" class="text-forest fw-bold">Terms of Service</a> and <a href="#" class="text-forest fw-bold">Privacy Policy</a>.
                    </p>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

    document.getElementById('regForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        const text = btn.querySelector('.btn-text');
        const spinner = btn.querySelector('.spinner-border');
        btn.disabled = true;
        spinner.classList.remove('d-none');
        text.textContent = "Setting up account...";
    });
</script>
</body>
</html>