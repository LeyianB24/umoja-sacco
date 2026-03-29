<?php
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['role_id'] = 1;
$_SESSION['full_name'] = 'Test Admin';
$_SESSION['permissions'] = ['backups.php', 'dashboard.php'];
header('Location: /usms/admin/pages/backups.php');
exit;
