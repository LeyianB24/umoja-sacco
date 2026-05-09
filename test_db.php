<?php
require "config/app.php";
$tables = ['fines', 'loans', 'member_documents', 'members'];
foreach ($tables as $t) {
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    echo "$t: " . $res->num_rows . "\n";
}
