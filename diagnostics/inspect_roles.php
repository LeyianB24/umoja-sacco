<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

function fetch_sql($conn, $sql) {
    echo "SQL: $sql\n";
    $res = $conn->query($sql);
    if (!$res) {
        echo "Error: " . $conn->error . "\n";
        return;
    }
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
    echo "\n";
}

fetch_sql($conn, "SELECT * FROM roles");
fetch_sql($conn, "SELECT * FROM permissions");
fetch_sql($conn, "SELECT r.name as role, p.slug FROM roles r JOIN role_permissions rp ON r.id = rp.role_id JOIN permissions p ON rp.permission_id = p.id");
