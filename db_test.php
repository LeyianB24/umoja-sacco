<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connection successful!\n";

$res = $conn->query("SELECT COUNT(*) FROM members");
if ($res) {
    echo "Member count: " . $res->fetch_row()[0] . "\n";
} else {
    echo "Query failed: " . $conn->error . "\n";
}
?>
