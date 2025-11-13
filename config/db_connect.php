<?php
// Database Configuration for XAMPP
$host = "localhost";          // Host
$user = "root";               // Default XAMPP MySQL user
$password = "";               // Default XAMPP MySQL password (leave empty)
$database = "umoja_sacco_db"; // Make sure this DB exists in phpMyAdmin

// Create connection
$conn = mysqli_connect($host, $user, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
