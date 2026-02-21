<?php
require_once 'config/db_connect.php';

echo "Databases found:\n";
$res = $conn->query("SHOW DATABASES");
while($r = $res->fetch_row()) echo " - " . $r[0] . "\n";

echo "\nPermissions Slugs:\n";
$res = $conn->query("SELECT slug FROM permissions");
if ($res) {
    while($r = $res->fetch_assoc()) echo " - " . $r['slug'] . "\n";
} else {
    echo "Error fetching permissions: " . $conn->error . "\n";
}
