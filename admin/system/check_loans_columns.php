<?php
require_once __DIR__ . '/../../config/app.php';
$result = $conn->query("SHOW COLUMNS FROM loans");
while($field = $result->fetch_assoc()) {
    echo $field['Field'] . "\n";
}
?>
