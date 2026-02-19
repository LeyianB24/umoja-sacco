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
    verify_csrf_token();
    
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $nok_name = trim($_POST['nok_name'] ?? '');
    $nok_phone = trim($_POST['nok_phone'] ?? '');
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

    // KYC Upload Handling
    if (!empty($_FILES['kyc_doc']['name']) && $_FILES['kyc_doc']['error'] === UPLOAD_ERR_OK) {
        $doc_type = $_POST['doc_type'] ?? '';
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_type = mime_content_type($_FILES['kyc_doc']['tmp_name']);
        $file_size = $_FILES['kyc_doc']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and PDF are allowed.";
        } elseif ($file_size > $max_size) {
            $_SESSION['error'] = "File is too large. Max size is 5MB.";
        } else {
            $upload_dir = __DIR__ . '/../../uploads/kyc/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_name = $_FILES['kyc_doc']['name'];
            $file_tmp = $_FILES['kyc_doc']['tmp_name'];
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_name = "{$doc_type}_{$member_id}_" . time() . ".$ext";
            
            if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                $stmt = $conn->prepare("INSERT INTO member_documents (member_id, document_type, file_path, status) VALUES (?, ?, ?, 'pending') ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), status = 'pending', uploaded_at = NOW()");
                $stmt->bind_param("iss", $member_id, $doc_type, $new_name);
                $stmt->execute();
                $stmt->close();
                
                // Update member's general kyc_status to pending if it was not_submitted
                $conn->query("UPDATE members SET kyc_status = 'pending' WHERE member_id = $member_id AND kyc_status = 'not_submitted'");
            }
        }
    }

    // Use current values if not provided in POST (for readonly/missing fields)
    $email = !empty($email) ? $email : $current['email'];
    $phone = !empty($phone) ? $phone : $current['phone'];
    $gender = !empty($gender) ? $gender : $current['gender'];
    $address = !empty($address) ? $address : $current['address'];

    // Update Profile
    $sql = "UPDATE members SET email=?, phone=?, address=?, gender=?, dob=?, occupation=?, next_of_kin_name=?, next_of_kin_phone=?, profile_pic=? WHERE member_id=?";
    $stmt = $conn->prepare($sql);
    $null = null; 
    $stmt->bind_param("ssssssssbi", $email, $phone, $address, $gender, $dob, $occupation, $nok_name, $nok_phone, $null, $member_id);
    if ($pic_data !== null) {
        $stmt->send_long_data(8, $pic_data);
    }
    
    if ($stmt->execute()) {
        // Trigger Profile Updated Notification
        require_once __DIR__ . '/../../inc/notification_helpers.php';
        send_notification($conn, (int)$member_id, 'profile_updated');

        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: profile.php");
        exit;
    } else {
        $_SESSION['error'] = "Update failed: " . $conn->error;
    }
    $stmt->close();
}

// 3. Fetch Data
$stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 3.1 Fetch Registration Fee Transaction
$fee_stmt = $conn->prepare("SELECT * FROM transactions WHERE member_id = ? AND transaction_type = 'registration_fee' OR (related_table = 'members' AND description LIKE '%Registration%') ORDER BY created_at DESC LIMIT 1");
$fee_stmt->bind_param("i", $member_id);
$fee_stmt->execute();
$fee_txn = $fee_stmt->get_result()->fetch_assoc();
$fee_stmt->close();

$reg_fee_paid = ($member['registration_fee_status'] === 'paid' || $member['reg_fee_paid'] == 1);

// 3.2 Determine Dynamic Account Status
$account_status = 'Incomplete';
$status_color = 'danger';

