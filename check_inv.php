<?php
require_once __DIR__ . '/config/db_connect.php';
$res = $conn->query("SHOW TABLES LIKE 'investments'");
if ($res->num_rows > 0) {
    echo "Table 'investments' exists.\n";
    $res2 = $conn->query("DESCRIBE investments");
    while($row = $res2->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Table 'investments' DOES NOT exist.\n";
}
?>
