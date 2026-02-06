<?php
require_once __DIR__ . '/config/db_connect.php';

$res = $conn->query("SELECT * FROM admins");
while ($admin = $res->fetch_assoc()) {
    $admin_id = $admin['admin_id'];
    $full_name = $admin['full_name'];
    
    // Check if this admin is already linked to an employee
    $check = $conn->query("SELECT employee_id FROM employees WHERE admin_id = $admin_id");
    if ($check->num_rows == 0) {
        // Not linked. Check if employee exists by name? (Risky)
        // Better: create a new employee record and link it.
        echo "Creating employee record for Admin: $full_name\n";
        $stmt = $conn->prepare("INSERT INTO employees (full_name, national_id, phone, job_title, salary, hire_date, status, admin_id) VALUES (?, ?, ?, ?, ?, NOW(), 'active', ?)");
        $nid = 'ADMIN-' . $admin_id;
        $phone = '';
        $job_title = 'System Administrator';
        $salary = 50000.00; // Default salary
        $stmt->bind_param("ssssdi", $full_name, $nid, $phone, $job_title, $salary, $admin_id);
        $stmt->execute();
    } else {
        echo "Admin $full_name already linked to employee.\n";
    }
}
?>
