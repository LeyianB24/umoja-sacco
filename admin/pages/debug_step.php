<?php
// admin/pages/debug_step.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Step 1: Start...<br>";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "Step 2: Session Started...<br>";
}

echo "Step 3: Loading Config...<br>";
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
echo "Step 3: Config Loaded.<br>";

echo "Step 4: Loading Auth...<br>";
require_once __DIR__ . '/../../inc/Auth.php';
echo "Step 4: Auth Loaded.<br>";

echo "Step 5: Testing ksh()...<br>";
if (function_exists('ksh')) {
    echo "ksh() exists: " . ksh(500) . "<br>";
} else {
    echo "ksh() MISSING!<br>";
}

echo "Step 6: Loading LayoutManager...<br>";
require_once __DIR__ . '/../../inc/LayoutManager.php';
echo "Step 6: LayoutManager Loaded.<br>";

echo "Step 7: Creating Layout...<br>";
$layout = LayoutManager::create('admin');
echo "Step 7: Layout Object Created.<br>";

echo "Step 8: Loading ReportGenerator...<br>";
require_once __DIR__ . '/../../inc/ReportGenerator.php';
echo "Step 8: ReportGenerator Loaded.<br>";

echo "Step 9: Loading Autoload...<br>";
require_once __DIR__ . '/../../vendor/autoload.php';
echo "Step 9: Autoload Loaded.<br>";

echo "Step 10: Checking Permission (Simulated)...<br>";
// We won't call the redirecting one to avoid redirect loop, just check logic
if (class_exists('Auth')) {
    echo "Auth Class exists.<br>";
    // Simulate require_permission logic without redirect
    $slug = basename($_SERVER['PHP_SELF']);
    echo "Slug: $slug<br>";
    // Auth::requireAdmin(); // This might redirect
    echo "Skipping actual requireAdmin() to prevent redirect during debug.<br>";
}

echo "Step 11: DB Query Check...<br>";
if (isset($conn)) {
    echo "DB Connection exists.<br>";
} else {
    echo "DB Connection MISSING.<br>";
}

echo "FINAL: Reached End of Script.";
?>
