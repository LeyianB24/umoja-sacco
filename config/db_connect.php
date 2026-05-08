<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment configuration
require_once __DIR__ . '/bootstrap.php';

use USMS\Config\EnvLoader;

// Get database credentials from environment variables
$host   = EnvLoader::get('DB_HOST', 'localhost');
$user   = EnvLoader::get('DB_USER', 'root');
$pass   = EnvLoader::get('DB_PASS', '');
$port   = EnvLoader::get('DB_PORT', 3306);
$dbname = EnvLoader::get('DB_NAME', 'umoja_drivers_sacco');

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
