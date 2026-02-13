<?php
require 'config/db_connect.php';

function list_cols($conn, $table) {
    echo "--- $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    while($row = $res->fetch_assoc()) {
        echo "{$row['Field']} ({$row['Type']})\n";
    }
    echo "\n";
}

list_cols($conn, 'contributions');
list_cols($conn, 'welfare_donations');
list_cols($conn, 'welfare_cases');
?>
