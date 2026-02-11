<?php
require_once __DIR__ . '/config/db_connect.php';

function columnExists($conn, $table, $column) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res->num_rows > 0;
}

// 1. Link Admins to Employees
if (!columnExists($conn, 'employees', 'admin_id')) {
    $conn->query("ALTER TABLE employees ADD COLUMN admin_id INT NULL DEFAULT NULL");
    echo "Added admin_id to employees.\n";
}

// 2. Add Sale/Disposal info to Investments
if (!columnExists($conn, 'investments', 'sale_date')) {
    $conn->query("ALTER TABLE investments ADD COLUMN sale_date DATE NULL DEFAULT NULL");
    $conn->query("ALTER TABLE investments ADD COLUMN sale_price DECIMAL(15,2) NULL DEFAULT NULL");
    $conn->query("ALTER TABLE investments ADD COLUMN sale_reason TEXT NULL DEFAULT NULL");
    $conn->query("ALTER TABLE investments ADD COLUMN disposal_status ENUM('active', 'sold', 'written_off') DEFAULT 'active'");
    echo "Added disposal columns to investments.\n";
}

// 3. Create Payroll Table
$conn->query("CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month VARCHAR(10) NOT NULL, -- Format: YYYY-MM
    year INT NOT NULL,
    basic_salary DECIMAL(15,2) NOT NULL,
    allowances DECIMAL(15,2) DEFAULT 0.00,
    deductions DECIMAL(15,2) DEFAULT 0.00,
    net_pay DECIMAL(15,2) NOT NULL,
    payment_date DATE NOT NULL,
    status ENUM('pending', 'paid') DEFAULT 'paid',
    transaction_id INT NULL, -- Link to gold ledger
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
)");
echo "Payroll table checked/created.\n";
?>
