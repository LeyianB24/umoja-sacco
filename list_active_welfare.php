<?php
require 'config/db_connect.php';
$res = $conn->query("SELECT * FROM contributions WHERE contribution_type = 'welfare' AND status = 'active' ORDER BY created_at DESC");
echo "Active Welfare Contributions:\n";
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['contribution_id']} | Member: {$row['member_id']} | Amount: {$row['amount']} | Date: {$row['created_at']} | Ref: {$row['reference_no']}\n";
}
?>
