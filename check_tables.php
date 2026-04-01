<?php
$c = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
if ($c->connect_error) die("Connection failed: " . $c->connect_error);

function dump($c, $table) {
    echo "--- TABLE: $table ---\n";
    $res = $c->query("DESCRIBE $table");
    if (!$res) {
        echo "Error: " . $c->error . "\n";
        return;
    }
    while ($row = $res->fetch_assoc()) {
        printf("%-20s | %-20s\n", $row['Field'], $row['Type']);
    }
    echo "\n";
}

dump($c, 'loans');
dump($c, 'ledger_accounts');
dump($c, 'members');
dump($c, 'ledger_entries');
dump($c, 'transactions');
