<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');
// member/withdrawal.php (TEST MODE FIXED)
session_start();

// 1. CONFIGURATION
$paystackSecretKey = "sk_test_0aa34fba5149697fcc25c4ae2556983ffc9b2fe6"; 
$minBalance = 50;

// --- HELPER: DETECT CARRIER ---
// NOTE: For Paystack Test Mode, we are FORCING 'MPESA' because 'AIRTEL' 
// often fails in the sandbox environment. Uncomment the logic below when going Live.
function get_carrier_bank_code($phone) {
    return 'MPESA'; 

    /* // --- SAVE THIS LOGIC FOR LIVE MODE ---
    $prefix_full = substr($phone, 3, 3); // e.g. 254(755)123... gets '755'
    
    // Airtel Prefixes
    if (strpos('73 78', substr($prefix_full, 0, 2)) !== false) return 'AIRTEL-MONEY';
    if (substr($prefix_full, 0, 2) == '10') return 'AIRTEL-MONEY'; 
    if (substr($prefix_full, 0, 2) == '75') {
        $third_digit = intval(substr($prefix_full, 2, 1));
        if ($third_digit <= 6) return 'AIRTEL-MONEY'; 
        return 'MPESA'; 
    }
    // Telkom
    if (substr($prefix_full, 0, 2) == '77') return 'TELKOM';
    
    // Default Safaricom
    return 'MPESA';
    */
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: savings.php");
    exit;
}

$member_id = $_SESSION['member_id'];
$amount = floatval($_POST['amount']);
$description = trim($_POST['description']);

// 2. SECURITY: VERIFY BALANCE
$sqlBalance = "SELECT 
    COALESCE(SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END), 0) -
    COALESCE(SUM(CASE WHEN transaction_type = 'withdrawal' THEN amount ELSE 0 END), 0) 
    as net_balance
    FROM savings WHERE member_id = ?";
$stmt = $conn->prepare($sqlBalance);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$currentBalance = $stmt->get_result()->fetch_assoc()['net_balance'];

if ($amount > $currentBalance) {
    $_SESSION['error'] = "Insufficient funds. Balance: KES " . number_format((float)$currentBalance);
    header("Location: savings.php");
    exit;
}

// 3. FETCH USER PHONE
$sqlUser = "SELECT phone, full_name FROM members WHERE member_id = ?";
$stmtUser = $conn->prepare($sqlUser);
$stmtUser->bind_param("i", $member_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

if (!$user || empty($user['phone'])) {
    $_SESSION['error'] = "Error: No phone number found in your profile.";
    header("Location: savings.php");
    exit;
}

// 4. CLEAN & FORMAT PHONE
$raw_phone = $user['phone']; 
$clean_phone = preg_replace('/[^0-9]/', '', $raw_phone);

// Force 254 format
if (substr($clean_phone, 0, 1) == '0') {
    $final_phone = '254' . substr($clean_phone, 1);
} elseif (substr($clean_phone, 0, 3) == '254') {
    $final_phone = $clean_phone;
} elseif (strlen($clean_phone) == 9) {
    $final_phone = '254' . $clean_phone;
} else {
    $_SESSION['error'] = "Invalid phone number format in profile.";
    header("Location: savings.php");
    exit;
}

// 5. GET BANK CODE (Forced to MPESA for Test Mode)
$bank_code = get_carrier_bank_code($final_phone);

// 6. PAYSTACK: CREATE RECIPIENT
$url = "https://api.paystack.co/transferrecipient";
$fields = [
    'type' => 'mobile_money',
    'name' => $user['full_name'],
    'account_number' => $final_phone,
    'bank_code' => $bank_code, 
    'currency' => 'KES'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $paystackSecretKey,
    "Content-Type: application/json"
]);

$result = curl_exec($ch);
$recipient_data = json_decode($result, true);

if (!$recipient_data['status']) {
    $_SESSION['error'] = "Paystack Error: " . $recipient_data['message'];
    header("Location: savings.php");
    exit;
}

$recipientCode = $recipient_data['data']['recipient_code'];

// 7. PAYSTACK: INITIATE TRANSFER
$urlTransfer = "https://api.paystack.co/transfer";
$transferFields = [
    'source' => 'balance', 
    'amount' => $amount * 100, 
    'recipient' => $recipientCode,
    'reason' => $description
];

curl_setopt($ch, CURLOPT_URL, $urlTransfer);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transferFields));
$resultTransfer = curl_exec($ch);
curl_close($ch);

$transResponse = json_decode($resultTransfer, true);

// 8. FINAL RESULT
if ($transResponse['status'] === true) {
    // Record in DB
    $sqlInsert = "INSERT INTO savings (member_id, transaction_type, amount, description, created_at) VALUES (?, 'withdrawal', ?, ?, NOW())";
    $stmtInsert = $conn->prepare($sqlInsert);
    
    // Add Paystack Reference to description
    $final_desc = $description . " (Ref: " . $transResponse['data']['reference'] . ")";
    $stmtInsert->bind_param("ids", $member_id, $amount, $final_desc);
    
    if ($stmtInsert->execute()) {
        $_SESSION['success'] = "Withdrawal Successful! Money sent to $bank_code ($final_phone).";
    } else {
        $_SESSION['error'] = "Withdrawal sent but DB update failed.";
    }

} else {
    $_SESSION['error'] = "Transfer Failed: " . $transResponse['message'];
}

header("Location: savings.php");
exit;
?>



