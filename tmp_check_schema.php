<?php
require_once __DIR__ . '/config/app.php';
$res = $conn->query("SELECT account_name, account_type, category FROM ledger_accounts");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
