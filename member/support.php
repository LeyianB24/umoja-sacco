<?php
// member/support.php
// Enhanced UI: Forest Green & Lime Theme + Responsive Sidebar

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

// 1. Auth Check
if (empty($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];
$success = "";
$error = "";

// 2. Process Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($subject === '' || $message === '') {
        $error = "Please fill in all required fields.";
    } else {
        $attachmentPath = null;
        if (!empty($_FILES['attachment']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                $error = "Invalid file type. Allowed: JPG, PNG, PDF, DOC.";
            } elseif ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
                $error = "File size exceeds 5MB limit.";
            } else {
                $fileName = time() . '_' . basename($_FILES['attachment']['name']);
                $dir = __DIR__ . "/../public/uploads/support/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);

                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . $fileName)) {
                    $attachmentPath = "public/uploads/support/" . $fileName;
                } else {
                    $error = "Failed to upload file.";
                }
            }
        }

        if (empty($error)) {
            // Assign to 'admin' (ID 1) by default or random logic
            $assigned_admin = 1; 
            $sql = "INSERT INTO support_tickets (admin_id, member_id, subject, message, status, attachment) VALUES (?, ?, ?, ?, 'Pending', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisss", $assigned_admin, $member_id, $subject, $message, $attachmentPath);
            
            if ($stmt->execute()) {
                $ticket_id = $stmt->insert_id;
                // Create Notification
                $n_msg = "Ticket #$ticket_id has been received.";
                $conn->query("INSERT INTO notifications (member_id, title, message, status) VALUES ($member_id, 'Ticket Received', '$n_msg', 'unread')");
                
                $success = "Ticket #$ticket_id submitted successfully!";
            } else {
                $error = "Database error: Unable to submit ticket.";
            }
        }
    }
}

$pageTitle = "Help Center";
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

        /* --- CARDS --- */
        .hope-card {
            background: var(--hop-card-bg);
            border-radius: var(--card-radius);
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            padding: 1.5rem;
            height: 100%;
        }

        /* Contact Card (Dark Green) */
        .card-contact {
            background: var(--hop-dark);
            color: white;
            position: relative;
            overflow: hidden;
        }
        .card-contact::before {
            content: ''; position: absolute; top: -50px; right: -50px;
            width: 200px; height: 200px; border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
        }

        /* Icons */
        .icon-circle {
            width: 48px; height: 48px; border-radius: 50%;
            background: rgba(255,255,255,0.1); color: var(--hop-lime);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; transition: all 0.3s;
        }
        .contact-item:hover .icon-circle {
            background: var(--hop-lime); color: var(--hop-dark);
        }

        /* --- FORMS --- */
        .form-control, .form-select {
            background-color: var(--hop-input);
            border: 1px solid transparent;
            border-radius: 12px;
            padding: 0.8rem 1rem;
            color: var(--hop-text);
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            background-color: var(--hop-card-bg);
            border-color: var(--hop-lime);
            box-shadow: 0 0 0 4px rgba(208, 243, 93, 0.2);
            color: var(--hop-text);
        }

        /* --- BUTTONS --- */
        .btn-lime {
            background-color: var(--hop-lime);
            color: var(--hop-dark);
            border: none; border-radius: 50px;
            padding: 12px 24px; font-weight: 700;
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
            background: var(--hop-card-bg);
        }
        .btn-back:hover { background: var(--hop-input); color: var(--hop-text); }
    </style>
</head>
<body>

