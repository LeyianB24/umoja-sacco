<?php
require_once __DIR__ . '/config/app.php';
$res = $conn->query("DESCRIBE ledger_entries");
$cols = [];
while($row = $res->fetch_assoc()) $cols[] = $row['Field'];
echo implode("\n", $cols);
?>
