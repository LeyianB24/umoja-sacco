<?php
require 'config/db_connect.php';
function desc($conn, $table) {
    echo "--- $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    while($row = $res->fetch_assoc()) echo "{$row['Field']} | {$row['Type']}\n";
}
desc($conn, 'contributions');
desc($conn, 'members');
