<?php
require_once 'config/db_connect.php';
$result = $conn->query("DESCRIBE payroll");
if (!$result) {
    echo "Error: " . $conn->error;
} else {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
}
?>
