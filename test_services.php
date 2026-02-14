<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "1. Loading config...\n";
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/config/db_connect.php';

echo "2. Loading Services...\n";
try {
    require_once __DIR__ . '/inc/HRService.php';
    echo "HRService loaded.\n";
    
    require_once __DIR__ . '/inc/SystemUserService.php';
    echo "SystemUserService loaded.\n";
    
    require_once __DIR__ . '/inc/PayrollService.php';
    echo "PayrollService loaded.\n";
} catch (Throwable $e) {
    echo "FATAL ERROR Loading Services: " . $e->getMessage() . "\n";
    exit;
}

echo "3. Instantiating Services...\n";
try {
    $db = $conn;
    $hr = new HRService($db);
    echo "HRService instantiated.\n";
    
    $sys = new SystemUserService($db);
    echo "SystemUserService instantiated.\n";
    
    $pay = new PayrollService($db);
    echo "PayrollService instantiated.\n";
} catch (Throwable $e) {
    echo "FATAL ERROR Instantiating: " . $e->getMessage() . "\n";
    exit;
}

echo "4. Testing HRService::generateEmployeeNo...\n";
try {
    echo "Next Emp No: " . $hr->generateEmployeeNo() . "\n";
} catch (Throwable $e) {
    echo "Error generating emp no: " . $e->getMessage() . "\n";
}

echo "Done.\n";
?>
