<?php
define('APP_ENV', 'development');
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['role_id'] = 1; // Superadmin
$_SESSION['full_name'] = 'Test Admin';
$_SESSION['permissions'] = ['backups.php']; // Mock permission
$_SERVER['PHP_SELF'] = '/usms/admin/pages/backups.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'create_backup';

// Include the script
require_once __DIR__ . '/admin/pages/backups.php';
