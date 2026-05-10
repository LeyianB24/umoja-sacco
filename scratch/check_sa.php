<?php
include 'config/app.php';
$r = $conn->query("SELECT admin_id, username, role_id FROM admins WHERE username='superadmin'");
var_dump($r->fetch_assoc());
?>
