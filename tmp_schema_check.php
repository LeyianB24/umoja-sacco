<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
function print_schema($conn, $table) {
    echo "--- $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    while($row = $res->fetch_assoc()) {
        echo sprintf("%-20s %-20s %-10s %-10s %-20s %s\n", 
            $row['Field'], $row['Type'], $row['Null'], $row['Key'], $row['Default'], $row['Extra']);
    }
}
print_schema($conn, 'loans');
print_schema($conn, 'loan_repayments');
print_schema($conn, 'system_settings');
