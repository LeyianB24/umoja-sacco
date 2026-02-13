<?php
// full_dump.php
require 'c:\xampp\htdocs\usms\config\db_connect.php';

$res = $conn->query("SHOW TABLES"); $output = "";
while($row = $res->fetch_array()) {
    $t = $row[0];
    $output .= "### $t ###\n";
    $q = $conn->query("DESCRIBE `$t` ");
    while($r = $q->fetch_assoc()) $output .= "{$r['Field']} | {$r['Type']}\n";
    $output .= "\n";
}
file_put_contents('full_schema.txt', $output);
echo "Dumped to full_schema.txt\n";
?>
