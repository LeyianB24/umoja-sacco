<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$sql = file_get_contents('c:/xampp/htdocs/usms/database/migrations/010_late_repayment_system.sql');
if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) { $res->free(); }
    } while ($conn->more_results() && $conn->next_result());
    echo "Migration successful\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
