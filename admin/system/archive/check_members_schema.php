<?php
require 'config/app.php';
$res = $conn->query("DESCRIBE members");
$out = "--- members table ---\n";
while($row = $res->fetch_assoc()) {
    foreach($row as $k => $v) $out .= "$k: $v | ";
    $out .= "\n";
}
file_put_contents('members_schema.txt', $out);
?>
