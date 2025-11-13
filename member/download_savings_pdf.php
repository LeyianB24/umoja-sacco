<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_config.php';

if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;

$member_id = $_SESSION['member_id'];

// Fetch all savings
$stmt = $conn->prepare("SELECT * FROM savings WHERE member_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

$html = '
<h3 style="text-align:center; color:green;">Savings History</h3>
<table border="1" cellspacing="0" cellpadding="6" width="100%">
<thead>
<tr style="background-color:#d4edda;">
<th>#</th><th>Type</th><th>Amount (KSh)</th><th>Description</th><th>Reference</th><th>Date</th>
</tr>
</thead><tbody>';

$count = 1;
while ($row = $result->fetch_assoc()) {
    $html .= "<tr>
        <td>{$count}</td>
        <td>{$row['transaction_type']}</td>
        <td>{$row['amount']}</td>
        <td>{$row['description']}</td>
        <td>{$row['reference_no']}</td>
        <td>{$row['created_at']}</td>
    </tr>";
    $count++;
}

$html .= '</tbody></table>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Savings_History.pdf", ["Attachment" => true]);
exit;
?>