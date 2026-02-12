<?php
include 'config/db_connect.php';

function desc($table) {
    global $conn;
    echo "--- $table ---" . PHP_EOL;
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo $row['Field'] . PHP_EOL;
        }
    } else {
        echo "Error describing $table" . PHP_EOL;
    }
}

desc('members');
desc('employees');
desc('admins');
