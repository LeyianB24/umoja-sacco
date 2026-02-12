<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db_connect.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "--- ROLES ---\n";
$res = $conn->query("SELECT id, name, slug FROM roles");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\n--- SUPPORT PERMISSIONS ---\n";
$res = $conn->query("SELECT id, name, slug FROM permissions WHERE slug LIKE 'support_%'");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
