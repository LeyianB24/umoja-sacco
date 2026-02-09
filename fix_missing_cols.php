<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'umoja_drivers_sacco';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

echo "Connected to $db\n";

$queries = [
    "ALTER TABLE investments ADD COLUMN target_start_date DATE DEFAULT NULL AFTER target_period",
    "ALTER TABLE investments ADD COLUMN viability_status ENUM('viable', 'underperforming', 'loss_making', 'pending') DEFAULT 'pending' AFTER target_start_date",
    "ALTER TABLE investments ADD COLUMN last_viability_check DATETIME DEFAULT NULL AFTER viability_status"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Executed: $q\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}

// Also update the target_period to include daily/annually if not already there
$conn->query("ALTER TABLE investments MODIFY COLUMN target_period ENUM('daily', 'monthly', 'annually') DEFAULT 'monthly'");

echo "Finished.\n";
?>
