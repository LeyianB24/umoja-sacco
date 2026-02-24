<?php
define('DIAG_MODE', true);
require_once __DIR__ . '/../config/app.php';
$f = fopen(__DIR__ . '/applied_migs.txt', 'w');
$res = $conn->query("SELECT * FROM _migrations");
while($row = $res->fetch_assoc()) {
    fwrite($f, "{$row['filename']} - batch {$row['batch']}\n");
}
fclose($f);
?>
