<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
$res = $conn->query("SELECT status, COUNT(*) as count FROM investments GROUP BY status");
while($row = $res->fetch_assoc()) {
    echo $row['status'] . ": " . $row['count'] . "\n";
}
echo "---\n";
$res = $conn->query("SELECT status, COUNT(*) as count FROM vehicles GROUP BY status");
while($row = $res->fetch_assoc()) {
    echo $row['status'] . ": " . $row['count'] . "\n";
}
?>
