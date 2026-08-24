<?php
require "config/app.php";
$r = $conn->query("DESCRIBE member_documents");
while($row = $r->fetch_assoc()) echo $row['Field'].' '.$row['Type']."\n";
echo "\n---\n";
// check uploads/kyc dir
$dir = __DIR__ . '/uploads/kyc';
echo "kyc dir exists: " . (is_dir($dir) ? 'yes' : 'no') . "\n";
echo "kyc dir writable: " . (is_writable($dir) ? 'yes' : 'no') . "\n";
echo "kyc dir: " . $dir . "\n";

// check if superadmin exists
$r = $conn->query("SELECT admin_id, username, role_id FROM admins WHERE username='superadmin'");
echo "\nsuperadmin: " . ($r->num_rows > 0 ? 'exists' : 'NOT FOUND') . "\n";
if ($r->num_rows > 0) {
    $a = $r->fetch_assoc();
    echo "admin_id: " . $a['admin_id'] . " role_id: " . $a['role_id'] . "\n";
}
