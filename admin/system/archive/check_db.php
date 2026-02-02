<?php
require_once __DIR__ . '/config/db_connect.php';
$tables = ['roles', 'role_permissions', 'permissions', 'admin_roles'];
foreach ($tables as $t) {
    if ($conn->query("SHOW TABLES LIKE '$t'")->num_rows > 0) echo "FOUND: $t\n";
    else echo "MISSING: $t\n";
}
?>
