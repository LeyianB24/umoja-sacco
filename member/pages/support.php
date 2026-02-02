<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');

// member/support.php
// HOPE UI Support Portal - Forest Deep Premium Style
// V15 Unified Logic & Design

if (session_status() === PHP_SESSION_NONE) session_start();
// Validate Member Login
require_member();

// Initialize Layout Manager
$layout = LayoutManager::create('member');

$member_id = $_SESSION['member_id'];
$success = "";
$error = "";

// Process Ticket Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? 'general';
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
                $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['attachment']['name']));
                $dir = __DIR__ . "/../uploads/support/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);

                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . $fileName)) {
                    $attachmentPath = "uploads/support/" . $fileName;
                } else {
                    $error = "Failed to upload file.";
                }
            }
        }

        if (empty($error)) {
            $assigned_admin = 1; // Default to main support admin
            $sql = "INSERT INTO support_tickets (admin_id, member_id, category, subject, message, status, attachment, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iissss", $assigned_admin, $member_id, $category, $subject, $message, $attachmentPath);
            
            if ($stmt->execute()) {
                $ticket_id = $stmt->insert_id;
                // Notify member
                $notif_msg = "Your support ticket #$ticket_id has been created. Our team will review it shortly.";
                $conn->query("INSERT INTO notifications (member_id, title, message, status, user_type, user_id, created_at) 
                             VALUES ($member_id, 'Ticket #$ticket_id', '$notif_msg', 'unread', 'member', $member_id, NOW())");
                $success = "Ticket #$ticket_id submitted successfully!";
            } else {
                $error = "System Error: Unable to save ticket. Please try again later.";
            }
            if(isset($stmt)) $stmt->close();
        }
    }
}

