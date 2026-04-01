<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
$refs = ['DR16788220', 'DR16788223', 'SIM-9277FDCB2B16', 'RVC-69'];
foreach ($refs as $r) {
    echo "--- CHECKING REFERENCE IN LEDGER: $r ---\n";
    $res = $conn->query("SELECT * FROM ledger_entries WHERE notes LIKE '%$r%'");
    while($row = $res->fetch_assoc()) echo "LEDGER: " . json_encode($row) . "\n";
}
?>
