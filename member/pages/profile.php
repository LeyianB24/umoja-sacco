<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');
// member/profile.php

// 1. Config & Auth
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];

// 2. Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $gender = trim($_POST['gender']);
    $remove_pic = isset($_POST['remove_pic']);

    // Get current pic
    $stmt = $conn->prepare("SELECT profile_pic FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $pic_data = $current['profile_pic'];

    if ($remove_pic) {
        $pic_data = null;
    } elseif (!empty($_FILES['profile_pic']['name']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $max_size = 1 * 1024 * 1024; // 1MB (matches small DB default)
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

    // Update
    $sql = "UPDATE members SET email=?, phone=?, address=?, gender=?, profile_pic=? WHERE member_id=?";
    $stmt = $conn->prepare($sql);
    $null = null; 
    $stmt->bind_param("ssssbi", $email, $phone, $address, $gender, $null, $member_id);
    $stmt->send_long_data(4, $pic_data);
    
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
$stmt = $conn->prepare("SELECT full_name, email, phone, national_id, address, join_date, gender, profile_pic FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 3.5 Fetch KYC Documents
$doc_stmt = $conn->prepare("SELECT * FROM member_documents WHERE member_id = ?");
$doc_stmt->bind_param("i", $member_id);
$doc_stmt->execute();
$kyc_docs = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$doc_stmt->close();


// --- MODIFIED PROFILE PIC LOGIC START ---

// 1. Determine the gender (normalize to lowercase to be safe)
$gender_check = strtolower(trim($member['gender'] ?? ''));

// 2. Set the default image based on gender
if ($gender_check === 'female') {
    $display_pic = BASE_URL . '/public/assets/uploads/female.jpg';
} else {
    // Default to male.jpg for 'male' or if gender is not specified
    $display_pic = BASE_URL . '/public/assets/uploads/male.jpg';
}

// 3. If a custom uploaded picture exists in the DB, override the default
if (!empty($member['profile_pic'])) {
    $display_pic = 'data:image/jpeg;base64,' . base64_encode($member['profile_pic']);
}

// --- MODIFIED PROFILE PIC LOGIC END ---

$pageTitle = "My Profile";

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        :root {
            --bs-font-sans-serif: 'Inter', sans-serif;
            --iq-primary: #3a57e8;
            --iq-primary-hover: #2643d4;
            --iq-secondary: #0d834b; /* SACCO Green */
            --iq-body-bg: #eef2f6; /* HOPE UI Grey */
            --iq-card-bg: #ffffff;
            --iq-text-title: #232D42;
            --iq-text-body: #8A92A6;
            --iq-border-radius: 1rem;
            --iq-shadow: 0 10px 30px 0 rgba(17,38,146,0.05);
        }

        body {
            font-family: var(--bs-font-sans-serif);
            background-color: var(--iq-body-bg);
            color: var(--iq-text-title);
            overflow-x: hidden;
        }

        /* --- Sidebar Wrapper Logic --- */
        #wrapper { display: flex; width: 100%; transition: 0.3s; }
        #sidebar-wrapper { min-width: 260px; max-width: 260px; transition: margin 0.3s ease-out; background: white; z-index: 1000; }
        #page-content-wrapper { width: 100%; overflow-x: hidden; }
        
        /* --- Hope UI Card Style --- */
        .iq-card {
            background: var(--iq-card-bg);
            border-radius: var(--iq-border-radius);
            box-shadow: var(--iq-shadow);
            border: none;
            margin-bottom: 30px;
            transition: all 0.3s ease-in-out;
        }

        /* --- Profile Header Section --- */
        .profile-header {
            position: relative;
            overflow: hidden;
            border-radius: var(--iq-border-radius) var(--iq-border-radius) 0 0;
            height: 180px;
            background: linear-gradient(90deg, var(--iq-secondary) 0%, #064422 100%);
        }
        
        .profile-header-content {
            position: relative;
            margin-top: -60px; /* Pull content up over the header */
            padding-bottom: 20px;
            text-align: center;
        }

        /* --- Avatar Styling --- */
        .profile-img-wrap {
            position: relative;
            display: inline-block;
            width: 140px;
            height: 140px;
        }
        
        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid var(--iq-card-bg); /* Thick white border */
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            background: white;
        }

        /* --- FAB Upload Button --- */
        .upload-btn-wrapper {
            position: absolute;
            bottom: 5px;
            right: 5px;
        }
        
        .btn-upload {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--iq-secondary);
            color: white;
            border: 3px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-upload:hover {
            transform: scale(1.1) rotate(15deg);
            background: #064422;
        }

        /* --- Form Styles --- */
        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--iq-text-body);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 1px solid #eee;
            background: #fcfcfc;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            color: var(--iq-text-title);
            transition: all 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--iq-secondary);
            background: white;
            box-shadow: 0 0 0 4px rgba(13, 131, 75, 0.1);
        }

        .input-group-text {
            border: 1px solid #eee;
            background: #f8f9fa;
            color: var(--iq-text-body);
            border-radius: 8px 0 0 8px;
        }
        
        /* Readonly inputs specific style */
        .form-control[readonly] {
            background-color: #f4f5f7;
            color: #666;
            border-color: transparent;
        }

        /* --- Buttons --- */
        .btn-iq-primary {
            background-color: var(--iq-secondary);
            border-color: var(--iq-secondary);
            color: white;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(13, 131, 75, 0.2);
            transition: all 0.3s;
        }
        
        .btn-iq-primary:hover {
            background-color: #064422;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(13, 131, 75, 0.3);
            color: white;
        }

        /* --- Utilities --- */
        .iq-bg-soft-danger { background: rgba(192, 50, 33, 0.1); color: #c03221; padding: 4px 12px; border-radius: 4px; }
        .iq-bg-soft-primary { background: rgba(13, 131, 75, 0.1); color: var(--iq-secondary); padding: 4px 12px; border-radius: 4px; }
        
        /* Responsive */
        @media (max-width: 991.98px) {
            #sidebar-wrapper { margin-left: -260px; position: fixed; height: 100%; box-shadow: 5px 0 15px rgba(0,0,0,0.1); }
            #wrapper.toggled #sidebar-wrapper { margin-left: 0; }
        }
    </style>
</head>
<body>

<div class="d-flex">
        <?php $layout->sidebar(); ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php $layout->topbar($pageTitle ?? ''); ?>
            
            <div class="container-fluid">
               
            
            
            <div class="row mb-4 align-items-center animate__animated animate__fadeInDown">
                <div class="col-md-6">
                    <h3 class="fw-bold m-0 text-dark">User Profile</h3>
                    <p class="text-muted mb-0 small mt-1">Manage your account parameters and settings.</p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <a href="dashboard.php" class="btn btn-light shadow-sm border text-muted fw-bold">
                        <i class="bi bi-arrow-left me-2"></i> Dashboard
                    </a>
                </div>
            </div>

            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success border-0 shadow-sm animate__animated animate__zoomIn d-flex align-items-center mb-4" role="alert" style="background: #d1e7dd; color: #0f5132;">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                </div>
            <?php elseif (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger border-0 shadow-sm animate__animated animate__zoomIn d-flex align-items-center mb-4" role="alert" style="background: #f8d7da; color: #842029;">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                </div>
            <?php endif; ?>

            <div class="row animate__animated animate__fadeInUp">
                
                <div class="col-xl-9 col-lg-10 mx-auto">
                    <div class="iq-card overflow-hidden">
                        
                        <div class="profile-header">
                            </div>

                        <div class="card-body p-0">
                            <div class="profile-header-content border-bottom">
                                <div class="profile-img-wrap">
                                    <img id="preview" src="<?= $display_pic ?>" class="profile-img">
                                    <div class="upload-btn-wrapper">
                                        <label for="profile_pic" class="btn-upload" title="Change Photo">
                                            <i class="bi bi-camera"></i>
                                        </label>
                                    </div>
                                </div>
                                <h4 class="fw-bold mt-3 mb-1"><?= htmlspecialchars($member['full_name']) ?></h4>
                                <div class="d-flex justify-content-center gap-2 mt-2">
                                    <span class="iq-bg-soft-primary small fw-bold">Member ID: <?= str_pad((string)$member_id, 4, '0', STR_PAD_LEFT) ?></span>
                                    <span class="iq-bg-soft-primary small fw-bold text-uppercase"><?= htmlspecialchars($member['gender'] ?: 'Unknown') ?></span>
                                </div>
                            </div>

                            <div class="p-4 p-md-5">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="file" name="profile_pic" id="profile_pic" accept="image/*" class="d-none" onchange="previewImage(event)">

                                    <div class="row g-4">
                                        
                                        <div class="col-lg-6">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="bg-light rounded-circle p-2 me-2 text-success"><i class="bi bi-pencil-square"></i></div>
                                                <h6 class="fw-bold m-0">Edit Contact Details</h6>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($member['email']); ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number</label>
                                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($member['phone']); ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Home Address</label>
                                                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($member['address']); ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Gender</label>
                                                <select name="gender" class="form-select">
                                                    <option value="male" <?= ($member['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                                    <option value="female" <?= ($member['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-check mt-3">
                                                <input class="form-check-input" type="checkbox" name="remove_pic" id="remove_pic">
                                                <label class="form-check-label text-danger small fw-bold" for="remove_pic">
                                                    Remove profile picture
                                                </label>
                                            </div>

                                            <div class="mt-4">
                                                <h6 class="fw-bold mb-3 border-bottom pb-2">Identity Documents (KYC)</h6>
                                                <div class="list-group list-group-flush">
                                                    <?php foreach ($kyc_docs as $doc): ?>
                                                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                            <div>
                                                                <span class="d-block fw-bold small text-uppercase"><?= str_replace('_', ' ', $doc['document_type']) ?></span>
                                                                <span class="text-muted x-small">Uploaded: <?= date('d M Y', strtotime($doc['uploaded_at'])) ?></span>
                                                            </div>
                                                            <span class="badge rounded-pill bg-<?= $doc['status'] == 'verified' ? 'success' : ($doc['status'] == 'rejected' ? 'danger' : 'warning') ?> bg-opacity-10 text-<?= $doc['status'] == 'verified' ? 'success' : ($doc['status'] == 'rejected' ? 'danger' : 'warning') ?> px-2 py-1">
                                                                <?= strtoupper($doc['status']) ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($kyc_docs)): ?>
                                                        <div class="text-muted small py-2">No documents uploaded.</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-lg-6">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="bg-light rounded-circle p-2 me-2 text-secondary"><i class="bi bi-shield-lock"></i></div>
                                                <h6 class="fw-bold m-0">Account Information (Locked)</h6>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Full Name</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($member['full_name']); ?>" readonly>
                                                </div>
                                                <div class="form-text small">Name changes require admin approval.</div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">National ID / Passport</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-card-heading"></i></span>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($member['national_id']); ?>" readonly>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Date Joined</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
                                                    <input type="text" class="form-control" value="<?= date('F j, Y', strtotime($member['join_date'])); ?>" readonly>
                                                </div>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="mt-5 pt-3 border-top d-flex justify-content-end">
                                        <button type="submit" class="btn btn-iq-primary btn-lg">
                                            <i class="bi bi-check-circle-fill me-2"></i>Save Changes
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // 1. Sidebar Toggle (Mobile Friendly)
    document.addEventListener('DOMContentLoaded', function() {
        // You can attach a click listener to your topbar toggle button here if you have its ID
        // Example: 
        // document.getElementById('menu-toggle').addEventListener('click', function(e) {
        //     e.preventDefault();
        //     document.getElementById('wrapper').classList.toggle('toggled');
        // });
    });

    // 2. Image Preview Logic
    function previewImage(event) {
        const reader = new FileReader();
        const file = event.target.files[0];
        if (file) {
            reader.onload = function() {
                const img = document.getElementById('preview');
                img.src = reader.result;
                // Add a subtle animation to show change
                img.classList.add('animate__animated', 'animate__pulse');
                setTimeout(() => img.classList.remove('animate__animated', 'animate__pulse'), 1000);
            }
            reader.readAsDataURL(file);
        }
    }
</script>
</body>
</html>




