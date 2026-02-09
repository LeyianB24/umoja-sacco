<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'umoja_drivers_sacco';

$conn = new mysqli($host, $user, $pass, $db);
$res = $conn->query("SHOW TABLES LIKE 'vehicles'");
if ($res->num_rows > 0) {
    echo "Table exists\n";
    $res2 = $conn->query("DESC vehicles");
    while($row = $res2->fetch_assoc()) echo $row['Field'] . "\n";
} else {
    echo "Table NOT found\n";
}
?>
