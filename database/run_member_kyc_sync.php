<?php
require_once __DIR__ . '/../config/db_connect.php';

$sql_file = __DIR__ . '/migrations/member_kyc_sync.sql';
$sql = file_get_contents($sql_file);

echo "Running Member KYC Sync Migration...\n";

if ($conn->query($sql)) {
    echo "✅ Success: Members table updated.\n";
} else {
    echo "✗ Error: " . $conn->error . "\n";
}
?>
