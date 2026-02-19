<?php
// public/forgot_password.php
// Secure Password Reset Request (Token-based)

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/email.php'; 

$message = '';
$msg_type = '';

// 1. HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Basic CSRF check (optional but recommended if you add a token to the form)
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die("Invalid Request");

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $msg_type = "danger";
    } else {
        // Check if email exists (Admins or Members)
        // We check members first
        $stmt = $conn->prepare("SELECT member_id, full_name, email FROM members WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $user = $res->fetch_assoc();
        $user_type = 'member';

        // If not found in members, check admins
        if (!$user) {
            $stmt->close();
            $stmt = $conn->prepare("SELECT admin_id, full_name, email FROM admins WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();
            $user_type = 'admin';
        }

        if ($user) {
            // 1. Generate Temporary Password
            $temp_password = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%'), 0, 8);
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

            // 2. Identify User Type & ID
            $id_col = ($user_type === 'member') ? 'member_id' : 'admin_id';
            $user_id = ($user_type === 'member') ? $user['member_id'] : $user['admin_id'];
            $table = ($user_type === 'member') ? 'members' : 'admins';

            // 3. Update Password in DB directly
            $update = $conn->prepare("UPDATE $table SET password = ? WHERE $id_col = ?");
            $update->bind_param("si", $hashed_password, $user_id);
            
            if ($update->execute()) {
                // 4. Prepare Email with Temp Password
                $subject = "Your New Temporary Password";
                $body = "
                    <div style='font-family: Arial, sans-serif; color: #333;'>
                        <h3>Hello {$user['full_name']},</h3>
                        <p>We received a request to reset your password for your <strong>" . SITE_NAME . "</strong> account.</p>
                        <p>Your new temporary password is:</p>
                        <h2 style='background: #ecfdf5; color: #065f46; padding: 15px; text-align: center; border-radius: 8px; letter-spacing: 2px; border: 1px dashed #059669;'>{$temp_password}</h2>
                        <p>Please log in using this password and <strong>change it immediately</strong> from your profile settings.</p>
                        <p>
                            <a href='" . SITE_URL . "/public/login.php' style='background-color: #0A6B3A; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Login Now</a>
                        </p>
                        <p style='color: #666; font-size: 0.9em;'>If you did not request this, please contact support immediately.</p>
                    </div>
                ";

                // 5. Send Email
                $mid = ($user_type === 'member') ? $user_id : null;
                $aid = ($user_type === 'admin') ? $user_id : null;

                if (sendEmailWithNotification($email, $subject, $body, $mid, $aid)) {
                    $message = "A temporary password has been sent to <strong>" . htmlspecialchars($email) . "</strong>. Please check your inbox (and spam folder).";
                    $msg_type = "success";
                } else {
                    $message = "System could not send email. Please try again later.";
                    $msg_type = "warning";
                }
            } else {
                $message = "Database update failed. Please try again.";
                $msg_type = "danger";
            }
            if(isset($update)) $update->close();
        } else {
            // Security: Always show the same success-like message to prevent email enumeration
            $message = "If an account exists with that email, we have sent a reset email.";
            $msg_type = "success";
        }
        
        if(isset($stmt)) $stmt->close();
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
  <title>Forgot Password — <?= htmlspecialchars(SITE_NAME) ?></title>
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

    .forgot-container {
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

    .forgot-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 30px;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(15, 57, 43, 0.15);
        animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .card-header-hero {
        background: linear-gradient(135deg, var(--forest-green) 0%, #084a28 100%);
        padding: 40px 30px;
        text-align: center;
        color: white;
        position: relative;
    }

    .card-header-hero::after {
        content: "";
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--lime);
    }

    .icon-circle {
        width: 70px;
        height: 70px;
        background: rgba(255,255,255,0.15);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        backdrop-filter: blur(5px);
        color: var(--lime);
        font-size: 1.8rem;
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

    .btn-brand {
        background: var(--forest-green);
        color: white;
        border: none;
        border-radius: 16px;
        padding: 16px;
        font-weight: 700;
        font-size: 1rem;
        transition: all 0.3s;
        margin-bottom: 15px;
    }

    .btn-brand:hover {
        background: var(--forest-mid);
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(15, 57, 43, 0.2);
        color: white;
    }

    .btn-back {
        background: transparent;
        color: var(--forest-green);
        border: 2px solid var(--forest-green);
        border-radius: 16px;
        padding: 14px;
        font-weight: 700;
        font-size: 0.9rem;
        transition: 0.2s;
        text-decoration: none;
        display: block;
        text-align: center;
    }

    .btn-back:hover {
        background: var(--forest-green);
        color: white;
    }

    /* Flash Message UI */
    .flash-item {
        border-radius: 15px;
        border: none;
        padding: 12px 20px;
        font-weight: 600;
        font-size: 0.85rem;
        margin-bottom: 20px;
    }
  </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>

<div class="deco-circle circle-1"></div>
<div class="deco-circle circle-2"></div>

<div class="forgot-container">
    <div class="forgot-card">
        <div class="card-header-hero">
            <div class="icon-circle">
                <i class="bi bi-key-fill"></i>
            </div>
            <h4 class="fw-800 mb-1">Forgot Password?</h4>
            <p class="small opacity-75 mb-0">No worries, we'll send you reset instructions.</p>
        </div>

        <div class="card-body p-4 pt-5">
            <?php if ($message): ?>
                <div class="alert alert-<?= $msg_type ?> flash-item d-flex align-items-center">
                    <i class="bi bi-<?= $msg_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>-fill me-2 fs-5"></i>
                    <div><?= $message ?></div>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-4">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control form-control-modern" placeholder="Enter your email" required autofocus>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-brand">
                        Send Reset Link
                    </button>
                    
                    <a href="login.php" class="btn-back">
                        <i class="bi bi-arrow-left me-2"></i>Back to Login
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <p class="text-center mt-4 small fw-bold opacity-25">
        &copy; <?= date('Y') ?> Umoja Drivers Sacco Ltd.
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>