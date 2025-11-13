<?php
require_once __DIR__ . '/../config/db_connect.php';

$data = file_get_contents('php://input');
file_put_contents(__DIR__ . '/../logs/mpesa_response.json', $data);

$response = json_decode($data, true);

if (isset($response['Body']['stkCallback']['ResultCode']) && $response['Body']['stkCallback']['ResultCode'] == 0) {
    $details = $response['Body']['stkCallback']['CallbackMetadata']['Item'];
    $amount = $details[0]['Value'];
    $mpesa_code = $details[1]['Value'];
    $phone = $details[4]['Value'];

    // Save to transactions table
    $stmt = $conn->prepare("INSERT INTO transactions (member_id, transaction_type, amount, transaction_date, notes, reference_no) VALUES (?, 'M-Pesa', ?, NOW(), 'Contribution via M-Pesa', ?)");
    $stmt->bind_param("ids", $member_id, $amount, $mpesa_code);
    $stmt->execute();
    $stmt->close();
}

http_response_code(200);
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
?>
