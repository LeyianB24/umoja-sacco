<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../inc/email.php';

$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $msg_type = "error";
    } else {
        $stmt = $conn->prepare("SELECT member_id, full_name, email FROM members WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $user_type = 'member';

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
            $temp_password = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%'), 0, 8);
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            $id_col  = ($user_type === 'member') ? 'member_id' : 'admin_id';
            $user_id = ($user_type === 'member') ? $user['member_id'] : $user['admin_id'];
            $table   = ($user_type === 'member') ? 'members' : 'admins';

            $update = $conn->prepare("UPDATE $table SET password = ? WHERE $id_col = ?");
            $update->bind_param("si", $hashed_password, $user_id);

            if ($update->execute()) {
                $subject = "Your New Temporary Password";
                $body = "
                    <div style='font-family:Arial,sans-serif;color:#333;'>
                        <h3>Hello {$user['full_name']},</h3>
                        <p>We received a request to reset your password for your <strong>" . SITE_NAME . "</strong> account.</p>
                        <p>Your new temporary password is:</p>
                        <h2 style='background:#ecfdf5;color:#065f46;padding:15px;text-align:center;border-radius:8px;letter-spacing:2px;border:1px dashed #059669;'>{$temp_password}</h2>
                        <p>Please log in using this password and <strong>change it immediately</strong> from your profile settings.</p>
                        <p><a href='" . SITE_URL . "/public/login.php' style='background-color:#0A6B3A;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>Login Now</a></p>
                        <p style='color:#666;font-size:0.9em;'>If you did not request this, please contact support immediately.</p>
                    </div>
                ";
                $mid = ($user_type === 'member') ? $user_id : null;
                $aid = ($user_type === 'admin')  ? $user_id : null;

                if (sendEmailWithNotification($email, $subject, $body, $mid, $aid)) {
                    $message = "A temporary password has been sent to <strong>" . htmlspecialchars($email) . "</strong>. Please check your inbox and spam folder.";
                    $msg_type = "success";
                } else {
                    $message = "System could not send the email. Please try again later.";
                    $msg_type = "warning";
                }
            } else {
                $message = "Database update failed. Please try again.";
                $msg_type = "error";
            }
            if (isset($update)) $update->close();
        } else {
            $message = "If an account exists with that email, a reset email has been sent.";
            $msg_type = "success";
        }
        if (isset($stmt)) $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        min-height: 100vh;
        display: flex;
        align-items: stretch;
        background: #0B1E17;
    }

    /* ─── Left Panel ─── */
    .fp-left {
        flex: 1;
        background:
            linear-gradient(155deg, rgba(11,30,22,0.93) 0%, rgba(15,57,43,0.89) 60%, rgba(10,24,18,0.96) 100%),
            url('<?= BACKGROUND_IMAGE ?>') center/cover no-repeat;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: space-between;
        padding: 48px 52px;
        position: relative;
        overflow: hidden;
    }
    .fp-left::before {
        content: '';
        position: absolute;
        top: -100px; right: -100px;
        width: 380px; height: 380px;
        background: radial-gradient(circle, rgba(57,181,74,0.17) 0%, transparent 65%);
        border-radius: 50%;
        pointer-events: none;
    }
    .fp-left::after {
        content: '';
        position: absolute;
        bottom: -80px; left: -60px;
        width: 280px; height: 280px;
        background: radial-gradient(circle, rgba(163,230,53,0.09) 0%, transparent 65%);
        border-radius: 50%;
        pointer-events: none;
    }

    .fl-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        position: relative;
        z-index: 2;
    }
    .fl-brand-logo {
        width: 46px; height: 46px;
        border-radius: 13px;
        background: #fff;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(0,0,0,0.25);
        flex-shrink: 0;
    }
    .fl-brand-logo img { width: 100%; height: 100%; object-fit: contain; padding: 6px; }
    .fl-brand-name { font-size: 0.9rem; font-weight: 800; color: #fff; letter-spacing: -0.2px; }
    .fl-brand-sub  { font-size: 0.62rem; font-weight: 600; color: rgba(255,255,255,0.38); text-transform: uppercase; letter-spacing: 0.8px; }

    .fl-hero { position: relative; z-index: 2; }
    .fl-icon-wrap {
        width: 72px; height: 72px;
        border-radius: 20px;
        background: rgba(163,230,53,0.1);
        border: 1px solid rgba(163,230,53,0.2);
        display: flex; align-items: center; justify-content: center;
        color: #A3E635;
        font-size: 1.8rem;
        margin-bottom: 22px;
        box-shadow: 0 8px 28px rgba(0,0,0,0.2);
    }
    .fl-title {
        font-size: 2.4rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.8px;
        line-height: 1.1;
        margin-bottom: 14px;
    }
    .fl-title span { color: #A3E635; }
    .fl-desc {
        font-size: 0.88rem;
        color: rgba(255,255,255,0.5);
        font-weight: 500;
        line-height: 1.75;
        max-width: 360px;
        margin-bottom: 32px;
    }

    /* How it works */
    .fl-steps { display: flex; flex-direction: column; gap: 16px; }
    .fl-step {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    .fl-step-icon {
        width: 32px; height: 32px;
        border-radius: 9px;
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.08);
        display: flex; align-items: center; justify-content: center;
        color: #A3E635;
        font-size: 0.85rem;
        flex-shrink: 0;
        margin-top: 1px;
    }
    .fl-step-title { font-size: 0.84rem; font-weight: 700; color: rgba(255,255,255,0.75); margin-bottom: 2px; }
    .fl-step-sub   { font-size: 0.72rem; color: rgba(255,255,255,0.33); font-weight: 500; }

    .fl-footer {
        position: relative;
        z-index: 2;
        font-size: 0.68rem;
        color: rgba(255,255,255,0.18);
        font-weight: 600;
    }

    /* ─── Right Panel ─── */
    .fp-right {
        width: 480px;
        min-width: 480px;
        background: #F7FBF9;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 52px;
        position: relative;
        overflow-y: auto;
    }
    .fp-right::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 4px; height: 100%;
        background: linear-gradient(to bottom, #0F392B, #39B54A, #A3E635);
    }

    .fr-wrap { width: 100%; max-width: 360px; }

    /* Heading */
    .fr-heading { margin-bottom: 28px; }
    .fr-heading h2 {
        font-size: 1.7rem;
        font-weight: 800;
        color: #0F392B;
        letter-spacing: -0.4px;
        margin: 0 0 6px;
    }
    .fr-heading p { font-size: 0.84rem; color: #7a9e8e; font-weight: 500; margin: 0; }

    /* Flash */
    .fr-flash {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 13px 16px;
        border-radius: 13px;
        font-size: 0.82rem;
        font-weight: 600;
        margin-bottom: 24px;
        animation: frFlashIn 0.35s ease both;
        line-height: 1.55;
    }
    @keyframes frFlashIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
    .fr-flash-success { background: #D1FAE5; color: #065f46; border: 1px solid #A7F3D0; }
    .fr-flash-error   { background: #FEE2E2; color: #991b1b; border: 1px solid #FECACA; }
    .fr-flash-warning { background: #FFFBEB; color: #92400e; border: 1px solid #FDE68A; }
    .fr-flash i { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }

    /* Success State */
    .fr-success-state {
        text-align: center;
        padding: 12px 0 24px;
        animation: frFlashIn 0.4s ease both;
    }
    .fr-success-icon {
        width: 64px; height: 64px;
        border-radius: 18px;
        background: #D1FAE5;
        border: 1px solid #A7F3D0;
        display: flex; align-items: center; justify-content: center;
        color: #059669;
        font-size: 1.6rem;
        margin: 0 auto 16px;
    }
    .fr-success-state h4 {
        font-size: 1rem;
        font-weight: 800;
        color: #0F392B;
        margin-bottom: 8px;
    }
    .fr-success-state p {
        font-size: 0.82rem;
        color: #7a9e8e;
        font-weight: 500;
        line-height: 1.65;
        margin-bottom: 20px;
    }

    /* Label */
    .fr-label {
        display: block;
        font-size: 0.67rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        color: #7a9e8e;
        margin-bottom: 7px;
    }

    /* Input */
    .fr-input-wrap {
        display: flex;
        align-items: center;
        background: #fff;
        border: 1.5px solid #E0EDE7;
        border-radius: 13px;
        overflow: hidden;
        transition: all 0.2s;
        margin-bottom: 24px;
    }
    .fr-input-wrap:focus-within {
        border-color: #39B54A;
        box-shadow: 0 0 0 4px rgba(57,181,74,0.09);
    }
    .fr-input-pfx {
        padding: 12px 14px;
        background: #F7FBF9;
        border-right: 1.5px solid #E0EDE7;
        color: #a0b8b0;
        font-size: 0.95rem;
        flex-shrink: 0;
        display: flex; align-items: center;
    }
    .fr-input {
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
    .fr-input::placeholder { color: #b8cec8; font-weight: 500; }

    /* Submit */
    .fr-submit {
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
        display: flex; align-items: center; justify-content: center;
        gap: 8px;
        letter-spacing: 0.2px;
        margin-bottom: 14px;
    }
    .fr-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(15,57,43,0.36); }
    .fr-submit:active { transform: translateY(0); }
    .fr-submit:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

    /* Back link */
    .fr-back {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        width: 100%;
        background: #fff;
        border: 1.5px solid #E0EDE7;
        border-radius: 13px;
        padding: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.84rem;
        font-weight: 700;
        color: #5a7a6e;
        text-decoration: none;
        transition: all 0.2s;
    }
    .fr-back:hover { background: #E0EDE7; color: #0F392B; border-color: #C8DDD6; }

    /* Security note */
    .fr-note {
        display: flex;
        align-items: center;
        gap: 7px;
        background: #F0F7F4;
        border: 1px solid #E0EDE7;
        border-radius: 11px;
        padding: 10px 14px;
        font-size: 0.73rem;
        font-weight: 600;
        color: #5a7a6e;
        margin-bottom: 22px;
        line-height: 1.5;
    }
    .fr-note i { color: #39B54A; font-size: 0.85rem; flex-shrink: 0; }

    /* Responsive */
    @media (max-width: 900px) {
        .fp-left { display: none; }
        .fp-right { width: 100%; min-width: 0; padding: 40px 28px; }
    }
    </style>
</head>
<body>

<!-- Left Panel -->
<div class="fp-left">
    <a href="<?= BASE_URL ?>/public/index.php" class="fl-brand text-decoration-none">
        <div class="fl-brand-logo">
            <img src="<?= SITE_LOGO ?>" alt="<?= SITE_NAME ?>">
        </div>
        <div>
            <div class="fl-brand-name"><?= htmlspecialchars(SITE_NAME) ?></div>
            <div class="fl-brand-sub">Unified Management System</div>
        </div>
    </a>

    <div class="fl-hero">
        <div class="fl-icon-wrap">
            <i class="bi bi-key-fill"></i>
        </div>
        <h1 class="fl-title">
            Reset your<br><span>password</span><br>securely.
        </h1>
        <p class="fl-desc">
            Enter the email linked to your account and we'll send a temporary password to get you back in.
        </p>
        <div class="fl-steps">
            <div class="fl-step">
                <div class="fl-step-icon"><i class="bi bi-envelope-fill"></i></div>
                <div>
                    <div class="fl-step-title">Enter your email</div>
                    <div class="fl-step-sub">The address registered to your account</div>
                </div>
            </div>
            <div class="fl-step">
                <div class="fl-step-icon"><i class="bi bi-inbox-fill"></i></div>
                <div>
                    <div class="fl-step-title">Check your inbox</div>
                    <div class="fl-step-sub">A temporary password will be emailed to you</div>
                </div>
            </div>
            <div class="fl-step">
                <div class="fl-step-icon"><i class="bi bi-shield-check-fill"></i></div>
                <div>
                    <div class="fl-step-title">Login &amp; update</div>
                    <div class="fl-step-sub">Sign in and change your password immediately</div>
                </div>
            </div>
        </div>
    </div>

    <div class="fl-footer">
        &copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. All rights reserved.
    </div>
</div>

<!-- Right Panel -->
<div class="fp-right">
    <div class="fr-wrap">

        <?php if ($msg_type === 'success'): ?>
            <!-- Success State -->
            <div class="fr-success-state">
                <div class="fr-success-icon">
                    <i class="bi bi-envelope-check-fill"></i>
                </div>
                <h4>Check your inbox</h4>
                <p><?= $message ?></p>
                <a href="login.php" class="fr-submit" style="text-decoration:none;">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            </div>

        <?php else: ?>
            <div class="fr-heading">
                <h2>Forgot password?</h2>
                <p>Enter your email and we'll send you a temporary password.</p>
            </div>

            <?php if ($message && $msg_type !== 'success'): ?>
                <div class="fr-flash fr-flash-<?= $msg_type === 'warning' ? 'warning' : 'error' ?>">
                    <i class="bi bi-<?= $msg_type === 'warning' ? 'exclamation-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="fr-note">
                <i class="bi bi-shield-lock-fill"></i>
                For security, we won't confirm if an email is registered. Check your inbox regardless.
            </div>

            <form method="POST" id="forgotForm">
                <label class="fr-label">Email Address</label>
                <div class="fr-input-wrap">
                    <span class="fr-input-pfx"><i class="bi bi-envelope-fill"></i></span>
                    <input type="email" name="email" class="fr-input"
                        placeholder="your@email.com" required autofocus
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <button type="submit" class="fr-submit" id="submitBtn">
                    <span id="btnText"><i class="bi bi-send-fill me-1"></i> Send Reset Email</span>
                    <div class="spinner-border spinner-border-sm d-none" id="btnSpinner"></div>
                </button>

                <a href="login.php" class="fr-back">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            </form>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('forgotForm')?.addEventListener('submit', function() {
    const btn     = document.getElementById('submitBtn');
    const text    = document.getElementById('btnText');
    const spinner = document.getElementById('btnSpinner');
    btn.disabled = true;
    spinner.classList.remove('d-none');
    text.innerHTML = 'Sending…';
});
</script>
</body>
</html>