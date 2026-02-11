<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');

function describe($table) {
    global $conn;
    echo "\n--- $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']}\n";
        }
    } else {
        echo "Table does not exist.\n";
    }
}

describe('investments');
describe('vehicles');
describe('transactions');
describe('ledger_entries');
describe('ledger_accounts');
?>
