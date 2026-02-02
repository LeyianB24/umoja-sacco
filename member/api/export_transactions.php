<?php
// member/export_transactions.php
session_start();
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/ExportHelper.php';

if (!isset($_SESSION['member_id'])) die("Unauthorized");

$member_id = $_SESSION['member_id'];
$format = $_GET['format'] ?? 'pdf';

// Fetch Data
$sql = "SELECT transaction_date, transaction_type, reference_no, amount 
        FROM transactions 
        WHERE member_id = ? 
        ORDER BY transaction_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        $row['transaction_date'],
        strtoupper(str_replace('_', ' ', $row['transaction_type'])),
        $row['reference_no'],
        number_format($row['amount'], 2)
    ];
}

$headers = ['Date', 'Type', 'Reference', 'Amount (KES)'];
$title = "Member Transaction Statement - " . ($_SESSION['member_name'] ?? 'Member');

if ($format === 'excel') {
    ExportHelper::csv("transactions_" . date('Ymd'), $headers, $data);
} else {
    ExportHelper::pdf($title, $headers, $data, "transactions_" . date('Ymd') . ".pdf");
}
