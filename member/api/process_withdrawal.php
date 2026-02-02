<?php
// member/process_withdrawal.php
// FEATURES: Paystack (Test Mode Fix) + Transactions Table + External Email/SMS
session_start();

// 1. CONFIGURATION & INCLUDES
require_once __DIR__ . '/../../config/app_config.php'; 
require_once __DIR__ . '/../../config/db_connect.php'; 

// Load your custom notification handlers
require_once __DIR__ . '/../../inc/email.php';
require_once __DIR__ . '/../../inc/sms.php';

// Paystack Configuration
$secret_key  = 'sk_test_0aa34fba5149697fcc25c4ae2556983ffc9b2fe6'; 
$currency    = 'KES';

// 2. CHECK LOGIN
if (!isset($_SESSION['member_id'])) {
    die("Error: You must be logged in.");
}
$member_id = $_SESSION['member_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate Inputs
    $amount_user_wants = $_POST['amount']; 
    $raw_phone         = $_POST['phone'];
    $description       = $_POST['description'] ?? 'Savings Withdrawal';

    // 3. FETCH MEMBER DETAILS
    $stmtUser = $conn->prepare("SELECT full_name, email, phone FROM members WHERE member_id = ?");
    $stmtUser->bind_param("i", $member_id);
    $stmtUser->execute();
    $user = $stmtUser->get_result()->fetch_assoc();

    if (!$user) die("Error: Member not found.");
    $user_email = $user['email'];
    $user_name  = $user['full_name'];

    // 4. SANITIZE PHONE (PAYSTACK TEST MODE FIX)
    // Paystack Sandbox often requires local format (07... or 01...) for MPESA
    $clean_phone = preg_replace('/[^0-9]/', '', $raw_phone);

    // Logic: Convert to 07... format
    if (substr($clean_phone, 0, 3) == '254') {
        $final_phone = '0' . substr($clean_phone, 3);
    } elseif (strlen($clean_phone) == 9) {
        $final_phone = '0' . $clean_phone;
    } else {
        $final_phone = $clean_phone;
    }

    // 5. PAYSTACK: CREATE RECIPIENT
    $bank_code = 'MPESA'; // Force MPESA for Test Mode
    
    $url = "https://api.paystack.co/transferrecipient";
    $fields = [
        'type'           => 'mobile_money',
        'name'           => $user_name, 
        'account_number' => $final_phone,
        'bank_code'      => $bank_code,
        'currency'       => $currency
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $secret_key,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    $recipient_data = json_decode($result, true);
    
    if (!$recipient_data['status']) {
        die("Paystack Recipient Error: " . $recipient_data['message']);
    }
    $recipient_code = $recipient_data['data']['recipient_code'];

    // 6. PAYSTACK: INITIATE TRANSFER
    $url = "https://api.paystack.co/transfer";
    $fields = [
        'source'    => 'balance', 
        'reason'    => $description, 
        'amount'    => $amount_user_wants * 100, // Paystack expects amount in cents
        'recipient' => $recipient_code
    ];

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    
    $result_transfer = curl_exec($ch);
    $transfer_data = json_decode($result_transfer, true);
    curl_close($ch);

    // 7. HANDLE SUCCESS & DB INSERTS
    if (isset($transfer_data['status']) && $transfer_data['status'] == true) {
        $paystack_ref = $transfer_data['data']['reference'];

        // A. Insert into SAVINGS table
        $stmt1 = $conn->prepare("INSERT INTO savings (member_id, transaction_type, amount, description) VALUES (?, 'withdrawal', ?, ?)");
        $stmt1->bind_param("ids", $member_id, $amount_user_wants, $description);
        $stmt1->execute();

        // B. Insert into TRANSACTIONS table
        $stmt2 = $conn->prepare("INSERT INTO transactions (member_id, reference_no, amount, transaction_type, notes, description) VALUES (?, ?, ?, 'withdrawal', 'success', ?)");
        $stmt2->bind_param("isds", $member_id, $paystack_ref, $amount_user_wants, $description);
        $stmt2->execute();

        // --- 8. SEND NOTIFICATIONS ---
        
        $msg_subject = "Withdrawal Successful";
        $msg_body    = "Dear $user_name, your withdrawal of KES " . number_format($amount_user_wants) . " has been sent to $final_phone. Ref: $paystack_ref";

        // A. Send Email (Pass $member_id to create internal notification)
        if (function_exists('sendEmail')) {
            // Args: Email, Subject, Body, MemberID (for in-app notification)
            sendEmail($user_email, $msg_subject, $msg_body, $member_id);
        }

        // B. Send SMS
        if (function_exists('send_sms')) {
            // We pass $final_phone (e.g., 0722...). 
            // Your inc/sms.php script automatically converts this to 254722...
            send_sms($final_phone, $msg_body);
        } else {
            error_log("Warning: send_sms function not available in process_withdrawal.php");
        }

        // 9. REDIRECT
        echo "<script>
            alert('Success! Money sent to $final_phone');
            window.location.href='savings.php';
         </script>";
        
    } else {
        $error_msg = $transfer_data['message'] ?? 'Unknown Error';
        die("Transfer Failed: " . $error_msg);
    }
}
?>