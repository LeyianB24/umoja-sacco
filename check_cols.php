<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'umoja_drivers_sacco';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed");

$res = $conn->query("DESC investments");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
