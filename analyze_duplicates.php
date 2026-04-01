<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
$sql = "SELECT * FROM transactions 
        WHERE reference_no IN ('DR16788220', 'DR16788223', 'SIM-9277FDCB2B16', 'RVC-69') 
        ORDER BY reference_no, status, transaction_id ASC";
$res = $conn->query($sql);
echo "ID | Ref | Member | Amount | Type | Status | Created\n";
echo str_repeat("-", 80) . "\n";
while($row = $res->fetch_assoc()) {
    printf("%4d | %-16s | %6d | %8.2f | %-12s | %-8s | %s\n", 
           $row['transaction_id'], $row['reference_no'], $row['member_id'], 
           $row['amount'], $row['type'], $row['status'], $row['created_at']);
}
?>
