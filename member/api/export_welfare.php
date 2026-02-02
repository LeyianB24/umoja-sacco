<?php
// member/export_welfare.php
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
$type   = $_GET['type'] ?? 'contributions'; // 'contributions' or 'support'

// Build Query
if ($type === 'support') {
    $sql = "SELECT date_granted, reason, status, amount FROM welfare_support WHERE member_id = ? ORDER BY date_granted DESC";
    $headers = ['Date Granted', 'Reason', 'Status', 'Amount (KES)'];
    $title = "WELFARE SUPPORT RECEIVED - " . strtoupper($member_name);
} else {
    $sql = "SELECT created_at, reference_no, status, amount FROM contributions 
            WHERE member_id = ? AND contribution_type IN ('welfare', 'welfare_case') 
            ORDER BY created_at DESC";
    $headers = ['Date', 'Reference', 'Status', 'Amount (KES)'];
    $title = "WELFARE CONTRIBUTIONS - " . strtoupper($member_name);
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    if ($type === 'support') {
        $data[] = [
            date('d M Y', strtotime($row['date_granted'])),
            $row['reason'],
            ucfirst($row['status']),
            number_format($row['amount'], 2)
        ];
    } else {
        $data[] = [
            date('d M Y, H:i', strtotime($row['created_at'])),
            $row['reference_no'],
            ucfirst($row['status']),
            number_format($row['amount'], 2)
        ];
    }
}

if ($format === 'excel') {
    ExportHelper::csv("welfare_" . $type . "_" . date('Ymd'), $headers, $data);
} else {
    ExportHelper::pdf($title, $headers, $data, "welfare_" . $type . "_" . date('Ymd') . ".pdf");
}
