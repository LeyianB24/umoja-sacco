<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');
// Initialize Layout Manager
$layout = LayoutManager::create('member');
// member/settings.php
// Enhanced UI: Forest Green Glassmorphism + Responsive Sidebar


// 1. Auth Check
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$member_id = $_SESSION['member_id'];
$success_message = $error_message = "";

// 2. Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update Profile
    if (isset($_POST['update_profile'])) {
        $email    = trim($_POST['email']);
        $phone    = trim($_POST['phone']);
        $gender   = trim($_POST['gender'] ?? '');
        $address  = trim($_POST['address'] ?? '');

        if (empty($email) || empty($phone)) {
            $error_message = "Email and Phone are required.";
        } else {
            $stmt = $conn->prepare("UPDATE members SET email=?, phone=?, gender=?, address=? WHERE member_id=?");
            $stmt->bind_param("ssssi", $email, $phone, $gender, $address, $member_id);

            if ($stmt->execute()) {
                $success_message = "Profile details updated successfully!";
            } else {
                $error_message = "Update failed. Please try again.";
            }
            $stmt->close();
        }
    }

    // Change Password
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password     = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        $stmt = $conn->prepare("SELECT password FROM members WHERE member_id=?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!password_verify($current_password, $user['password'])) {
            $error_message = "Incorrect current password.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters.";
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE members SET password=? WHERE member_id=?");
            $stmt->bind_param("si", $hashed, $member_id);

            if ($stmt->execute()) {
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Error changing password.";
            }
            $stmt->close();
        }
    }
}

