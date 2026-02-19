<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');
// usms/member/pay_registration.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/RegistrationHelper.php';
require_once __DIR__ . '/../layouts/includes/auth_check.php';

// Validate log in
if (!isset($_SESSION['member_id'])) {
    header("Location: ../public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];
$success = false;
$error = "";

// Fetch Member Data
$res = $conn->query("SELECT * FROM members WHERE member_id = $member_id");
$member = $res->fetch_assoc();

// Double check if already paid
if (($_SESSION['registration_fee_status'] ?? 'unpaid') === 'paid' || ($member['registration_fee_status'] ?? '') === 'paid') {
    $_SESSION['registration_fee_status'] = 'paid';
    flash_set("You have already paid your registration fee.", "info");
    header("Location: dashboard.php");
    exit;
}

// Handle Payment Simulation
// In a real app, this would integrate with M-Pesa STK Push
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_fee'])) {
    verify_csrf_token();
    
    $amount = 1000.00; // Database Default
    
    // Check system settings for amount if available
    $s_query = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'registration_fee'");
    if($s_query && $s_row = $s_query->fetch_assoc()) {
        $amount = (float)$s_row['setting_value'];
    }

    $ref = 'REG-' . strtoupper(bin2hex(random_bytes(4)));
    $method = $_POST['payment_method'] ?? 'mpesa';

    if (RegistrationHelper::markAsPaid($member_id, $amount, $ref, $conn)) {
        $_SESSION['registration_fee_status'] = 'paid';
        unset($_SESSION['pending_pay']);
        $success = true;
    } else {
        $error = "Payment processing failed. Please try again.";
    }
}

$pageTitle = "Registration Fee Payment";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>/public/assets/css/style.css" rel="stylesheet">
    
    <style>
        body { 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex; align-items: center; min-height: 100vh;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .pay-card { 
            border: none;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(15, 46, 37, 0.08);
            overflow: hidden;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .pay-header {
            background: linear-gradient(135deg, var(--hope-green) 0%, #0a2e1f 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .pay-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.3; }
        }
        .pay-header::after {
            content: '';
            position: absolute; bottom: -24px; left: 0; right: 0;
            height: 48px;
            background: white;
            border-radius: 50% 50% 0 0;
        }
        .fee-badge { 
            background: rgba(255,255,255,0.2); 
            color: var(--hope-lime); 
            padding: 0.5rem 1.2rem; 
            border-radius: 50px; 
            font-weight: 700; 
            font-size: 0.8rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            animation: glow 2s ease-in-out infinite;
        }
        @keyframes glow {
            0%, 100% { box-shadow: 0 0 20px rgba(255,255,255,0.3); }
            50% { box-shadow: 0 0 30px rgba(255,255,255,0.5); }
        }
        .amount-display {
            font-size: 3rem;
            font-weight: 800;
            color: var(--hope-green);
            letter-spacing: -1px;
            animation: countUp 1s ease-out;
        }
        @keyframes countUp {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .method-label {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid var(--bs-border-color);
            position: relative;
            overflow: hidden;
        }
        .method-label::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(15, 46, 37, 0.05);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: all 0.5s ease;
        }
        .btn-check:checked + .method-label {
            border-color: var(--hope-green);
            background-color: rgba(15, 46, 37, 0.05);
            color: var(--hope-green);
            transform: scale(1.05);
        }
        .btn-check:checked + .method-label::before {
            width: 300px;
            height: 300px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--hope-green) 0%, #0a2e1f 100%);
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        .btn-primary:hover::before {
            left: 100%;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(15, 46, 37, 0.3);
        }
        .alert-success {
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card pay-card">
                <div class="pay-header">
                    <div class="fee-badge mb-3 d-inline-block">Action Required</div>
                    <h2 class="fw-bold mb-1">Welcome aboard!</h2>
                    <p class="text-white-50 mb-0">Complete your registration to access the vault.</p>
                </div>
                
                <div class="card-body p-4 pt-4 mt-3">
                    <div class="text-center mb-5">
                        <p class="text-muted small fw-bold text-uppercase mb-1">Processing Fee</p>
                        <div class="amount-display">KES <?= number_format($amount ?? 1000) ?></div>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success border-0 py-5 text-center bg-success bg-opacity-10 rounded-4">
                            <div class="display-1 text-success mb-3"><i class="bi bi-check-circle-fill"></i></div>
                            <h4 class="fw-bold text-success mb-2">Access Granted!</h4>
                            <p class="small text-muted mb-4 px-4">Your payment has been verified. Welcome to the Umoja Sacco Digital Platform.</p>
                            <a href="dashboard.php" class="btn btn-dark rounded-pill px-5 py-3 fw-bold w-100 shadow-sm">
                                Enter Dashboard <i class="bi bi-arrow-right ms-2"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 rounded-4 small mb-4 d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                                <div><?= $error ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <?= csrf_field() ?>
                            
                            <label class="form-label small fw-bold text-uppercase text-muted mb-3">Select Payment Method</label>
                            <div class="row g-3 mb-4">
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="payment_method" id="pay_mpesa" value="mpesa" checked>
                                    <label class="btn method-label w-100 py-3 rounded-4 fw-bold d-flex flex-column align-items-center justify-content-center h-100" for="pay_mpesa">
                                        <i class="bi bi-phone fs-3 mb-2"></i>
                                        <span>M-Pesa</span>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="payment_method" id="pay_cash" value="cash">
                                    <label class="btn method-label w-100 py-3 rounded-4 fw-bold d-flex flex-column align-items-center justify-content-center h-100" for="pay_cash">
                                        <i class="bi bi-cash-stack fs-3 mb-2"></i>
                                        <span>Cash / Office</span>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-4 p-3 rounded-4 bg-light small text-secondary d-flex align-items-start">
                                <i class="bi bi-shield-lock-fill me-3 fs-5  mt-1"></i>
                                <div>
                                    <strong class="">Secure Transaction</strong><br>
                                    Your payment is processed securely. For M-Pesa, check your phone for the STK push prompt.
                                </div>
                            </div>

                            <button type="submit" name="pay_fee" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-lg" style="background: var(--hope-green); border-color: var(--hope-green);">
                                Process Payment & Unlock
                            </button>
                        </form>

                        <div class="mt-4 text-center">
                            <a href="../public/logout.php" class="text-decoration-none small text-secondary fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">
                                <i class="bi bi-box-arrow-left me-1"></i> Sign Out
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <p class="small text-muted opacity-50">&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>




