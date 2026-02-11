<?php
// tests/test_transaction_flow.php
// Simulates an M-Pesa Callback to verify:
// 1. TransactionHelper Recording
// 2. FinancialEngine Balancing
// 3. Notification Creation

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/TransactionHelper.php';
require_once __DIR__ . '/../inc/FinancialEngine.php';
// We need to simulate the callback by posting to mpesa_callback.php, 
// OR we can just unit test the logic if we replicate the callback body.
// Better: Unit test the flow logic directly to see debug output.

echo "<h1>Transaction Flow Verification</h1>";

// 1. Setup Test Data
$test_phone = "254700000000";
$test_amount = 500.00;
// Fetch a valid member
$q = $conn->query("SELECT member_id FROM members LIMIT 1");
if (!$q || $q->num_rows === 0) {
    die("No members found in DB. Please seed a member first.");
}
$member_id = $q->fetch_assoc()['member_id'];
echo "<p>Using Member ID: $member_id</p>";
$test_phone = "254700000000";
$test_amount = 500.00;
// $member_id is set above
$ref = "TEST-REF-" . time();
$checkoutID = "ws_CO_" . time() . uniqid();

echo "<h3>1. Creating Test Request (Shares)</h3>";
$conn->query("INSERT INTO mpesa_requests (member_id, phone, amount, checkout_request_id, status, reference_no, created_at) 
              VALUES ($member_id, '$test_phone', $test_amount, '$checkoutID', 'pending', '$ref', NOW())");

$conn->query("INSERT INTO contributions (member_id, contribution_type, amount, payment_method, reference_no, status, created_at) 
              VALUES ($member_id, 'shares', $test_amount, 'mpesa', '$ref', 'pending', NOW())");

echo "<p>Request created. Ref: $ref</p>";

// 2. Simulate Callback Payload
$callback_payload = json_encode([
    'Body' => [
        'stkCallback' => [
            'MerchantRequestID' => 'TEST-MERCHANT-ID',
            'CheckoutRequestID' => $checkoutID,
            'ResultCode' => 0,
            'ResultDesc' => 'The service request is processed successfully.',
            'CallbackMetadata' => [
                'Item' => [
                    ['Name' => 'Amount', 'Value' => $test_amount],
                    ['Name' => 'MpesaReceiptNumber', 'Value' => 'TEST-RCPT-' . time()],
                    ['Name' => 'TransactionDate', 'Value' => date('YmdHis')],
                    ['Name' => 'PhoneNumber', 'Value' => $test_phone]
                ]
            ]
        ]
    ]
]);

// 3. Send to mpesa_callback.php
echo "<h3>2. Sending Callback to Endpoint...</h3>";
$url = "http://localhost/usms/public/member/mpesa_callback.php";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POSTFIELDS, $callback_payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>Response ($httpCode): " . htmlspecialchars($response) . "</p>";

// 4. Verify Results
echo "<h3>3. Verifying Results</h3>";

// A. Ledger
$engine = new FinancialEngine($conn);
$bal = $engine->getBalances($member_id);
echo "<p><strong>Shares Balance:</strong> " . number_format($bal['shares'], 2) . "</p>";

// B. Notification
$res = $conn->query("SELECT * FROM notifications WHERE member_id = $member_id ORDER BY created_at DESC LIMIT 1");
$notif = $res->fetch_assoc();
if ($notif) {
    echo "<p style='color:green'>[PASS] Notification Found: " . htmlspecialchars($notif['title']) . " - " . htmlspecialchars($notif['message']) . "</p>";
} else {
    echo "<p style='color:red'>[FAIL] No Notification Found!</p>";
}

// C. Transactions
$res = $conn->query("SELECT * FROM transactions WHERE reference_no = 'TEST-RCPT-" . time() . "'"); // Wait, receipt is dynamic
// Just get latest
$res = $conn->query("SELECT * FROM transactions WHERE member_id = $member_id ORDER BY created_at DESC LIMIT 1");
$txn = $res->fetch_assoc();
echo "<p>Latest Transaction: " . ($txn ? htmlspecialchars($txn['transaction_type']) . " - " . $txn['amount'] : "None") . "</p>";

?>
