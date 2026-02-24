<?php
define('DIAG_MODE', true);
require_once __DIR__ . '/../config/app.php';

echo "=== USMS Table List ===\n";
$res = $conn->query("SHOW TABLES");
if ($res) {
    while($row = $res->fetch_array()) {
        echo $row[0] . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}
?>
