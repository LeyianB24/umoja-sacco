<?php
require "config/app.php";
// Check roles table structure
$r3 = $conn->query("DESCRIBE roles");
echo "Roles columns:\n";
while($row = $r3->fetch_assoc()) echo "  " . $row['Field'] . " " . $row['Type'] . "\n";
$r4 = $conn->query("SELECT * FROM roles");
echo "\nRoles data:\n";
while($row = $r4->fetch_assoc()) { echo "  "; print_r($row); }

// Check withdrawal_requests table
$r5 = $conn->query("SHOW TABLES LIKE 'withdrawal_requests'");
echo "\nwithdrawal_requests: " . ($r5->num_rows > 0 ? 'exists' : 'NOT FOUND') . "\n";
