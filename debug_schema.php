<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/app.php';

function check_table($conn, $name) {
    echo "\nTABLE: $name\n";
    $res = $conn->query("DESCRIBE `$name` ");
    if (!$res) {
        echo "Error: " . $conn->error . "\n";
        return;
    }
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . "\n";
    }
}

check_table($conn, 'permissions');
check_table($conn, 'role_permissions');
check_table($conn, 'audit_logs');
