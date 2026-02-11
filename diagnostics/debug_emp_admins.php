<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

echo "--- EMPLOYEES ---\n";
$res = $conn->query("DESCRIBE employees");
while ($row = $res->fetch_assoc()) {
    echo str_pad($row['Field'], 20) . " | " . $row['Type'] . "\n";
}

echo "\n--- ADMINS ---\n";
$res = $conn->query("DESCRIBE admins");
while ($row = $res->fetch_assoc()) {
    echo str_pad($row['Field'], 20) . " | " . $row['Type'] . "\n";
}
