<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'config/db_connect.php';

echo "Database connection check:\n";
if (isset($conn) && $conn instanceof mysqli) {
    echo " - SUCCESS: Connected to " . $conn->host_info . "\n";
    $res = $conn->query("SELECT DATABASE()");
    $row = $res->fetch_row();
    echo " - Active DB: " . $row[0] . "\n";
} else {
    echo " - FAILED: \$conn is not set or not a mysqli instance.\n";
}

echo "\nChecking Core Tables:\n";
$tables = ['members', 'transactions', 'permissions', 'role_permissions', 'cron_runs'];
foreach ($tables as $t) {
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    if ($res && $res->num_rows > 0) {
        echo " - Table '$t' exists.\n";
    } else {
        echo " - Table '$t' MISSING!\n";
    }
}
