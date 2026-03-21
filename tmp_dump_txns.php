<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
require_once 'c:/xampp/htdocs/usms/core/Database/Database.php';

use USMS\Database\Database;

$db = Database::getInstance();
$rows = $db->fetchAll("SELECT DISTINCT transaction_type, category FROM transactions");

foreach ($rows as $row) {
    echo "Type: " . $row['transaction_type'] . " | Category: " . $row['category'] . "\n";
}
