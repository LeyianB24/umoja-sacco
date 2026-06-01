<?php
/**
 * admin/system/sync_permissions.php
 * Scans admin/pages/ and auto-registers them as permissions.
 */

require_once __DIR__ . '/../../config/app.php';

echo "Scanning for permissions...\n";

$pagesDir = __DIR__ . '/../pages';
$files = glob($pagesDir . '/*.php');

$added = 0;

foreach ($files as $file) {
    $slug = basename($file);
    
    // Check if exists (use prepared to avoid injection if filenames are unexpected)
    $stmt_check = $conn->prepare("SELECT id FROM permissions WHERE slug = ?");
    $stmt_check->bind_param('s', $slug);
    $stmt_check->execute();
    $check = $stmt_check->get_result();
    if ($check->num_rows == 0) {
        $name = ucwords(str_replace(['_', '.php'], [' ', ''], $slug));
        $desc = "Access to $name page";
        
        $stmt = $conn->prepare("INSERT INTO permissions (name, slug, description, category) VALUES (?, ?, ?, 'system')");
        $stmt->bind_param("sss", $name, $slug, $desc);
        $stmt->execute();
        $added++;
        echo "Added: $slug\n";
        $stmt->close();
    }
    $stmt_check->close();
}

echo "Scan complete. Added $added new permissions.\n";
echo "Please go to Roles & Permissions to assign them.\n";
?>
