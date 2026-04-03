<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SHOW TABLES LIKE 'shares'");
if ($res->num_rows > 0) {
    $res = $conn->query("SHOW COLUMNS FROM shares");
    while($row = $res->fetch_assoc()) echo $row['Field'] . ' ' . $row['Type'] . "\n";
} else {
    echo "Table 'shares' does not exist.\n";
}
$res = $conn->query("SHOW COLUMNS FROM member_shareholdings");
while($row = $res->fetch_assoc()) echo "member_shareholdings: " . $row['Field'] . ' ' . $row['Type'] . "\n";
