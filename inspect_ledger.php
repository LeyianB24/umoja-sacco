<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
$tables = ['ledger_transactions', 'ledger_accounts', 'ledger_entries'];
$out = "";
foreach ($tables as $table) {
    if ($res = $conn->query("SHOW TABLES LIKE '$table'")) {
        if ($res->num_rows > 0) {
            $create = $conn->query("SHOW CREATE TABLE $table")->fetch_row()[1];
            $out .= "\n/* TABLE: $table */\n" . $create . ";\n";
        }
    }
}
file_put_contents('db_ledger_report.txt', $out);
?>
