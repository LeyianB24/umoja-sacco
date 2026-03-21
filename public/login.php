<?php
ob_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/Auth.php';

define('REMEMBER_SECONDS', 30 * 24 * 60 * 60);
define('COOKIE_NAME', 'usms_rem');
$cookie_secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

function verifyAndUpgradePassword($conn, $table, $id_col, $id_val, $input_pass, $stored_hash) {
    $valid = false; $needs_rehash = false;
    if (!empty($stored_hash) && password_verify($input_pass, $stored_hash)) {
        $valid = true;
        if (password_needs_rehash($stored_hash, PASSWORD_DEFAULT)) $needs_rehash = true;
    } elseif (!empty($stored_hash) && hash('sha256', $input_pass) === $stored_hash) {
        $valid = true; $needs_rehash = true;
    } elseif ($stored_hash === $input_pass) {
        $valid = true; $needs_rehash = true;
    }
    if ($valid && $needs_rehash) {
        $new_hash = password_hash($input_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE $id_col = ?");
        $stmt->bind_param('si', $new_hash, $id_val);
        $stmt->execute(); $stmt->close();
    }
    return $valid;
}

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
                    header("Location: " . BASE_URL . '/admin/pages/dashboard.php'); exit;
                }
            }
            $stmt_admin->close();

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
                        $_SESSION['role'] = 'member';
                        if (($member['registration_fee_status'] ?? 'unpaid') === 'unpaid') {
                            $_SESSION['pending_pay'] = true;
                            flash_set('Payment Required: Please settle your registration fee to access the dashboard.', 'warning');
                            header("Location: " . BASE_URL . "/member/pages/pay_registration.php"); exit;
                        }
                        flash_set('Welcome, ' . $_SESSION['member_name'] . '! Access your portal.', 'success');
                        header("Location: " . BASE_URL . "/member/pages/dashboard.php"); exit;
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
    *, *::before, *::after { box-sizing: border-box; }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        min-height: 100vh;
        margin: 0;
        display: flex;
        align-items: stretch;
        overflow: hidden;
    }

    /* ─── Left Panel ─── */
    .login-left {
        flex: 1;
        background:
            linear-gradient(155deg, rgba(11,30,22,0.92) 0%, rgba(15,57,43,0.88) 60%, rgba(10,24,18,0.95) 100%),
            url('<?= BACKGROUND_IMAGE ?>') center/cover no-repeat;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: space-between;
        padding: 48px 52px;
        position: relative;
        overflow: hidden;
    }
    .login-left::before {
        content: '';
        position: absolute;
        top: -120px; right: -120px;
        width: 420px; height: 420px;
        background: radial-gradient(circle, rgba(57,181,74,0.18) 0%, transparent 65%);
        border-radius: 50%;
        pointer-events: none;
    }
    .login-left::after {
        content: '';
        position: absolute;
        bottom: -80px; left: -60px;
        width: 300px; height: 300px;
        background: radial-gradient(circle, rgba(163,230,53,0.1) 0%, transparent 65%);
        border-radius: 50%;
        pointer-events: none;
    }

    .ll-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        position: relative;
        z-index: 2;
    }
    .ll-brand-logo {
        width: 46px; height: 46px;
        border-radius: 13px;
        background: #fff;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(0,0,0,0.25);
    }
    .ll-brand-logo img { width: 100%; height: 100%; object-fit: contain; padding: 6px; }
    .ll-brand-name {
        font-size: 0.9rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.2px;
    }
    .ll-brand-sub {
        font-size: 0.62rem;
        font-weight: 600;
        color: rgba(255,255,255,0.4);
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }

    .ll-hero { position: relative; z-index: 2; }
    .ll-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: rgba(163,230,53,0.12);
        border: 1px solid rgba(163,230,53,0.22);
        border-radius: 100px;
        padding: 5px 13px;
        font-size: 0.67rem;
        font-weight: 700;
        color: #A3E635;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        margin-bottom: 18px;
    }
    .ll-eyebrow-dot {
        width: 6px; height: 6px;
        border-radius: 50%;
        background: #A3E635;
        animation: llPulse 2s ease-in-out infinite;
    }
    @keyframes llPulse {
        0%,100% { opacity: 1; } 50% { opacity: 0.4; }
    }
    .ll-title {
        font-size: 2.8rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: -1px;
        line-height: 1.08;
        margin-bottom: 16px;
    }
    .ll-title span { color: #A3E635; }
    .ll-desc {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.5);
        font-weight: 500;
        line-height: 1.7;
        max-width: 360px;
        margin-bottom: 32px;
    }
    .ll-stats {
        display: flex;
        gap: 20px;
    }
    .ll-stat {
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 14px;
        padding: 14px 20px;
        text-align: center;
        min-width: 90px;
    }
    .ll-stat-num {
        font-size: 1.4rem;
        font-weight: 800;
        color: #A3E635;
        line-height: 1;
        margin-bottom: 4px;
    }
    .ll-stat-label {
        font-size: 0.62rem;
        font-weight: 600;
        color: rgba(255,255,255,0.35);
        text-transform: uppercase;
        letter-spacing: 0.7px;
    }

    .ll-footer {
        position: relative;
        z-index: 2;
        font-size: 0.7rem;
        color: rgba(255,255,255,0.2);
        font-weight: 600;
    }

    /* ─── Right Panel ─── */
    .login-right {
        width: 480px;
        min-width: 480px;
        background: #F7FBF9;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 48px 52px;
        position: relative;
        overflow-y: auto;
    }
    .login-right::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 4px; height: 100%;
        background: linear-gradient(to bottom, #0F392B, #39B54A, #A3E635);
    }

    .lr-form-wrap { width: 100%; max-width: 360px; }

    .lr-heading {
        margin-bottom: 32px;
    }
    .lr-heading h2 {
        font-size: 1.75rem;
        font-weight: 800;
        color: #0F392B;
        letter-spacing: -0.5px;
        margin: 0 0 6px;
    }
    .lr-heading p {
        font-size: 0.84rem;
        color: #7a9e8e;
        font-weight: 500;
        margin: 0;
    }

    /* Flash */
    .lr-flash {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 11px 15px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 22px;
        animation: flashIn 0.3s ease both;
    }
    @keyframes flashIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
    .lr-flash-success { background: #D1FAE5; color: #065f46; border: 1px solid #A7F3D0; }
    .lr-flash-error   { background: #FEE2E2; color: #991b1b; border: 1px solid #FECACA; }
    .lr-flash-warning { background: #FFFBEB; color: #92400e; border: 1px solid #FDE68A; }
    .lr-flash i { font-size: 0.95rem; flex-shrink: 0; }

    /* Labels */
    .lr-label {
        display: block;
        font-size: 0.67rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        color: #7a9e8e;
        margin-bottom: 7px;
    }

    /* Inputs */
    .lr-input-wrap {
        background: #fff;
        border: 1.5px solid #E0EDE7;
        border-radius: 13px;
        display: flex;
        align-items: center;
        overflow: hidden;
        transition: all 0.2s;
        margin-bottom: 18px;
    }
    .lr-input-wrap:focus-within {
        border-color: #39B54A;
        box-shadow: 0 0 0 4px rgba(57,181,74,0.09);
    }
    .lr-input-pfx {
        padding: 12px 14px;
        color: #a0b8b0;
        font-size: 0.95rem;
        background: #F7FBF9;
        border-right: 1.5px solid #E0EDE7;
        flex-shrink: 0;
        display: flex; align-items: center;
    }
    .lr-input {
        flex: 1;
        border: none;
        outline: none;
        background: transparent;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.875rem;
        font-weight: 600;
        color: #0F392B;
        padding: 12px 14px;
    }
    .lr-input::placeholder { color: #b8cec8; font-weight: 500; }
    .lr-toggle-btn {
        padding: 12px 14px;
        color: #a0b8b0;
        cursor: pointer;
        font-size: 0.9rem;
        flex-shrink: 0;
        transition: color 0.2s;
        background: transparent;
        border: none;
        outline: none;
    }
    .lr-toggle-btn:hover { color: #0F392B; }

    /* Remember + Forgot row */
    .lr-meta-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        gap: 10px;
    }
    .lr-check-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    .lr-check-wrap input[type="checkbox"] {
        width: 16px; height: 16px;
        accent-color: #39B54A;
        cursor: pointer;
    }
    .lr-check-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #5a7a6e;
        cursor: pointer;
    }
    .lr-forgot {
        font-size: 0.8rem;
        font-weight: 700;
        color: #0F392B;
        text-decoration: none;
        border-bottom: 1.5px solid #A3E635;
        padding-bottom: 1px;
        transition: opacity 0.2s;
    }
    .lr-forgot:hover { opacity: 0.65; }

    /* Submit Button */
    .lr-submit {
        width: 100%;
        background: linear-gradient(135deg, #0F392B, #1a5c43);
        color: #fff;
        border: none;
        border-radius: 13px;
        padding: 14px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.9rem;
        font-weight: 800;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 6px 20px rgba(15,57,43,0.28);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        letter-spacing: 0.2px;
    }
    .lr-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(15,57,43,0.36); }
    .lr-submit:active { transform: translateY(0); }
    .lr-submit:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
    .lr-submit .spinner-border { width: 1rem; height: 1rem; border-width: 0.15em; }

    /* Register Footer */
    .lr-register {
        text-align: center;
        margin-top: 26px;
        font-size: 0.83rem;
        font-weight: 600;
        color: #7a9e8e;
    }
    .lr-register a {
        color: #0F392B;
        font-weight: 800;
        text-decoration: none;
        border-bottom: 2px solid #A3E635;
        padding-bottom: 1px;
        transition: opacity 0.2s;
    }
    .lr-register a:hover { opacity: 0.65; }

    /* Divider */
    .lr-divider {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #c8ddd6;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin: 22px 0;
    }
    .lr-divider::before, .lr-divider::after {
        content: ''; flex: 1;
        height: 1px; background: #E0EDE7;
    }

    /* Responsive */
    @media (max-width: 900px) {
        .login-left { display: none; }
        .login-right { width: 100%; min-width: 0; padding: 40px 28px; }
    }
    </style>
</head>
<body>

<!-- Left Panel -->
<div class="login-left">
    <a href="<?= BASE_URL ?>/public/index.php" class="ll-brand text-decoration-none">
        <div class="ll-brand-logo">
            <img src="<?= SITE_LOGO ?>" alt="<?= SITE_NAME ?>">
        </div>
        <div>
            <div class="ll-brand-name"><?= htmlspecialchars(SITE_NAME) ?></div>
            <div class="ll-brand-sub">Unified Management System</div>
        </div>
    </a>

    <div class="ll-hero">
        <div class="ll-eyebrow">
            <span class="ll-eyebrow-dot"></span> Secure Portal
        </div>
        <h1 class="ll-title">
            Manage your<br>Sacco with<br><span>precision.</span>
        </h1>
        <p class="ll-desc">
            A unified platform for members, admins, and managers — tracking loans, savings, and communication in real time.
        </p>
        <div class="ll-stats">
            <div class="ll-stat">
                <div class="ll-stat-num">100%</div>
                <div class="ll-stat-label">Secure</div>
            </div>
            <div class="ll-stat">
                <div class="ll-stat-num">24/7</div>
                <div class="ll-stat-label">Access</div>
            </div>
            <div class="ll-stat">
                <div class="ll-stat-num">Real‑Time</div>
                <div class="ll-stat-label">Data</div>
            </div>
        </div>
    </div>

    <div class="ll-footer">
        &copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. All rights reserved.
    </div>
</div>

<!-- Right Panel -->
<div class="login-right">
    <div class="lr-form-wrap">

        <div class="lr-heading">
            <h2>Welcome back.</h2>
            <p>Sign in to access your portal and manage your account.</p>
        </div>

        <?php
        // Render flash messages with custom styling
        $flash = flash_get();
        if ($flash):
            $flashIcons = ['success'=>'bi-check-circle-fill','error'=>'bi-exclamation-triangle-fill','warning'=>'bi-exclamation-circle-fill','info'=>'bi-info-circle-fill'];
            $flashClasses = ['success'=>'lr-flash-success','error'=>'lr-flash-error','warning'=>'lr-flash-warning','info'=>'lr-flash-success'];
            $type = $flash['type'] ?? 'info';
        ?>
            <div class="lr-flash <?= $flashClasses[$type] ?? 'lr-flash-error' ?>">
                <i class="bi <?= $flashIcons[$type] ?? 'bi-info-circle-fill' ?>"></i>
                <?= htmlspecialchars($flash['message'] ?? '') ?>
            </div>
        <?php else: flash_render(); endif; ?>

        <form method="post" id="loginForm" novalidate>
            <?= csrf_field() ?>

            <div class="mb-1">
                <label class="lr-label">Email or Username</label>
                <div class="lr-input-wrap">
                    <span class="lr-input-pfx"><i class="bi bi-person-fill"></i></span>
                    <input type="text" name="email" class="lr-input" required
                        placeholder="your@email.com or member ID"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-1">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="lr-label" style="margin-bottom:0;">Password</label>
                    <a href="forgot_password.php" class="lr-forgot">Forgot password?</a>
                </div>
                <div class="lr-input-wrap">
                    <span class="lr-input-pfx"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" name="password" id="passwordInput" class="lr-input" required placeholder="••••••••">
                    <button type="button" class="lr-toggle-btn" onclick="togglePassword()" id="toggleBtn">
                        <i class="bi bi-eye-slash" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <div class="lr-meta-row">
                <label class="lr-check-wrap">
                    <input type="checkbox" name="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                    <span class="lr-check-label">Keep me logged in</span>
                </label>
            </div>

            <button type="submit" class="lr-submit" id="loginBtn">
                <span id="btnText">Access Portal</span>
                <div class="spinner-border d-none" role="status" id="btnSpinner"></div>
            </button>

        </form>

        <div class="lr-register">
            Don't have an account? <a href="register.php">Create Account</a>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword() {
    const input = document.getElementById('passwordInput');
    const icon  = document.getElementById('toggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    }
}

document.getElementById('loginForm').addEventListener('submit', function() {
    const btn     = document.getElementById('loginBtn');
    const text    = document.getElementById('btnText');
    const spinner = document.getElementById('btnSpinner');
    btn.disabled = true;
    spinner.classList.remove('d-none');
    text.textContent = 'Verifying…';
});
</script>
</body>
</html>