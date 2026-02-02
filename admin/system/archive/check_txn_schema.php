<?php
require 'config/db_connect.php';
$res = $conn->query("DESCRIBE transactions");
$out = "";
while($row = $res->fetch_assoc()) {
    $out .= $row['Field'] . "\n";
}
file_put_contents('transactions_schema.txt', $out);
echo "Done\n";
