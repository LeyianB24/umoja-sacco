<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script><title>Test</title>
    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head><body>";
echo "<h1>Testing Employee Page Components</h1>";

try {
    echo "<p>1. Starting session...</p>";
    if (session_status() === PHP_SESSION_NONE) session_start();
    echo "<p>✓ Session started</p>";
    
    echo "<p>2. Loading app_config...</p>";
    require_once __DIR__ . '/../../config/app_config.php';
    echo "<p>✓ app_config loaded</p>";
    
    echo "<p>3. Loading db_connect...</p>";
    require_once __DIR__ . '/../../config/db_connect.php';
    echo "<p>✓ db_connect loaded</p>";
    
    echo "<p>4. Loading Auth...</p>";
    require_once __DIR__ . '/../../inc/Auth.php';
    echo "<p>✓ Auth loaded</p>";
    
    echo "<p>5. Loading LayoutManager...</p>";
    require_once __DIR__ . '/../../inc/LayoutManager.php';
    echo "<p>✓ LayoutManager loaded</p>";
    
    echo "<p>6. Loading EmployeeService...</p>";
    require_once __DIR__ . '/../../inc/EmployeeService.php';
    echo "<p>✓ EmployeeService loaded</p>";
    
    echo "<p>7. Checking permission...</p>";
    // Skip permission check for now
    // require_permission();
    echo "<p>⚠ Permission check skipped</p>";
    
    echo "<p>8. Creating LayoutManager...</p>";
    $layout = LayoutManager::create('admin');
    echo "<p>✓ LayoutManager created</p>";
    
    echo "<p>9. Creating EmployeeService...</p>";
    $db = $conn;
    $svc = new EmployeeService($db);
    echo "<p>✓ EmployeeService created</p>";
    
    echo "<h2 style='color: green;'>✓ All components loaded successfully!</h2>";
    echo "<p>The issue is NOT with the includes or class definitions.</p>";
    echo "<p>The problem must be in the HTML/PHP rendering section of employees.php</p>";
    
} catch (Throwable $e) {
    echo "<h2 style='color: red;'>✗ Error Found!</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
?>
