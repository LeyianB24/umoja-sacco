<?php
session_start();
require '../vendor/autoload.php';
include("../config/db_connect.php");

use Dompdf\Dompdf;
use Dompdf\Options;

// Ensure only admin can access
if (!isset($_SESSION['admin_id'])) {
  header("Location: ../login.php");
  exit;
}

$filter_from = $_GET['from'] ?? '';
$filter_to = $_GET['to'] ?? '';
$filter_member = $_GET['member'] ?? '';

$query = "
  SELECT m.full_name, c.amount, c.contribution_date
  FROM contributions c
  JOIN members m ON c.member_id = m.id
  WHERE 1
";

if (!empty($filter_from) && !empty($filter_to)) {
  $query .= " AND DATE(c.contribution_date) BETWEEN '$filter_from' AND '$filter_to'";
}
if (!empty($filter_member)) {
  $query .= " AND m.id = '$filter_member'";
}
$query .= " ORDER BY c.contribution_date DESC";

$result = $conn->query($query);

$html = "
  <h2 style='text-align:center;'>Umoja Sacco Contributions Report</h2>
  <p style='text-align:center;'>Generated on " . date('d M Y, h:i A') . "</p>
  <table width='100%' border='1' cellspacing='0' cellpadding='5'>
    <thead style='background-color:#f2f2f2;'>
      <tr>
        <th>#</th>
        <th>Member Name</th>
        <th>Amount (KSh)</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
";

$total = 0;
$count = 1;
while ($row = $result->fetch_assoc()) {
  $html .= "<tr>
    <td>{$count}</td>
    <td>{$row['full_name']}</td>
    <td>" . number_format($row['amount'], 2) . "</td>
    <td>{$row['contribution_date']}</td>
  </tr>";
  $total += $row['amount'];
  $count++;
}

if ($count === 1) {
  $html .= "<tr><td colspan='4' style='text-align:center;'>No contributions found</td></tr>";
}

$html .= "
    </tbody>
  </table>
  <h3 style='text-align:right;'>Total: KSh " . number_format($total, 2) . "</h3>
";

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("usms_contributions_report.pdf", ["Attachment" => true]);
?>
