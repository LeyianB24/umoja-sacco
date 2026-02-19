<?php
declare(strict_types=1);

// Simple test - just output HTML
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <title>Test</title>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>
    <h1>If you see this, PHP is working!</h1>
    <p>Testing employees.php components...</p>
    <?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    echo "<p>Session starting...</p>";
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    echo "<p>Loading includes...</p>";
    require_once __DIR__ . '/../../config/app_config.php';
    require_once __DIR__ . '/../../config/db_connect.php';
    require_once __DIR__ . '/../../inc/Auth.php';
    require_once __DIR__ . '/../../inc/LayoutManager.php';
    require_once __DIR__ . '/../../inc/EmployeeService.php';
    
    echo "<p style='color: green; font-weight: bold;'>✓ All includes loaded successfully!</p>";
    echo "<p>Now testing the actual employees.php file...</p>";
    ?>
</body>
</html>
