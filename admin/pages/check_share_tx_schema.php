<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$cols = $conn->query("SHOW COLUMNS FROM share_transactions");
while($c = $cols->fetch_assoc()) echo $c['Field'] . " " . $c['Type'] . "\n";
