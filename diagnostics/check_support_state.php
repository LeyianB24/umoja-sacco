<?php
require_once __DIR__ . '/../config/db_connect.php';

$r = $conn->query('DESCRIBE messages');
echo "=== MESSAGES TABLE ===\n";
while($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Null'] . ' | ' . $row['Default'] . "\n";
}
