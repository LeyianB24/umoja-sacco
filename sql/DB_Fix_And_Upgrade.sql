-- Umoja SACCO V25 - Database Refinement & System Perfection
-- Diagnosis & Repair Script

-- 1. DATA CLEANUP & INTEGRITY
-- Fix the "Ghost" Role (Admin #5 / Role ID 7 -> Superadmin ID 1)
UPDATE admins SET role_id = 1 WHERE role_id = 7;

-- Ensure all admins have a valid role_id (Default to Manager=2 if 0/Null)
UPDATE admins SET role_id = 2 WHERE role_id IS NULL OR role_id = 0;

-- Remove redundant 'role' column to enforce Single Source of Truth via 'role_id'
-- using a procedure to check existence first to avoid errors
SET @exist := (SELECT count(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'admins' AND column_name = 'role');
SET @sql := IF (@exist > 0, 'ALTER TABLE admins DROP COLUMN role', 'SELECT "Column role not found"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. THE GRAND LEDGER (Master Transactions Table)
CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_reference VARCHAR(50) UNIQUE NOT NULL,
    member_id INT NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    type ENUM('credit', 'debit') NOT NULL,
    category VARCHAR(50) NOT NULL COMMENT 'Savings, Shares, Loan, Income, Expense',
    source_table VARCHAR(50) NULL,
    source_id INT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (member_id),
    INDEX (type),
    INDEX (category),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. RBAC SCHEMA & SEEDING
-- Ensure Tables Exist
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) DEFAULT 'system'
);

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Seed Core Roles (Ignore if exist)
INSERT IGNORE INTO roles (id, name, description) VALUES 
(1, 'Superadmin', 'Full System Access'),
(2, 'Manager', 'Operational Management'),
(3, 'Accountant', 'Financial Management'),
(4, 'Clerk', 'Data Entry & Member Support'),
(5, 'Welfare', 'Welfare Management');

-- Seed Core Permissions
INSERT IGNORE INTO permissions (slug, name, category) VALUES
('view_dashboard', 'View Dashboard', 'system'),
('view_members', 'View Members', 'operations'),
('view_loans', 'View Loans', 'operations'),
('view_financials', 'View Financials', 'operations'),
('view_welfare', 'View Welfare', 'operations'),
('view_reports', 'View Reports', 'operations'),
('view_tickets', 'View Support Tickets', 'operations'),
('manage_users', 'Manage Admin Users', 'system'),
('manage_roles', 'Manage Roles & Permissions', 'system'),
('manage_settings', 'Manage System Settings', 'system'),
('manage_expenses', 'Manage Expenses', 'operations'),
('investment_mgmt', 'Investment Management', 'operations'),
('manage_staff', 'HR & Staff', 'operations');

-- Grant Superadmin All Permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Fix Auto_Increment spacing if needed
ALTER TABLE transactions AUTO_INCREMENT = 1000;
