<?php
// Minimal test to isolate the error
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Test 1: Basic PHP works\n";

// Test session
session_start();
echo "Test 2: Session started\n";

// Test includes
require_once __DIR__ . '/config/app_config.php';
echo "Test 3: app_config loaded\n";

require_once __DIR__ . '/config/db_connect.php';
echo "Test 4: db_connect loaded\n";

require_once __DIR__ . '/inc/Auth.php';
echo "Test 5: Auth loaded\n";

require_once __DIR__ . '/inc/LayoutManager.php';
echo "Test 6: LayoutManager loaded\n";

require_once __DIR__ . '/inc/EmployeeService.php';
echo "Test 7: EmployeeService loaded\n";

// Test permission (this might fail if not logged in)
try {
    require_permission();
    echo "Test 8: Permission check passed\n";
} catch (Exception $e) {
    echo "Test 8 FAILED: " . $e->getMessage() . "\n";
}

echo "\nAll basic tests passed!\n";
?>
