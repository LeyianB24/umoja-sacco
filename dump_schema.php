<?php
// dump_schema.php
require 'c:\xampp\htdocs\usms\config\db_connect.php';
$res = $conn->query("DESCRIBE welfare_cases");
$cols = [];
while($row = $res->fetch_assoc()) $cols[] = $row;
file_put_contents('welfare_cases_schema.json', json_encode($cols, JSON_PRETTY_PRINT));
echo "Dumped schema to welfare_cases_schema.json\n";
?>
