<?php
require 'config/app.php';
$res = $conn->query("DESCRIBE transactions");
while($row = $res->fetch_assoc()) echo $row['Field'].' ('.$row['Type'].")\n";
