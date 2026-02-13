<?php
require 'config/db_connect.php';
$res = $conn->query("SELECT * FROM welfare_donations ORDER BY donation_date DESC LIMIT 20");
echo "Recent Welfare Donations:\n";
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['donation_id']} | Case: {$row['case_id']} | Ref: {$row['reference_no']} | Date: {$row['donation_date']}\n";
}
?>
