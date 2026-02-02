<?php
require 'config/db_connect.php';
$res = $conn->query("DESCRIBE admins");
$out = "--- admins table ---\n";
while($row = $res->fetch_assoc()) {
    foreach($row as $k => $v) $out .= "$k: $v | ";
    $out .= "\n";
}
file_put_contents('admins_schema.txt', $out);
?>
