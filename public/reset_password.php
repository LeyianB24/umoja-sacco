<?php
// public/reset_password.php
// Actual Password Reset Form
session_start();
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/functions.php';

$token = $_GET['token'] ?? '';
$type  = $_GET['type'] ?? '';
$uid   = intval($_GET['uid'] ?? 0);

$message = '';
$msg_type = '';
$valid_request = false;

if ($token && $type && $uid) {
    // Verify Token
    $stmt = $conn->prepare("SELECT token_id FROM password_reset_tokens WHERE user_type = ? AND user_id = ? AND token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->bind_param("sis", $type, $uid, $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $valid_request = true;
    } else {
        $message = "Invalid or expired reset link. Please request a new one.";
        $msg_type = "danger";
    }
    $stmt->close();
} else {
    $message = "Critical: Missing reset parameters.";
    $msg_type = "danger";
}

// Handle Form Submission
if ($valid_request && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($pass) < 6) {
        $message = "Password must be at least 6 characters.";
        $msg_type = "warning";
    } elseif ($pass !== $confirm) {
        $message = "Passwords do not match.";
        $msg_type = "warning";
    } else {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $table = ($type === 'admin') ? 'admins' : 'members';
        $pcol = ($type === 'admin') ? 'admin_id' : 'member_id';

        $conn->begin_transaction();
        try {
            // Update Password
            $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE $pcol = ?");
            $stmt->bind_param("si", $hashed, $uid);
            $stmt->execute();

            // Clear Token
            $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_type = ? AND user_id = ?");
            $stmt->bind_param("si", $type, $uid);
            $stmt->execute();

            $conn->commit();
            
            $_SESSION['flash'] = ["message" => "Password updated successfully. You can now login.", "type" => "success"];
            header("Location: login.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Reset failed: " . $e->getMessage();
            $msg_type = "danger";
        }
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
  <title>Reset Password — <?= htmlspecialchars(SITE_NAME) ?></title>
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
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%), url("https://www.transparenttextures.com/patterns/cubes.png");
        min-height: 100vh; display: flex; align-items: center; justify-content: center; overflow-x: hidden;
    }
    .reset-container { width: 100%; max-width: 450px; padding: 20px; position: relative; }
    .deco-circle { position: absolute; width: 300px; height: 300px; border-radius: 50%; background: var(--lime); filter: blur(80px); opacity: 0.15; z-index: -1; }
    .circle-1 { top: -100px; right: -150px; }
    .circle-2 { bottom: -100px; left: -150px; background: var(--forest-green); }
    .reset-card { background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); border-radius: 30px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(15, 57, 43, 0.15); animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
    @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    .card-header-hero { background: linear-gradient(135deg, var(--forest-green) 0%, #084a28 100%); padding: 40px 30px; text-align: center; color: white; position: relative; }
    .card-header-hero::after { content: ""; position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: var(--lime); }
    .icon-circle { width: 70px; height: 70px; background: rgba(255,255,255,0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; backdrop-filter: blur(5px); color: var(--lime); font-size: 1.8rem; }
    .form-label { font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--forest-mid); margin-bottom: 8px; opacity: 0.7; }
    .form-control-modern { background: #f1f3f5 !important; border: 2px solid transparent !important; border-radius: 16px !important; padding: 14px 20px !important; transition: all 0.3s !important; font-weight: 600; }
    .form-control-modern:focus { background: white !important; border-color: var(--forest-green) !important; box-shadow: 0 0 0 5px rgba(15, 57, 43, 0.05) !important; }
    .btn-brand { background: var(--forest-green); color: white; border: none; border-radius: 16px; padding: 16px; font-weight: 700; font-size: 1rem; transition: all 0.3s; margin-bottom: 15px; }
    .btn-brand:hover { background: var(--forest-mid); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(15, 57, 43, 0.2); color: white; }
    .flash-item { border-radius: 15px; border: none; padding: 12px 20px; font-weight: 600; font-size: 0.85rem; margin-bottom: 20px; }
  </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>
<div class="deco-circle circle-1"></div>
<div class="deco-circle circle-2"></div>
<div class="reset-container">
    <div class="reset-card">
        <div class="card-header-hero">
            <div class="icon-circle"><i class="bi bi-shield-lock"></i></div>
            <h4 class="fw-800 mb-1">Reset Password</h4>
            <p class="small opacity-75 mb-0">Set a strong new password for your account.</p>
        </div>
        <div class="card-body p-4 pt-5">
            <?php if ($message): ?>
                <div class="alert alert-<?= $msg_type ?> flash-item d-flex align-items-center">
                    <i class="bi bi-<?= $msg_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>-fill me-2 fs-5"></i>
                    <div><?= $message ?></div>
                </div>
            <?php endif; ?>

            <?php if ($valid_request): ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control form-control-modern" placeholder="••••••••" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control form-control-modern" placeholder="••••••••" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-brand">Update Password</button>
                    <a href="login.php" class="text-center text-decoration-none small fw-bold text-forest opacity-50">Back to Login</a>
                </div>
            </form>
            <?php else: ?>
                <div class="text-center py-3">
                    <a href="forgot_password.php" class="btn btn-brand w-100">Request New Link</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <p class="text-center mt-4 small fw-bold opacity-25">&copy; <?= date('Y') ?> Umoja Drivers Sacco Ltd.</p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
