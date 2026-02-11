<?php
require_once __DIR__ . '/../config/db_connect.php';

echo "Latest Contribution:\n";
$res = $conn->query("SELECT * FROM contributions ORDER BY created_at DESC LIMIT 1");
print_r($res->fetch_assoc());

echo "\nLatest Transaction:\n";
$res = $conn->query("SELECT * FROM transactions ORDER BY created_at DESC LIMIT 1");
print_r($res->fetch_assoc());
?>
