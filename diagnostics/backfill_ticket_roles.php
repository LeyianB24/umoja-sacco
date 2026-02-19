<?php
/**
 * Backfill: Re-assign existing support tickets based on corrected SUPPORT_ROUTING_MAP
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

echo "=== Backfilling Support Ticket Role Assignments ===\n\n";

$routing_map = SUPPORT_ROUTING_MAP;
$updated = 0;
$errors = 0;

// Build role name => id lookup
$role_lookup = [];
$res = $conn->query("SELECT id, name FROM roles");
while ($r = $res->fetch_assoc()) {
    $role_lookup[$r['name']] = (int)$r['id'];
}

echo "Roles found: " . implode(', ', array_map(fn($n, $id) => "$n ($id)", array_keys($role_lookup), $role_lookup)) . "\n\n";

// Fetch all tickets
$tickets = $conn->query("SELECT support_id, category, assigned_role_id FROM support_tickets");

while ($t = $tickets->fetch_assoc()) {
    $category = $t['category'];
    $target_role_name = $routing_map[$category] ?? 'superadmin';
    $correct_role_id = $role_lookup[$target_role_name] ?? 1;

    if ((int)$t['assigned_role_id'] !== $correct_role_id) {
        $stmt = $conn->prepare("UPDATE support_tickets SET assigned_role_id = ? WHERE support_id = ?");
        $stmt->bind_param("ii", $correct_role_id, $t['support_id']);
        if ($stmt->execute()) {
            echo "  [FIXED] Ticket #{$t['support_id']} ({$category}): role {$t['assigned_role_id']} → {$correct_role_id} ({$target_role_name})\n";
            $updated++;
        } else {
            echo "  [ERROR] Ticket #{$t['support_id']}: " . $conn->error . "\n";
            $errors++;
        }
    } else {
        echo "  [OK]    Ticket #{$t['support_id']} ({$category}): already correct (role {$correct_role_id})\n";
    }
}

echo "\n=== Done. Updated: $updated | Errors: $errors ===\n";
