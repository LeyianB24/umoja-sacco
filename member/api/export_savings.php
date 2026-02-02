<?php
// member/export_savings.php
session_start();
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/ExportHelper.php';

// Auth Check
if (!isset($_SESSION['member_id'])) die("Access Denied");
$member_id = $_SESSION['member_id'];
$member_name = $_SESSION['member_name'];

$format = $_GET['format'] ?? 'pdf';
$typeFilter = $_GET['type'] ?? '';
$startDate  = $_GET['start_date'] ?? '';
$endDate    = $_GET['end_date'] ?? '';

// Build Query
$where = "WHERE member_id = ?";
$params = [$member_id];
$types = "i";

if ($typeFilter && in_array($typeFilter, ['deposit', 'withdrawal'])) {
    $where .= " AND transaction_type = ?";
    $params[] = $typeFilter;
    $types .= "s";
}
if ($startDate && $endDate) {
    $where .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= "ss";
}

$sql = "SELECT created_at, transaction_type, amount, description, status FROM savings $where ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        date('d M Y, H:i', strtotime($row['created_at'])),
        ucfirst($row['transaction_type']),
        number_format($row['amount'], 2),
        $row['description'],
        ucfirst($row['status'])
    ];
}

$headers = ['Date', 'Type', 'Amount (KES)', 'Description', 'Status'];
$title = "SAVINGS STATEMENT - " . strtoupper($member_name);

if ($format === 'excel') {
    ExportHelper::csv("savings_" . date('Ymd'), $headers, $data);
} else {
    ExportHelper::pdf($title, $headers, $data, "savings_" . date('Ymd') . ".pdf");
}
