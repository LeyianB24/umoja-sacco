<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment configuration
require_once __DIR__ . '/bootstrap.php';

use USMS\Config\EnvLoader;

// Get database credentials from environment variables
// Priority: MYSQL_* (Railway) > DB_* (.env.local) > defaults (XAMPP local dev)
$host   = EnvLoader::get('MYSQLHOST', EnvLoader::get('DB_HOST', 'localhost'));
$user   = EnvLoader::get('MYSQLUSER', EnvLoader::get('DB_USER', 'root'));
$pass   = EnvLoader::get('MYSQLPASSWORD', EnvLoader::get('DB_PASS', ''));
$port   = EnvLoader::get('MYSQLPORT', EnvLoader::get('DB_PORT', '3306'));
$dbname = EnvLoader::get('MYSQLDATABASE', EnvLoader::get('DB_NAME', 'umoja_drivers_sacco'));

$conn = new mysqli($host, $user, $pass, $dbname, (int)$port);


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
