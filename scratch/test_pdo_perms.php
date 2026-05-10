<?php
include 'config/app.php';
try {
    $db = \USMS\Database\Database::getInstance()->getPdo();
    echo "PDO Connection: SUCCESS\n";
    
    $role_id = 1;
    $sql = "SELECT p.slug FROM permissions p 
            JOIN role_permissions rp ON p.id = rp.permission_id 
            WHERE rp.role_id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$role_id]);
    $perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Permissions found for role 1: " . count($perms) . "\n";
    
    // Superadmin override check
    $all = $db->query("SELECT slug FROM permissions");
    $total_perms = $all->rowCount();
    echo "Total system permissions: " . $total_perms . "\n";

} catch (Exception $e) {
    echo "PDO Error: " . $e->getMessage() . "\n";
}
?>
