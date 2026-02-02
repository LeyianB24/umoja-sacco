<?php
// usms/member/download_certified_statement.php
session_start();
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/ReportGenerator.php';

// 1. Auth Check
if (!isset($_SESSION['member_id'])) {
    die("Unauthorized.");
}

$member_id = (int)$_SESSION['member_id'];

// 2. Fetch Member Data
$stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$memberData = $stmt->get_result()->fetch_assoc();

if (!$memberData) die("Member not found.");

// Calculate totals for summary
function get_sum($conn, $sql, $id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $val = $stmt->get_result()->fetch_row()[0] ?? 0;
    return (float)$val;
}

$memberData['total_savings'] = get_sum($conn, "SELECT COALESCE(SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE -amount END), 0) FROM savings WHERE member_id = ?", $member_id);
$memberData['total_shares']  = get_sum($conn, "SELECT COALESCE(SUM(total_value), 0) FROM shares WHERE member_id = ?", $member_id);
$memberData['loan_debt']     = get_sum($conn, "SELECT COALESCE(SUM(current_balance), 0) FROM loans WHERE member_id = ? AND status IN ('approved', 'disbursed', 'active')", $member_id);

// 3. Fetch Transaction History
$transactions = [];
$stmt = $conn->prepare("SELECT transaction_type, amount, transaction_date, reference_no FROM transactions WHERE member_id = ? ORDER BY transaction_date DESC LIMIT 50");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) $transactions[] = $row;

// 4. Generate PDF
$reportGen = new ReportGenerator($conn);
$pdf = $reportGen->generateMemberStatement($memberData, $transactions);

$pdf->Output('D', 'Certified_Statement_' . date('Ymd') . '.pdf');
exit;
