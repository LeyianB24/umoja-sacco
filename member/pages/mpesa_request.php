<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

// ---------------------------------------------------
// 1. Load Config & DB
// ---------------------------------------------------
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/app_config.php';
$env_config = require_once __DIR__ . '/../../config/environment.php';
require_once __DIR__ . '/../../inc/RegistrationHelper.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php'; // Added this back as it's needed for LayoutManager::create()
require_once __DIR__ . '/../../inc/functions.php'; // Added this back as it's a general utility file

$layout = LayoutManager::create('member');
require_once __DIR__ . '/../../inc/email.php';
require_once __DIR__ . '/../../inc/sms.php';

// ---------- Config / Helpers (BACKEND LOGIC UNCHANGED) ----------
define('MIN_AMOUNT', 10.0);
define('PHONE_COUNTRY_CODE', '254');

function normalize_phone(string $raw): string {
    $digits = preg_replace('/\D+/', '', $raw);
    if (preg_match('/^0([0-9]{9})$/', $digits, $m)) {
        return PHONE_COUNTRY_CODE . $m[1];
    }
    if (preg_match('/^7([0-9]{8})$/', $digits, $m)) {
        return PHONE_COUNTRY_CODE . '7' . $m[1]; 
    }
    if (preg_match('/^' . PHONE_COUNTRY_CODE . '7[0-9]{8}$/', $digits)) {
        return $digits;
    }
    return '';
}

function log_mpesa_error(mysqli $conn, string $member_id, string $msg, array $context = []): void {
    $stmt = $conn->prepare("INSERT INTO mpesa_error_logs (member_id, message, payload, created_at) VALUES (?, ?, ?, NOW())");
    if ($stmt) {
        $payload = json_encode($context);
        $stmt->bind_param("sss", $member_id, $msg, $payload);
        $stmt->execute();
        $stmt->close();
    }
}

