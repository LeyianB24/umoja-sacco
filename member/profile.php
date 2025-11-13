<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['member_id'])) {
    header("Location: ../public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];

// Fetch member info
$stmt = $conn->prepare("SELECT full_name, email, phone, national_id, address, join_date, gender, profile_pic 
                        FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $gender = trim($_POST['gender']);
    $remove_pic = isset($_POST['remove_pic']);

    // Default: keep current profile pic
    $profile_pic_data = $member['profile_pic'];

    // If removing the picture
    if ($remove_pic) {
        $profile_pic_data = null;
    }

    // If a new picture was uploaded
    if (!empty($_FILES['profile_pic']['name']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $imageData = file_get_contents($_FILES['profile_pic']['tmp_name']);
        $profile_pic_data = $imageData;
    }

    // Update in DB
    $sql = "UPDATE members 
            SET email=?, phone=?, address=?, gender=?, profile_pic=? 
            WHERE member_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssbi", $email, $phone, $address, $gender, $null, $member_id);
    $stmt->send_long_data(4, $profile_pic_data);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: profile.php");
        exit;
    } else {
        $_SESSION['error'] = "Failed to update profile.";
    }
    $stmt->close();
}

// Convert BLOB to base64 for preview
$profile_pic_base64 = '';
if (!empty($member['profile_pic'])) {
    $profile_pic_base64 = 'data:image/jpeg;base64,' . base64_encode($member['profile_pic']);
} else {
    // default male/female placeholder
    $profile_pic_base64 = ($member['gender'] === 'female')
        ? '../public/assets/uploads/female.jpg'
        : '../public/assets/uploads/male.jpg';
}
?>

<div class="container-fluid px-4 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold text-success"><i class="fas fa-user-circle me-2"></i>My Profile</h4>
        <a href="dashboard.php" class="btn btn-secondary rounded-pill">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php elseif (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-success text-white fw-semibold">
            <i class="fas fa-id-card me-2"></i>Personal Information
        </div>
        <div class="card-body text-center">
            <img id="preview" src="<?= htmlspecialchars($profile_pic_base64) ?>" 
                 alt="Profile Picture" 
                 class="rounded-circle border border-success mb-3" 
                 style="width:120px; height:120px; object-fit:cover;">

            <form method="POST" enctype="multipart/form-data" class="row g-3 mt-3">
                <div class="col-md-12">
                    <label class="form-label fw-semibold">Change Profile Picture</label>
                    <input type="file" name="profile_pic" accept="image/*" class="form-control" onchange="previewImage(event)">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="remove_pic" id="remove_pic">
                        <label class="form-check-label text-danger" for="remove_pic">Remove current picture</label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($member['full_name']); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">National ID</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($member['national_id']); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($member['email']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($member['phone']); ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($member['address']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="male" <?= $member['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= $member['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Join Date</label>
                    <input type="text" class="form-control" value="<?= date('d M Y', strtotime($member['join_date'])); ?>" readonly>
                </div>
                <div class="col-12 mt-3 text-center">
                    <button type="submit" class="btn btn-success rounded-pill px-4">
                        <i class="fas fa-save me-1"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function previewImage(event) {
  const reader = new FileReader();
  reader.onload = function() {
    document.getElementById('preview').src = reader.result;
  }
  reader.readAsDataURL(event.target.files[0]);
}
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>