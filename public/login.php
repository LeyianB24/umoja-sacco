<?php
// public/login.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';

if (!defined('REMEMBER_SECONDS')) define('REMEMBER_SECONDS', 30 * 24 * 60 * 60);
if (!defined('COOKIE_NAME'))     define('COOKIE_NAME',     'usms_rem');
$cookie_secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

// Handle redirection parameter
$redirect_to = $_GET['redirect_to'] ?? $_POST['redirect_to'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if ($email === '' || $password === '') {
        flash_set('Please enter both email/username and password.', 'error');
    } else {
        $validator = new \USMS\Services\Validator($_POST);
        $validator->required('email')->required('password');
        
        if ($validator->passes()) {
            $user_found = false;

            // 1. Try Admin Login
            $stmt_admin = $conn->prepare("SELECT a.admin_id, a.full_name, a.username, a.role_id, r.name as role_name, a.password FROM admins a JOIN roles r ON a.role_id = r.id WHERE a.email = ? OR a.username = ? LIMIT 1");
            $stmt_admin->bind_param('ss', $email, $email);
            $stmt_admin->execute();
            $res_admin = $stmt_admin->get_result();

            if ($res_admin && $res_admin->num_rows > 0) {
                $admin = $res_admin->fetch_assoc();
                if (verifyAndUpgradePassword($conn, 'admins', 'admin_id', $admin['admin_id'], $password, $admin['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['admin_id']   = $admin['admin_id'];
                    $_SESSION['admin_name'] = !empty($admin['full_name']) ? $admin['full_name'] : $admin['username'];
                    $_SESSION['role_id']    = $admin['role_id'];
                    $_SESSION['role']       = strtolower($admin['role_name']);
                    $_SESSION['role_name']  = $admin['role_name'];
                    
                    Auth::loadPermissions($admin['role_id']);
                    
                    if ($_SESSION['role_id'] == 1) {
                        $res_all = $conn->query("SELECT slug FROM permissions");
                        $all_perms = [];
                        while($p = $res_all->fetch_assoc()) $all_perms[] = $p['slug'];
                        $_SESSION['permissions'] = $all_perms;
                    }
                    
                    $user_found = true;
                    if ($remember) {
                        $rnd = bin2hex(random_bytes(32));
                        $token_hash = hash('sha256', $rnd);
                        $ua_hash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
                        $expires = date('Y-m-d H:i:s', time() + REMEMBER_SECONDS);
                        $up = $conn->prepare("UPDATE admins SET remember_token=?, remember_expires=?, remember_ua=? WHERE admin_id=?");
                        $up->bind_param('sssi', $token_hash, $expires, $ua_hash, $admin['admin_id']);
                        $up->execute(); $up->close();
                        setcookie(COOKIE_NAME, $rnd, time() + REMEMBER_SECONDS, '/', '', $cookie_secure, true);
                    }
                    
                    flash_set('Welcome back, ' . $_SESSION['admin_name'] . '!', 'success');
                    
                    // Specific Redirection logic for Admins
                    $final_redirect = !empty($redirect_to) ? $redirect_to : BASE_URL . '/admin/pages/dashboard.php';
                    header("Location: " . $final_redirect); 
                    exit;
                }
            }
            $stmt_admin->close();

            // 2. Try Member Login
            if (!$user_found) {
                $stmt_member = $conn->prepare("SELECT member_id, full_name, member_reg_no, password, registration_fee_status FROM members WHERE email = ? OR member_reg_no = ? LIMIT 1");
                $stmt_member->bind_param('ss', $email, $email);
                $stmt_member->execute();
                $res_member = $stmt_member->get_result();
                
                if ($res_member && $res_member->num_rows > 0) {
                    $member = $res_member->fetch_assoc();
                    if (verifyAndUpgradePassword($conn, 'members', 'member_id', $member['member_id'], $password, $member['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['member_id']   = $member['member_id'];
                        $_SESSION['member_name'] = $member['full_name'];
                        $_SESSION['reg_no']      = $member['member_reg_no'];
                        $_SESSION['role']        = 'member';
                        
                        // REMOVED: registration_fee_status check detour
                        
                        $user_found = true;
                        flash_set('Welcome, ' . $_SESSION['member_name'] . '! Access your portal.', 'success');
                        
                        // Specific Redirection logic for Members
                        $final_redirect = !empty($redirect_to) ? $redirect_to : BASE_URL . "/member/pages/dashboard.php";
                        header("Location: " . $final_redirect); 
                        exit;
                    }
                }
                $stmt_member->close();
            }

            if (!$user_found) flash_set('Invalid login credentials. Please double-check your details.', 'error');
        } else {
            flash_set('Validation failed: ' . implode(', ', $validator->getFirstErrors()), 'error');
        }
    }
}

// Check if user is already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "/admin/pages/dashboard.php"); exit;
}
if (isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/member/pages/dashboard.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <style>
    :root {
        --forest: #0F392B;
        --forest-mid: #1a5c43;
        --lime: #A3E635;
        --gold: #F5C842;
        --white: #ffffff;
        --text-muted: rgba(255,255,255,0.6);
        --radius-lg: 24px;
        --radius-md: 16px;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background-color: #f8fafc;
        min-height: 100vh;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .login-container {
        max-width: 1000px;
        width: 100%;
        background: #fff;
        border-radius: var(--radius-lg);
        overflow: hidden;
        display: flex;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
    }

    /* ── Left Branding Panel ── */
    .login-brand {
        flex: 1;
        background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%);
        padding: 60px;
        color: #fff;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }

    .login-brand::after {
        content: '';
        position: absolute;
        top: -100px;
        right: -100px;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(163,230,53,0.1) 0%, transparent 70%);
        border-radius: 50%;
    }

    .brand-content h2 {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 20px;
        line-height: 1.1;
    }

    .brand-content h2 span { color: var(--lime); }
    .brand-content p { color: var(--text-muted); font-size: 1.1rem; line-height: 1.6; }

    .brand-stats {
        display: flex;
        gap: 30px;
        margin-top: auto;
    }

    .stat-item .val { font-size: 1.5rem; font-weight: 800; color: var(--lime); display: block; }
    .stat-item .lbl { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); }

    /* ── Right Form Panel ── */
    .login-form-area {
        flex: 1;
        padding: 60px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .form-header { margin-bottom: 40px; }
    .form-header h3 { font-weight: 800; color: var(--forest); font-size: 1.75rem; }
    .form-header p { color: #64748b; font-weight: 500; }

    .form-control {
        padding: 14px 20px;
        border-radius: 12px;
        border: 1.5px solid #e2e8f0;
        font-weight: 500;
        transition: all 0.2s;
    }

    .form-control:focus {
        border-color: var(--forest);
        box-shadow: 0 0 0 4px rgba(15, 57, 43, 0.05);
    }

    .input-group-text {
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-right: none;
        border-radius: 12px 0 0 12px;
        color: #64748b;
    }

    .input-group .form-control { border-left: none; border-radius: 0 12px 12px 0; }

    .btn-login {
        background: var(--forest);
        color: #fff;
        border: none;
        padding: 14px;
        border-radius: 12px;
        font-weight: 800;
        font-size: 1rem;
        transition: all 0.2s;
        margin-top: 10px;
    }

    .btn-login:hover { background: var(--forest-mid); transform: translateY(-1px); box-shadow: 0 10px 15px -3px rgba(15, 57, 43, 0.2); }

    .alert { border-radius: 12px; font-weight: 600; font-size: 0.9rem; border: none; }

    @media (max-width: 991px) {
        .login-container { flex-direction: column; max-width: 500px; }
        .login-brand { padding: 40px; }
        .login-form-area { padding: 40px; }
        .brand-stats { display: none; }
    }
    </style>
</head>
<body>

<div class="login-container">
    <!-- Brand Panel -->
    <div class="login-brand">
        <div class="brand-content">
            <a href="<?= BASE_URL ?>/" class="text-white text-decoration-none d-flex align-items-center mb-5">
                <i class="bi bi-shield-fill-check fs-2 me-2 text-lime"></i>
                <span class="fw-800 fs-4 tracking-tight"><?= strtoupper(SITE_NAME) ?></span>
            </a>
            <h2>Secure<br>Access to your<br><span>Wealth.</span></h2>
            <p>Enter your credentials to manage your savings, shares, and loans in the ultimate Sacco ecosystem.</p>
        </div>

        <div class="brand-stats">
            <div class="stat-item">
                <span class="val">100%</span>
                <span class="lbl">Secure</span>
            </div>
            <div class="stat-item">
                <span class="val">24/7</span>
                <span class="lbl">Access</span>
            </div>
            <div class="stat-item">
                <span class="val">ACID</span>
                <span class="lbl">Ledger</span>
            </div>
        </div>
    </div>

    <!-- Form Panel -->
    <div class="login-form-area">
        <div class="form-header">
            <h3>Welcome back</h3>
            <p>Please enter your account details</p>
        </div>

        <?php flash_render(); ?>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirect_to) ?>">

            <div class="mb-4">
                <label class="form-label fw-bold text-dark small">Email or Registration No.</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="email" class="form-control" placeholder="name@example.com" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between">
                    <label class="form-label fw-bold text-dark small">Password</label>
                    <a href="forgot_password.php" class="text-decoration-none small fw-bold" style="color: var(--forest-mid)">Forgot?</a>
                </div>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label small fw-semibold" for="remember">
                        Remember this device
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-login w-100 mb-3">
                Sign In to Dashboard
            </button>

            <div class="text-center mt-4">
                <p class="text-muted small">Don't have an account? <a href="register.php" class="fw-bold text-decoration-none" style="color: var(--forest-mid)">Join Umoja Drivers Sacco</a></p>
            </div>
        </form>
    </div>
</div>

</body>
</html>