$pageTitle = "Support Center";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= SITE_NAME ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --forest-deep: #0F2E25;
            --lime-accent: #D0F35D;
            --font-main: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            font-family: var(--font-main);
            background-color: #F9FAFB;
            color: #111827;
        }

        .main-content {
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-premium {
            background: #FFFFFF;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.03);
            height: 100%;
        }

        .info-card {
            background: #F3F4F6;
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 1rem;
            transition: 0.2s;
            border: 1px solid transparent;
        }

        .info-card:hover {
            background: #FFFFFF;
            border-color: var(--forest-deep);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .info-icon {
            width: 52px;
            height: 52px;
            background: var(--forest-deep);
            color: var(--lime-accent);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .form-control, .form-select {
            border-radius: 14px;
            padding: 0.8rem 1.2rem;
            border: 1px solid #E5E7EB;
            background: #FAFAFA;
            font-size: 0.95rem;
        }

        .form-control:focus, .form-select:focus {
            background: #FFFFFF;
            border-color: var(--forest-deep);
            box-shadow: 0 0 0 4px rgba(208, 243, 93, 0.15);
        }

        .btn-submit {
            background: var(--forest-deep);
            color: #FFFFFF;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 700;
            width: 100%;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            background: #154e3d;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(15, 46, 37, 0.2);
            color: var(--lime-accent);
        }

        .hours-badge {
            background: var(--forest-deep);
            color: white;
            padding: 2rem;
            border-radius: 24px;
            text-align: center;
            margin-top: 2.5rem;
        }

        .alert-custom {
            border-radius: 20px;
            padding: 1.5rem;
            border: none;
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
    </style>
</head>
<body>
<div class="d-flex">
        <?php $layout->sidebar(); ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php $layout->topbar($pageTitle ?? ''); ?>
            
            <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
                <div>
                    <h1 class="fw-800 mb-1" style="color: var(--forest-deep); letter-spacing: -1px;">Support Center</h1>
                    <p class="text-muted mb-0">Experience premium assistance for all your sacco needs.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="dashboard.php" class="btn btn-light rounded-pill px-4 border">
                        <i class="bi bi-arrow-left me-2"></i>Dashboard
                    </a>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-custom shadow-sm mb-4">
                    <div class="info-icon" style="background: #D1FAE5; color: #065F46;"><i class="bi bi-check2-circle"></i></div>
                    <div>
                        <div class="fw-bold fs-5">Ticket Submitted</div>
                        <div class="opacity-75"><?= htmlspecialchars($success) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-custom shadow-sm mb-4">
                    <div class="info-icon" style="background: #FEE2E2; color: #991B1B;"><i class="bi bi-exclamation-triangle"></i></div>
                    <div>
                        <div class="fw-bold fs-5">Execution Error</div>
                        <div class="opacity-75"><?= htmlspecialchars($error) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Contact Info -->
                <div class="col-lg-5">
                    <div class="card-premium">
                        <h4 class="fw-bold mb-4" style="color: var(--forest-deep);">Direct Contact</h4>
                        <p class="text-muted small mb-4">Connect with our specialized support desk for immediate assistance.</p>
                        
                        <div class="info-card">
                            <div class="info-icon"><i class="bi bi-geo-alt-fill"></i></div>
                            <div>
                                <small class="text-muted d-block uppercase fw-700" style="font-size: 0.65rem; letter-spacing: 1px;">HEADQUARTERS</small>
                                <div class="fw-bold"><?= defined('OFFICE_LOCATION') ? htmlspecialchars(OFFICE_LOCATION) : 'Nairobi, Kenya' ?></div>
                            </div>
                        </div>

                        <div class="info-card">
                            <div class="info-icon"><i class="bi bi-telephone-outbound-fill"></i></div>
                            <div>
                                <small class="text-muted d-block uppercase fw-700" style="font-size: 0.65rem; letter-spacing: 1px;">HOTLINE</small>
                                <div class="fw-bold"><?= defined('OFFICE_PHONE') ? htmlspecialchars(OFFICE_PHONE) : '+254 700 000000' ?></div>
                            </div>
                        </div>

                        <div class="info-card">
                            <div class="info-icon"><i class="bi bi-envelope-paper-fill"></i></div>
                            <div>
                                <small class="text-muted d-block uppercase fw-700" style="font-size: 0.65rem; letter-spacing: 1px;">OFFICIAL EMAIL</small>
                                <div class="fw-bold"><?= defined('OFFICE_EMAIL') ? htmlspecialchars(OFFICE_EMAIL) : 'support@umoja.com' ?></div>
                            </div>
                        </div>

                        <div class="hours-badge">
                            <i class="bi bi-clock-history fs-2 mb-3 d-block" style="color: var(--lime-accent);"></i>
                            <h5 class="fw-bold mb-2">Service Hours</h5>
                            <div class="opacity-75 small">Monday — Friday: 08:00 - 17:00</div>
                            <div class="opacity-75 small">Saturday: 09:00 - 13:00</div>
                        </div>
                    </div>
                </div>

                <!-- Ticket Form -->
                <div class="col-lg-7">
                    <div class="card-premium">
                        <h4 class="fw-bold mb-4" style="color: var(--forest-deep);">Create Support Ticket</h4>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-muted">Ticket Category</label>
                                    <select name="category" class="form-select">
                                        <option value="general">General Inquiry</option>
                                        <option value="funds">Savings & Withdrawals</option>
                                        <option value="loans">Loan Applications</option>
                                        <option value="welfare">Welfare & Benefits</option>
                                        <option value="technical">Technical Support</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-muted">Subject</label>
                                    <input type="text" name="subject" class="form-control" placeholder="Briefly describe the issue" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-muted">Detailed Description</label>
                                    <textarea name="message" class="form-control" rows="6" placeholder="Provide as much detail as possible..." required></textarea>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-muted">Supporting Document (Optional)</label>
                                    <input type="file" name="attachment" class="form-control">
                                    <small class="text-muted">Max file size: 5MB (JPG, PNG, PDF)</small>
                                </div>
                                <div class="col-md-12 mt-4">
                                    <button type="submit" class="btn-submit">
                                        <i class="bi bi-send-check-fill"></i> Submit Formal Request
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php $layout->footer(); ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>




