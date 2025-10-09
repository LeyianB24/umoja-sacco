<?php
include("../config/db_connect.php");

$filter_from = $_GET['from'] ?? '';
$filter_to = $_GET['to'] ?? '';
$filter_member = $_GET['member'] ?? '';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Contributions_Report.xls");

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

echo "<table border='1'>";
echo "<tr><th>Member Name</th><th>Amount (KSh)</th><th>Date</th></tr>";

$total = 0;
while ($row = $result->fetch_assoc()) {
  echo "<tr>";
  echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
  echo "<td>" . number_format($row['amount'], 2) . "</td>";
  echo "<td>" . $row['contribution_date'] . "</td>";
  echo "</tr>";
  $total += $row['amount'];
}

echo "<tr style='font-weight:bold; background:#eef'>";
echo "<td colspan='1' align='right'>Total:</td>";
echo "<td colspan='2'>KSh " . number_format($total, 2) . "</td>";
echo "</tr>";
echo "</table>";
?>
