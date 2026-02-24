<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$q = $conn->query('DESCRIBE payroll_runs');
while($r = $q->fetch_assoc()) {
    echo $r['Field'] . "\n";
}
