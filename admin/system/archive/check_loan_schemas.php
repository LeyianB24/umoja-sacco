<?php
require 'config/db_connect.php';
$out = "";
foreach(['loans', 'loan_guarantors', 'loan_repayments', 'members', 'transactions'] as $t) {
    $out .= "Table: $t\n";
    $res = $conn->query("DESCRIBE $t");
    if($res) while($row = $res->fetch_assoc()) $out .= "  {$row['Field']} ({$row['Type']})\n";
    else $out .= "  (Not found)\n";
}
file_put_contents('schema_dump.txt', $out);
echo "Dumped to schema_dump.txt";
