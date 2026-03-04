<?php
require 'config/db_connect.php';
$res = $conn->query("DESCRIBE transactions");
while($row = $res->fetch_assoc()) echo $row['Field'].' ('.$row['Type'].")\n";
