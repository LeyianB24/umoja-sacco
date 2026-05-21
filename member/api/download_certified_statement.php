<?php
// usms/member/download_certified_statement.php
session_start();
require_once __DIR__ . '/../../config/app.php';
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

$memberData['member_no'] = $memberData['member_reg_no'] ?? ('MEM-' . $member_id);
$memberData['total_savings'] = get_sum($conn, "
    SELECT COALESCE(SUM(
        CASE
            WHEN transaction_type IN ('deposit','contribution','savings_deposit','interest','dividend') THEN amount
            WHEN transaction_type IN ('withdrawal','withdrawal_initiate','withdrawal_finalize') THEN -amount
            ELSE 0
        END
    ), 0)
    FROM transactions
    WHERE member_id = ?
", $member_id);
$memberData['total_shares'] = get_sum($conn, "
    SELECT COALESCE(SUM(amount), 0)
    FROM transactions
    WHERE member_id = ?
      AND (related_table = 'shares' OR transaction_type IN ('share_purchase','shares') OR notes LIKE '%Shares%')
", $member_id);
$memberData['loan_debt']     = get_sum($conn, "SELECT COALESCE(SUM(current_balance), 0) FROM loans WHERE member_id = ? AND status IN ('approved', 'disbursed', 'active')", $member_id);

// 3. Fetch Transaction History
$transactions = [];
$stmt = $conn->prepare("SELECT transaction_type, amount, COALESCE(transaction_date, created_at) AS transaction_date, reference_no FROM transactions WHERE member_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) $transactions[] = $row;

// 4. Generate PDF
$reportGen = new ReportGenerator($conn);
$pdf = $reportGen->generateMemberStatement($memberData, $transactions);

$pdf->Output('D', 'Certified_Statement_' . date('Ymd') . '.pdf');
exit;