if (!$reg_fee_paid) {
    $account_status = 'Incomplete (Fee Unpaid)';
} elseif ($member['kyc_status'] === 'not_submitted') {
    $account_status = 'Pending Verification (No KYC)';
    $status_color = 'warning';
} elseif ($member['kyc_status'] === 'pending') {
    $account_status = 'Under Review';
    $status_color = 'info';
} elseif ($member['kyc_status'] === 'approved' && $reg_fee_paid) {
    $account_status = 'Active';
    $status_color = 'success';
} elseif ($member['kyc_status'] === 'rejected') {
    $account_status = 'KYC Rejected';
    $status_color = 'danger';
}

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
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
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

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>

<div class="d-flex">
        <?php $layout->sidebar(); ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php $layout->topbar($pageTitle ?? ''); ?>
            
            <div class="container-fluid">
               
            
            
            <div class="row mb-4 align-items-center animate__animated animate__fadeInDown">
                <div class="col-md-6">
                    <h3 class="fw-bold m-0">User Profile</h3>
                    <p class="text-muted mb-0 small mt-1">Manage your account parameters and settings.</p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <span class="badge bg-<?= $status_color ?> bg-opacity-10 text-<?= $status_color ?> px-3 py-2 fs-6 border border-<?= $status_color ?> border-opacity-25 rounded-pill">
                        Status: <?= strtoupper($account_status) ?>
                    </span>
                </div>
            </div>

            <?php if ($member['kyc_status'] === 'not_submitted'): ?>
                <div class="alert alert-warning border-0 shadow-sm animate__animated animate__shakeX d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-shield-exclamation me-3 fs-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">KYC documents are pending!</h6>
                        <p class="mb-0 small">Please upload your National ID and Passport photo to complete verification and activate your account.</p>
                    </div>
                </div>
            <?php elseif ($member['kyc_status'] === 'rejected'): ?>
                <div class="alert alert-danger border-0 shadow-sm animate__animated animate__shakeX d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-x-octagon-fill me-3 fs-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">KYC documents were rejected!</h6>
                        <p class="mb-0 small">Reason: <?= esc($member['kyc_notes'] ?? 'Please re-upload clear documents.') ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$reg_fee_paid): ?>
                <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-cash-coin me-3 fs-3"></i>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-1">Registration Fee Pending</h6>
                        <p class="mb-0 small">Your registration fee of KES 1,000 is required to access full member benefits.</p>
                    </div>
                    <a href="pay_registration.php" class="btn btn-info btn-sm fw-bold">Pay Now</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success border-0 shadow-sm animate__animated animate__zoomIn d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                </div>
            <?php elseif (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger border-0 shadow-sm animate__animated animate__zoomIn d-flex align-items-center mb-4" role="alert">
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
                                    <span class="iq-bg-soft-primary small fw-bold">Member No: <?= htmlspecialchars($member['member_reg_no']) ?></span>
                                    <span class="iq-bg-soft-primary small fw-bold text-uppercase"><?= htmlspecialchars($member['gender'] ?: 'Unknown') ?></span>
                                </div>
                            </div>

                            <div class="p-4 p-md-5">
                                <form method="POST" enctype="multipart/form-data">
                                    <?= csrf_field() ?>
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
                                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($member['phone'] ?? ''); ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Date of Birth</label>
                                                <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($member['dob'] ?? ''); ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Occupation</label>
                                                <input type="text" name="occupation" class="form-control" value="<?= htmlspecialchars($member['occupation'] ?? ''); ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Home Address</label>
                                                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($member['address'] ?? ''); ?>">
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
                                                <div class="input-group text-muted">
                                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($member['full_name']); ?>" readonly>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Gender</label>
                                                <div class="input-group text-muted">
                                                    <span class="input-group-text"><i class="bi bi-gender-ambiguous"></i></span>
                                                    <input type="text" class="form-control" value="<?= ucwords($member['gender'] ?? 'Unknown'); ?>" readonly>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">National ID / Passport</label>
                                                <div class="input-group text-muted">
                                                    <span class="input-group-text"><i class="bi bi-card-heading"></i></span>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($member['national_id']); ?>" readonly>
                                                </div>
                                            </div>

                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Next of Kin</label>
                                                    <input type="text" name="nok_name" class="form-control" value="<?= htmlspecialchars($member['next_of_kin_name'] ?? ''); ?>" placeholder="Name">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">NOK Phone</label>
                                                    <input type="text" name="nok_phone" class="form-control" value="<?= htmlspecialchars($member['next_of_kin_phone'] ?? ''); ?>" placeholder="Phone">
                                                </div>
                                            </div>

                                            <div class="mt-4 p-3 rounded-4 bg-light border">
                                                <h6 class="fw-bold mb-3 small text-uppercase opacity-75">Registration Payment</h6>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <span class="d-block fw-bold small">Status: 
                                                            <span class="text-<?= $reg_fee_paid ? 'success' : 'danger' ?>">
                                                                <?= $reg_fee_paid ? 'PAID' : 'PENDING' ?>
                                                            </span>
                                                        </span>
                                                        <?php if ($fee_txn): ?>
                                                            <span class="text-muted x-small">Ref: <?= $fee_txn['reference_no'] ?> | <?= date('d M Y', strtotime($fee_txn['created_at'])) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($reg_fee_paid): ?>
                                                        <i class="bi bi-patch-check-fill text-success fs-4"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-exclamation-circle-fill text-danger fs-4"></i>
                                                    <?php endif; ?>
                                                </div>
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

                                    <div class="mt-5 pt-3 border-top d-flex justify-content-between align-items-center">
                                        <div class="text-muted small">
                                            <i class="bi bi-info-circle me-1"></i> Locked fields require admin intervention to change.
                                        </div>
                                        <button type="submit" class="btn btn-iq-primary btn-lg">
                                            <i class="bi bi-check-circle-fill me-2"></i>Save Changes
                                        </button>
                                    </div>
                                </form>

                                <div class="mt-5 pt-4 border-top">
                                    <h5 class="fw-bold mb-4">Complete Your KYC</h5>
                                    <div class="row g-4">
                                        <?php 
                                        $required_docs = [
                                            'national_id_front' => 'National ID (Front)',
                                            'national_id_back' => 'National ID (Back)',
                                            'passport_photo' => 'Passport Photo'
                                        ];
                                        foreach ($required_docs as $type => $label): 
                                            $doc = array_filter($kyc_docs, fn($d) => $d['document_type'] === $type);
                                            $doc = !empty($doc) ? array_shift($doc) : null;
                                        ?>
                                            <div class="col-md-4">
                                                <div class="p-3 rounded-4 border bg-white shadow-sm h-100">
                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                        <h6 class="fw-bold mb-0"><?= $label ?></h6>
                                                        <?php if ($doc): ?>
                                                            <span class="badge rounded-pill bg-<?= $doc['status'] == 'verified' ? 'success' : ($doc['status'] == 'rejected' ? 'danger' : 'warning') ?> bg-opacity-10 text-<?= $doc['status'] == 'verified' ? 'success' : ($doc['status'] == 'rejected' ? 'danger' : 'warning') ?>">
                                                                <?= strtoupper($doc['status']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge rounded-pill bg-secondary bg-opacity-10 text-secondary">MISSING</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if ($doc && $doc['status'] === 'verified'): ?>
                                                        <div class="text-center py-3">
                                                            <i class="bi bi-shield-check text-success fs-1"></i>
                                                            <p class="text-muted small mt-2">Verified on <?= date('d M Y', strtotime($doc['verified_at'] ?? 'now')) ?></p>
                                                        </div>
                                                    <?php else: ?>
                                                        <form method="POST" enctype="multipart/form-data" class="mt-auto">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="doc_type" value="<?= $type ?>">
                                                            <div class="mb-2">
                                                                <input type="file" name="kyc_doc" class="form-control form-control-sm" required accept="image/*,application/pdf">
                                                            </div>
                                                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100 fw-bold">
                                                                <?= $doc ? 'Re-upload' : 'Upload' ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
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




