<?php
require_once 'config/app.php';
$res = $conn->query("DESCRIBE audit_logs");
echo "AUDIT_LOGS columns:\n";
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
echo "\nADMINS columns:\n";
$res = $conn->query("DESCRIBE admins");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
