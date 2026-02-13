<?php
require 'config/db_connect.php';

$queries = [
    "ALTER TABLE welfare_cases ADD COLUMN IF NOT EXISTS requested_amount DECIMAL(15,2) DEFAULT 0.00 AFTER target_amount",
    "ALTER TABLE welfare_cases ADD COLUMN IF NOT EXISTS approved_amount DECIMAL(15,2) DEFAULT 0.00 AFTER requested_amount",
    "ALTER TABLE welfare_cases ADD COLUMN IF NOT EXISTS total_raised DECIMAL(15,2) DEFAULT 0.00 AFTER approved_amount",
    "ALTER TABLE welfare_cases ADD COLUMN IF NOT EXISTS total_disbursed DECIMAL(15,2) DEFAULT 0.00 AFTER total_raised"
];

foreach ($queries as $q) {
    if (!$conn->query($q)) {
        echo "Error: " . $conn->error . "\n";
    } else {
        echo "Success: $q\n";
    }
}
?>
