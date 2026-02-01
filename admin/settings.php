<?php
// usms/admin/settings.php
// IT Admin - Configuration & Profile

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

// Enforce Admin Role
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin'])) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

$admin_id   = $_SESSION['admin_id'];
$db = $conn;
$msg = "";
$msg_type = "";

// --- LOGIC: Profile Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    verify_csrf_token();
    $fullname = trim($_POST['full_name']);
    $email    = trim($_POST['email']);
    $username = trim($_POST['username']);

    if (!$fullname || !$email || !$username) {
        $msg = "All fields are required."; $msg_type = "danger";
    } else {
        $stmt = $db->prepare("UPDATE admins SET full_name = ?, email = ?, username = ? WHERE admin_id = ?");
        $stmt->bind_param("sssi", $fullname, $email, $username, $admin_id);
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $fullname;
            $msg = "Profile updated successfully."; $msg_type = "success";
            // Audit Log (simplified)
            $db->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'profile_update', 'Updated Profile', '{$_SERVER['REMOTE_ADDR']}')");
        } else {
            $msg = "Update failed: " . $db->error; $msg_type = "danger";
        }
    }
}

// --- LOGIC: Password Change ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verify_csrf_token();
    $current = $_POST['current_password'];
    $new     = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $stmt = $db->prepare("SELECT password FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!password_verify($current, $user['password'])) {
        $msg = "Incorrect current password."; $msg_type = "danger";
    } elseif ($new !== $confirm) {
        $msg = "New passwords do not match."; $msg_type = "danger";
    } elseif (strlen($new) < 6) {
        $msg = "Password must be at least 6 characters."; $msg_type = "danger";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $db->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
        $upd->bind_param("si", $hash, $admin_id);
        if ($upd->execute()) {
            $msg = "Password updated successfully."; $msg_type = "success";
        }
    }
}

// Get User Data
$me = $db->query("SELECT * FROM admins WHERE admin_id = $admin_id")->fetch_assoc();

