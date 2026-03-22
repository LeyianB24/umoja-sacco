<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT TRIGGER_NAME, ACTION_STATEMENT FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND ACTION_STATEMENT LIKE '%savings_balance%'");
$out = '';
while($r = $res->fetch_assoc()) {
    $out .= "TRIGGER: " . $r['TRIGGER_NAME'] . "\n" . $r['ACTION_STATEMENT'] . "\n\n";
}
file_put_contents('c:/xampp/htdocs/usms/trigger_search.txt', $out);
echo "Done";
