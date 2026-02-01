<?php
ob_start(); // <--- FIX 1: Add Output Buffering to prevent header errors
require_once __DIR__ . '/../inc/functions.php'; // Includes session_start()
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Constants
define('REMEMBER_SECONDS', 30 * 24 * 60 * 60);
define('COOKIE_NAME', 'usms_rem');
$cookie_secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

// --- HELPER FUNCTIONS ---

function verifyAndUpgradePassword($conn, $table, $id_col, $id_val, $input_pass, $stored_hash) {
    $valid = false;
    $needs_rehash = false;

    // 1. Check modern Bcrypt hash
    if (!empty($stored_hash) && password_verify($input_pass, $stored_hash)) {
        $valid = true;
        if (password_needs_rehash($stored_hash, PASSWORD_DEFAULT)) {
            $needs_rehash = true;
        }
    }
    // 2. Check SHA256 hash (Legacy Fallback)
    elseif (!empty($stored_hash) && hash('sha256', $input_pass) === $stored_hash) {
        $valid = true;
        $needs_rehash = true;
    }
    // 3. Check Plaintext (Legacy Fallback)
    elseif ($stored_hash === $input_pass) {
        $valid = true;
        $needs_rehash = true;
    }

    // Auto-upgrade legacy passwords
    if ($valid && $needs_rehash) {
        $new_hash = password_hash($input_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE $id_col = ?");
        $stmt->bind_param('si', $new_hash, $id_val);
        $stmt->execute();
        $stmt->close();
    }

    return $valid;
}

/**
 * --- MAIN LOGIN LOGIC ---
 */
// FIX 2: Changed isset($_POST['login']) to checking REQUEST_METHOD only
// We removed the button check because JS disables it, removing it from POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') { 

    // CSRF Check (Dies on failure)
    verify_csrf_token();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if ($email === '' || $password === '') {
        flash_set('Please enter both email/username and password.', 'error');
    } else {
        $user_found = false;

        // --- 1. Attempt Admin Login ---
        $stmt_admin = $conn->prepare("SELECT admin_id, full_name, username, role, password FROM admins WHERE email = ? OR username = ? LIMIT 1");
        $stmt_admin->bind_param('ss', $email, $email);
        $stmt_admin->execute();
        $res_admin = $stmt_admin->get_result();

        if ($res_admin && $res_admin->num_rows > 0) {
            $admin = $res_admin->fetch_assoc();
            
            if (verifyAndUpgradePassword($conn, 'admins', 'admin_id', $admin['admin_id'], $password, $admin['password'])) {
                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_name'] = !empty($admin['full_name']) ? $admin['full_name'] : $admin['username'];
                $_SESSION['role'] = strtolower($admin['role']);
                $user_found = true;

                // Handle 'Remember Me'
                if ($remember) {
                    $rnd = bin2hex(random_bytes(32));
                    $token_hash = hash('sha256', $rnd);
                    $ua_hash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
                    $expires = date('Y-m-d H:i:s', time() + REMEMBER_SECONDS);

                    $up = $conn->prepare("UPDATE admins SET remember_token=?, remember_expires=?, remember_ua=? WHERE admin_id=?");
                    $up->bind_param('sssi', $token_hash, $expires, $ua_hash, $admin['admin_id']);
                    $up->execute();
                    $up->close();

                    setcookie(COOKIE_NAME, $rnd, time() + REMEMBER_SECONDS, '/', '', $cookie_secure, true);
                }

                flash_set('Welcome back, ' . $_SESSION['admin_name'] . '!', 'success');

                // Redirect Map
                $redirects = [
                    'superadmin' => '../superadmin/dashboard.php',
                    'manager'    => '../manager/dashboard.php',
                    'accountant' => '../accountant/dashboard.php'
                ];
                $dest = $redirects[$_SESSION['role']] ?? '../admin/dashboard.php';
                
                header("Location: $dest");
                exit;
            }
        }
        $stmt_admin->close();

        // --- 2. Attempt Member Login (If not Admin) ---
        if (!$user_found) {
            $stmt_member = $conn->prepare("SELECT member_id, full_name, password FROM members WHERE email = ? LIMIT 1");
            $stmt_member->bind_param('s', $email);
            $stmt_member->execute();
            $res_member = $stmt_member->get_result();

            if ($res_member && $res_member->num_rows > 0) {
                $member = $res_member->fetch_assoc();

                if (verifyAndUpgradePassword($conn, 'members', 'member_id', $member['member_id'], $password, $member['password'])) {
                    session_regenerate_id(true);
                    
                    $_SESSION['member_id'] = $member['member_id'];
                    $_SESSION['member_name'] = $member['full_name'];
                    $_SESSION['role'] = 'member';
                    
                    flash_set('Welcome, ' . $_SESSION['member_name'] . '! Access your portal.', 'success');
                    header("Location: ../member/dashboard.php");
                    exit;
                }
            }
            $stmt_member->close();
        }

        // --- 3. Failed Login ---
        flash_set('Invalid login credentials. Please double-check your details.', 'error');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Login — <?= htmlspecialchars(SITE_NAME) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  
  <link href="<?= ASSET_BASE ?>/css/style.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script>
      // Init Theme
      const theme = localStorage.getItem('theme') || 'light';
      document.documentElement.setAttribute('data-bs-theme', theme);
  </script>

  <style>
    :root {
      --umoja-green: #0A6B3A;
      --umoja-dark-green: #085a30;
      --umoja-golden: #FFC107;
      --umoja-light-green: #E6F3EB;
    }
    
    [data-bs-theme="dark"] :root {
      --umoja-light-green: #0b1210; /* Dark background */
    }
    [data-bs-theme="dark"] .login-card {
        background: rgba(30, 41, 59, 0.95);
        color: #f1f5f9;
        border-top-color: var(--umoja-green); /* Less bright gold in dark */
    }
    [data-bs-theme="dark"] .input-group-text {
        background-color: #0f172a;
        color: var(--umoja-green);
        border-color: #334155;
    }
    [data-bs-theme="dark"] .form-control {
        background-color: #0f172a;
        border-color: #334155;
        color: #f8fafc;
    }
    [data-bs-theme="dark"] .text-muted {
        color: #94a3b8 !important; 
    }
    [data-bs-theme="dark"] .btn-sacco-primary {
        box-shadow: 0 4px 6px rgba(0,0,0,0.3);
    }
    [data-bs-theme="dark"] .toggle-password {
        background-color: #0f172a;
        border-color: #334155;
        color: #94a3b8;
    }
    
    body {
        background-color: var(--umoja-light-green) !important;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .bg-image-holder {
        background-image: url('<?= defined('BACKGROUND_IMAGE') ? BACKGROUND_IMAGE : "" ?>');
        background-size: cover;
        background-position: center;
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        z-index: -1;
        opacity: 0.15;
    }

    .login-card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 15px 35px rgba(10, 107, 58, 0.12);
        border-top: 5px solid var(--umoja-golden);
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
    }
    
    .btn-sacco-primary {
        background-color: var(--umoja-green);
        border-color: var(--umoja-green);
        color: #fff;
        font-weight: 600;
        transition: all 0.3s;
    }
    .btn-sacco-primary:hover {
        background-color: var(--umoja-dark-green);
        border-color: var(--umoja-dark-green);
        transform: translateY(-1px);
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
    .form-control {
        border-left: none;
    }
    
    .toggle-password {
        cursor: pointer;
        background: white;
    }
  </style>
</head>
<body>

<div class="bg-image-holder"></div>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-4">
      <div class="card login-card p-2">
        <div class="card-body p-4">
          <div class="text-center mb-4">
            <img src="<?= ASSET_BASE ?>/images/people_logo.png" 
                 alt="<?= SITE_NAME ?> logo" 
                 class="rounded-circle mb-3 shadow-sm"
                 style="width:85px;height:85px;object-fit:cover;border:3px solid var(--umoja-golden);">
            <h4 class="text-sacco-green fw-bold mb-0"><?= htmlspecialchars(SITE_NAME) ?></h4>
            <p class="text-muted small"><?= htmlspecialchars(defined('TAGLINE') ? TAGLINE : 'Secure Portal Access') ?></p>
          </div>

          <?php flash_render(); ?>

          <form method="post" id="loginForm" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
              <label class="form-label fw-semibold small text-secondary">Email or Username</label>
              <div class="input-group has-validation">
                <span class="input-group-text border-end-0"><i class="bi bi-person-fill"></i></span>
                <input type="text" name="email" class="form-control border-start-0 ps-0" required 
                       placeholder="e.g. john@example.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold small text-secondary">Password</label>
              <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-lock-fill"></i></span>
                <input type="password" name="password" id="password" class="form-control border-start-0 border-end-0 ps-0" required placeholder="••••••••">
                <span class="input-group-text toggle-password border-start-0" onclick="togglePassword()">
                    <i class="bi bi-eye-slash" id="toggleIcon"></i>
                </span>
              </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" 
                       <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                <label class="form-check-label small text-muted" for="remember">Keep me signed in</label>
              </div>
              <a href="forgot_password.php" class="small text-decoration-none text-sacco-green fw-bold">Forgot Password?</a>
            </div>

            <div class="d-grid mb-3">
              <button type="submit" name="login" class="btn btn-sacco-primary py-2" id="loginBtn">
                <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                <span class="btn-text"><i class="bi bi-box-arrow-in-right me-2"></i> Access Portal</span>
              </button>
            </div>

            <div class="text-center small pt-3 border-top text-muted">
              Not yet a member? <a href="register.php" class="text-sacco-green fw-bold text-decoration-none">Join Us Today</a>
            </div>
          </form>
        </div>
      </div>
      <div class="text-center mt-3 text-muted small">
        &copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. All rights reserved.
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        }
    }

    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn = document.getElementById('loginBtn');
        const spinner = btn.querySelector('.spinner-border');
        const text = btn.querySelector('.btn-text');
        
        btn.disabled = true;
        spinner.classList.remove('d-none');
        text.textContent = 'Verifying...';
    });
</script>
</body>
</html>