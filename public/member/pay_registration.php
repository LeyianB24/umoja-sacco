<?php
// usms/public/pay_registration.php
session_start();
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/mpesa_lib.php';

if (!isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit;
}

$member_id = $_SESSION['member_id'];
$reg_no = $_SESSION['reg_no'] ?? '';

// Check if already paid
$check = $conn->query("SELECT reg_fee_paid, phone, full_name FROM members WHERE member_id = $member_id")->fetch_assoc();
if ($check['reg_fee_paid']) {
    header("Location: ../member/pages/dashboard.php");
    exit;
}

$phone = $check['phone'];
$full_name = $check['full_name'];
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    
    $amount = 1000.00;
    $phone_input = trim($_POST['phone']);
    // Normalize phone (simple version or use library)
    $phone_clean = preg_replace('/\D/', '', $phone_input);
    if (strlen($phone_clean) == 10 && $phone_clean[0] == '0') $phone_clean = '254' . substr($phone_clean, 1);
    
    try {
        $cfg = mpesa_config();
        $token = mpesa_get_access_token($conn);
        if (!$token) throw new Exception("M-Pesa service unavailable.");

        $timestamp = date('YmdHis');
        $password = base64_encode($cfg['shortcode'] . $cfg['passkey'] . $timestamp);
        $ref = 'REG-' . strtoupper(bin2hex(random_bytes(4)));

        $payload = [
            'BusinessShortCode' => $cfg['shortcode'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => 1, // Testing: 1 KES. Change to $amount for production
            'PartyA' => $phone_clean,
            'PartyB' => $cfg['shortcode'],
            'PhoneNumber' => $phone_clean,
            'CallBackURL' => $cfg['callback_url'],
            'AccountReference' => $ref,
            'TransactionDesc' => 'Registration Fee'
        ];

        // Perform STK Push (Abstracted for brevity, assuming inc/mpesa_lib.php has this or use curl)
        // For this task, I'll use the logic from mpesa_request.php
        $ch = curl_init(mpesa_base_url() . '/mpesa/stkpush/v1/processrequest');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (($resp['ResponseCode'] ?? '') === '0') {
            $checkoutID = $resp['CheckoutRequestID'];
            
            $conn->begin_transaction();
            // 1. Log M-Pesa Request
            $stmt = $conn->prepare("INSERT INTO mpesa_requests (member_id, phone, amount, checkout_request_id, status, reference_no, created_at) VALUES (?, ?, ?, ?, 'pending', ?, NOW())");
            $stmt->bind_param("isdss", $member_id, $phone_clean, $amount, $checkoutID, $ref);
            $stmt->execute();

            // 2. Log Contribution
            $stmt = $conn->prepare("INSERT INTO contributions (member_id, contribution_type, amount, payment_method, reference_no, status, created_at) VALUES (?, 'registration', ?, 'mpesa', ?, 'pending', NOW())");
            $stmt->bind_param("ids", $member_id, $amount, $ref);
            $stmt->execute();
            
            $conn->commit();
            $success = "STK Push sent! Please enter your M-Pesa PIN on your phone.";
        } else {
            throw new Exception($resp['errorMessage'] ?? "STK Push failed.");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Payment — <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .pay-card { background: white; padding: 2rem; border-radius: 1.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%; max-width: 450px; }
        .btn-mpesa { background: #39B54A; color: white; border: none; font-weight: bold; width: 100%; padding: 0.8rem; border-radius: 0.8rem; }
        .btn-mpesa:hover { background: #2e933c; color: white; }
    </style>
</head>
<body>
    <div class="pay-card animate__animated animate__zoomIn">
        <div class="text-center mb-4">
            <h3 class="fw-bold">Payment Required</h3>
            <p class="text-muted">To complete your registration for <strong><?= $reg_no ?></strong>, please pay the registration fee.</p>
        </div>

        <div class="alert alert-info border-0 shadow-sm rounded-4 text-center">
            <div class="small fw-bold text-uppercase">Registration Fee</div>
            <div class="h2 fw-bold mb-0">KES 1,000.00</div>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
            <div class="text-center">
                <div class="spinner-border text-success mb-3"></div>
                <p>Waiting for payment confirmation...</p>
                <script>setTimeout(() => window.location.reload(), 5000);</script>
            </div>
        <?php else: ?>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="mb-4">
                    <label class="form-label small fw-bold">Confirm M-Pesa Number</label>
                    <input type="text" name="phone" class="form-control form-control-lg text-center fw-bold" value="<?= $phone ?>" required>
                </div>
                <button type="submit" class="btn btn-mpesa fs-5">
                    <i class="bi bi-phone-vibrate me-2"></i> Pay with M-Pesa
                </button>
            </form>
            <div class="mt-4 text-center">
                <p class="small text-muted">Or visit our office to pay in Cash.</p>
                <a href="login.php" class="text-decoration-none fw-bold text-success">Exit to Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

