<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$res=$conn->query("SHOW COLUMNS FROM loans");
while($r=$res->fetch_assoc()) echo $r['Field']."\n";
