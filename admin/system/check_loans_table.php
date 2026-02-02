<?php
require_once __DIR__ . '/../../config/db_connect.php';
$result = $conn->query("DESCRIBE loans");
echo "Loans table structure:\n";
while($field = $result->fetch_assoc()) {
    echo $field['Field'] . " (" . $field['Type'] . ")\n";
}
?>
