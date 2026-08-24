<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment configuration
require_once __DIR__ . '/bootstrap.php';

use USMS\Config\EnvLoader;

// Get database credentials from environment variables
// Priority: MYSQL_* / MYSQL_* with underscore (Railway/Cloud) > DB_* (.env.local) > defaults (XAMPP local dev)
$host = EnvLoader::getAny(['MYSQLHOST', 'MYSQL_HOST'], 'localhost');
$port = EnvLoader::getAny(['MYSQLPORT', 'MYSQL_PORT'], '3306');
$user = EnvLoader::getAny(['MYSQLUSER', 'MYSQL_USER'], 'root');
$pass = EnvLoader::getAny(['MYSQLPASSWORD', 'MYSQL_PASSWORD'], '');
$db   = EnvLoader::getAny(['MYSQLDATABASE', 'MYSQL_DATABASE'], 'umoja_drivers_sacco');
$conn = new mysqli($host, $user, $pass, $db, (int)$port);


// Check connection
if ($conn->connect_errno) {
    $app_env = EnvLoader::get('APP_ENV', 'production');
    if ($app_env === 'development') {
        die("Database connection failed: " . $conn->connect_error);
    } else {
        die("Error connecting to system database. Please try again later.");
    }
}

// Set default charset
$conn->set_charset(EnvLoader::get('DB_CHARSET', 'utf8mb4'));
