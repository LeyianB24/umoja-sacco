<?php
require_once __DIR__ . '/../../config/db_connect.php';

echo "Tables with 'saving' in name:\n";
$result = $conn->query("SHOW TABLES LIKE '%saving%'");
while($table = $result->fetch_array()) {
    echo $table[0] . "\n";
}

echo "\nTables with 'share' in name:\n";
$result = $conn->query("SHOW TABLES LIKE '%share%'");
while($table = $result->fetch_array()) {
    echo $table[0] . "\n";
}

echo "\nTables with 'welfare' in name:\n";
$result = $conn->query("SHOW TABLES LIKE '%welfare%'");
while($table = $result->fetch_array()) {
    echo $table[0] . "\n";
}
?>
