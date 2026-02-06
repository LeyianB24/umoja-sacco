<?php
session_start();

// Create admin session
require_once __DIR__ . '/../config/db_connect.php';

// Get superadmin user
$result = $conn->query("SELECT admin_id, username, full_name, role_id FROM admins WHERE admin_id = 1");
if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    
    // Set session variables
    $_SESSION['admin_id'] = $admin['admin_id'];
    $_SESSION['username'] = $admin['username'];
    $_SESSION['full_name'] = $admin['full_name'];
    $_SESSION['role_id'] = $admin['role_id'];
    
    // Load permissions
    require_once __DIR__ . '/../inc/Auth.php';
    Auth::loadPermissions($admin['role_id']);
    
    echo "<h2>✅ Admin Session Created Successfully!</h2>";
    echo "<p><strong>Admin:</strong> {$admin['full_name']}</p>";
    echo "<p><strong>Username:</strong> {$admin['username']}</p>";
    echo "<p><strong>Role ID:</strong> {$admin['role_id']}</p>";
    echo "<p><strong>Permissions:</strong> " . count($_SESSION['permissions']) . " loaded</p>";
    echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
    
    echo "<hr>";
    echo "<h3>🚀 Now you can access the pages:</h3>";
    echo "<ul>";
    echo "<li><a href='pages/loans_payouts.php' target='_blank'><strong>Loans Management</strong></a></li>";
    echo "<li><a href='pages/expenses.php' target='_blank'><strong>Expense Management</strong></a></li>";
    echo "</ul>";
    
    echo "<hr>";
    echo "<h3>🔍 Debug Information:</h3>";
    echo "<p>Both pages now have debug information showing:</p>";
    echo "<ul>";
    echo "<li>Session status and ID</li>";
    echo "<li>Admin authentication status</li>";
    echo "<li>Database query results</li>";
    echo "<li>Data retrieval counts</li>";
    echo "</ul>";
    
    echo "<script>
        setTimeout(() => {
            window.open('pages/loans_payouts.php', '_blank');
            window.open('pages/expenses.php', '_blank');
        }, 2000);
    </script>";
    
} else {
    echo "<h2>❌ No admin found!</h2>";
}
?>
