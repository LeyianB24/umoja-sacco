<?php
// Database Configuration
$host = "localhost";          // MySQL host
$user = "root";               // Default XAMPP MySQL user
$password = "";               // Default XAMPP MySQL password is empty
$database = "umoja_sacco_db"; // Database name

// Create Database Connection
$conn = mysqli_connect($host, $user, $password, $database);

// Check Connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
