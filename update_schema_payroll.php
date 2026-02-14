<?php
require_once __DIR__ . '/config/db_connect.php';

$sql = [
    // 1. Create Salary Grades Table
    "CREATE TABLE IF NOT EXISTS salary_grades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grade_name VARCHAR(50) NOT NULL,
        basic_salary DECIMAL(15,2) DEFAULT 0.00,
        house_allowance DECIMAL(15,2) DEFAULT 0.00,
        transport_allowance DECIMAL(15,2) DEFAULT 0.00,
        risk_allowance DECIMAL(15,2) DEFAULT 0.00,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // 2. Create Payroll Runs Table
    "CREATE TABLE IF NOT EXISTS payroll_runs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        month VARCHAR(7) NOT NULL, -- YYYY-MM
        status ENUM('draft', 'approved', 'paid') DEFAULT 'draft',
        total_gross DECIMAL(15,2) DEFAULT 0.00,
        total_net DECIMAL(15,2) DEFAULT 0.00,
        processed_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_month (month)
    )",

    // 3. Create Statutory Deductions Table
    "CREATE TABLE IF NOT EXISTS statutory_deductions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        type ENUM('percentage', 'fixed', 'bracket') DEFAULT 'percentage',
        value JSON, -- Store brackets or float value
        is_active BOOLEAN DEFAULT TRUE
    )",

    // 4. Alter Employees Table
    "ALTER TABLE employees 
        ADD COLUMN IF NOT EXISTS employee_no VARCHAR(20) UNIQUE AFTER employee_id,
        ADD COLUMN IF NOT EXISTS company_email VARCHAR(100) UNIQUE AFTER phone,
        ADD COLUMN IF NOT EXISTS personal_email VARCHAR(100) AFTER company_email,
        ADD COLUMN IF NOT EXISTS grade_id INT AFTER salary,
        ADD COLUMN IF NOT EXISTS kra_pin VARCHAR(20) AFTER national_id,
        ADD COLUMN IF NOT EXISTS nssf_no VARCHAR(20) AFTER kra_pin,
        ADD COLUMN IF NOT EXISTS nhif_no VARCHAR(20) AFTER nssf_no,
        ADD COLUMN IF NOT EXISTS bank_name VARCHAR(50),
        ADD COLUMN IF NOT EXISTS bank_account VARCHAR(50),
        ADD KEY IF NOT EXISTS idx_grade (grade_id)
    ",

    // 5. Alter Payroll Table
    "ALTER TABLE payroll
        ADD COLUMN IF NOT EXISTS payroll_run_id INT AFTER id,
        ADD COLUMN IF NOT EXISTS gross_pay DECIMAL(15,2) DEFAULT 0.00 AFTER allowances,
        ADD COLUMN IF NOT EXISTS tax_paye DECIMAL(15,2) DEFAULT 0.00 AFTER deductions,
        ADD COLUMN IF NOT EXISTS tax_nssf DECIMAL(15,2) DEFAULT 0.00 AFTER tax_paye,
        ADD COLUMN IF NOT EXISTS tax_nhif DECIMAL(15,2) DEFAULT 0.00 AFTER tax_nssf,
        ADD COLUMN IF NOT EXISTS tax_housing DECIMAL(15,2) DEFAULT 0.00 AFTER tax_nhif,
        ADD KEY IF NOT EXISTS idx_run (payroll_run_id)
    ",

    // 6. Seed Default Deductions (Kenya 2024 roughly)
    "INSERT IGNORE INTO statutory_deductions (name, type, value) VALUES 
    ('NSSF', 'fixed', '200'), 
    ('NHIF', 'bracket', '{\"0\":0, \"5999\":150, \"7999\":300, \"11999\":400, \"14999\":500, \"19999\":600, \"24999\":750, \"29999\":850, \"34999\":900, \"39999\":950, \"44999\":1000, \"49999\":1100, \"59999\":1200, \"69999\":1300, \"79999\":1400, \"89999\":1500, \"99999\":1600, \"100000\":1700}'),
    ('Housing Levy', 'percentage', '1.5'),
    ('PAYE', 'bracket', '{\"24000\":10, \"32333\":25, \"500000\":30, \"800000\":32.5, \"9999999\":35}')"
];

foreach ($sql as $q) {
    if ($conn->query($q) === TRUE) {
        echo "Query executed successfully.\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}
echo "Database schema update complete.";
?>
