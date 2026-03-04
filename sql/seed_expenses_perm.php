<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../config/db_connect.php';

// Ensure $conn is global
global $conn;

$conn->query("INSERT IGNORE INTO permissions (perm_slug, perm_name, category) VALUES ('manage_expenses', 'Manage System Expenses', 'Finance')");

$res = $conn->query("SELECT perm_id FROM permissions WHERE perm_slug='manage_expenses'");
$row = $res->fetch_assoc();
if ($row) {
    $perm_id = $row['perm_id'];
    $roles = $conn->query("SELECT role_id FROM roles WHERE role_name IN ('Superadmin', 'Accountant', 'Manager')");
    while($r = $roles->fetch_assoc()) {
        $rid = $r['role_id'];
        $conn->query("INSERT IGNORE INTO role_permissions (role_id, perm_id) VALUES ($rid, $perm_id)");
    }
    echo "Permission 'manage_expenses' seeded successfully.\n";
} else {
    echo "Error: Could not retrieve perm_id.\n";
}
?>
