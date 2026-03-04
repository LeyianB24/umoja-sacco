<?php
require 'config/db_connect.php';
$res = $conn->query("DESCRIBE contributions");
$out = "--- contributions table ---\n";
while($row = $res->fetch_assoc()) {
    foreach($row as $k => $v) $out .= "$k: $v | ";
    $out .= "\n";
}
file_put_contents('contributions_schema.txt', $out);
?>
