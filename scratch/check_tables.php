<?php
include 'config/app.php';
$r = $conn->query("SHOW TABLES");
echo "Tables:\n";
while($row = $r->fetch_row()) {
    echo "{$row[0]}\n";
}

$r = $conn->query("SELECT * FROM admins");
echo "\nAdmins:\n";
if ($r) {
    while($row = $r->fetch_assoc()) {
        echo "ID: {$row['admin_id']} | User: {$row['username']} | Email: {$row['email']} | Pass (hashed?): " . (empty($row['password']) ? 'EMPTY' : 'SET') . "\n";
    }
} else {
    echo "Admins table query failed.\n";
}

$r = $conn->query("SELECT * FROM roles");
echo "\nRoles:\n";
if ($r) {
    while($row = $r->fetch_assoc()) {
        echo "ID: {$row['id']} | Name: {$row['name']}\n";
    }
} else {
    echo "Roles table query failed.\n";
}
?>
