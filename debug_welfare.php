<?php
@session_start();
$_SESSION['member_id'] = 1;
$_SESSION['role'] = 'member';
$_SESSION['member_name'] = 'Test User';

// Mock DB connection if needed or just require the file
require 'config/db_connect.php';
require 'member/pages/welfare.php';
?>
