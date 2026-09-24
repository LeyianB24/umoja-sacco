<?php
require_once __DIR__ . '/../config/db_connect.php';
echo "DB: " . $dbname . "\n";
$res = $conn->query("SHOW TABLES LIKE 'admins'");
echo "Table 'admins' exists: " . ($res->num_rows > 0 ? "YES" : "NO") . "\n";
if ($res->num_rows > 0) {
    $res = $conn->query("SELECT COUNT(*) FROM admins");
    echo "Count: " . ($res ? $res->fetch_row()[0] : "QUERY FAILED: " . $conn->error) . "\n";
    if ($res) {
        $res = $conn->query("SELECT * FROM admins LIMIT 5");
        while ($row = $res->fetch_assoc()) {
            echo "Admin: " . $row['username'] . " (" . $row['role'] . ")\n";
        }
    }
} else {
    $res = $conn->query("SHOW TABLES");
    echo "Tables in DB:\n";
    while ($row = $res->fetch_row()) echo "- " . $row[0] . "\n";
}
