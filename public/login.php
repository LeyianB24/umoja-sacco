<?php
ob_start();
// Load config FIRST — prevents 'constant already defined' errors from functions.php auto-include
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/Auth.php';

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
        $stmt_admin = $conn->prepare("SELECT a.admin_id, a.full_name, a.username, a.role_id, r.name as role_name, a.password 
                                    FROM admins a 
                                    JOIN roles r ON a.role_id = r.id 
                                    WHERE a.email = ? OR a.username = ? LIMIT 1");
        $stmt_admin->bind_param('ss', $email, $email);
        $stmt_admin->execute();
        $res_admin = $stmt_admin->get_result();

        if ($res_admin && $res_admin->num_rows > 0) {
            $admin = $res_admin->fetch_assoc();
            
            if (verifyAndUpgradePassword($conn, 'admins', 'admin_id', $admin['admin_id'], $password, $admin['password'])) {
                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_name'] = !empty($admin['full_name']) ? $admin['full_name'] : $admin['username'];
                $_SESSION['role_id'] = $admin['role_id'];
                $_SESSION['role'] = strtolower($admin['role_name']);
                $_SESSION['role_name'] = $admin['role_name'];
                
                // Load Permissions into Session
                Auth::loadPermissions($admin['role_id']);
                
                // Superadmin Rule: If role_id == 1, force load all permissions for session visibility
                if ($_SESSION['role_id'] == 1) {
                    $res_all = $conn->query("SELECT slug FROM permissions");
                    $all_perms = [];
                    while($p = $res_all->fetch_assoc()) $all_perms[] = $p['slug'];
                    $_SESSION['permissions'] = $all_perms;
                }
                
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

                // Redirect to Admin Dashboard
                $dest = BASE_URL . '/admin/pages/dashboard.php';
                
                header("Location: $dest");
                exit;
            }
        }
        $stmt_admin->close();

        // --- 2. Attempt Member Login (If not Admin) ---
        if (!$user_found) {
            // Check by Email OR RegNo
            $stmt_member = $conn->prepare("SELECT member_id, full_name, member_reg_no, password, registration_fee_status FROM members WHERE email = ? OR member_reg_no = ? LIMIT 1");
            $stmt_member->bind_param('ss', $email, $email);
            $stmt_member->execute();
            $res_member = $stmt_member->get_result();

            if ($res_member && $res_member->num_rows > 0) {
                $member = $res_member->fetch_assoc();

                if (verifyAndUpgradePassword($conn, 'members', 'member_id', $member['member_id'], $password, $member['password'])) {
                    session_regenerate_id(true);
                    
                    $_SESSION['member_id'] = $member['member_id'];
                    $_SESSION['member_name'] = $member['full_name'];
                    $_SESSION['reg_no']      = $member['member_reg_no']; // Store RegNo in session
                    $_SESSION['role'] = 'member';

                    // PAY-GATE CHECK
                    if (($member['registration_fee_status'] ?? 'unpaid') === 'unpaid') {
                        $_SESSION['pending_pay'] = true;
                        flash_set('Payment Required: Please settle your registration fee to access the dashboard.', 'warning');
                        header("Location: " . BASE_URL . "/member/pages/pay_registration.php");
                        exit;
                    }
                    
                    flash_set('Welcome, ' . $_SESSION['member_name'] . '! Access your portal.', 'success');
                    header("Location: " . BASE_URL . "/member/pages/dashboard.php");
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
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
  <meta charset="utf-8">
  <title>Login — <?= htmlspecialchars(SITE_NAME) ?></title>
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
        overflow-x: hidden;
    }

    .login-container {
        width: 100%;
        max-width: 450px;
        padding: 20px;
        position: relative;
    }

    /* Decorative circles */
    .deco-circle {
        position: absolute;
        width: 300px;
        height: 300px;
        border-radius: 50%;
        background: var(--lime);
        filter: blur(80px);
        opacity: 0.15;
        z-index: -1;
    }
    .circle-1 { top: -100px; right: -150px; }
    .circle-2 { bottom: -100px; left: -150px; background: var(--forest-green); }

    .login-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 30px;
        padding: 45px;
        box-shadow: 0 25px 50px -12px rgba(15, 57, 43, 0.15);
        animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .brand-logo {
        width: 80px;
        height: 80px;
        background: white;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 25px;
        box-shadow: 0 10px 20px rgba(15, 57, 43, 0.2);
        border: 2px solid var(--lime);
        font-size: 2rem;
        transform: rotate(-5deg);
    }

    .login-title {
        font-weight: 800;
        color: var(--forest-green);
        letter-spacing: -1px;
    }

    .form-label {
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--forest-mid);
        margin-bottom: 8px;
        opacity: 0.7;
    }

    .form-control-modern {
        background: #f1f3f5 !important;
        border: 2px solid transparent !important;
        border-radius: 16px !important;
        padding: 14px 20px !important;
        transition: all 0.3s !important;
        font-weight: 600;
    }

    .form-control-modern:focus {
        background: white !important;
        border-color: var(--forest-green) !important;
        box-shadow: 0 0 0 5px rgba(15, 57, 43, 0.05) !important;
    }

    .btn-login {
        background: var(--forest-green);
        color: white;
        border: none;
        border-radius: 16px;
        padding: 16px;
        font-weight: 700;
        font-size: 1rem;
        transition: all 0.3s;
        margin-top: 10px;
    }

    .btn-login:hover {
        background: var(--forest-mid);
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(15, 57, 43, 0.2);
        color: white;
    }

    .btn-login:active {
        transform: scale(0.98);
    }

    .toggle-pass {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: var(--forest-green);
        opacity: 0.5;
        transition: 0.2s;
        z-index: 10;
    }
    .toggle-pass:hover { opacity: 1; }

    .forgot-link {
        color: var(--forest-green);
        text-decoration: none;
        font-weight: 700;
        font-size: 0.85rem;
        transition: 0.2s;
    }
    .forgot-link:hover { opacity: 0.6; }

    .register-footer {
        margin-top: 30px;
        text-align: center;
        font-size: 0.9rem;
        font-weight: 600;
        color: #64748b;
    }

    .register-link {
        color: var(--forest-green);
        text-decoration: none;
        font-weight: 800;
        border-bottom: 2px solid var(--lime);
    }

    .input-wrapper { position: relative; }

    /* Flash Message UI */
    .flash-item {
        border-radius: 15px;
        border: none;
        padding: 12px 20px;
        font-weight: 600;
        font-size: 0.85rem;
        margin-bottom: 20px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
  </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>

<div class="deco-circle circle-1"></div>
<div class="deco-circle circle-2"></div>

<div class="login-container">
    <div class="login-card">
        <div class="text-center">
            <div class="brand-logo">
                <img src="<?= SITE_LOGO ?>" alt="<?= SITE_NAME ?>" style="width: 100%; height: 100%; object-fit: contain; padding: 10px;">
            
            </div>
            <h2 class="login-title mb-1"><?= htmlspecialchars(SITE_NAME) ?></h2>
            <p class="small text-muted mb-4 fw-medium">Unified Management System</p>
        </div>

        <?php flash_render(); ?>

        <form method="post" id="loginForm" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3 position-relative">
                <label class="form-label">Email or Username</label>
                <div class="input-wrapper">
                    <input type="text" name="email" class="form-control form-control-modern" required 
                           placeholder="Email or Member No (e.g. UDS-202X-XXXX)"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-4 position-relative">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label">Password</label>
                    <a href="forgot_password.php" class="forgot-link mb-2">Forgot?</a>
                </div>
                <div class="input-wrapper">
                    <input type="password" name="password" id="password" class="form-control form-control-modern" required placeholder="••••••••">
                    <div class="toggle-pass" onclick="togglePassword()">
                        <i class="bi bi-eye-slash" id="toggleIcon"></i>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" 
                           <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-bold text-muted" for="remember">Keep me logged in</label>
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-login" id="loginBtn">
                    <span class="btn-text">Access Portal</span>
                    <div class="spinner-border spinner-border-sm d-none ms-2" role="status"></div>
                </button>
            </div>

            <div class="register-footer">
                Don't have an account? <a href="register.php" class="register-link">Create Account</a>
            </div>
        </form>
    </div>
    
    <p class="text-center mt-4 small fw-bold opacity-25">
        &copy; <?= date('Y') ?> Umoja Drivers Sacco Ltd.
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
        const text = btn.querySelector('.btn-text');
        const spinner = btn.querySelector('.spinner-border');
        
        btn.disabled = true;
        spinner.classList.remove('d-none');
        text.textContent = 'Verifying Account...';
    });
</script>
</body>
</html>
