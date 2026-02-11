<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

$res = $conn->query("SELECT slug FROM permissions");
while ($row = $res->fetch_assoc()) {
    echo $row['slug'] . "\n";
}
