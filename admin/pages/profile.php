<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

// Auth Check
require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');

$admin_id = $_SESSION['admin_id'];

// 2. Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $remove_pic = isset($_POST['remove_pic']);

    // Get current pic
    $stmt = $conn->prepare("SELECT profile_pic FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $pic_data = $current['profile_pic'];

    if ($remove_pic) {
        $pic_data = null;
    } elseif (!empty($_FILES['profile_pic']['name']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $max_size = 1 * 1024 * 1024; // 1MB 
        $file_size = $_FILES['profile_pic']['size'];
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $file_type = mime_content_type($file_tmp);
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Invalid file type. Please upload a JPG, PNG or WEBP image.";
            header("Location: profile.php");
            exit;
        }

        if ($file_size > $max_size) {
            $_SESSION['error'] = "The image is too large (Maximum 1MB). Please compress it or use a smaller photo.";
            header("Location: profile.php");
            exit;
        }

        $pic_data = file_get_contents($file_tmp);
    }

    // Update Profile
    $sql = "UPDATE admins SET email=?, phone=?, profile_pic=? WHERE admin_id=?";
    $stmt = $conn->prepare($sql);
    $null = null; 
    $stmt->bind_param("ssbi", $email, $phone, $null, $admin_id);
    if ($pic_data !== null) {
        $stmt->send_long_data(2, $pic_data);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: profile.php");
        exit;
    } else {
        $_SESSION['error'] = "Update failed: " . $conn->error;
    }
    $stmt->close();
}

// 3. Fetch Data
$stmt = $conn->prepare("SELECT a.*, r.department, r.role_name FROM admins a LEFT JOIN roles r ON a.role_id = r.role_id WHERE a.admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();


$display_pic = BASE_URL . '/public/assets/uploads/male.jpg';

// If a custom uploaded picture exists in the DB, override the default
if (!empty($admin['profile_pic'])) {
    $display_pic = 'data:image/jpeg;base64,' . base64_encode($admin['profile_pic']);
}

$pageTitle = "My Profile";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= defined('SITE_NAME') ? SITE_NAME : 'Admin Staff Center' ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        :root {
            --bs-font-sans-serif: 'Inter', sans-serif;
            --brand-forest: #0F392B;     /* Deep forest green */
            --brand-lime: #D1FF27;       /* Bright lime accent */
            --brand-lime-hover: #bce623; 
            --brand-emerald: #10b981;    /* Secondary accent */
            
            --bg-app: #f4f7f6;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
            --radius-lg: 24px;
        }

        [data-bs-theme="dark"] {
            --bg-app: #0f172a;
            --card-bg: #1e293b;
            --text-dark: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --glass-bg: rgba(30, 41, 59, 0.95);
            --glass-border: rgba(255, 255, 255, 0.05);
            --brand-forest: #10b981;
            --brand-lime: rgba(209, 255, 39, 0.8);
        }

        body {
            font-family: var(--bs-font-sans-serif);
            background-color: var(--bg-app);
            color: var(--text-dark);
        }

        /* Profile Header Cover */
        .profile-cover {
            height: 220px;
            background: linear-gradient(135deg, var(--brand-forest) 0%, #08241b 100%);
            border-radius: var(--radius-lg);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-sm);
        }
        
        .profile-cover::after {
            content: '';
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            background: url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3z" fill="rgba(255,255,255,0.05)" fill-rule="evenodd"/></svg>');
        }

        /* Profile Avatar Layout */
        .profile-header-content {
            display: flex;
            align-items: flex-end;
            margin-top: -70px;
            padding: 0 40px;
            position: relative;
            z-index: 10;
        }

        .profile-img-wrap {
            position: relative;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: var(--card-bg);
            padding: 6px;
            box-shadow: var(--shadow-md);
        }
        
        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            background: #f1f5f9;
        }

        .btn-upload {
            position: absolute;
            bottom: 5px; right: 5px;
            width: 42px; height: 42px;
            border-radius: 50%;
            background: var(--brand-lime);
            color: var(--brand-forest);
            border: 4px solid var(--card-bg);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .btn-upload:hover {
            transform: scale(1.1) rotate(15deg);
            background: var(--brand-lime-hover);
        }

        /* Profile Info */
        .profile-info {
            padding-left: 30px;
            padding-bottom: 10px;
            flex-grow: 1;
        }

        /* Hope UI Card */
        .glass-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }

        /* Form Styling */
        .form-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            background: rgba(241, 245, 249, 0.5);
            padding: 0.85rem 1rem;
            border-radius: 12px;
            font-weight: 500;
            color: var(--text-dark);
            transition: all 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--brand-forest);
            background: var(--card-bg);
            box-shadow: 0 0 0 4px rgba(15, 57, 43, 0.1);
        }

        .input-group-text {
            border: 1px solid var(--border-color);
            background: rgba(241, 245, 249, 0.8);
            color: var(--text-muted);
            border-radius: 12px 0 0 12px;
            border-right: none;
        }
        .form-control.with-icon { border-left: none; }

        .form-control[readonly] {
            background-color: rgba(241, 245, 249, 0.8);
            color: var(--text-muted);
            opacity: 0.8;
            cursor: not-allowed;
        }

        /* Buttons */
        .btn-lime {
            background-color: var(--brand-lime);
            border-color: var(--brand-lime);
            color: var(--brand-forest);
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(209, 255, 39, 0.4);
            transition: all 0.3s;
        }
        
        .btn-lime:hover {
            background-color: var(--brand-lime-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(209, 255, 39, 0.6);
            color: var(--brand-forest);
        }

        /* Layout */
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; padding: 2.5rem; min-height: 100vh; }
        @media (max-width: 991px) { 
            .main-content-wrapper { margin-left: 0; padding: 1.5rem; } 
            .profile-header-content { flex-direction: column; align-items: center; text-align: center; }
            .profile-info { padding-left: 0; padding-top: 20px; }
        }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>

