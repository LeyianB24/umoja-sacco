<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$tables = ['dividend_periods', 'dividend_payouts'];
foreach ($tables as $t) {
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    if ($res->num_rows > 0) {
        echo "Table $t exists. Schema:\n";
        $cols = $conn->query("SHOW COLUMNS FROM $t");
        while($c = $cols->fetch_assoc()) echo "  " . $c['Field'] . " " . $c['Type'] . "\n";
    } else {
        echo "Table $t does NOT exist.\n";
    }
}
