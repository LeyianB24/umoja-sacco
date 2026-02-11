<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
$tables = ['investments', 'vehicles', 'transactions', 'ledger_entries', 'ledger_accounts'];
$out = "";
foreach ($tables as $table) {
    $out .= "\n/* TABLE: $table */\n";
    $res = $conn->query("SHOW CREATE TABLE $table");
    if ($res) {
        $row = $res->fetch_row();
        $out .= $row[1] . ";\n";
    } else {
        $out .= "-- Table $table does not exist.\n";
    }
}
file_put_contents('db_schema_report.txt', $out);
echo "Report written to db_schema_report.txt\n";
?>
