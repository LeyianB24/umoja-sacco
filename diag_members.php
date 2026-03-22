<?php
require 'config/app.php';
$res = $conn->query('DESCRIBE members');
$rows = [];
while($row = $res->fetch_assoc()) $rows[] = $row;
file_put_contents('members_schema.json', json_encode($rows, JSON_PRETTY_PRINT));
echo "Dumped members schema to members_schema.json\n";
