<?php
include 'config/app.php';
$email = '1704781@students.kcau.ac.ke';
$stmt_admin = $conn->prepare("SELECT a.admin_id, a.full_name, a.username, a.role_id, r.name as role_name, a.password FROM admins a JOIN roles r ON a.role_id = r.id WHERE a.email = ? OR a.username = ? LIMIT 1");
$stmt_admin->bind_param('ss', $email, $email);
$stmt_admin->execute();
$res_admin = $stmt_admin->get_result();
if ($res_admin && $res_admin->num_rows > 0) {
    $admin = $res_admin->fetch_assoc();
    echo "Found Admin: " . $admin['username'] . " with role: " . $admin['role_name'] . "\n";
} else {
    echo "Admin not found with email: $email\n";
    // Check if roles table has the role_id
    $r = $conn->query("SELECT * FROM admins WHERE email = '$email'");
    if ($row = $r->fetch_assoc()) {
        echo "Admin exists in table, but JOIN failed. role_id: " . $row['role_id'] . "\n";
        $role_id = $row['role_id'];
        $r2 = $conn->query("SELECT * FROM roles WHERE id = $role_id");
        if ($row2 = $r2->fetch_assoc()) {
            echo "Role exists: " . $row2['name'] . "\n";
        } else {
            echo "Role DOES NOT exist in roles table!\n";
        }
    }
}
?>
