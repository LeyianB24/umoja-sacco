<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

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
    echo "Permission 'manage_expenses' seeded successfully with direct connection.\n";
}
?>
