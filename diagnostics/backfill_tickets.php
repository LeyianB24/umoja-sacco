<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

echo "Starting backfill of support ticket role assignments...\n";

// 1. Get all roles for mapping name to ID
$role_res = $conn->query("SELECT id, name FROM roles");
$role_ids = [];
while ($r = $role_res->fetch_assoc()) {
    $role_ids[$r['name']] = (int)$r['id'];
}

// 2. Process routing map to get role IDs
$routing_map = SUPPORT_ROUTING_MAP;
$category_to_role_id = [];
foreach ($routing_map as $cat => $role_name) {
    if (isset($role_ids[$role_name])) {
        $category_to_role_id[$cat] = $role_ids[$role_name];
    } else {
        // Fallback to Superadmin (ID 1) if name not found
        $category_to_role_id[$cat] = 1;
        echo "Warning: Role '$role_name' not found in database. Using Superadmin for category '$cat'.\n";
    }
}

// 3. Update existing tickets
$tickets_res = $conn->query("SELECT support_id, category FROM support_tickets");
$updated_count = 0;

while ($t = $tickets_res->fetch_assoc()) {
    $tid = $t['support_id'];
    $cat = $t['category'];
    $rid = $category_to_role_id[$cat] ?? 1; // Default to Superadmin

    $stmt = $conn->prepare("UPDATE support_tickets SET assigned_role_id = ? WHERE support_id = ?");
    $stmt->bind_param("ii", $rid, $tid);
    if ($stmt->execute()) {
        $updated_count++;
    }
}

echo "Successfully updated $updated_count tickets.\n";
echo "Backfill complete.\n";
