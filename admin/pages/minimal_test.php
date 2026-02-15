<?php
// Minimal test - just load the page and catch any errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "Starting minimal employees test...\n\n";

// Simulate being logged in
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['role'] = 'superadmin';
$_SESSION['role_id'] = 1;

echo "Session set\n";

try {
    require_once __DIR__ . '/../../config/app_config.php';
    echo "✓ app_config loaded\n";
    
    require_once __DIR__ . '/../../config/db_connect.php';
    echo "✓ db_connect loaded\n";
    
    require_once __DIR__ . '/../../inc/Auth.php';
    echo "✓ Auth loaded\n";
    
    require_once __DIR__ . '/../../inc/LayoutManager.php';
    echo "✓ LayoutManager loaded\n";
    
    require_once __DIR__ . '/../../inc/HRService.php';
    echo "✓ HRService loaded\n";
    
    require_once __DIR__ . '/../../inc/SystemUserService.php';
    echo "✓ SystemUserService loaded\n";
    
    require_once __DIR__ . '/../../inc/PayrollService.php';
    echo "✓ PayrollService loaded\n";
    
    // Try to instantiate services
    $hrService = new HRService($conn);
    echo "✓ HRService instantiated\n";
    
    $systemUserService = new SystemUserService($conn);
    echo "✓ SystemUserService instantiated\n";
    
    $payrollService = new PayrollService($conn);
    echo "✓ PayrollService instantiated\n";
    
    echo "\n✓✓✓ ALL TESTS PASSED ✓✓✓\n";
    
} catch (Throwable $e) {
    echo "\n✗✗✗ ERROR ✗✗✗\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>
