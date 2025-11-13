<?php
// callback_url.php
// Log the raw M-Pesa callback for debugging
$mpesaResponse = file_get_contents('php://input');
file_put_contents('mpesa_callback_log.txt', $mpesaResponse . PHP_EOL, FILE_APPEND);

$data = json_decode($mpesaResponse, true);

if (!isset($data['Body']['stkCallback'])) {
    exit('Invalid M-Pesa callback format');
}

$callback = $data['Body']['stkCallback'];
$resultCode = $callback['ResultCode'];
$resultDesc = $callback['ResultDesc'];

// Only process successful transactions
if ($resultCode == 0) {
    $metadata = $callback['CallbackMetadata']['Item'];

    // Initialize default values
    $mpesaCode = '';
    $amount = 0;
    $phone = '';
    $transactionDate = date('Y-m-d H:i:s');

    foreach ($metadata as $item) {
        switch ($item['Name']) {
            case 'MpesaReceiptNumber':
                $mpesaCode = $item['Value'];
                break;
            case 'Amount':
                $amount = $item['Value'];
                break;
            case 'PhoneNumber':
                $phone = $item['Value'];
                break;
            case 'TransactionDate':
                // Convert to MySQL datetime format
                $dt = DateTime::createFromFormat('YmdHis', $item['Value']);
                if ($dt) $transactionDate = $dt->format('Y-m-d H:i:s');
                break;
        }
    }

    // --- Connect to DB ---
    $conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
    if ($conn->connect_error) {
        file_put_contents('mpesa_callback_log.txt', "DB Connection Failed: " . $conn->connect_error, FILE_APPEND);
        exit;
    }

    // --- Find member by phone ---
    $stmt = $conn->prepare("SELECT member_id FROM members WHERE phone_number = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->bind_result($member_id);
    $stmt->fetch();
    $stmt->close();

    if ($member_id) {
        // --- Insert contribution record ---
        $type = 'savings'; // default or you can detect based on context
        $method = 'mpesa';

        $stmt = $conn->prepare("INSERT INTO contributions 
            (member_id, contribution_type, amount, contribution_date, payment_method, reference_no) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isdsss", $member_id, $type, $amount, $transactionDate, $method, $mpesaCode);
        $stmt->execute();
        $stmt->close();
    } else {
        // Log unmatched phone for manual review
        file_put_contents('mpesa_callback_log.txt', "No member found for phone: $phone" . PHP_EOL, FILE_APPEND);
    }

    $conn->close();
}
?>