<div class="d-flex">
    
    <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

    <div class="flex-fill main-content-wrapper d-flex flex-column min-vh-100">
        
        <?php require_once __DIR__ . '/../inc/topbar.php'; ?>

        <div class="container-fluid grow py-5 px-4">
            
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="fw-bold mb-1">Help Center</h2>
                    <p class="text-secondary mb-0">We are here to assist you.</p>
                </div>
                <a href="dashboard.php" class="btn btn-back text-decoration-none">
                    <i class="bi bi-arrow-left me-2"></i>Back
                </a>
            </div>

            <div class="row g-4 align-items-stretch">
                
                <div class="col-lg-4">
                    <div class="hope-card card-contact p-4 p-xl-5 d-flex flex-column justify-content-between">
                        <div>
                            <h4 class="fw-bold mb-4 text-white">Get in touch</h4>
                            <p class="opacity-75 mb-5 small text-white">
                                Prefer to talk directly? Reach out via phone, email, or visit our headquarters.
                            </p>

                            <div class="col-lg-4 col-md-12">
                <h5 class="section-title">Connect With Us</h5>

                <ul class="list-unstyled mb-4 opacity-75 small footer-contact-list">
                     <div class="mt-5 pt-3 border-top border-white border-opacity-10">
                   
                        <i class="bi bi-geo-alt-fill me-3 text-warning mt-1"></i>
                        <span><?= htmlspecialchars(OFFICE_LOCATION) ?></span>
                    </div>
 <div class="mt-5 pt-3 border-top border-white border-opacity-10">
                    <li class="mb-3 d-flex align-items-center">
                        <i class="bi bi-telephone-fill me-3 text-warning"></i>
                        <a href="tel:<?= htmlspecialchars(OFFICE_PHONE) ?>"
                           class="text-reset text-decoration-none fw-bold">
                           <?= htmlspecialchars(OFFICE_PHONE) ?>
                        </a>
                    </li></div>
 <div class="mt-5 pt-3 border-top border-white border-opacity-10">
                    <li class="mb-3 d-flex align-items-center">
                        <i class="bi bi-envelope-fill me-3 text-warning"></i>
                        <a href="mailto:<?= htmlspecialchars(OFFICE_EMAIL) ?>"
                           class="text-reset text-decoration-none">
                           <?= htmlspecialchars(OFFICE_EMAIL) ?>
                        </a>
                    </li></div>
</ul></div>
                        </div>

                        <div class="mt-5 pt-3 border-top border-white border-opacity-10">
                            <div class="d-flex align-items-center gap-2 small opacity-50 text-white">
                                <i class="bi bi-clock"></i> Mon - Fri, 8:00 AM - 5:00 PM
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="hope-card p-4 p-xl-5">
                        
                        <?php if ($success): ?>
                            <div class="alert border-0 rounded-4 d-flex align-items-center mb-4" style="background: var(--hop-lime); color: var(--hop-dark);">
                                <i class="bi bi-check-circle-fill me-2 fs-5"></i> <?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 rounded-4 bg-danger bg-opacity-10 text-danger d-flex align-items-center mb-4">
                                <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="supportForm">
                            <h4 class="fw-bold mb-4">Submit a Ticket</h4>
                            
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-secondary">Topic</label>
                                    <select name="subject" class="form-select" required>
                                        <option value="">Select Category...</option>
                                        <optgroup label="Financial">
                                            <option>Loan Issue</option>
                                            <option>Repayment Problem</option>
                                            <option>MPESA Transaction Failed</option>
                                        </optgroup>
                                        <optgroup label="Account">
                                            <option>Login Issue</option>
                                            <option>Profile Update</option>
                                        </optgroup>
                                        <optgroup label="General">
                                            <option>Inquiry</option>
                                            <option>Other</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold small text-secondary">Description</label>
                                    <textarea name="message" class="form-control" style="height: 160px; resize: none;" placeholder="Please describe your issue in detail..." required></textarea>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold small text-secondary">Attachment <span class="text-muted fw-normal">(Optional, Max 5MB)</span></label>
                                    <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" id="fileInput">
                                </div>

                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-lime w-100" id="submitBtn">
                                        Send Message <i class="bi bi-send-fill ms-2"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
        
        <?php require_once __DIR__ . '/../inc/footer.php'; ?>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // 1. File Size Check
    document.getElementById('fileInput')?.addEventListener('change', function() {
        if(this.files[0].size > 5242880) { // 5MB
            alert("File is too big! Max 5MB allowed.");
            this.value = "";
        }
    });

    // 2. Prevent Double Submit
    document.getElementById('supportForm')?.addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
        btn.style.opacity = '0.8';
        btn.disabled = true;
    });
</script>
</body>
</html>