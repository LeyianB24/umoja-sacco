<?php
// Force error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/app_config.php'; // Ensure constants are loaded
require_once __DIR__ . '/config/db_connect.php';

if (!isset($conn) || $conn === null) {
    die("ERROR: \$conn is null after require.");
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "DB Connection OK.\n";

// Get valid member ID
$res = $conn->query("SELECT id FROM members LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    echo "First Member ID: " . $row['id'] . "\n";
} else {
    echo "No members found.\n";
}

// Get valid admin ID
$res = $conn->query("SELECT id FROM admins LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    echo "First Admin ID: " . $row['id'] . "\n";
} else {
    echo "No admins found.\n";
}

// Show Create Table
$res = $conn->query("SHOW CREATE TABLE notifications");
if ($res && $row = $res->fetch_row()) {
    echo "Table Definition:\n" . $row[1] . "\n";
}
