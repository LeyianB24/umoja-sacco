<?php
require "config/app.php";
// Check the superadmin password
$r = $conn->query("SELECT admin_id, username, password, role_id FROM admins WHERE username='superadmin'");
$a = $r->fetch_assoc();
echo "superadmin found\n";
echo "password hash: " . substr($a['password'], 0, 30) . "...\n";
echo "password_verify('admin123'): " . (password_verify('admin123', $a['password']) ? 'CORRECT' : 'WRONG') . "\n";
echo "role_id: " . $a['role_id'] . "\n";

// Check roles table
$r2 = $conn->query("SELECT * FROM roles WHERE role_id=" . (int)$a['role_id']);
if ($r2->num_rows > 0) {
    $role = $r2->fetch_assoc();
    echo "role name: " . $role['role_name'] . "\n";
    echo "role slug: " . ($role['slug'] ?? $role['role_slug'] ?? 'N/A') . "\n";
} else {
    echo "role NOT FOUND for role_id=" . $a['role_id'] . "\n";
    $r3 = $conn->query("DESCRIBE roles");
    echo "Roles columns: ";
    while($row = $r3->fetch_assoc()) echo $row['Field'] . " ";
    echo "\n";
}
