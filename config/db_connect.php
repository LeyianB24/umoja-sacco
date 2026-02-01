<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host   = 'localhost';                 // XAMPP default server
$user   = 'root';                      // XAMPP default username
$pass   = '';                          // XAMPP default password is empty
$dbname = 'umoja_drivers_sacco';       // Your database name 

$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_errno) {
    if (defined('APP_ENV') && APP_ENV === 'development') {
        die("Database connection failed: " . $conn->connect_error);
    } else {
        die("Error connecting to system database. Please try again later.");
    }
}

// Set default charset
$conn->set_charset('utf8mb4');
