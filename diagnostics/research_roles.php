<?php
require_once __DIR__ . '/../config/db_connect.php';

echo "--- ROLES ---\n";
$res = $conn->query("SELECT id, name, slug FROM roles");
while ($row = $res->fetch_row()) {
    echo implode(" | ", $row) . "\n";
}

echo "\n--- SUPPORT PERMISSIONS ---\n";
$res = $conn->query("SELECT id, name, slug FROM permissions WHERE slug LIKE 'support_%'");
while ($row = $res->fetch_row()) {
    echo implode(" | ", $row) . "\n";
}
