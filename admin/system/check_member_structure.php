<?php
require_once __DIR__ . '/../../config/db_connect.php';

echo "Members table columns:\n";
$result = $conn->query("SHOW COLUMNS FROM members");
while($field = $result->fetch_assoc()) {
    echo $field['Field'] . "\n";
}

echo "\n\nContributions table columns:\n";
$result = $conn->query("SHOW COLUMNS FROM contributions");
while($field = $result->fetch_assoc()) {
    echo $field['Field'] . "\n";
}
?>
