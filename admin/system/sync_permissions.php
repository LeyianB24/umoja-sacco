<?php
/**
 * admin/system/sync_permissions.php
 * Scans admin/pages/ and auto-registers them as permissions.
 */

require_once __DIR__ . '/../../config/db_connect.php';

echo "Scanning for permissions...\n";

$pagesDir = __DIR__ . '/../pages';
$files = glob($pagesDir . '/*.php');

$added = 0;

foreach ($files as $file) {
    $slug = basename($file);
    
    // Check if exists
    $check = $conn->query("SELECT id FROM permissions WHERE slug = '$slug'");
    if ($check->num_rows == 0) {
        $name = ucwords(str_replace(['_', '.php'], [' ', ''], $slug));
        $desc = "Access to $name page";
        
        $stmt = $conn->prepare("INSERT INTO permissions (name, slug, description, category) VALUES (?, ?, ?, 'system')");
        $stmt->bind_param("sss", $name, $slug, $desc);
        $stmt->execute();
        $added++;
        echo "Added: $slug\n";
    }
}

echo "Scan complete. Added $added new permissions.\n";
echo "Please go to Roles & Permissions to assign them.\n";
?>
