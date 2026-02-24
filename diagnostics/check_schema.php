<?php
define('DIAG_MODE', true);
require_once __DIR__ . '/../config/app.php';

function checkTable($conn, $table) {
    echo "--- Table: $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    if (!$res) {
        echo "Error: " . $conn->error . "\n";
        return;
    }
    while($row = $res->fetch_assoc()) {
        echo sprintf("%-20s | %-15s | %-5s | %-3s | %-10s\n", 
            $row['Field'], $row['Type'], $row['Null'], $row['Key'], $row['Default'] ?? 'NULL');
    }
    echo "\n";
}

checkTable($conn, 'support_tickets');
checkTable($conn, 'support_replies');
?>

