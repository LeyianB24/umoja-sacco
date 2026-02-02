-- MASTER MIGRATION V10
-- "The Architect Level"

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------------------------------
-- 1. RBAC FINALIZATION
-- --------------------------------------------------------------------------------

-- Ensure Roles Table Exists
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
) ENGINE=InnoDB;

-- Ensure Permissions Table Exists
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
) ENGINE=InnoDB;

-- Role Permissions Pivot
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed Data (Roles)
INSERT INTO roles (id, name, description) VALUES
(1, 'Superadmin', 'Full Access'),
(2, 'Manager', 'Operations'),
(3, 'Accountant', 'Finance'),
(4, 'Clerk', 'Data Entry'),
(5, 'IT Admin', 'Support')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Admins Table Cleanup
-- Ensure role_id column exists
ALTER TABLE admins ADD COLUMN IF NOT EXISTS role_id INT DEFAULT 4;

-- Map old 'role' enum to 'role_id' if column exists
-- (This block is safe to run even if 'role' column is already gone, using PREPARE)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_NAME='admins' AND COLUMN_NAME='role' AND TABLE_SCHEMA=DATABASE());
SET @sql := IF(@exist > 0, 'UPDATE admins SET role_id = CASE role WHEN "superadmin" THEN 1 WHEN "manager" THEN 2 WHEN "accountant" THEN 3 ELSE 4 END', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop old role column if exists
SET @sql := IF(@exist > 0, 'ALTER TABLE admins DROP COLUMN role', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add FK
ALTER TABLE admins ADD CONSTRAINT fk_admin_role FOREIGN KEY (role_id) REFERENCES roles(id);


-- --------------------------------------------------------------------------------
-- 2. THE GOLDEN LEDGER
-- --------------------------------------------------------------------------------

-- Transactions Table Hardening
ALTER TABLE transactions MODIFY COLUMN member_id INT NULL;
ALTER TABLE transactions MODIFY COLUMN related_table ENUM('loans','shares','welfare','savings','fine','investment','vehicle','registration_fee','dividend','expense') NOT NULL;


-- --------------------------------------------------------------------------------
-- 3. SYSTEM SETTINGS
-- --------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL
) ENGINE=InnoDB;

INSERT INTO system_settings (setting_key, setting_value) VALUES
('loan_interest_rate', '12.0'),
('registration_fee', '1000'),
('min_guarantors', '2')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);


SET FOREIGN_KEY_CHECKS = 1;
