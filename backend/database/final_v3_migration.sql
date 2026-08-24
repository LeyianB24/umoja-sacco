-- final_v3_migration.sql
-- Umoja Drivers SACCO Final Architecture Refactor

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Create RBAC Tables
CREATE TABLE IF NOT EXISTS `roles` (
    `role_id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_name` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `permission_key` VARCHAR(50) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) DEFAULT 'General'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT NOT NULL,
    `permission_id` INT NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`role_id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2. Seed Default Roles
INSERT IGNORE INTO `roles` (role_name, description) VALUES 
('superadmin', 'Full system access and RBAC management'),
('manager', 'Daily operations and fleet oversight'),
('accountant', 'Ledger management and financial reporting'),
('clerk', 'Member onboarding and basic support');

-- 3. Seed Default Permissions
INSERT IGNORE INTO `permissions` (permission_key, display_name, category) VALUES 
('member_directory', 'Access Member Directory', 'Operations'),
('member_registration', 'Register New Members', 'Operations'),
('loan_reviews', 'Review & Approve Loans', 'Operations'),
('welfare_cases', 'Manage Welfare Cases', 'Operations'),
('investment_mgmt', 'Manage Shared Investments', 'Operations'),
('fleet_mgmt', 'Manage Vehicle Fleet', 'Operations'),
('employee_mgmt', 'Staff Management', 'Operations'),
('revenue_tracking', 'Track Revenue (Investments & Fleet)', 'Finance'),
('payment_processing', 'Process Member Payments', 'Finance'),
('expense_mgmt', 'Manage System Expenses', 'Finance'),
('loan_disbursement', 'Process Loan Disbursements', 'Finance'),
('financial_statements', 'View Ledger & Statements', 'Finance'),
('financial_reports', 'Generate Financial Reports', 'Finance'),
('audit_logs', 'View System Security Logs', 'Maintenance'),
('system_backups', 'Generate DB Backups', 'Maintenance'),
('system_settings', 'Global System Configuration', 'Maintenance'),
('tech_support', 'Resolve Support Tickets', 'Maintenance');

-- 4. Map Superadmin Permissions (All)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT (SELECT role_id FROM roles WHERE role_name='superadmin'), id FROM permissions;

-- 5. Map Accountant Permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT (SELECT role_id FROM roles WHERE role_name='accountant'), id FROM permissions 
WHERE permission_key IN ('revenue_tracking', 'payment_processing', 'expense_mgmt', 'loan_disbursement', 'financial_statements', 'financial_reports', 'member_directory');

-- 6. Map Manager Permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT (SELECT role_id FROM roles WHERE role_name='manager'), id FROM permissions 
WHERE permission_key IN ('loan_reviews', 'welfare_cases', 'investment_mgmt', 'fleet_mgmt', 'member_directory', 'employee_mgmt');

-- 7. Map Clerk Permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT (SELECT role_id FROM roles WHERE role_name='clerk'), id FROM permissions 
WHERE permission_key IN ('member_registration', 'member_directory', 'tech_support');

-- 8. Alter Admins Table
-- Add role_id if not exists
ALTER TABLE `admins` ADD COLUMN IF NOT EXISTS `role_id` INT DEFAULT NULL AFTER `full_name`;

-- Migrate data from role enum to role_id
UPDATE admins a JOIN roles r ON a.role = r.role_name SET a.role_id = r.role_id;

-- Drop deprecated role enum (CAUTION: Ensure migration data move above first)
-- ALTER TABLE `admins` DROP COLUMN `role`; -- Uncomment after verification if safe

-- 9. Fix vehicle_income bug (Ensure it links to vehicles, not investments)
-- (Confirmed in audit, but adding a check/fix script here)
-- ALTER TABLE vehicle_income DROP FOREIGN KEY IF EXISTS vehicle_income_ibfk_vehicle; -- Just in case
-- ALTER TABLE vehicle_income MODIFY COLUMN vehicle_id INT NOT NULL;
-- ALTER TABLE vehicle_income ADD CONSTRAINT fk_vehicle_income_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE;

-- 10. Update Ledger (Transactions Table)
ALTER TABLE `transactions` MODIFY COLUMN `related_table` ENUM('loans', 'shares', 'welfare', 'savings', 'fine', 'investment', 'vehicle', 'registration_fee', 'unknown') DEFAULT 'unknown';
ALTER TABLE `transactions` MODIFY COLUMN `member_id` INT DEFAULT NULL;

-- 11. Member Registration Status
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `registration_fee_status` ENUM('paid', 'unpaid') DEFAULT 'unpaid' AFTER `status`;

SET FOREIGN_KEY_CHECKS = 1;
