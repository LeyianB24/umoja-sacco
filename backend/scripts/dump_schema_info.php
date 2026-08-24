<?php
require_once __DIR__ . '/../config/db_connect.php';

$tables_res = $conn->query("SHOW TABLES");
$tables = [];

while ($row = $tables_res->fetch_row()) {
    $table = $row[0];
    $cols_res = $conn->query("SHOW FULL COLUMNS FROM `$table`");
    $cols = [];
    while ($c = $cols_res->fetch_assoc()) {
        $cols[] = [
            'field' => $c['Field'],
            'type' => $c['Type'],
            'null' => $c['Null'],
            'key' => $c['Key'],
            'default' => $c['Default'],
            'extra' => $c['Extra'],
            'comment' => $c['Comment']
        ];
    }
    
    // Get foreign keys
    $fk_res = $conn->query("
        SELECT 
            COLUMN_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = '$db' 
          AND TABLE_NAME = '$table' 
          AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $fks = [];
    if ($fk_res) {
        while ($fk = $fk_res->fetch_assoc()) {
            $fks[] = $fk;
        }
    }

    $tables[$table] = [
        'columns' => $cols,
        'foreign_keys' => $fks
    ];
}

file_put_contents(__DIR__ . '/schema_dump.json', json_encode($tables, JSON_PRETTY_PRINT));
echo "Dumped " . count($tables) . " tables to schema_dump.json" . PHP_EOL;
