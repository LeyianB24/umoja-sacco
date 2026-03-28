<?php
require_once 'config/app.php';
$_SESSION['admin_id'] = 1; 
$_SESSION['full_name'] = 'System Tester';

echo "--- Testing Backup Persistence ---\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'create_backup';

ob_start();
try {
    include 'admin/pages/backups.php';
} catch (Exception $e) { echo "Caught: " . $e->getMessage(); }
$output = ob_get_clean();

// Check if file exists in backups/
$files = glob('backups/USMS_Backup_*.sql');
if (!empty($files)) {
    echo "SUCCESS: Backup file found in backups/ directory: " . $files[0] . "\n";
    echo "File Size: " . filesize($files[0]) . " bytes\n";
} else {
    echo "FAILED: No backup file found in backups/ directory.\n";
}
