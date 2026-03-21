<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$tables = ['fines', 'email_queue', 'loans', 'loan_repayments'];
$out = "";
foreach ($tables as $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    $exists = ($res->num_rows > 0);
    $out .= "$table: " . ($exists ? "EXISTS" : "MISSING") . "\n";
    if ($exists) {
        $res2 = $conn->query("DESCRIBE $table");
        while($row = $res2->fetch_assoc()) {
            $out .= "  - {$row['Field']} ({$row['Type']})\n";
        }
    }
}
file_put_contents('c:/xampp/htdocs/usms/final_table_check.txt', $out);
