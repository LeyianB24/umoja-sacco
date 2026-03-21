<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT * FROM members WHERE member_id = 8");
$row = $res->fetch_assoc();
echo "Member 8 Full Data:\n";
foreach ($row as $k => $v) {
    echo "$k: " . (is_null($v) ? 'NULL' : $v) . "\n";
}
?>