// Initials helper
function getInitials($name) {
    $words = explode(" ", $name);
    $acronym = "";
    foreach ($words as $w) $acronym .= mb_substr($w, 0, 1);
    return strtoupper(substr($acronym, 0, 2));
}
$server_ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
$php_v     = phpversion();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Settings | USMS Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --bg-body: #f8f9fa;
            --bg-card: #ffffff;
            --text-dark: #111827;
            --text-gray: #6b7280;
            --accent-lime: #bef264;
            --accent-dark: #1a2e05;
            --border-color: #e5e7eb;
        }
        body { background-color: var(--bg-body); color: var(--text-dark); font-family: 'Poppins', sans-serif; }
        
        /* Layout */
        .main-content-wrapper { margin-left: 260px; min-height: 100vh; display: flex; flex-direction: column; }
        @media(max-width: 991px){ .main-content-wrapper{ margin-left:0; } }

        /* Component Styles */
        .card-custom {
            background: var(--bg-card);
            border-radius: 24px;
            border: 1px solid rgba(0,0,0,0.03);
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }
        .nav-pills .nav-link {
            color: var(--text-gray); font-weight: 500; border-radius: 12px; padding: 12px 20px; margin-bottom: 8px; transition: all 0.3s;
        }
        .nav-pills .nav-link:hover { background-color: rgba(0,0,0,0.03); color: var(--text-dark); }
        .nav-pills .nav-link.active { background-color: var(--accent-dark); color: var(--accent-lime); }
        
        .form-control { background-color: #f3f4f6; border: 1px solid transparent; border-radius: 12px; padding: 12px 16px; }
        .form-control:focus { background-color: #fff; border-color: var(--accent-lime); box-shadow: 0 0 0 4px rgba(190, 242, 100, 0.2); }
        
        .btn-lime { background-color: var(--accent-lime); color: var(--accent-dark); font-weight: 600; border-radius: 50px; padding: 10px 24px; border: none; }
        .btn-lime:hover { background-color: #a3e635; transform: translateY(-1px); }
        
        .avatar-circle { width: 80px; height: 80px; background-color: var(--accent-dark); color: var(--accent-lime); font-size: 1.8rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto; }
    </style>
</head>
<body>

<div class="d-flex">
        <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php require_once __DIR__ . '/../inc/topbar.php'; ?>
            
            <div class="container-fluid">
        
            
            <div class="d-flex justify-content-between align-items-end mb-5">
                <div>
                    <h2 class="fw-bold mb-1" style="color: var(--accent-dark);">Settings</h2>
                    <p class="text-muted mb-0">Manage your profile and system preferences</p>
                </div>
            </div>

            <?php if($msg): ?>
                <div class="alert alert-<?= $msg_type ?> rounded-4 border-0 shadow-sm mb-4 d-flex align-items-center">
                    <i class="bi bi-info-circle-fill me-2"></i> <?= $msg ?>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                
                <div class="col-lg-4 col-xl-3">
                    
                    <div class="card-custom p-4 text-center mb-4" style="background: linear-gradient(180deg, var(--accent-dark) 0%, #0f1c03 100%); color: white;">
                        <div class="position-relative d-inline-block mb-3">
                            <div class="avatar-circle border border-2 border-white text-dark bg-white">
                                 <?= getInitials($me['full_name']) ?>
                            </div>
                            <span class="position-absolute bottom-0 end-0 bg-success border border-2 border-dark rounded-circle p-2"></span>
                        </div>
                        <h5 class="mb-1 fw-bold"><?= htmlspecialchars($me['full_name']) ?></h5>
                        <p class="text-white-50 small mb-3"><?= $me['email'] ?></p>
                        
                        <div class="d-flex justify-content-center gap-4 mt-3 pt-3 border-top border-secondary border-opacity-25">
                            <div class="text-center">
                                <h6 class="mb-0 fw-bold text-white">ID</h6>
                                <small class="text-white-50">#<?= $admin_id ?></small>
                            </div>
                            <div class="text-center">
                                <h6 class="mb-0 fw-bold text-white">Role</h6>
                                <small class="text-white-50">Admin</small>
                            </div>
                        </div>
                    </div>

                    <div class="card-custom p-3">
                        <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist">
                            <button class="nav-link active text-start" data-bs-toggle="pill" data-bs-target="#v-pills-profile">
                                <i class="bi bi-person me-2"></i> Personal Info
                            </button>
                            <button class="nav-link text-start" data-bs-toggle="pill" data-bs-target="#v-pills-security">
                                <i class="bi bi-shield-lock me-2"></i> Security
                            </button>
                            <button class="nav-link text-start" data-bs-toggle="pill" data-bs-target="#v-pills-system">
                                <i class="bi bi-hdd-network me-2"></i> System Status
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 col-xl-9">
                    <div class="tab-content" id="v-pills-tabContent">
                        
                        <div class="tab-pane fade show active" id="v-pills-profile">
                            <div class="card-custom p-4 p-md-5">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="fw-bold mb-0">Edit Profile</h5>
                                    <span class="badge bg-light text-dark border rounded-pill px-3 py-2">Personal Details</span>
                                </div>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label>Full Name</label>
                                            <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($me['full_name']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label>Username</label>
                                            <div class="input-group">
                                                <span class="input-group-text border-0 bg-light text-muted" style="border-radius: 12px 0 0 12px;">@</span>
                                                <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($me['username']) ?>" style="border-radius: 0 12px 12px 0;" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label>Email Address</label>
                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($me['email']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="mt-5 d-flex justify-content-end">
                                        <button type="submit" name="update_profile" class="btn btn-lime shadow-sm">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="v-pills-security">
                            <div class="card-custom p-4 p-md-5">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="fw-bold mb-0">Security</h5>
                                    <span class="badge bg-light text-dark border rounded-pill px-3 py-2">Password Manager</span>
                                </div>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <div class="mb-4">
                                        <label>Current Password</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    <div class="row g-4 mb-4">
                                        <div class="col-md-6">
                                            <label>New Password</label>
                                            <input type="password" class="form-control" name="new_password" placeholder="Min 6 characters" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label>Confirm Password</label>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>
                                    </div>
                                    <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning d-flex align-items-start small">
                                        <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                                        <div>Changing your password will log you out of all other active sessions.</div>
                                    </div>
                                    <div class="mt-4 d-flex justify-content-end">
                                        <button type="submit" name="change_password" class="btn btn-dark rounded-pill px-4">Update Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="v-pills-system">
                            <div class="card-custom p-4 p-md-5">
                                <h5 class="fw-bold mb-4">Environment Status</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded-4 border border-light">
                                            <small class="text-muted d-block mb-1">PHP Version</small>
                                            <span class="fw-bold font-monospace fs-5"><?= $php_v ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded-4 border border-light">
                                            <small class="text-muted d-block mb-1">Server IP</small>
                                            <span class="fw-bold font-monospace fs-5"><?= $server_ip ?></span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="p-3 bg-light rounded-4 border border-light">
                                            <small class="text-muted d-block mb-1">Software</small>
                                            <span class="fw-bold font-monospace"><?= $_SERVER['SERVER_SOFTWARE'] ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 text-center">
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2">
                                        <i class="bi bi-activity"></i> System Operational
                                    </span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <?php require_once __DIR__ . '/../inc/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
