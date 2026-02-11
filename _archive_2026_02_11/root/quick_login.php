<?php
session_start();
require_once 'config/db_connect.php';

echo "=== QUICK ADMIN LOGIN ===" . PHP_EOL;

// Get the superadmin user
$result = $conn->query("SELECT admin_id, username, full_name, role_id FROM admins WHERE admin_id = 1");
if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    
    // Set session variables exactly like the real login system
    $_SESSION['admin_id'] = $admin['admin_id'];
    $_SESSION['username'] = $admin['username'];
    $_SESSION['full_name'] = $admin['full_name'];
    $_SESSION['role_id'] = $admin['role_id'];
    
    // Load permissions
    require_once 'inc/Auth.php';
    Auth::loadPermissions($admin['role_id']);
    
    echo "Login successful!" . PHP_EOL;
    echo "Admin: {$admin['full_name']}" . PHP_EOL;
    echo "Session ID: " . session_id() . PHP_EOL;
    echo "Permissions loaded: " . count($_SESSION['permissions']) . PHP_EOL;
    
    echo PHP_EOL . "You can now access:" . PHP_EOL;
    echo "1. http://localhost/usms/admin/pages/expenses.php" . PHP_EOL;
    echo "2. http://localhost/usms/admin/pages/loans_payouts.php" . PHP_EOL;
    
    // Redirect to expenses page
    echo PHP_EOL . "Redirecting to expenses page..." . PHP_EOL;
    header('Location: admin/pages/expenses.php');
    exit;
    
} else {
    echo "No admin found!" . PHP_EOL;
}
?>
