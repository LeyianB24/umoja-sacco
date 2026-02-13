<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require 'config/db_connect.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

echo "Connected to: " . $conn->query("SELECT DATABASE()")->fetch_row()[0] . "\n\n";

// List all tables to be sure
$res = $conn->query("SHOW TABLES");
$tables = [];
while($row = $res->fetch_row()) $tables[] = $row[0];
echo "Tables in DB: " . implode(', ', $tables) . "\n\n";

if (!in_array('welfare_donations', $tables)) {
    die("ERROR: welfare_donations table is missing!\n");
}

// Check Case #4
$res = $conn->query("SELECT case_id, title, total_raised FROM welfare_cases WHERE case_id = 4");
if ($row = $res->fetch_assoc()) {
    echo "--- CASE #4 STATS ---\n";
    echo "ID: " . $row['case_id'] . "\n";
    echo "Title: " . $row['title'] . "\n";
    echo "Total Raised: " . $row['total_raised'] . "\n";
} else {
    echo "Case #4 not found!\n";
}

// Aggregate check
$res = $conn->query("SELECT SUM(amount) as total FROM welfare_donations WHERE case_id = 4");
$row = $res->fetch_assoc();
echo "Actual donation sum for Case #4: " . ($row['total'] ?? 0) . "\n";

// Count donations
$res = $conn->query("SELECT COUNT(*) as cnt FROM welfare_donations WHERE case_id = 4");
$row = $res->fetch_assoc();
echo "Number of donation records for Case #4: " . $row['cnt'] . "\n";

?>
