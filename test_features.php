<?php
require_once 'config/app.php';
// Simulate Admin Session
session_start();
$_SESSION['admin_id'] = 1; 

echo "--- Testing AJAX Audit Feed ---\n";
$_GET['since_id'] = 0;
ob_start();
include 'admin/pages/ajax_audit_feed.php';
$output = ob_get_clean();
$data = json_encode(json_decode($output), JSON_PRETTY_PRINT);
echo "Feed Output (Head):\n" . substr($data, 0, 500) . "...\n";

echo "\n--- Testing Backup Handler (Simulated POST) ---\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'create_backup';
$_SESSION['full_name'] = 'System Tester';

ob_start();
// We skip the headers sent issues in terminal
try {
    include 'admin/pages/backups.php';
} catch (Exception $e) { echo "Caught: " . $e->getMessage(); }
$output = ob_get_clean();

if (str_contains($output, 'INSERT INTO') && str_contains($output, 'CREATE TABLE')) {
    echo "SUCCESS: Backup output contains SQL structure and data.\n";
    echo "Backup Size: " . strlen($output) . " bytes\n";
    echo "Sample Output:\n" . substr($output, 0, 300) . "...\n";
} else {
    echo "FAILED: Backup output missing SQL statements.\n";
    echo "Output: " . substr($output, 0, 500) . "\n";
}
