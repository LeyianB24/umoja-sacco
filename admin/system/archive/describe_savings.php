<?php
require 'config/db_connect.php';
$res = $conn->query("DESCRIBE savings");
while($row = $res->fetch_assoc()) echo $row['Field'].' ('.$row['Type'].")\n";
