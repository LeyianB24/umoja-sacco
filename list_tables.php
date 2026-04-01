<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
$res = $conn->query("SHOW TABLES");
echo "TABLES:\n";
while($row = $res->fetch_row()) echo $row[0]."\n";
?>
