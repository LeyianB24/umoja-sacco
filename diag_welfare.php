<?php
require 'config/db_connect.php';
$res = $conn->query("SHOW TABLES");
echo "TABLES:\n";
while($row = $res->fetch_row()) echo "- " . $row[0] . "\n";

echo "\nWELFARE ACCOUNTS:\n";
$res = $conn->query("SELECT account_name, account_type FROM ledger_accounts WHERE category = 'welfare'");
while($row = $res->fetch_assoc()) echo "- " . $row['account_name'] . " (" . $row['account_type'] . ")\n";

echo "\nWELFARE CASES SCHEMA:\n";
if ($conn->query("SHOW TABLES LIKE 'welfare_cases'")->num_rows > 0) {
    $res = $conn->query("DESCRIBE welfare_cases");
    while($row = $res->fetch_assoc()) echo $row['Field'] . " | " . $row['Type'] . "\n";
}

echo "\nWELFARE SUPPORT SCHEMA:\n";
if ($conn->query("SHOW TABLES LIKE 'welfare_support'")->num_rows > 0) {
    $res = $conn->query("DESCRIBE welfare_support");
    while($row = $res->fetch_assoc()) echo $row['Field'] . " | " . $row['Type'] . "\n";
}
