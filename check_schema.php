<?php
require_once 'config/app.php';
$res = $conn->query("DESCRIBE audit_logs");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\n--- DESCRIBE admins ---\n";
$res = $conn->query("DESCRIBE admins");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
