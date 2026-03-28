<?php
require_once 'config/app.php';
$res = $conn->query("DESCRIBE audit_logs");
while ($row = $res->fetch_assoc()) {
    echo "Field: " . str_pad($row['Field'], 15) . " | Type: " . $row['Type'] . "\n";
}
echo "\n--- DESCRIBE admins ---\n";
$res = $conn->query("DESCRIBE admins");
while ($row = $res->fetch_assoc()) {
    echo "Field: " . str_pad($row['Field'], 15) . " | Type: " . $row['Type'] . "\n";
}
