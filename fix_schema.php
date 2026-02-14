<?php
require_once 'config/db_connect.php';

// disable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$conn->query("DROP TABLE IF EXISTS payroll");
$conn->query("DROP TABLE IF EXISTS payroll_runs");

$sqlRuns = "CREATE TABLE payroll_runs (
    payroll_run_id INT AUTO_INCREMENT PRIMARY KEY,
    run_date DATE NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    run_name VARCHAR(100),
    status ENUM('draft', 'approved', 'paid') DEFAULT 'draft',
    created_by INT,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sqlRuns)) {
    echo "✅ payroll_runs table recreated successfully.\n";
} else {
    echo "❌ Error recreating payroll_runs: " . $conn->error . "\n";
}

$sqlPayroll = "CREATE TABLE payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_run_id INT NOT NULL,
    employee_id INT NOT NULL,
    basic_salary DECIMAL(15,2),
    paye DECIMAL(15,2),
    nssf DECIMAL(15,2),
    nhif DECIMAL(15,2),
    housing_levy DECIMAL(15,2),
    net_salary DECIMAL(15,2),
    status ENUM('draft', 'approved', 'paid') DEFAULT 'draft',
    paid_at TIMESTAMP NULL,
    transaction_id INT, -- Link to ledger transaction
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(payroll_run_id),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
)";

if ($conn->query($sqlPayroll)) {
    echo "✅ payroll table recreated successfully.\n";
} else {
    echo "❌ Error recreating payroll: " . $conn->error . "\n";
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1");
?>
