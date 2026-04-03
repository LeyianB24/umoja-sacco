<?php
require 'c:/xampp/htdocs/usms/config/app.php';

// Mock some parameters for testing
$_POST['member_id'] = 1; // Assuming member 1 exists
$_POST['start_date'] = date('Y-m-d', strtotime('-1 month'));
$_POST['end_date'] = date('Y-m-d');
$_POST['report_type'] = 'full';
$_POST['format'] = 'pdf';

// We won't actually call the API file because it calls ExportHelper which has exit() calls.
// Instead we test the core SQL and logic.

function test_member($conn, $member_id) {
    $stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function test_transactions($conn, $member_id, $start, $end) {
    $sql = "SELECT * FROM transactions WHERE member_id = ? AND created_at BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $s = "$start 00:00:00";
    $e = "$end 23:59:59";
    $stmt->bind_param("iss", $member_id, $s, $e);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$member = test_member($conn, 1);
if ($member) {
    echo "Found Member: " . $member['full_name'] . "\n";
    $txns = test_transactions($conn, 1, $_POST['start_date'], $_POST['end_date']);
    echo "Found " . count($txns) . " transactions in the last month.\n";
} else {
    echo "Member 1 not found. Testing with first available member.\n";
    $res = $conn->query("SELECT member_id, full_name FROM members LIMIT 1");
    if ($row = $res->fetch_assoc()) {
        echo "Testing with Member " . $row['member_id'] . ": " . $row['full_name'] . "\n";
        $txns = test_transactions($conn, $row['member_id'], $_POST['start_date'], $_POST['end_date']);
        echo "Found " . count($txns) . " transactions.\n";
    }
}
