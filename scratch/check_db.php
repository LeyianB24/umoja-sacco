<?php
include 'config/app.php';
$r = $conn->query("DESCRIBE members");
echo "Table: members\n";
while($row = $r->fetch_assoc()) {
    echo "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']}\n";
}
?>
