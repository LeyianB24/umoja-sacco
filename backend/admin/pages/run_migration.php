<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$sql = file_get_contents('c:/xampp/htdocs/usms/database/migrations/020_dividend_infrastructure.sql');
if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Migration Successful\n";
} else {
    echo "Migration Failed: " . $conn->error . "\n";
}
