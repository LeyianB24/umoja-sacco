<?php
require_once __DIR__ . '/config/app.php';
$res = $conn->query("DESCRIBE transactions");
$cols = [];
while($row = $res->fetch_assoc()) echo $row['Field'] . " (" . $row['Type'] . ")\n";
?>
