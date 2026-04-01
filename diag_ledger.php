<?php
require_once __DIR__ . '/config/app.php';
$res = $conn->query("DESCRIBE ledger_entries");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
