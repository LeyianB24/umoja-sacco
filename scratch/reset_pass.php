<?php
include 'config/app.php';
$pass = 'admin123';
$hash = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE admins SET password = ? WHERE username = 'superadmin'");
$stmt->bind_param('s', $hash);
if ($stmt->execute()) {
    echo "Superadmin password reset to 'admin123'\n";
} else {
    echo "Reset failed: " . $conn->error . "\n";
}
?>
