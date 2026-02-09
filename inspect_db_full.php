<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');

$tables = ['investments', 'vehicles', 'transactions', 'ledger_entries', 'ledger_accounts'];

foreach ($tables as $table) {
    echo "\n/* TABLE: $table */\n";
    $res = $conn->query("SHOW CREATE TABLE $table");
    if ($res) {
        $row = $res->fetch_row();
        echo $row[1] . ";\n";
    } else {
        echo "-- Table $table does not exist.\n";
    }
}
?>
