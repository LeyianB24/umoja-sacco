-- V9 MASTER MIGRATION SCRIPT
-- "The Architect Level" Overhaul
-- --------------------------------------------------------------------------------

-- DISABLE FOREIGN KEY CHECKS FOR MIGRATION
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------------------------------
-- 1. DYNAMIC RBAC (REPLACING ENUMS)
-- --------------------------------------------------------------------------------

-- 1.1 Create Roles Table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 1.2 Create Permissions Table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE, -- e.g., 'loan_approve'
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 1.3 Create Role-Permission Pivot
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 1.4 Seed Roles
INSERT IGNORE INTO roles (id, name, description) VALUES
(1, 'Superadmin', 'Full system access'),
(2, 'Manager', 'Loan approvals and member management'),
(3, 'Accountant', 'Financial records and reports'),
(4, 'Clerk', 'Basic data entry'),
(5, 'IT Admin', 'System configuration and support');

-- 1.5 Seed Core Permissions
INSERT IGNORE INTO permissions (slug, description) VALUES
('dashboard_view', 'View Admin Dashboard'),
('settings_edit', 'Modify System Configuration'),
('loan_view', 'View Loan Applications'),
('loan_approve', 'Approve/Reject Loans'),
('transaction_view', 'View Financial Ledger'),
('transaction_create', 'Record New Transactions'),
('member_view', 'View Member Profiles'),
('member_edit', 'Edit Member Details'),
('report_export', 'Export PDF/Excel Reports'),
('tech_support', 'Manage Technical Support Tickets');

-- 1.6 Map Permissions to Roles (Basic Seeding)
-- Superadmin gets EVERYTHING
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Manager
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE slug IN ('dashboard_view', 'loan_view', 'loan_approve', 'member_view', 'member_edit', 'report_export', 'transaction_view');

-- Accountant
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE slug IN ('dashboard_view', 'transaction_view', 'transaction_create', 'report_export', 'loan_view', 'member_view');

-- IT Admin
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE slug IN ('dashboard_view', 'settings_edit', 'tech_support');

-- 1.7 Migrate Admins Table
-- 1.7 Migrate Admins Table
-- Add role_id column
ALTER TABLE admins ADD COLUMN role_id INT DEFAULT 4;

-- Update specific known admins (Example logic)
UPDATE admins SET role_id = 1 WHERE role = 'superadmin';
UPDATE admins SET role_id = 2 WHERE role = 'manager';
UPDATE admins SET role_id = 3 WHERE role = 'accountant';

-- Add FK constraint
ALTER TABLE admins ADD CONSTRAINT fk_admins_role FOREIGN KEY (role_id) REFERENCES roles(id);

-- Drop old role column (Uncomment after verifying data)
-- ALTER TABLE admins DROP COLUMN role;


-- --------------------------------------------------------------------------------
-- 2. THE GOLDEN LEDGER (INTEGRITY UPGRADES)
-- --------------------------------------------------------------------------------

-- 2.1 Update Transactions Table
-- Allow NULL member_id for Organization transactions
ALTER TABLE transactions MODIFY COLUMN member_id INT NULL;

-- 2.2 Ensure related_table supports all types
-- If it's an ENUM, modify it. If VARCHAR, this is fine.
ALTER TABLE transactions MODIFY COLUMN related_table VARCHAR(50) NOT NULL;

-- --------------------------------------------------------------------------------
-- 3. MODERN FINTECH FEATURES
-- --------------------------------------------------------------------------------

-- 3.1 Guarantors Table
CREATE TABLE IF NOT EXISTS loan_guarantors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    member_id INT NOT NULL,
    amount_locked DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('pending', 'approved', 'rejected', 'active', 'released') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id)
) ENGINE=InnoDB;

-- 3.2 Next of Kin
CREATE TABLE IF NOT EXISTS next_of_kin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    relationship VARCHAR(50),
    phone VARCHAR(20),
    id_number VARCHAR(20),
    allocation_percent DECIMAL(5,2) DEFAULT 100.00,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3.3 Dynamic System Configuration
CREATE TABLE IF NOT EXISTS system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3.4 Seed Configuration
INSERT IGNORE INTO system_config (setting_key, setting_value, description) VALUES
('interest_rate_default', '12.0', 'Default annual interest rate for loans (%)'),
('registration_fee', '1000.00', 'One-time registration fee for new members'),
('min_savings_for_loan', '3000.00', 'Minimum savings required to qualify for a loan'),
('loan_limit_multiplier', '3', 'Multiplier of savings to determine loan limit'),
('company_name', 'Umoja Drivers SACCO', 'Official Organization Name'),
('company_email', 'info@umojadriverssacco.co.ke', 'Official Contact Email'),
('company_phone', '+254 700 000 000', 'Official Contact Phone');


-- --------------------------------------------------------------------------------
-- 4. CLEANUP & OPTIMIZATION
-- --------------------------------------------------------------------------------
ALTER TABLE members ADD COLUMN IF NOT EXISTS registration_fee_status ENUM('paid', 'unpaid') DEFAULT 'unpaid';

-- Re-enable Checks
SET FOREIGN_KEY_CHECKS = 1;

-- END OF MIGRATION
