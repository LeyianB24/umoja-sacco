<?php
/**
 * Create Missing Tables for Employee Architecture
 */
require_once __DIR__ . '/config/db_connect.php';

echo "Creating Missing Tables...\n\n";

// 1. Create job_role_mapping if missing
$sql1 = "CREATE TABLE IF NOT EXISTS job_role_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_title VARCHAR(100) NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_job_role (job_title, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql1)) {
    echo "✓ job_role_mapping table ready\n";
} else {
    echo "✗ Error creating job_role_mapping: " . $conn->error . "\n";
}

// 2. Create payslips table if missing
$sql2 = "CREATE TABLE IF NOT EXISTS payslips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_item_id INT NOT NULL,
    employee_id INT NOT NULL,
    period VARCHAR(7) NOT NULL,
    pdf_path VARCHAR(255),
    email_sent BOOLEAN DEFAULT FALSE,
    email_sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_period (period),
    INDEX idx_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql2)) {
    echo "✓ payslips table ready\n";
} else {
    echo "✗ Error creating payslips: " . $conn->error . "\n";
}

// 3. Seed job_role_mapping if empty
$check = $conn->query("SELECT COUNT(*) as cnt FROM job_role_mapping")->fetch_assoc()['cnt'];
if ($check == 0) {
    echo "\nSeeding job_role_mapping...\n";
    $mappings = [
        ['General Manager', 1],
        ['Accountant', 3],
        ['Loan Officer', 4],
        ['Teller', 5],
        ['IT Officer', 2],
        ['HR Officer', 2]
    ];
    
    foreach ($mappings as $map) {
        $stmt = $conn->prepare("INSERT IGNORE INTO job_role_mapping (job_title, role_id) VALUES (?, ?)");
        $stmt->bind_param("si", $map[0], $map[1]);
        if ($stmt->execute()) {
            echo "  ✓ Mapped {$map[0]} → Role {$map[1]}\n";
        }
    }
}

echo "\n=================================\n";
echo "Schema Update Complete!\n";
echo "=================================\n";

$conn->close();
?>
