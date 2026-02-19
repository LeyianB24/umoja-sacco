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
// member/repay_loan.php

// 1. Auth & Input Validation
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];
$loan_id = filter_input(INPUT_GET, 'loan_id', FILTER_VALIDATE_INT);

if (!$loan_id) {
    // Redirect back if no loan selected
    header("Location: loans.php");
    exit;
}

// 2. Fetch Loan Details
// We check for 'current_balance' column (from your DB update) or fallback to total_payable
$sql = "SELECT l.*, m.phone 
        FROM loans l 
        JOIN members m ON l.member_id = m.member_id
        WHERE l.loan_id = ? AND l.member_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $loan_id, $member_id);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();

if (!$loan) die("Loan not found or access denied.");

// Determine actual balance
$balance = $loan['current_balance'] ?? $loan['total_payable']; 
// If balance is 0, they shouldn't be here, but handle gracefully
if ($balance <= 0) {
    echo "<script>alert('This loan is already cleared!'); window.location='loans.php';</script>";
    exit;
}

$pageTitle = "Repay Loan #" . $loan_id;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --bg-app: #f0f2f5;
            --glass-bg: rgba(255, 255, 255, 0.8);
            --glass-border: 1px solid rgba(255, 255, 255, 0.5);
            --text-main: #2c3e50;
            --text-muted: #6c757d;
            --input-bg: rgba(255, 255, 255, 0.6);
            --accent-green: #0d834b;
        }

        [data-bs-theme="dark"] {
            --bg-app: #050505;
            --glass-bg: rgba(20, 20, 20, 0.7);
            --glass-border: 1px solid rgba(255, 255, 255, 0.08);
            --text-main: #e0e0e0;
            --text-muted: #a0a0a0;
            --input-bg: rgba(255, 255, 255, 0.05);
            --accent-green: #15a86b;
        }

        body {
            background-color: var(--bg-app);
            color: var(--text-main);
            font-family: 'Segoe UI', sans-serif;
            transition: background 0.3s ease;
        }

        .hd-glass {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: var(--glass-border);
            box-shadow: 0 8px 32px 0 rgba(0,0,0,0.1);
            border-radius: 16px;
        }

        .form-control {
            background-color: var(--input-bg);
            border: 1px solid rgba(128, 128, 128, 0.2);
            color: var(--text-main);
            padding: 0.8rem 1rem;
        }
        .form-control:focus {
            background-color: var(--input-bg);
            border-color: var(--accent-green);
            color: var(--text-main);
            box-shadow: 0 0 0 0.25rem rgba(13, 131, 75, 0.25);
        }

        .mpesa-logo { filter: drop-shadow(0px 2px 4px rgba(0,0,0,0.1)); }
        
        /* Layout */
        .main-content-wrapper { margin-left: 260px; transition: margin-left 0.3s ease; }
        @media (max-width: 991.98px) { .main-content-wrapper { margin-left: 0; } }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>

<div class="d-flex">
    
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content-wrapper" style="min-height:100vh; overflow-x:hidden;">
        
        <?php $layout->topbar($pageTitle ?? ''); ?>

        <div class="container-fluid px-lg-5 px-4 py-4 d-flex align-items-center justify-content-center" style="min-height: 80vh;">
            
            <div class="row w-100 justify-content-center g-4">
                
                <div class="col-lg-5">
                    <div class="hd-glass p-4 h-100 d-flex flex-column justify-content-center">
                        <div class="text-center mb-4">
                            <div class="rounded-circle bg-secondary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="bi bi-file-earmark-text fs-2 text-muted"></i>
                            </div>
                            <h5 class="fw-bold mb-1" style="color: var(--text-main);">Loan Details</h5>
                            <span class="badge bg-warning  bg-opacity-25 border border-warning border-opacity-25 px-3">
                                ID: #<?= $loan['loan_id'] ?>
                            </span>
                        </div>

                        <div class="list-group list-group-flush bg-transparent">
                            <div class="list-group-item bg-transparent d-flex justify-content-between px-0 border-secondary border-opacity-10">
                                <span class="text-muted">Loan Type</span>
                                <span class="fw-bold text-uppercase" style="color: var(--text-main);"><?= $loan['loan_type'] ?></span>
                            </div>
                            <div class="list-group-item bg-transparent d-flex justify-content-between px-0 border-secondary border-opacity-10">
                                <span class="text-muted">Interest Rate</span>
                                <span class="fw-bold" style="color: var(--text-main);"><?= $loan['interest_rate'] ?>%</span>
                            </div>
                            <div class="list-group-item bg-transparent d-flex justify-content-between px-0 border-secondary border-opacity-10">
                                <span class="text-muted">Principal Amount</span>
                                <span class="fw-bold" style="color: var(--text-main);">KES <?= number_format((float)$loan['amount']) ?></span>
                            </div>
                            <div class="list-group-item bg-transparent d-flex justify-content-between px-0 border-0 pt-3">
                                <span class="text-muted fw-bold">OUTSTANDING BALANCE</span>
                                <span class="h4 fw-bold text-danger mb-0">KES <?= number_format((float)$balance, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="hd-glass p-4 p-md-5">
                        
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <img src="<?= ASSET_BASE ?>/images/mpesa_logo.png" alt="M-Pesa" class="mpesa-logo" height="40" 
                                 >
                            <h4 class="fw-bold mb-0" style="color: var(--text-main);">Make Repayment</h4>
                        </div>

                        <form method="POST" action="<?= BASE_URL ?>/member/pages/mpesa_request.php" id="repayForm">
                            
                            <input type="hidden" name="loan_id" value="<?= $loan['loan_id'] ?>">
                            <input type="hidden" name="contribution_type" value="loan_repayment">

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">M-Pesa Number</label>
                                <div class="input-group">
                                    <span class="input-group-text border-end-0" style="background: var(--input-bg); border-color: rgba(128,128,128,0.2); color: var(--text-muted);">
                                        <i class="bi bi-phone"></i>
                                    </span>
                                    <input type="tel" name="phone" class="form-control border-start-0" 
                                           value="<?= htmlspecialchars($loan['phone']) ?>" 
                                           placeholder="07XXXXXXXX" required pattern="^0[0-9]{9}$">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-uppercase text-muted">Amount to Pay</label>
                                <div class="input-group">
                                    <span class="input-group-text border-end-0 fw-bold" style="background: var(--input-bg); border-color: rgba(128,128,128,0.2); color: var(--accent-green);">
                                        KES
                                    </span>
                                    <input type="number" name="amount" class="form-control border-start-0 fw-bold" 
                                           placeholder="Enter Amount" min="10" max="<?= $balance ?>" required>
                                </div>
                                <div class="form-text text-muted small">Max payable: KES <?= number_format((float)$balance, 2) ?></div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success py-2 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" 
                                        style="background-color: var(--accent-green); border: none; border-radius: 8px;">
                                    <i class="bi bi-send-check-fill"></i> <span>Send STK Push</span>
                                </button>
                                <a href="loans.php" class="btn btn-link text-muted text-decoration-none">Cancel</a>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Theme Init
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', savedTheme);

    // Button Loading State
    document.getElementById('repayForm').addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
        
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }, 15000);
    });
</script>
</body>
</html>





