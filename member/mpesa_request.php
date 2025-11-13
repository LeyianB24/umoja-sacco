<?php
session_start();
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/mpesa_lib.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_SESSION['member_id'] ?? null;
    $phone_raw = trim($_POST['phone'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $type = trim($_POST['contribution_type'] ?? 'savings');
    $loan_id = isset($_POST['loan_id']) ? intval($_POST['loan_id']) : null;

    if (!$member_id) {
        $error = "Session expired. Please log in again.";
    }

    // Normalize phone number
    $phone = preg_replace('/[^0-9]/', '', $phone_raw);
    if (strpos($phone, '0') === 0) $phone = '254' . substr($phone, 1);
    if (strpos($phone, '7') === 0) $phone = '254' . $phone;

    if (strpos($phone, '254') !== 0 || strlen($phone) !== 12) {
        $error = 'Enter a valid Kenyan phone number (e.g. 07XXXXXXXX).';
    } elseif ($amount < 10) {
        $error = 'Minimum payment is KES 10.';
    }

    if (!$error) {
        try {
            // M-PESA setup
            $cfg = mpesa_config();
            $base = mpesa_base_url();
            $token = mpesa_get_access_token($conn);

            $timestamp = date('YmdHis');
            $password = base64_encode($cfg['shortcode'] . $cfg['passkey'] . $timestamp);

            // Generate consistent reference number
            $reference_no = 'RVC-' . strtoupper(uniqid());

            $payload = [
                'BusinessShortCode' => $cfg['shortcode'],
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int)$amount,
                'PartyA' => $phone,
                'PartyB' => $cfg['shortcode'],
                'PhoneNumber' => $phone,
                'CallBackURL' => $cfg['callback_url'],
                'AccountReference' => $reference_no,
                'TransactionDesc' => ucfirst($type) . ' payment via M-Pesa'
            ];

            // Send STK Push
            $ch = curl_init($base . '/mpesa/stkpush/v1/processrequest');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload)
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);

            if ($resp === false) throw new Exception('cURL error');
            $response = json_decode($resp, true);

            if (!isset($response['ResponseCode']) || $response['ResponseCode'] != '0') {
                throw new Exception('STK Push failed: ' . ($response['errorMessage'] ?? json_encode($response)));
            }

            $checkout_id = $response['CheckoutRequestID'] ?? uniqid('chk_');

            // --- Log M-Pesa request ---
            $stmt = $conn->prepare("
                INSERT INTO mpesa_requests (member_id, phone, amount, checkout_request_id, status, reference_no)
                VALUES (?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->bind_param('isdss', $member_id, $phone, $amount, $checkout_id, $reference_no);
            $stmt->execute();
            $stmt->close();

            // --- Insert into contributions ---
            $stmt = $conn->prepare("
                INSERT INTO contributions (member_id, contribution_type, amount, payment_method, reference_no)
                VALUES (?, ?, ?, 'mpesa', ?)
            ");
            $stmt->bind_param('isds', $member_id, $type, $amount, $reference_no);
            $stmt->execute();
            $stmt->close();

            $related_id = null;

            // --- Specific table insertions ---
            switch (strtolower($type)) {
                case 'savings':
                    $stmt = $conn->prepare("
                        INSERT INTO savings (member_id, amount, transaction_type, description, created_at, reference_no)
                        VALUES (?, ?, 'deposit', 'M-Pesa savings contribution', NOW(), ?)
                    ");
                    $stmt->bind_param('ids', $member_id, $amount, $reference_no);
                    $stmt->execute();
                    $related_id = $conn->insert_id;
                    $stmt->close();
                    break;

                case 'shares':
                    $unit_price = 100;
                    $units = $amount / $unit_price;
                    $stmt = $conn->prepare("
                        INSERT INTO shares (member_id, share_units, unit_price, total_value, reference_no)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param('iddds', $member_id, $units, $unit_price, $amount, $reference_no);
                    $stmt->execute();
                    $related_id = $conn->insert_id;
                    $stmt->close();
                    break;

                case 'welfare':
                    $stmt = $conn->prepare("
                        INSERT INTO welfare_support (member_id, amount, reason, status, reference_no)
                        VALUES (?, ?, 'Member welfare contribution', 'completed', ?)
                    ");
                    $stmt->bind_param('ids', $member_id, $amount, $reference_no);
                    $stmt->execute();
                    $related_id = $conn->insert_id;
                    $stmt->close();
                    break;

                case 'loan_repayment':
                    if (!$loan_id) throw new Exception('Loan ID is required for repayment.');
                    $stmt = $conn->prepare("
                        INSERT INTO loan_repayments (loan_id, amount_paid, payment_date, payment_method, reference_no, remaining_balance, created_by_admin)
                        VALUES (?, ?, NOW(), 'mpesa', ?, 0, NULL)
                    ");
                    $stmt->bind_param('ids', $loan_id, $amount, $reference_no);
                    $stmt->execute();
                    $related_id = $conn->insert_id;
                    $stmt->close();
                    break;
            }

           // --- Record transaction ---
$transaction_type = ($type === 'loan_repayment') ? 'loan_repayment' : 'deposit';
$note = ucfirst(str_replace('_', ' ', $type)) . ' via M-Pesa';

$stmt = $conn->prepare("
    INSERT INTO transactions (member_id, transaction_type, amount, related_id, payment_channel, notes, created_at, reference_no)
    VALUES (?, ?, ?, ?, 'mpesa', ?, NOW(), ?)
");
$stmt->bind_param('isdiss', $member_id, $transaction_type, $amount, $related_id, $note, $reference_no);
$stmt->execute();
$stmt->close();

// --- SEND EMAIL AND CREATE NOTIFICATIONS ---
require_once __DIR__ . '/../inc/email.php';

// Fetch member details
$email_stmt = $conn->prepare("SELECT email, full_name FROM members WHERE member_id = ?");
$email_stmt->bind_param("i", $member_id);
$email_stmt->execute();
$email_stmt->bind_result($member_email, $member_name);
$email_stmt->fetch();
$email_stmt->close();

$display_name = $member_name ?: "Member";
$formatted_amount = "KES " . number_format($amount, 2);
$formatted_type = ucfirst(str_replace('_', ' ', $type));
$formatted_date = date('d M Y, h:i A');

// ------------------ MEMBER CONFIRMATION EMAIL ------------------
if (!empty($member_email)) {
    $subject = "Payment Confirmation - Umoja Drivers Sacco";
    $body = "Dear {$display_name},

Your {$formatted_type} payment of {$formatted_amount} via M-Pesa has been received successfully.

Details:
- Reference No: {$reference_no}
- Date: {$formatted_date}

Thank you for your contribution to Umoja Drivers Sacco.

Umoja Sacco System";

    sendEmailWithNotification(
        $member_email,
        $subject,
        $body,
        $member_id,
        null
    );
}

// ------------------ ACCOUNTANT / ADMIN NOTIFICATION ------------------
if (strtolower($type) === 'loan_repayment') {
    $acc_stmt = $conn->query("SELECT admin_id, email, full_name FROM admins WHERE role='accountant' LIMIT 1");
    $accountant = $acc_stmt->fetch_assoc();
    $acc_stmt->close();

    if ($accountant) {
        $acc_subject = "Loan Repayment Notification";
        $acc_body = "Hello {$accountant['full_name']},

Member {$display_name} (ID: {$member_id}) has made a loan repayment via M-Pesa.

Details:
- Amount Paid: {$formatted_amount}
- Reference No: {$reference_no}
- Date: {$formatted_date}

Please review and update their loan account accordingly.

Umoja Sacco System";

        sendEmailWithNotification(
            $accountant['email'],
            $acc_subject,
            $acc_body,
            null,
            $accountant['admin_id']
        );
    }
}

// ------------------ GENERAL SUCCESS MESSAGE ------------------
$success = "STK Push sent successfully! Check your phone to complete the payment.";
header("Refresh:5; URL=" . BASE_URL . "/member/dashboard.php");
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<?php require_once __DIR__ . '/../inc/header.php'; ?>
<div class="container py-4">
    <h4 class="mb-4 text-success fw-bold">Pay via M-Pesa</h4>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" class="card p-4 shadow-sm rounded-3">
        <div class="mb-3">
            <label class="form-label fw-semibold">Phone (07XXXXXXXX)</label>
            <input type="tel" name="phone" class="form-control" required pattern="^0[0-9]{9}$">
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Amount (KES)</label>
            <input type="number" name="amount" class="form-control" required min="10" step="0.01">
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Payment Type</label>
            <select class="form-select" name="contribution_type" id="paymentType" required>
                <option value="savings">Savings</option>
                <option value="shares">Shares</option>
                <option value="welfare">Welfare</option>
                <option value="loan_repayment">Loan Repayment</option>
            </select>
        </div>

        <div class="mb-3" id="loanIdDiv" style="display:none;">
            <label class="form-label fw-semibold">Loan ID</label>
            <input type="number" name="loan_id" class="form-control" placeholder="Enter your Loan ID">
        </div>

        <button type="submit" class="btn btn-success w-100 fw-bold">Proceed to Pay</button>
    </form>
</div>

<script>
document.getElementById('paymentType').addEventListener('change', function() {
    document.getElementById('loanIdDiv').style.display = 
        this.value === 'loan_repayment' ? 'block' : 'none';
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>