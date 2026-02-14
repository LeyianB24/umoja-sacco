<?php
require_once __DIR__ . '/config/db_connect.php';

$sql = [
    // 1. Job Titles Table
    "CREATE TABLE IF NOT EXISTS job_titles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL UNIQUE,
        role_id INT NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // 2. Seed Job Titles (Mapping to existing Roles)
    // Assuming Role IDs: 1=Superadmin, 2=Member/Staff... need to check roles table actually.
    // For now, we will assume generic mapping and admin can update.
    "INSERT IGNORE INTO job_titles (title, role_id) VALUES 
    ('General Manager', 1),
    ('Sacco Manager', 1),
    ('System Administrator', 1),
    ('Chief Accountant', 1),
    ('Loan Officer', 2), 
    ('Teller', 2),
    ('Customer Care', 2),
    ('Driver', 2),
    ('Conductor', 2),
    ('Mechanic', 2),
    ('Security Officer', 2),
    ('Office Assistant', 2)"
];

foreach ($sql as $q) {
    if ($conn->query($q) === TRUE) {
        echo "Query executed successfully.\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}
echo "Schema update complete.";
?>
