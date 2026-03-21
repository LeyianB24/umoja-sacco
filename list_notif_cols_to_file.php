<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SHOW COLUMNS FROM notifications");
$out = "";
while($r = $res->fetch_assoc()) {
    $out .= $r['Field'] . " (" . $r['Type'] . ")\n";
}
file_put_contents('c:/xampp/htdocs/usms/notif_cols_clean.txt', $out);
echo "Done\n";
?>
