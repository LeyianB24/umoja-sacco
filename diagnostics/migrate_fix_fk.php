<?php
require_once __DIR__ . '/../config/db_connect.php';

$sql = "ALTER TABLE support_tickets MODIFY admin_id INT(11) NULL";
echo "Executing: $sql ... ";

if ($conn->query($sql)) {
    echo "SUCCESS!\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}

echo "--- Verifying Schema ---\n";
$res = $conn->query("DESCRIBE support_tickets");
while ($row = $res->fetch_assoc()) {
    if ($row['Field'] === 'admin_id') {
        print_r($row);
    }
}
