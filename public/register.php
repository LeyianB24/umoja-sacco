<?php
// usms/public/register.php
session_start();

// includes
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/header.php'; // header should output opening <body> etc.

$errors = [];
// prefill values
$full_name = $national_id = $phone = $email = '';

/**
 * Normalize phone to +254XXXXXXXXX
 */
function normalize_phone($raw) {
    $p = preg_replace('/[^\d\+]/', '', $raw); // keep digits and plus
    if ($p === '') return '';
    if (strpos($p, '+') === 0) {
        if (strpos($p, '+254') === 0) return $p;
        return $p;
    }
    if (preg_match('/^0(\d{8,9})$/', $p, $m)) {
        return '+254' . $m[1];
    }
    if (preg_match('/^7(\d{8})$/', $p, $m)) {
        return '+254' . $p;
    }
    return $p;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // collect & sanitize
    $full_name   = trim($_POST['full_name'] ?? '');
    $national_id = trim($_POST['national_id'] ?? '');
    $phone_raw   = trim($_POST['phone'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    // normalize phone
    $phone = normalize_phone($phone_raw);

    // validation
    if ($full_name === '') $errors[] = "Full name is required.";
    if ($national_id === '') $errors[] = "National ID / Passport is required.";
    if ($phone === '') $errors[] = "Phone number is required.";
    if ($email === '') $errors[] = "Email is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Enter a valid email address.";
    if ($password === '') $errors[] = "Password is required.";
    if ($password !== $confirm) $errors[] = "Passwords do not match.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";

    // uniqueness checks
    if (empty($errors)) {
        $checkSql = "SELECT member_id FROM members WHERE email = ? OR phone = ? OR national_id = ? LIMIT 1";
        if ($stmt = $conn->prepare($checkSql)) {
            $stmt->bind_param("sss", $email, $phone, $national_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = "A member with that email, phone or national ID already exists.";
            }
            $stmt->close();
        } else {
            $errors[] = "Server error (DB). Please try again later.";
        }
    }

    // insert + AUTO LOGIN
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $insertSql = "INSERT INTO members (full_name, national_id, phone, email, password, join_date, status) VALUES (?, ?, ?, ?, ?, NOW(), 'active')";
        if ($ins = $conn->prepare($insertSql)) {
            $ins->bind_param("sssss", $full_name, $national_id, $phone, $email, $hashed);
            if ($ins->execute()) {

                // ✅ AUTO LOGIN
                $newMemberId = $ins->insert_id;
                $_SESSION['member_id'] = $newMemberId;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 'member';
                $_SESSION['status'] = 'active';

                header("Location: ../member/dashboard.php");
                exit;

            } else {
                $errors[] = "Error saving to database: " . htmlspecialchars($ins->error);
            }
            $ins->close();
        } else {
            $errors[] = "Server error (DB prepare). Please try again later.";
        }
    }
}
?>

<!-- Two-tone layout: left + right -->
<style>
  .reg-hero { min-height: 60vh; display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: center; margin: 2.5rem 0; }
  .reg-left { padding: 2.5rem; border-radius: 14px; background: linear-gradient(180deg, rgba(10,104,51,0.95), rgba(22,163,74,0.95)); color: #fff; }
  .reg-right { padding: 2rem; background: #fff; border-radius: 14px; }
  .form-control { border-radius: 8px; padding: .75rem .9rem; }
  .btn-primary-umt { background: linear-gradient(90deg,#0A6833,#16a34a); border:none; color:#fff; padding:.8rem 1rem; border-radius:10px; font-weight:600; }
  .btn-outline-umt { background: transparent; border: 1px solid #16a34a; color:#0b6623; padding:.7rem 1rem; border-radius:10px; }
  @media (max-width: 991px) { .reg-hero { grid-template-columns: 1fr; } .reg-left { order: -1; } }
</style>

<div class="container">
  <div class="reg-hero">

    <!-- LEFT SECTION -->
    <div class="reg-left">
      <h2><?= SITE_NAME ?></h2>
      <p><?= TAGLINE ?></p>
      <p class="mt-3">Join Umoja Sacco – a growing investment community.</p>
      <a href="login.php" class="btn btn-outline-light mt-3">Already a member? Login</a>
    </div>

    <!-- RIGHT SECTION -->
    <div class="reg-right">
      <h4>Create Member Account</h4>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul>
        </div>
      <?php endif; ?>

      <form method="POST">
        <input type="text" name="full_name" placeholder="Full Name" class="form-control mb-2" value="<?= htmlspecialchars($full_name) ?>">
        <input type="text" name="national_id" placeholder="National ID / Passport" class="form-control mb-2" value="<?= htmlspecialchars($national_id) ?>">
        <input type="text" name="phone" placeholder="Phone" class="form-control mb-2" value="<?= htmlspecialchars($phone ?? '') ?>">
        <input type="email" name="email" placeholder="Email" class="form-control mb-2" value="<?= htmlspecialchars($email) ?>">
        <input type="password" name="password" placeholder="Password" class="form-control mb-2">
        <input type="password" name="confirm_password" placeholder="Confirm Password" class="form-control mb-2">
        <button type="submit" class="btn btn-primary-umt w-100 mt-2">Create Account</button>
      </form>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
