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
            // Generate Secure Token
            $token = bin2hex(random_bytes(32)); 
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour
            
            // Determine ID column
            $id_col = ($user_type === 'member') ? 'member_id' : 'admin_id';
            $user_id = ($user_type === 'member') ? $user['member_id'] : $user['admin_id'];

            // Store Token in DB
            // NOTE: Ensure your 'password_resets' table exists. 
            // SQL: CREATE TABLE password_resets (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(150), token VARCHAR(255), expires_at DATETIME);
            // OR update your members/admins table to have `reset_token` and `reset_expires_at` columns.
            // Assuming we use a dedicated table for cleanliness:
            
            $del = $conn->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
            $del->bind_param("s", $email);
            $del->execute();

            $ins = $conn->prepare("INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $email, $token, $expiry);
            
            if ($ins->execute()) {
                // Prepare Email
                $reset_link = BASE_URL . "/public/reset_password.php?token=" . $token . "&email=" . urlencode($email);
                
                $subject = "Password Reset Request";
                $body = "
                    <div style='font-family: Arial, sans-serif; color: #333;'>
                        <h3>Hello {$user['full_name']},</h3>
                        <p>We received a request to reset your password for your <strong>" . SITE_NAME . "</strong> account.</p>
                        <p>Click the button below to set a new password:</p>
                        <p>
                            <a href='{$reset_link}' style='background-color: #0A6B3A; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
                        </p>
                        <p style='color: #666; font-size: 0.9em;'>This link will expire in 1 hour.</p>
                        <p style='color: #666; font-size: 0.9em;'>If you did not request this, you can safely ignore this email.</p>
                    </div>
                ";

                // Send
                if (sendEmailWithNotification($email, $subject, $body, $user_id, $user_type)) {
                    $message = "We have emailed a password reset link to <strong>" . htmlspecialchars($email) . "</strong>.";
                    $msg_type = "success";
                } else {
                    $message = "System could not send email. Please try again later.";
                    $msg_type = "warning";
                }
            }
        } else {
            // Security: Always show the same message even if email not found
            $message = "If an account exists with that email, we have sent a reset link.";
            $msg_type = "success";
        }
        
        if(isset($stmt)) $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            overflow: hidden;
            background: #fff;
        }
        .card-header-hero {
            background: linear-gradient(135deg, var(--brand-green) 0%, #084a28 100%);
            padding: 2.5rem 2rem;
            text-align: center;
            color: white;
            position: relative;
        }
        .card-header-hero::after {
            content: "";
            position: absolute; bottom: 0; left: 0; right: 0;
            height: 4px; background: var(--brand-accent);
        }
        .icon-circle {
            width: 70px; height: 70px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            backdrop-filter: blur(5px);
        }
        .btn-brand {
            background-color: var(--brand-green);
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
        }
        .btn-brand:hover {
            background-color: #064022;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(10, 107, 58, 0.25);
        }
        .form-control:focus {
            border-color: var(--brand-green);
            box-shadow: 0 0 0 0.25rem rgba(10, 107, 58, 0.15);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            
            <div class="auth-card">
                <div class="card-header-hero">
                    <div class="icon-circle">
                        <i class="bi bi-key-fill fs-2"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Forgot Password?</h4>
                    <p class="small opacity-75 mb-0">No worries, we'll send you reset instructions.</p>
                </div>

                <div class="card-body p-4 pt-5">
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $msg_type ?> border-0 d-flex align-items-center mb-4 shadow-sm">
                            <i class="bi bi-<?= $msg_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>-fill me-2 fs-5"></i>
                            <div><?= $message ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label for="email" class="form-label fw-bold small text-muted text-uppercase">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" id="email" class="form-control border-start-0 bg-light" placeholder="Enter your email" required autofocus>
                            </div>
                        </div>

                        <div class="d-grid gap-3">
                            <button type="submit" class="btn btn-brand">
                                Reset Password
                            </button>
                            
                            <a href="login.php" class="btn btn-light text-muted fw-medium py-2">
                                <i class="bi bi-arrow-left me-2"></i>Back to Login
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="text-center mt-4">
                <small class="text-muted">&copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. All rights reserved.</small>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>