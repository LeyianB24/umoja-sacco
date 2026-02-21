<?php
require_once 'config/db_connect.php';

echo "Database: " . DB_NAME . "\n";
echo "Tables:\n";
$res = $conn->query("SHOW TABLES");
while($r = $res->fetch_row()) echo " - " . $r[0] . "\n";

echo "\nPermissions Snippet:\n";
$res = $conn->query("SELECT * FROM permissions LIMIT 10");
if ($res) {
    while($r = $res->fetch_assoc()) print_r($r);
} else {
    echo "Error fetching permissions: " . $conn->error . "\n";
}

echo "\nAdmin Roles Snippet:\n";
$res = $conn->query("SELECT * FROM role_permissions LIMIT 10");
if ($res) {
    while($r = $res->fetch_assoc()) print_r($r);
} else {
    echo "Error fetching role_permissions: " . $conn->error . "\n";
}

echo "\nSession Status:\n";
session_start();
print_r($_SESSION);
