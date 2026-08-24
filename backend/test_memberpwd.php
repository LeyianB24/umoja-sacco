<?php
require "config/app.php";
// Check member passwords
$r = $conn->query("SELECT member_id, full_name, email, password FROM members WHERE member_id IN (2,3) LIMIT 3");
while ($row = $r->fetch_assoc()) {
    $test_pass = password_verify('admin123', $row['password']);
    echo "id={$row['member_id']} {$row['full_name']} - admin123 match: " . ($test_pass ? 'YES' : 'NO') . "\n";
    echo "  hash: " . substr($row['password'], 0, 40) . "...\n";
}

// Also check if temp_password might be set
$r2 = $conn->query("SELECT member_id, full_name, temp_password FROM members WHERE member_id IN (2,3) LIMIT 3");
while ($row = $r2->fetch_assoc()) {
    echo "id={$row['member_id']} temp_password: " . ($row['temp_password'] ?? 'NULL') . "\n";
}
