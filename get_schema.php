<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$tables = ['ledger_entries', 'contributions', 'callback_logs', 'mpesa_requests'];

foreach ($tables as $table) {
    echo "--- $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo "{$row['Field']} ({$row['Type']})\n";
        }
    } else {
        echo "Error: " . $conn->error . "\n";
    }
    echo "\n";
}
?>
