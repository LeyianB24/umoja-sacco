<?php
// sql/v3_migration.php
require_once __DIR__ . '/../config/db_connect.php';

$queries = [
    // 1. Dynamic RBAC Tables
    "CREATE TABLE IF NOT EXISTS `roles` (
        `role_id` INT AUTO_INCREMENT PRIMARY KEY,
        `role_name` VARCHAR(50) NOT NULL UNIQUE,
        `description` TEXT DEFAULT NULL
    ) ENGINE=InnoDB;",

    "CREATE TABLE IF NOT EXISTS `permissions` (
        `perm_id` INT AUTO_INCREMENT PRIMARY KEY,
        `perm_slug` VARCHAR(50) NOT NULL UNIQUE,
        `perm_name` VARCHAR(100) NOT NULL,
        `category` VARCHAR(50) DEFAULT 'General'
    ) ENGINE=InnoDB;",

    "CREATE TABLE IF NOT EXISTS `role_permissions` (
        `role_id` INT NOT NULL,
        `perm_id` INT NOT NULL,
        PRIMARY KEY (`role_id`, `perm_id`),
        FOREIGN KEY (`role_id`) REFERENCES `roles`(`role_id`) ON DELETE CASCADE,
        FOREIGN KEY (`perm_id`) REFERENCES `permissions`(`perm_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;",

    // 2. Update Admins Table
    // Add role_id and drop role enum (done carefully)
    "ALTER TABLE `admins` ADD COLUMN IF NOT EXISTS `role_id` INT DEFAULT NULL AFTER `full_name`;",

    // 3. Update Members Table
    "ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `registration_fee_status` ENUM('paid', 'unpaid') DEFAULT 'unpaid' AFTER `status`;",

    // 4. Update Transactions Table
    "ALTER TABLE `transactions` MODIFY COLUMN `related_table` ENUM('loans', 'shares', 'welfare', 'savings', 'fine', 'investment', 'vehicle', 'registration_fee', 'unknown') DEFAULT 'unknown';",
    
    // 5. Seed Roles
    "INSERT IGNORE INTO `roles` (role_name, description) VALUES 
    ('Superadmin', 'Full system access'),
    ('Manager', 'Operational oversight'),
    ('Accountant', 'Financial data management'),
    ('Clerk', 'Member onboarding and basic data entry'),
    ('IT Admin', 'Technical maintenance');",

    // 6. Seed Basic Permissions
    "INSERT IGNORE INTO `permissions` (perm_slug, perm_name, category) VALUES 
    ('view_dashboard', 'View Dashboard', 'General'),
    ('manage_staff', 'Manage Staff Users', 'System'),
    ('view_financials', 'View Financial Reports', 'Finance'),
    ('record_revenue', 'Record Revenue', 'Finance'),
    ('approve_loans', 'Approve Loan Applications', 'Loans'),
    ('disburse_loans', 'Disburse Approved Loans', 'Loans'),
    ('onboard_members', 'Register New Members', 'Operations'),
    ('manage_fleet', 'Manage Vehicle Fleet', 'Operations'),
    ('manage_investments', 'Manage Investment Assets', 'Operations'),
    ('manage_welfare', 'Manage Welfare Cases', 'Welfare');"
];

echo "Starting V3 Migration...\n";
mysqli_report(MYSQLI_REPORT_OFF);

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "Success: " . substr($sql, 0, 50) . "...\n";
    } else {
        echo "Info: " . $conn->error . " (Skipping if already exists)\n";
    }
}

// 7. Data Migration: Map existing admin roles to new role_id
$roles_map = [];
$res = $conn->query("SELECT role_id, role_name FROM roles");
while($r = $res->fetch_assoc()) $roles_map[strtolower($r['role_name'])] = $r['role_id'];

// Map 'admin' role to 'it admin' if needed
if(isset($roles_map['it admin'])) $roles_map['admin'] = $roles_map['it admin'];

$admins = $conn->query("SELECT admin_id, role FROM admins WHERE role_id IS NULL");
if ($admins && $admins->num_rows > 0) {
    while($a = $admins->fetch_assoc()) {
        $old_role = strtolower(trim($a['role']));
        if(isset($roles_map[$old_role])) {
            $rid = $roles_map[$old_role];
            $conn->query("UPDATE admins SET role_id = $rid WHERE admin_id = " . $a['admin_id']);
        }
    }
}

// 8. Auto-assign Superadmin permissions
$super_id = $roles_map['superadmin'];
$conn->query("INSERT IGNORE INTO role_permissions (role_id, perm_id) SELECT $super_id, perm_id FROM permissions");

echo "\nMigration Complete. Admin consolidation can now proceed.\n";
?>
