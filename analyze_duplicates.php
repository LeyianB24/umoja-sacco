<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
$refs = ['DR16788220', 'DR16788223', 'SIM-9277FDCB2B16', 'RVC-69'];
foreach ($refs as $r) {
    echo "--- REFERENCE: $r ---\n";
    $res = $conn->query("SELECT * FROM transactions WHERE reference_no = '$r'");
    while($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}
?>
