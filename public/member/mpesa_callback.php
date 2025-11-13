<?php
// mpesa_callback.php
header("Content-Type: application/json");
require_once __DIR__ . '/../../config/db_connect.php'; // adjust if your DB file is elsewhere

$data = file_get_contents('php://input');
$logFile = __DIR__ . '/mpesa_callback_log.txt';
file_put_contents($logFile, date('Y-m-d H:i:s') . " Raw Callback:\n" . $data . "\n\n", FILE_APPEND);

$response = json_decode($data, true);

if (!isset($response['Body']['stkCallback'])) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback']);
    exit;
}

$callback = $response['Body']['stkCallback'];

if ($callback['ResultCode'] == 0) {
    $amount = $callback['CallbackMetadata']['Item'][0]['Value'] ?? 0;
    $mpesaCode = $callback['CallbackMetadata']['Item'][1]['Value'] ?? '';
    $phone = $callback['CallbackMetadata']['Item'][4]['Value'] ?? '';
    $contributionType = 'savings'; // Default type, can customize
    $memberId = null; // You can map this based on phone number if stored

    $stmt = $conn->prepare("INSERT INTO contributions (member_id, contribution_type, amount, payment_method, reference_no) VALUES (?, ?, ?, 'mpesa', ?)");
    $stmt->bind_param('isds', $memberId, $contributionType, $amount, $mpesaCode);
    $stmt->execute();
    $stmt->close();

    file_put_contents($logFile, "✅ Payment logged: {$mpesaCode} - KES {$amount}\n", FILE_APPEND);

    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
} else {
    file_put_contents($logFile, "❌ Failed payment: " . $callback['ResultDesc'] . "\n", FILE_APPEND);
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback received']);
}