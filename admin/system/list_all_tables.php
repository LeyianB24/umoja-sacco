<?php
require_once __DIR__ . '/../../config/app.php';

echo "All tables in database:\n";
$result = $conn->query("SHOW TABLES");
while($table = $result->fetch_array()) {
    echo $table[0] . "\n";
}
?>