// ---------- Auth ----------
if (!isset($_SESSION['member_id']) || empty($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}
$member_id = (int) $_SESSION['member_id'];

// ---------- Fetch member ----------
$member_email = '';
$member_name  = 'Member';
$db_phone     = '';

try {
    $stmt = $conn->prepare("SELECT full_name, email, phone FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $member_name  = $row['full_name'] ?? $member_name;
        $member_email = $row['email'] ?? $member_email;
        $db_phone     = $row['phone'] ?? $db_phone;
    }
    $stmt->close();
} catch (Throwable $t) { }

// ---------- Fetch active welfare cases ----------
$active_cases = [];
try {
    $res_cases = $conn->query("SELECT case_id, title FROM welfare_cases WHERE status IN ('active','approved','funded') ORDER BY created_at DESC");
    if ($res_cases) {
        while ($r = $res_cases->fetch_assoc()) {
            $active_cases[] = $r;
        }
    }
} catch (Throwable $t) { }

// Pre-fill from URL
$url_type    = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'savings';
$url_loan_id = filter_input(INPUT_GET, 'loan_id', FILTER_SANITIZE_NUMBER_INT) ?? '';
$url_case_id = filter_input(INPUT_GET, 'case_id', FILTER_SANITIZE_NUMBER_INT) ?? '';

$error = $success = '';

// ---------- Handle POST (Form submit) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF (Dies on failure)
    verify_csrf_token();

    $phone_raw = trim((string) ($_POST['phone'] ?? $db_phone));
    $amount_raw = $_POST['amount'] ?? '';
    $type = filter_input(INPUT_POST, 'contribution_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'savings';
    $loan_id = isset($_POST['loan_id']) ? (int)$_POST['loan_id'] : null;
    $case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : null;

    $phone = normalize_phone($phone_raw);
    $amount = is_numeric($amount_raw) ? (float)$amount_raw : 0.0;

    if (empty($phone)) {
        $error = "Invalid phone number. Use format 07XXXXXXXX.";
    } elseif ($amount < MIN_AMOUNT) {
        $error = "Minimum amount is KES " . number_format(MIN_AMOUNT, 2) . ".";
    } elseif ($type === 'loan_repayment' && empty($loan_id)) {
        $error = "Loan ID is required for repayments.";
    } elseif ($type === 'welfare_case' && empty($case_id)) {
        $error = "Please select a Welfare Situation.";
    } else {
        // Idempotency: Avoid double-submissions within 60 seconds for same amount/phone
        $stmt_check = $conn->prepare("SELECT id FROM mpesa_requests WHERE member_id = ? AND amount = ? AND status = 'pending' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE) LIMIT 1");
        $stmt_check->bind_param("id", $member_id, $amount);
        $stmt_check->execute();
        if ($stmt_check->get_result()->fetch_assoc()) {
            $error = "A similar request is already pending. Please wait a minute before trying again.";
        } else {
            $inTransaction = false;
            try {
            require_once __DIR__ . '/../../inc/GatewayFactory.php';
            $gateway = GatewayFactory::get('mpesa');

            $ref = 'PAY-' . strtoupper(bin2hex(random_bytes(6))); 
            $txn_desc_map = [
                'savings' => 'Savings Deposit',
                'shares' => 'Share Capital',
                'welfare' => 'Welfare Fund',
                'welfare_case' => 'Case Donation',
                'loan_repayment' => 'Loan Repayment'
            ];
            $txn_desc = $txn_desc_map[$type] ?? 'Payment';

            // Simulated realistic processing delays (2–5 seconds) in sandbox
            if ($gateway->getEnvironment() === 'sandbox') {
                sleep(rand(2, 4));
            }

            $result = $gateway->initiateDeposit($phone, (float)$amount, $ref, $txn_desc);

            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            $checkoutRequestID = $result['checkout_id'];

            $is_sandbox = (defined('APP_ENV') && APP_ENV === 'sandbox');
            $request_status = $is_sandbox ? 'completed' : 'pending';
            $contrib_status = $is_sandbox ? 'active' : 'pending';
            $repayment_status = $is_sandbox ? 'Completed' : 'Pending';
            $mock_receipt = $is_sandbox ? 'SANDBOX-' . strtoupper(bin2hex(random_bytes(4))) : null;

            $conn->begin_transaction();
            $inTransaction = true;

            // 1) mpesa_requests
            $stmt = $conn->prepare("INSERT INTO mpesa_requests (member_id, phone, amount, checkout_request_id, status, reference_no, mpesa_receipt, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isdssss", $member_id, $phone, $amount, $checkoutRequestID, $request_status, $ref, $mock_receipt);
            $stmt->execute();
            $stmt->close();

            // 2) contributions
            $contrib_type_db = ($type === 'welfare_case') ? 'welfare' : $type;
            $stmt = $conn->prepare("INSERT INTO contributions (member_id, contribution_type, amount, payment_method, reference_no, status, created_at) VALUES (?, ?, ?, 'mpesa', ?, ?, NOW())");
            $stmt->bind_param("isdss", $member_id, $contrib_type_db, $amount, $ref, $contrib_status);
            $stmt->execute();
            $stmt->close();

            $related_id = null;

            // 3) Specific inserts
            if ($type === 'savings') {
                $related_id = $conn->insert_id; 
            } elseif ($type === 'loan_repayment') {
                $stmt = $conn->prepare("SELECT total_payable, current_balance FROM loans WHERE loan_id = ?");
                $stmt->bind_param("i", $loan_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $loan_row = $res ? $res->fetch_assoc() : null;
                $stmt->close();
                
                if ($loan_row) {
                    $curr_bal = (float)$loan_row['current_balance'];
                    $new_remaining = max(0.0, $curr_bal - $amount);

                    $stmt = $conn->prepare("INSERT INTO loan_repayments (loan_id, amount_paid, payment_date, payment_method, reference_no, mpesa_receipt, remaining_balance, status) VALUES (?, ?, NOW(), 'mpesa', ?, ?, ?, ?)");
                    $stmt->bind_param("idssds", $loan_id, $amount, $ref, $mock_receipt, $new_remaining, $repayment_status);
                    $stmt->execute();
                    $related_id = $conn->insert_id;
                    $stmt->close();
                }
            } elseif ($type === 'shares') {
                require_once __DIR__ . '/../../inc/ShareValuationEngine.php';
                $svEngine = new ShareValuationEngine($conn);
                $svEngine->issueShares($member_id, $amount, $ref, 'mpesa');
                $related_id = $conn->insert_id; // Legacy hook if needed
            } elseif ($type === 'welfare_case') {
                // 1) Record in welfare_donations
                $stmt = $conn->prepare("INSERT INTO welfare_donations (case_id, member_id, amount, created_at, reference_no) VALUES (?, ?, ?, NOW(), ?)");
                $stmt->bind_param("iids", $case_id, $member_id, $amount, $ref);
                $stmt->execute();
                $related_id = $conn->insert_id;
                $stmt->close();

                // 2) Increment total_raised in welfare_cases
                $stmt = $conn->prepare("UPDATE welfare_cases SET total_raised = total_raised + ? WHERE case_id = ?");
                $stmt->bind_param("di", $amount, $case_id);
                $stmt->execute();
                $stmt->close();
            }

            // 4) RECORD IN LEDGER IMMEDIATELY IF SANDBOX
            if ($is_sandbox) {
                require_once __DIR__ . '/../../inc/TransactionHelper.php';
                TransactionHelper::record([
                    'member_id'     => $member_id,
                    'amount'        => $amount,
                    'type'          => 'credit',
                    'category'      => $contrib_type_db,
                    'ref_no'        => $mock_receipt,
                    'notes'         => ucfirst($contrib_type_db) . " deposit via Sandbox M-Pesa (Auto-Activated)",
                    'method'        => 'mpesa',
                    'related_id'    => ($type === 'loan_repayment') ? $loan_id : $related_id,
                    'related_table' => ($type === 'loan_repayment') ? 'loans' : null
                ]);
            }

            $conn->commit();
            $inTransaction = false;

            // Notifications
            require_once __DIR__ . '/../../inc/notification_helpers.php';
            send_notification($conn, (int)$member_id, 'payment_request', ['amount' => $amount, 'ref' => $ref]);

            // Store referrer URL in session for redirect after success
            if (!empty($_SERVER['HTTP_REFERER'])) {
                $_SESSION['mpesa_return_url'] = $_SERVER['HTTP_REFERER'];
            }

            // Store success message in session for Dashboard toast
            $_SESSION['success'] = "Payment request sent successfully. Please complete on your phone.";
            
            $return_url = $_SESSION['mpesa_return_url'] ?? BASE_URL . "/member/pages/dashboard.php";
            header("Location: " . $return_url);
            exit;

        } catch (Throwable $e) {
            if (!empty($inTransaction) && $inTransaction === true) {
                try { $conn->rollback(); } catch (Throwable $_) {}
            }
            $error = $e->getMessage();
            try { log_mpesa_error($conn, (string)$member_id, $error); } catch (Throwable $_) {}
        }
    }
}
}
$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= esc($theme) ?>">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Secure M-PESA Checkout - <?= defined('SITE_NAME') ? esc(SITE_NAME) : 'SACCO' ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">

    <style>
        :root {
            --font-family-sans: 'Plus Jakarta Sans', sans-serif;
            --mpesa-green: #39B54A;
            --mpesa-dark-green: #1d7c2a;
            --mpesa-red: #E31F26;
            --glass-border: rgba(255, 255, 255, 0.4);
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
        }

        body {
            font-family: var(--font-family-sans);
            background-color: #f3f4f6;
            /* Mesh Gradient Background */
            background-image: 
                radial-gradient(at 0% 0%, rgba(57, 181, 74, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(227, 31, 38, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(57, 181, 74, 0.1) 0px, transparent 50%);
            background-attachment: fixed;
            min-height: 100vh;
        }

        /* Glassmorphism Card */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            box-shadow: var(--glass-shadow);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        /* M-PESA Header Brand */
        .mpesa-header {
            background: linear-gradient(135deg, var(--mpesa-green) 0%, var(--mpesa-dark-green) 100%);
            padding: 2.5rem 2rem;
            text-align: center;
            color: white;
            position: relative;
        }
        .mpesa-header::after {
            content: "";
            position: absolute;
            bottom: -15px;
            left: 0;
            right: 0;
            height: 30px;
            background: var(--glass-bg);
            border-radius: 50% 50% 0 0 / 25px 25px 0 0;
        }

        .mpesa-logo {
            font-weight: 900;
            letter-spacing: -1px;
            font-size: 2rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Secure Badge */
        .secure-badge {
            background: rgba(57, 181, 74, 0.1);
            color: var(--mpesa-dark-green);
            border: 1px solid rgba(57, 181, 74, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        /* Form Inputs */
        .form-floating > .form-control, .form-floating > .form-select {
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            background-color: rgba(255,255,255,0.7);
            height: calc(3.5rem + 2px);
        }
        .form-floating > .form-control:focus, .form-floating > .form-select:focus {
            border-color: var(--mpesa-green);
            box-shadow: 0 0 0 4px rgba(57, 181, 74, 0.1);
            background-color: white;
        }
        
        /* Amount Field Special Styling */
        .amount-wrapper {
            background: #f8fafc;
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.3s ease;
        }
        .amount-wrapper:focus-within {
            border-color: var(--mpesa-green);
            box-shadow: 0 0 0 4px rgba(57, 181, 74, 0.1);
            background: white;
        }
        .currency-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.25rem;
            display: block;
        }
        .amount-input-lg {
            border: none;
            background: transparent;
            font-size: 2.5rem;
            font-weight: 800;
            color: #1e293b;
            text-align: center;
            width: 100%;
            padding: 0;
            outline: none;
        }
        .amount-input-lg::placeholder { color: #cbd5e1; }

        /* Button */
        .btn-mpesa {
            background-color: var(--mpesa-green);
            border: none;
            color: white;
            font-weight: 700;
            padding: 1rem;
            border-radius: 12px;
            width: 100%;
            font-size: 1.1rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(57, 181, 74, 0.3);
        }
        .btn-mpesa:hover {
            background-color: var(--mpesa-dark-green);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(57, 181, 74, 0.4);
            color: white;
        }
        
        /* Dark Mode Overrides */
        [data-bs-theme="dark"] body {
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, rgba(57, 181, 74, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(14, 165, 233, 0.1) 0px, transparent 50%);
        }
        [data-bs-theme="dark"] .glass-card {
            background: rgba(30, 41, 59, 0.7);
            border-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        [data-bs-theme="dark"] .mpesa-header::after {
            background: rgba(30, 41, 59, 0.7);
        }
        [data-bs-theme="dark"] .form-floating > .form-control,
        [data-bs-theme="dark"] .form-floating > .form-select,
        [data-bs-theme="dark"] .amount-wrapper {
            background-color: rgba(15, 23, 42, 0.6);
            border-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        [data-bs-theme="dark"] .amount-input-lg { color: white; }

        /* Processing Overlay */
        #processingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
        }
        .spinner-glow {
            width: 80px;
            height: 80px;
            border: 4px solid rgba(57, 181, 74, 0.1);
            border-left-color: var(--mpesa-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            filter: drop-shadow(0 0 15px rgba(57, 181, 74, 0.5));
            margin: 0 auto 20px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>
    <div id="processingOverlay">
        <div>
            <div class="spinner-glow"></div>
            <h3 class="fw-bold mb-2">Processing Payment...</h3>
            <p class="text-white-50">Please do not refresh or close this page.</p>
        </div>
    </div>

 <div class="d-flex">
        <?php $layout->sidebar(); ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php $layout->topbar($pageTitle ?? ''); ?>
            
            <div class="container-fluid">
            
        <main class="grow d-flex align-items-center justify-content-center p-4">
            <div class="w-100" style="max-width: 500px;">
                
                <div class="text-center">
                    <div class="secure-badge">
                        <i class="bi bi-shield-lock-fill"></i>
                        256-bit End-to-End Encryption
                    </div>
                </div>

                <div class="glass-card animate__animated animate__fadeInUp">
                    
                    <div class="mpesa-header">
                        <div class="mpesa-logo">M-PESA</div>
                        <div class="small opacity-75 text-uppercase fw-bold ls-1 mt-1">Sim Toolkit Checkout</div>
                    </div>

                    <div class="p-4 p-md-5 pt-4">
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger border-0 d-flex align-items-center gap-3 rounded-3 shadow-sm mb-4">
                                <i class="bi bi-exclamation-octagon-fill fs-4 text-danger"></i>
                                <div class="small fw-medium "><?= esc($error) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success border-0 d-flex align-items-center gap-3 rounded-3 shadow-sm mb-4">
                                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                                <div class="small fw-medium "><?= esc($success) ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="post" id="paymentForm" novalidate>
                            <?= csrf_field() ?>

                            <div class="mb-4">
                                <div class="amount-wrapper">
                                    <label class="currency-label">Enter Amount (KES)</label>
                                    <input type="number" name="amount" class="amount-input-lg" 
                                           id="floatingAmount" placeholder="0.00" min="<?= (int)MIN_AMOUNT ?>" required autofocus>
                                </div>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="tel" name="phone" class="form-control fw-bold" 
                                       id="floatingPhone" placeholder="07XXXXXXXX" value="<?= esc($db_phone) ?>" required>
                                <label for="floatingPhone">M-Pesa Number</label>
                            </div>

                            <div class="form-floating mb-3">
                                <select class="form-select fw-bold" name="contribution_type" id="paymentType" required>
                                    <option value="savings" <?= $url_type === 'savings' ? 'selected' : '' ?>>Savings Deposit</option>
                                    <option value="shares" <?= $url_type === 'shares' ? 'selected' : '' ?>>Buy Shares</option>
                                    <option value="welfare" <?= $url_type === 'welfare' ? 'selected' : '' ?>>General Welfare</option>
                                    <option value="welfare_case" <?= $url_type === 'welfare_case' ? 'selected' : '' ?>>Specific Case Donation</option>
                                    <option value="loan_repayment" <?= $url_type === 'loan_repayment' ? 'selected' : '' ?>>Loan Repayment</option>
                                </select>
                                <label for="paymentType">Payment For</label>
                            </div>

                            <div id="loanIdDiv" class="mb-3 animate__animated animate__fadeIn" style="display:none;">
                                <div class="form-floating">
                                    <input type="number" name="loan_id" class="form-control" 
                                           id="floatingLoan" placeholder="Loan ID" value="<?= esc($url_loan_id) ?>">
                                    <label for="floatingLoan">Loan Reference ID</label>
                                </div>
                            </div>

                            <div id="caseIdDiv" class="mb-3 animate__animated animate__fadeIn" style="display:none;">
                                <div class="form-floating">
                                    <select name="case_id" class="form-select" id="floatingCase">
                                        <option value="">-- Select Welfare Situation --</option>
                                        <?php foreach ($active_cases as $c): ?>
                                            <option value="<?= esc($c['case_id']) ?>" <?= ($url_case_id == $c['case_id']) ? 'selected' : '' ?>>
                                                <?= esc($c['title']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="floatingCase">Select Case</label>
                                </div>
                            </div>

                            <button type="submit" class="btn-mpesa mt-3">
                                <i class="bi bi-phone-vibrate me-2"></i> Send Request
                            </button>
                        </form>
                    </div>

                    <div class="p-3 text-center border-top border-light-subtle bg-body-tertiary">
                        <a href="<?= BASE_URL ?>/member/pages/dashboard.php" class="text-decoration-none text-secondary small fw-bold">
                            Cancel Transaction
                        </a>
                    </div>
                </div>

               
            </div>
        </main>
         <?php $layout->footer(); ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('paymentType');
    const loanDiv = document.getElementById('loanIdDiv');
    const caseDiv = document.getElementById('caseIdDiv');
    
    // Inputs to toggle required status
    const loanInput = document.getElementById('floatingLoan');
    const caseInput = document.getElementById('floatingCase');

    function toggleFields() {
        const val = typeSelect.value;
        
        // Reset
        loanDiv.style.display = 'none';
        caseDiv.style.display = 'none';
        loanInput.removeAttribute('required');
        caseInput.removeAttribute('required');
        
        // Activate Logic
        if(val === 'loan_repayment') {
            loanDiv.style.display = 'block';
            loanInput.setAttribute('required', 'required');
        } else if(val === 'welfare_case') {
            caseDiv.style.display = 'block';
            caseInput.setAttribute('required', 'required');
        }
    }

    // Initialize & Listen
    toggleFields();
    typeSelect.addEventListener('change', toggleFields);

    // Show overlay on submit
    const paymentForm = document.getElementById('paymentForm');
    paymentForm.addEventListener('submit', function() {
        if (paymentForm.checkValidity()) {
            document.getElementById('processingOverlay').style.display = 'flex';
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>





