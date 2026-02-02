<?php
// usms/public/logout.php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/Auth.php';

// If we are using the new Auth engine
if (class_exists('Auth')) {
    Auth::logout(BASE_URL . '/public/login.php');
} else {
    // Fallback if Auth class is not available for some reason
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
