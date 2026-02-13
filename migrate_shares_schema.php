<?php
require 'config/db_connect.php';

$sql = "
-- 1. Share Settings
CREATE TABLE IF NOT EXISTS share_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    total_authorized_units DECIMAL(20,4) DEFAULT 1000000.0000,
    initial_unit_price DECIMAL(20,2) DEFAULT 100.00,
    par_value DECIMAL(20,2) DEFAULT 100.00,
    last_valuation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Initialize settings if empty
INSERT INTO share_settings (total_authorized_units, initial_unit_price, par_value) 
SELECT 1000000, 100, 100 WHERE (SELECT COUNT(*) FROM share_settings) = 0;

-- 2. Member Shareholdings
CREATE TABLE IF NOT EXISTS member_shareholdings (
    member_id INT PRIMARY KEY,
    units_owned DECIMAL(20,4) DEFAULT 0.0000,
    total_amount_paid DECIMAL(30,2) DEFAULT 0.00,
    average_purchase_price DECIMAL(20,2) DEFAULT 0.00,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
);

-- 3. Share Transactions (Equity Layer)
CREATE TABLE IF NOT EXISTS share_transactions (
    txn_id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    units DECIMAL(20,4) NOT NULL,
    unit_price DECIMAL(20,2) NOT NULL,
    total_value DECIMAL(30,2) NOT NULL,
    transaction_type ENUM('purchase', 'dividend', 'transfer', 'migration') NOT NULL,
    reference_no VARCHAR(100) UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
);
";

if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) { $res->free(); }
    } while ($conn->next_result());
    echo "Migration Success: Tables created/verified.\n";
} else {
    echo "Migration Failed: " . $conn->error . "\n";
}
