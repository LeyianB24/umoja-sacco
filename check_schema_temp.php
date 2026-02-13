<?php
require 'config/db_connect.php';
$res = $conn->query("DESCRIBE welfare_cases");
$cols = [];
while($row = $res->fetch_assoc()) $cols[] = $row['Field'];
echo "COLS:" . implode(',', $cols) . ":END";
