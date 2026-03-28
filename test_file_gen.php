<?php
require_once 'config/app.php';
$admin_name = 'Test Runner';
echo "--- Testing SQL File Generation ---\n";

$filename = "USMS_Backup_TEST_" . date('Y-m-d_His') . ".sql";
$sql_dump = "-- Test Backup Content\nINSERT INTO tests VALUES (1);";

$backup_path = BASE_PATH . '/backups/' . $filename;
file_put_contents($backup_path, $sql_dump);

if (file_exists($backup_path)) {
    echo "SUCCESS: File created at $backup_path\n";
    unlink($backup_path); // clean up
    echo "Cleanup done.\n";
} else {
    echo "FAILED: File not created at $backup_path\n";
}
