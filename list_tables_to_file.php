<?php
// list_tables_to_file.php
require 'c:\xampp\htdocs\usms\config\db_connect.php';
$res = $conn->query("SHOW TABLES");
$tables = [];
while($row = $res->fetch_array()) {
    $tables[] = $row[0];
}
file_put_contents('db_tables_dump.txt', implode("\n", $tables));
echo "Dumped " . count($tables) . " tables to db_tables_dump.txt\n";
?>
