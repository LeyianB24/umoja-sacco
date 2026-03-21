<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SHOW COLUMNS FROM members");
$out = "";
while($r = $res->fetch_assoc()) {
    $out .= $r['Field'] . "\n";
}
file_put_contents('c:/xampp/htdocs/usms/members_clean.txt', $out);
echo "Done\n";
?>
