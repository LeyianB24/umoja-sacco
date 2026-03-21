<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
require_once 'c:/xampp/htdocs/usms/core/Database/Database.php';

use USMS\Database\Database;

$db = Database::getInstance();
$rows = $db->fetchAll("SELECT DISTINCT transaction_type, category FROM transactions");

$out = "";
foreach ($rows as $row) {
    $out .= "T:" . $row['transaction_type'] . "|C:" . $row['category'] . "\n";
}
file_put_contents('c:/xampp/htdocs/usms/txn_results.txt', $out);
