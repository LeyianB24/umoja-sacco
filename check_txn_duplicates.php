<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
$sql = "SELECT reference_no, COUNT(*) as cnt FROM transactions WHERE reference_no != '' AND reference_no IS NOT NULL GROUP BY reference_no HAVING cnt > 1";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    while($row = $res->fetch_assoc()) echo "⚠️ Duplicate Tx Ref: {$row['reference_no']} (Count: {$row['cnt']})\n";
} else {
    echo "✅ No duplicate transaction references.\n";
}
?>
