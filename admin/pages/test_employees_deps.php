<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing employees.php dependencies...\n\n";

// Test 1: Check if files exist
$files = [
    'app_config' => __DIR__ . '/../../config/app_config.php',
    'db_connect' => __DIR__ . '/../../config/db_connect.php',
    'Auth' => __DIR__ . '/../../inc/Auth.php',
    'LayoutManager' => __DIR__ . '/../../inc/LayoutManager.php',
    'HRService' => __DIR__ . '/../../inc/HRService.php',
    'SystemUserService' => __DIR__ . '/../../inc/SystemUserService.php',
    'PayrollService' => __DIR__ . '/../../inc/PayrollService.php'
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✓ $name exists\n";
    } else {
        echo "✗ $name MISSING at $path\n";
    }
}

echo "\n\nTesting includes...\n";

try {
    require_once __DIR__ . '/../../config/app_config.php';
    echo "✓ app_config loaded\n";
} catch (Exception $e) {
    echo "✗ app_config error: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/../../config/db_connect.php';
    echo "✓ db_connect loaded\n";
} catch (Exception $e) {
    echo "✗ db_connect error: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/../../inc/Auth.php';
    echo "✓ Auth loaded\n";
} catch (Exception $e) {
    echo "✗ Auth error: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/../../inc/HRService.php';
    echo "✓ HRService loaded\n";
} catch (Exception $e) {
    echo "✗ HRService error: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/../../inc/SystemUserService.php';
    echo "✓ SystemUserService loaded\n";
} catch (Exception $e) {
    echo "✗ SystemUserService error: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/../../inc/PayrollService.php';
    echo "✓ PayrollService loaded\n";
} catch (Exception $e) {
    echo "✗ PayrollService error: " . $e->getMessage() . "\n";
}

echo "\n\nTest complete!\n";
?>
