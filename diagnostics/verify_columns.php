<?php
include 'c:/xampp/htdocs/usms/config/db_connect.php';

function checkColumn($conn, $table, $column) {
    $res = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    if ($res && $res->num_rows > 0) {
        echo "[EXISTS] $table.$column\n";
    } else {
        echo "[MISSING] $table.$column\n";
    }
}

checkColumn($conn, 'loans', 'total_payable');
checkColumn($conn, 'loans', 'current_balance');
checkColumn($conn, 'loans', 'amount');
checkColumn($conn, 'loans', 'duration_months');
checkColumn($conn, 'loans', 'interest_rate');
checkColumn($conn, 'loans', 'disbursed_amount');
checkColumn($conn, 'loans', 'disbursed_date');
checkColumn($conn, 'members', 'profile_pic');
?>
