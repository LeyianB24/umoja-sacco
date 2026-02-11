<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

$res = $conn->query("DESCRIBE notifications");
while ($row = $res->fetch_assoc()) {
    echo str_pad($row['Field'], 20) . " | " . $row['Type'] . "\n";
}
