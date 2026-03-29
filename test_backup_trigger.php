<?php
require_once 'config/app.php';

try {
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) { $tables[] = $row[0]; }

    echo "Found " . count($tables) . " tables\n";
    $count = 0;
    foreach ($tables as $table) {
        $count++;
        $res = $conn->query("SELECT * FROM `$table` shadow_rows");
        if (!$res) {
            echo "Error on table $table: " . $conn->error . "\n";
            exit(1);
        }
    }
    echo "Successfully queried $count tables.\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