// 3. Fetch Current Data
$stmt = $conn->prepare("SELECT full_name, email, phone, gender, address, profile_pic, created_at, member_reg_no FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Visuals
$initials = strtoupper(substr($user['full_name'], 0, 1));
$avatar_bg = ($user['gender'] == 'female') ? '#e91e63' : '#0F392B'; // Forest Green for male
$pageTitle = "Settings";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* --- HOPE UI VARIABLES --- */
            --hop-dark: #0F2E25;      /* Deep Forest Green */
            --hop-lime: #D0F35D;      /* Vibrant Lime */
            --hop-bg: #F8F9FA;        /* Light Background */
            --hop-card-bg: #FFFFFF;
            --hop-text: #1F2937;
            --hop-border: #EDEFF2;
            --hop-input: #F3F4F6;
            --card-radius: 24px;
        }

        [data-bs-theme="dark"] {
            --hop-bg: #0b1210;
            --hop-card-bg: #1F2937;
            --hop-text: #F9FAFB;
            --hop-border: #374151;
            --hop-dark: #13241f;
            --hop-input: #374151;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--hop-bg);
            color: var(--hop-text);
        }

        /* --- LAYOUT WRAPPER --- */
        .main-content-wrapper {
            margin-left: 280px; 
            transition: margin-left 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0 !important; } }

        /* --- CARDS & GLASS --- */
        .hope-card {
            background: var(--hop-card-bg);
            border-radius: var(--card-radius);
            border: 1px solid var(--hop-border);
            box-shadow: 0 10px 40px rgba(0,0,0,0.03);
            height: 100%;
            overflow: hidden;
        }

        /* Profile Card (Glass effect on top of green) */
        .profile-card {
            background: linear-gradient(135deg, var(--hop-dark) 0%, #1a4d40 100%);
            color: white;
            text-align: center;
            position: relative;
        }
        .profile-card::after {
            content: ''; position: absolute; top: 0; right: 0; bottom: 0; left: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.5; pointer-events: none;
        }

        .avatar-box {
            width: 100px; height: 100px; margin: 0 auto;
            border-radius: 50%; border: 4px solid rgba(255,255,255,0.2);
            background: <?= $avatar_bg ?>; color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; font-weight: 700;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        /* --- FORMS --- */
        .form-control {
            background-color: var(--hop-input);
            border: 1px solid transparent;
            border-radius: 12px;
            padding: 0.8rem 1rem;
            color: var(--hop-text);
            font-size: 0.95rem;
        }
        .form-control:focus {
            background-color: var(--hop-card-bg);
            border-color: var(--hop-lime);
            box-shadow: 0 0 0 4px rgba(208, 243, 93, 0.2);
            color: var(--hop-text);
        }

        /* --- TABS --- */
        .nav-pills .nav-link {
            color: #6B7280;
            border-radius: 50px;
            padding: 10px 24px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .nav-pills .nav-link.active {
            background-color: var(--hop-dark);
            color: white;
            box-shadow: 0 4px 15px rgba(15, 46, 37, 0.2);
        }

        /* --- BUTTONS --- */
        .btn-lime {
            background-color: var(--hop-lime);
            color: var(--hop-dark);
            border: none; border-radius: 50px;
            padding: 12px 30px; font-weight: 700;
            transition: all 0.2s;
        }
        .btn-lime:hover {
            background-color: #c2e035; color: var(--hop-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(208, 243, 93, 0.3);
        }
        
        .btn-back {
            color: #6B7280; border: 1px solid var(--hop-border);
            border-radius: 50px; padding: 8px 20px; font-weight: 600;
            background: var(--hop-card-bg); text-decoration: none;
        }
        .btn-back:hover { background: var(--hop-input); color: var(--hop-text); }
    </style>
</head>
<body>

<div class="d-flex">
    
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content-wrapper d-flex flex-column min-vh-100">
        
        <?php $layout->topbar($pageTitle ?? ''); ?>

        <div class="container-fluid flex-grow-1 px-lg-5 px-4 py-5">
            
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="fw-bold mb-1">Account Settings</h2>
                    <p class="text-secondary mb-0">Manage your profile & security preferences.</p>
                </div>
                <a href="dashboard.php" class="btn btn-back">
                    <i class="bi bi-arrow-left me-2"></i>Back
                </a>
            </div>

            <?php if ($success_message): ?>
                <div class="alert border-0 rounded-4 d-flex align-items-center mb-4" style="background: var(--hop-lime); color: var(--hop-dark);">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i> <?= htmlspecialchars($success_message) ?>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger border-0 rounded-4 bg-danger bg-opacity-10 text-danger d-flex align-items-center mb-4">
                    <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i> <?= htmlspecialchars($error_message) ?>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                
                <div class="col-lg-4">
                    <div class="hope-card profile-card p-5 d-flex flex-column align-items-center justify-content-center h-100">
                        <div class="avatar-box mb-4">
                            <?= $initials ?>
                        </div>
                        <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['full_name']) ?></h4>
                        <p class="opacity-75 mb-4"><?= htmlspecialchars($user['email']) ?></p>
                        
                        <div class="d-flex gap-2 mb-4">
                            <span class="badge bg-white bg-opacity-10 border border-white border-opacity-25 rounded-pill px-3 py-2 fw-normal">
                                ID: #<?= htmlspecialchars($user['member_reg_no']) ?>
                            </span>
                            <span class="badge bg-white bg-opacity-10 border border-white border-opacity-25 rounded-pill px-3 py-2 fw-normal">
                                Member
                            </span>
                        </div>

                        <div class="mt-auto w-100 pt-4 border-top border-white border-opacity-10 d-flex justify-content-between text-white-50 small">
                            <span>Joined</span>
                            <span class="text-white fw-bold"><?= date('M Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="hope-card p-4 p-xl-5">
                        
                        <ul class="nav nav-pills mb-5" id="settingsTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="profile-tab" data-bs-toggle="pill" data-bs-target="#profile" type="button">
                                    <i class="bi bi-person-bounding-box me-2"></i>Personal Details
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security" type="button">
                                    <i class="bi bi-shield-lock me-2"></i>Security
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="settingsTabContent">
                            
                            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                <form method="POST">
                                    <h5 class="fw-bold mb-4">Edit Profile</h5>
                                    <div class="row g-4">
                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-muted text-uppercase">Full Name (Locked)</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted text-uppercase">Email Address</label>
                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted text-uppercase">Phone Number</label>
                                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted text-uppercase">Gender</label>
                                            <select name="gender" class="form-select">
                                                <option value="male" <?= ($user['gender'] == 'male') ? 'selected' : '' ?>>Male</option>
                                                <option value="female" <?= ($user['gender'] == 'female') ? 'selected' : '' ?>>Female</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted text-uppercase">Address</label>
                                            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                                        </div>
                                        <div class="col-12 pt-3">
                                            <button type="submit" name="update_profile" class="btn btn-lime">
                                                Save Changes
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="tab-pane fade" id="security" role="tabpanel">
                                <form method="POST">
                                    <h5 class="fw-bold mb-4">Change Password</h5>
                                    <div class="row g-4">
                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-muted text-uppercase">Current Password</label>
                                            <div class="input-group">
                                                <input type="password" name="current_password" class="form-control" id="currentPass" required>
                                                <button class="btn btn-outline-secondary border-0 bg-light" type="button" onclick="togglePass('currentPass')">
                                                    <i class="bi bi-eye text-muted"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted text-uppercase">New Password</label>
                                            <div class="input-group">
                                                <input type="password" name="new_password" class="form-control" id="newPass" required>
                                                <button class="btn btn-outline-secondary border-0 bg-light" type="button" onclick="togglePass('newPass')">
                                                    <i class="bi bi-eye text-muted"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted text-uppercase">Confirm Password</label>
                                            <div class="input-group">
                                                <input type="password" name="confirm_password" class="form-control" id="confirmPass" required>
                                                <button class="btn btn-outline-secondary border-0 bg-light" type="button" onclick="togglePass('confirmPass')">
                                                    <i class="bi bi-eye text-muted"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-12 pt-3">
                                            <button type="submit" name="change_password" class="btn btn-lime">
                                                Update Password
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
        
        <?php $layout->footer(); ?>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Password Toggle
    function togglePass(id) {
        const input = document.getElementById(id);
        const icon = input.nextElementSibling.querySelector('i');
        if (input.type === "password") {
            input.type = "text";
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            input.type = "password";
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    }
</script>
</body>
</html>




