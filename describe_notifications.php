<?php
require_once __DIR__ . '/config/db_connect.php';
$res = $conn->query('DESCRIBE notifications');
if ($res) {
    while($row = $res->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error: " . $conn->error;
}
