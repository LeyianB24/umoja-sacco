<?php
$host = 'localhost';
$user = 'root';
$pass = ''; // Default XAMPP password is empty
$db   = 'umoja_drivers_sacco'; // Correct database name from config/db_connect.php

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully to database: $db\n";

$queries = [
    "ALTER TABLE investments ADD COLUMN target_amount DECIMAL(15,2) DEFAULT 0.00",
    "ALTER TABLE investments ADD COLUMN target_period ENUM('daily', 'monthly', 'annually') DEFAULT 'monthly'",
    "ALTER TABLE investments ADD COLUMN target_start_date DATE DEFAULT NULL",
    "ALTER TABLE investments ADD COLUMN viability_status ENUM('viable', 'underperforming', 'loss_making', 'pending') DEFAULT 'pending'",
    "ALTER TABLE investments ADD COLUMN last_viability_check DATETIME DEFAULT NULL",
    "CREATE INDEX idx_investment_viability ON investments(viability_status, status)",
    "CREATE INDEX idx_investment_targets ON investments(target_period, target_start_date)"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Executed: " . substr($q, 0, 50) . "...\n";
    } else {
        echo "Error or Already Exists: " . $conn->error . "\n";
    }
}

$conn->query("UPDATE investments SET viability_status = 'pending' WHERE viability_status IS NULL OR viability_status = ''");
echo "Done.\n";
?>
