<?php
require 'c:/xampp/htdocs/usms/config/db_connect.php';
$q = $conn->query('DESCRIBE payroll_runs');
while($r = $q->fetch_assoc()) {
    echo $r['Field'] . "\n";
}
