<?php
// usms/public/phpinfo.php

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. SECURITY CHECK
// Only allow IT Admins ('admin') and Superadmins to view server config
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("HTTP/1.1 403 Forbidden");
    die("<h3>⛔ Access Denied</h3><p>You do not have permission to view server configuration.</p><a href='login.php'>Go to Login</a>");
}

// 2. Render Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHP Configuration</title>
    <style>
        body { margin: 0; padding: 0; }
        
        /* Floating Back Button */
        .back-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #dc3545;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-weight: bold;
            border-radius: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            z-index: 99999;
            transition: transform 0.2s;
        }
        .back-btn:hover {
            transform: scale(1.05);
            background-color: #bb2d3b;
        }
    </style>
</head>
<body>

    <a href="../admin/dashboard.php" class="back-btn">← Return to Console</a>

    <?php phpinfo(); ?>

</body>
</html>