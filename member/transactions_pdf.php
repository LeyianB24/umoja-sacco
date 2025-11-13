<?php
session_start();
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Check login
if (!isset($_SESSION['member_id'])) {
    header("Location: ../public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];

// Get totals
function get_total($conn, $member_id, $type) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM transactions WHERE member_id = ? AND transaction_type = ?");
    $stmt->bind_param("is", $member_id, $type);
    $stmt->execute();
    return (float) $stmt->get_result()->fetch_assoc()['total'];
}

$total_contributions = get_total($conn, $member_id, 'contribution');
$total_loans = get_total($conn, $member_id, 'loan_disbursement');
$total_repayments = get_total($conn, $member_id, 'repayment');
$net_savings = $total_contributions + $total_repayments - $total_loans;

// Fetch all transactions
$stmt = $conn->prepare("SELECT transaction_type, amount, reference_no, payment_channel, created_at, description 
                        FROM transactions 
                        WHERE member_id = ? 
                        ORDER BY created_at DESC");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

// Generate HTML content
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
    h2, h3 { text-align: center; color: #d9dee6ff; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #0b0101ff; padding: 8px; text-align: left; }
    th { background-color: #004aad; color: white; }
    tr:nth-child(even) { background-color: #0c0101ff; }
    .summary { margin-top: 20px; width: 100%; }
    .summary td { padding: 6px; }
    .summary th { text-align: left; color: #004aad; }
</style>
</head>
<body>
<h2>Umoja Drivers Sacco</h2>
<h3>Member Transactions Summary</h3>

<table class="summary">
    <tr><th>Total Contributions:</th><td>KSh ' . number_format($total_contributions, 2) . '</td></tr>
    <tr><th>Total Loans:</th><td>KSh ' . number_format($total_loans, 2) . '</td></tr>
    <tr><th>Total Repayments:</th><td>KSh ' . number_format($total_repayments, 2) . '</td></tr>
    <tr><th><strong>Net Savings:</strong></th><td><strong>KSh ' . number_format($net_savings, 2) . '</strong></td></tr>
</table>

<h3>Transaction Records</h3>
<table>
<thead>
<tr>
    <th>#</th>
    <th>Transaction Type</th>
    <th>Amount (KSh)</th>
    <th>Reference No</th>
    <th>Payment Channel</th>
    <th>Date</th>
    <th>Description</th>
</tr>
</thead>
<tbody>';

$counter = 1;
while ($row = $result->fetch_assoc()) {
    $html .= '<tr>
        <td>' . $counter++ . '</td>
        <td>' . htmlspecialchars($row['transaction_type'] ?? '-') . '</td>
        <td>' . number_format($row['amount'], 2) . '</td>
        <td>' . htmlspecialchars($row['reference_no'] ?? '-') . '</td>
        <td>' . htmlspecialchars($row['payment_channel'] ?? '-') . '</td>
        <td>' . date('d M Y, h:i A', strtotime($row['created_at'])) . '</td>
        <td>' . htmlspecialchars($row['description'] ?? '-') . '</td>
    </tr>';
}

if ($counter === 1) {
    $html .= '<tr><td colspan="7" style="text-align:center; color:#777;">No transactions found.</td></tr>';
}

$html .= '
</tbody>
</table>
</body>
</html>';

// Create PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output for download
$dompdf->stream("Umoja_Transactions.pdf", ["Attachment" => true]);
?>