<div class="d-flex">
        <?php $layout->sidebar(); ?>

        <div class="flex-fill main-content-wrapper p-0">
            <?php $layout->topbar($pageTitle ?? ''); ?>
            
            <div class="container-fluid p-4 p-md-5">
            
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 animate__animated animate__zoomIn d-flex align-items-center mb-4 p-3" role="alert" style="background: rgba(16, 185, 129, 0.1); color: #064E3B;">
                    <i class="bi bi-check-circle-fill me-3 fs-4 text-success"></i>
                    <div class="fw-bold fs-6"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                </div>
            <?php elseif (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-4 animate__animated animate__zoomIn d-flex align-items-center mb-4 p-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                    <div class="fw-bold fs-6"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                </div>
            <?php endif; ?>

            <div class="row animate__animated animate__fadeInUp">
                <div class="col-xl-10 mx-auto">
                    
                    <div class="glass-card overflow-hidden">
                        
                        <div class="profile-cover p-4">
                            <!-- Background Cover -->
                        </div>

                        <div class="card-body p-0 pb-5">
                            <div class="profile-header-content pb-4 border-bottom">
                                <div class="profile-img-wrap">
                                    <img id="preview" src="<?= $display_pic ?>" class="profile-img">
                                    <div class="upload-btn-wrapper">
                                        <label for="profile_pic" class="btn-upload" title="Change Photo">
                                            <i class="bi bi-camera-fill"></i>
                                        </label>
                                    </div>
                                </div>
                                <div class="profile-info">
                                    <h2 class="fw-800 mb-1" style="color: var(--brand-forest); letter-spacing: -0.5px;">
                                        <?= htmlspecialchars($admin['full_name']) ?>
                                    </h2>
                                    <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success px-3 py-2 fw-bold">
                                            <i class="bi bi-shield-check me-1"></i> Active
                                        </span>
                                        <span class="text-muted fw-bold small">
                                            <i class="bi bi-building me-1"></i> <?= htmlspecialchars($admin['department'] ?? 'General') ?>
                                        </span>
                                        <span class="text-muted fw-bold small text-uppercase">
                                            <i class="bi bi-person-badge me-1"></i> <?= htmlspecialchars($admin['role_name'] ?? 'Admin') ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="px-4 px-md-5 pt-5">
                                <form method="POST" enctype="multipart/form-data">
                                    <?= csrf_field() ?>
                                    <input type="file" name="profile_pic" id="profile_pic" accept="image/*" class="d-none" onchange="previewImage(event)">

                                    <div class="row g-5">
                                        
                                        <!-- Editable Section -->
                                        <div class="col-lg-6">
                                            <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                                                <div class="bg-forest bg-opacity-10 text-forest rounded-circle p-2 me-3">
                                                    <i class="bi bi-pencil-square fs-5"></i>
                                                </div>
                                                <h5 class="fw-800 m-0" style="color: var(--brand-forest);">Contact Details</h5>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" name="email" class="form-control form-control-lg fs-6" value="<?= htmlspecialchars($admin['email']); ?>" required>
                                            </div>
                                            
                                            <div class="mb-4">
                                                <label class="form-label">Phone Number</label>
                                                <input type="text" name="phone" class="form-control form-control-lg fs-6" value="<?= htmlspecialchars($admin['phone'] ?? ''); ?>">
                                            </div>

                                            <div class="form-check mt-3 bg-light p-3 rounded-3 border">
                                                <input class="form-check-input ms-1" type="checkbox" name="remove_pic" id="remove_pic">
                                                <label class="form-check-label text-danger fw-bold ms-2" for="remove_pic">
                                                    Remove profile picture
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Readonly Section -->
                                        <div class="col-lg-6">
                                            <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                                                <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle p-2 me-3">
                                                    <i class="bi bi-shield-lock-fill fs-5"></i>
                                                </div>
                                                <h5 class="fw-800 m-0" style="color: var(--brand-forest);">Official Record <sup class="text-danger">*</sup></h5>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label">Full Name</label>
                                                <div class="input-group input-group-lg">
                                                    <span class="input-group-text"><i class="bi bi-person ms-1"></i></span>
                                                    <input type="text" class="form-control with-icon fs-6" value="<?= htmlspecialchars($admin['full_name']); ?>" readonly>
                                                </div>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label">Employee Username</label>
                                                <div class="input-group input-group-lg">
                                                    <span class="input-group-text"><i class="bi bi-at ms-1"></i></span>
                                                    <input type="text" class="form-control with-icon fs-6" value="<?= htmlspecialchars($admin['username'] ?? ''); ?>" readonly>
                                                </div>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label">System Role Access</label>
                                                <div class="input-group input-group-lg">
                                                    <span class="input-group-text"><i class="bi bi-key-fill ms-1"></i></span>
                                                    <input type="text" class="form-control with-icon fs-6" value="<?= htmlspecialchars($admin['role_name'] ?? 'Admin'); ?>" readonly>
                                                </div>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label">Account Creation Date</label>
                                                <div class="input-group input-group-lg">
                                                    <span class="input-group-text"><i class="bi bi-calendar3 ms-1"></i></span>
                                                    <input type="text" class="form-control with-icon fs-6" value="<?= date('d M, Y', strtotime($admin['created_at'])); ?>" readonly>
                                                </div>
                                                <div class="form-text mt-2">Locked fields require Super Admin intervention to modify.</div>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="mt-5 pt-4 border-top d-flex justify-content-between align-items-center">
                                        <a href="dashboard.php" class="btn btn-light border fw-bold text-muted px-4 rounded-pill">Cancel</a>
                                        <button type="submit" class="btn btn-lime text-uppercase rounded-pill px-5">
                                            <i class="bi bi-check-circle-fill me-2 fs-5 align-middle"></i> Save Changes
                                        </button>
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
     
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Image Preview Logic
    function previewImage(event) {
        const reader = new FileReader();
        const file = event.target.files[0];
        if (file) {
            reader.onload = function() {
                const img = document.getElementById('preview');
                img.src = reader.result;
                img.classList.add('animate__animated', 'animate__pulse');
                setTimeout(() => img.classList.remove('animate__animated', 'animate__pulse'), 1000);
            }
            reader.readAsDataURL(file);
        }
    }
</script>
</body>
</html>
