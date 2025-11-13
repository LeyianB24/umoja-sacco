<?php
session_start();
include('../config/db_connect.php');
include('../inc/header.php');

if (!isset($_SESSION['member_id'])) {
    header("Location: ../public/login.php");
    exit();
}

$member_id = $_SESSION['member_id'];
$success_message = $error_message = "";

// Fetch current user details
$query = "SELECT full_name, email, phone FROM members WHERE member_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Update profile info
if (isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    $update = "UPDATE members SET full_name=?, email=?, phone=? WHERE member_id=?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("sssi", $fullname, $email, $phone, $member_id);

    if ($stmt->execute()) {
        $success_message = "Profile updated successfully!";
    } else {
        $error_message = "Error updating profile.";
    }
}

// Change password
if (isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    $query = "SELECT password FROM members WHERE member_id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!password_verify($current_password, $row['password'])) {
        $error_message = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $update = "UPDATE members SET password=? WHERE member_id=?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("si", $hashed, $member_id);

        if ($stmt->execute()) {
            $success_message = "Password changed successfully!";
        } else {
            $error_message = "Error changing password.";
        }
    }
}
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="text-center mb-4"><i class="fas fa-hand-holding-usd me-2"></i>Account Settings</h3>
    <a href="dashboard.php" class="btn btn-secondary rounded-pill">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Update Profile -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <strong>Update Profile</strong>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group mb-3">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-success w-100">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <strong>Change Password</strong>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group mb-3">
                            <label>Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-warning w-100">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('../inc/footer.php'); ?>
