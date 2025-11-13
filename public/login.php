<?php
session_start();
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

define('REMEMBER_SECONDS', 30 * 24 * 60 * 60);
define('COOKIE_NAME', 'usms_rem');
$cookie_secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

/**
 * LOGIN PROCESS
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if ($email === '' || $password === '') {
        flash_set('Please enter both email and password.', 'error');
    } else {
        // ---- ADMIN LOGIN ----
        $sql = "SELECT * FROM admins WHERE email = ? OR username = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $email, $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $admin = $res->fetch_assoc();
            $stored = $admin['password'] ?? '';
            $valid = false;

            if ($stored && password_verify($password, $stored)) $valid = true;
            elseif ($stored && hash('sha256', $password) === $stored) $valid = true;
            elseif ($stored === $password) $valid = true; // fallback for plain passwords

            if ($valid) {
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_name'] = $admin['full_name'] ?? $admin['username'];
                $_SESSION['role'] = strtolower($admin['role']);

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
                switch ($_SESSION['role']) {
                    case 'superadmin': header("Location: ../superadmin/dashboard.php"); break;
                    case 'manager': header("Location: ../manager/dashboard.php"); break;
                    case 'accountant': header("Location: ../accountant/dashboard.php"); break;
                    default: header("Location: ../admin/dashboard.php");
                }
                exit;
            }
        }

        // ---- MEMBER LOGIN ----
        $sql = "SELECT * FROM members WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $member = $res->fetch_assoc();
            $stored = $member['password'] ?? '';
            $valid = false;

            if ($stored && password_verify($password, $stored)) $valid = true;
            elseif ($stored && hash('sha256', $password) === $stored) $valid = true;
            elseif ($stored === $password) $valid = true;

            if ($valid) {
                $_SESSION['member_id'] = $member['member_id'];
                $_SESSION['member_name'] = $member['full_name'];
                $_SESSION['role'] = 'member';

                flash_set('Welcome, ' . $_SESSION['member_name'] . '!', 'success');
                header("Location: ../member/dashboard.php");
                exit;
            }
        }

        flash_set('Invalid credentials. Please check your email/username and password.', 'error');
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
</head>
<body class="bg-light">

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <div class="text-center mb-3">
            <img src="<?= ASSET_BASE ?>/images/people_logo.png" alt="logo" style="width:72px;height:72px;border-radius:50%;">
            <h4 class="mt-2 mb-0"><?= htmlspecialchars(SITE_NAME) ?></h4>
            <small class="text-muted"><?= htmlspecialchars(TAGLINE) ?></small>
          </div>

          <?php flash_render(); ?>

          <form method="post" novalidate>
            <div class="mb-3">
              <label class="form-label">Email or Username</label>
              <input type="text" name="email" class="form-control" required placeholder="you@example.com / username">
            </div>

            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" required placeholder="••••••••">
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label small" for="remember">Remember me (30 days)</label>
              </div>
              <a href="forgot_password.php" class="small">Forgot?</a>
            </div>

            <div class="d-grid mb-2">
              <button type="submit" name="login" class="btn btn-success">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
              </button>
            </div>

            <div class="text-center small">
              Not a member? <a href="register.php">Register</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>