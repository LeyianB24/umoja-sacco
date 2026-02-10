<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/db_connect.php';

// 1. List All Tables
echo "=== TABLES ===\n";
$res = $conn->query("SHOW TABLES");
if ($res) {
    while($row = $res->fetch_row()) {
        echo $row[0] . "\n";
    }
} else {
    echo "Failed to show tables.\n";
}
echo "==============\n";

// 2. Check members structure
echo "=== MEMBERS Columns ===\n";
$res = $conn->query("DESCRIBE members");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . "\n"; // Just print field names
    }
} else {
    echo "Failed to describe members (Table might not exist).\n";
}
echo "=======================\n";

// 3. Get first member
echo "=== First Member ===\n";
$res = $conn->query("SELECT * FROM members LIMIT 1");
if ($res) {
    $row = $res->fetch_assoc();
    if ($row) {
        print_r($row);
    } else {
        echo "No members found.\n";
    }
}
