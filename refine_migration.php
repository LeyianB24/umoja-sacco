<?php
require 'config/db_connect.php';

echo "Updating admins.role enum...\n";
$conn->query("ALTER TABLE admins MODIFY COLUMN role ENUM('superadmin','manager','accountant','admin','clerk') NOT NULL DEFAULT 'clerk'");

echo "Updating roles table...\n";
$conn->query("INSERT IGNORE INTO roles (role_name, description) VALUES ('clerk', 'Member Registration & Support')");

echo "Updating members table...\n";
$conn->query("ALTER TABLE members ADD COLUMN member_reg_no VARCHAR(20) DEFAULT NULL AFTER member_id");
$conn->query("ALTER TABLE members ADD COLUMN reg_fee_paid TINYINT(1) DEFAULT 0 AFTER status");
$conn->query("CREATE UNIQUE INDEX idx_member_reg_no ON members(member_reg_no)");

echo "Migration Complete.\n";
?>
