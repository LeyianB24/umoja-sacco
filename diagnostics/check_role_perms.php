<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

$res = $conn->query("SELECT r.name as role, p.slug FROM roles r JOIN role_permissions rp ON r.id = rp.role_id JOIN permissions p ON rp.permission_id = p.id ORDER BY r.name");
$roles = [];
while ($row = $res->fetch_assoc()) {
    $roles[$row['role']][] = $row['slug'];
}

foreach ($roles as $role => $perms) {
    echo "ROLE: $role\n";
    foreach ($perms as $p) {
        echo "  - $p\n";
    }
}
