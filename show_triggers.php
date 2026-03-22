<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$res=$conn->query("SHOW TRIGGERS");
while($r=$res->fetch_assoc()) echo "TRIGGER: " . $r['Trigger']. "\nSTATEMENT: " . $r['Statement']."\n\n";
