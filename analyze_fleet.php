<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');

echo "--- Vehicle Fleet Analysis ---\n";
echo "Investments with Category 'vehicle_fleet':\n";
$res1 = $conn->query("SELECT investment_id, title, reg_no FROM investments WHERE category = 'vehicle_fleet'");
while($row = $res1->fetch_assoc()) {
    echo "INV #{$row['investment_id']}: {$row['title']} ({$row['reg_no']})\n";
}

echo "\nVehicles Table Contents:\n";
$res2 = $conn->query("SELECT vehicle_id, reg_no, model, investment_id FROM vehicles");
while($row = $res2->fetch_assoc()) {
    echo "VEH #{$row['vehicle_id']}: {$row['reg_no']} ({$row['model']}) -> Linked to INV #".($row['investment_id'] ?? 'NONE')."\n";
}
?>
