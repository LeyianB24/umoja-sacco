-- MASTER REFACTOR V12
-- "The Production-Grade Core Banking Platform"
-- Umoja Drivers SACCO Management System

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ================================================================================
-- SECTION 1: RBAC SCHEMA UPDATES
-- ================================================================================

-- Update Permissions Table to support Role Matrix UI
-- Add display_name and category columns if they don't exist
SET @exist_display := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_NAME='permissions' AND COLUMN_NAME='display_name' AND TABLE_SCHEMA=DATABASE());
SET @exist_category := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_NAME='permissions' AND COLUMN_NAME='category' AND TABLE_SCHEMA=DATABASE());

-- Add display_name column
SET @sql := IF(@exist_display = 0, 
    'ALTER TABLE permissions ADD COLUMN display_name VARCHAR(100) AFTER slug', 
    'SELECT "display_name already exists" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add category column
SET @sql := IF(@exist_category = 0, 
    'ALTER TABLE permissions ADD COLUMN category VARCHAR(50) AFTER display_name', 
    'SELECT "category already exists" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing permissions to have display_name and category
UPDATE permissions SET 
    display_name = CASE slug
        WHEN 'member_directory' THEN 'Access Member Directory'
        WHEN 'loan_reviews' THEN 'Review & Approve Loans'
        WHEN 'welfare_cases' THEN 'Manage Welfare Cases'
        WHEN 'investment_mgmt' THEN 'Manage Shared Investments'
        WHEN 'fleet_mgmt' THEN 'Manage Vehicle Fleet'
        WHEN 'employee_mgmt' THEN 'Staff Management'
        WHEN 'member_registration' THEN 'Register New Members'
        WHEN 'revenue_tracking' THEN 'Track Revenue (Investments & Fleet)'
        WHEN 'payment_processing' THEN 'Process Member Payments'
        WHEN 'expense_mgmt' THEN 'Manage System Expenses'
        WHEN 'loan_disbursement' THEN 'Process Loan Disbursements'
        WHEN 'financial_statements' THEN 'View Ledger & Statements'
        WHEN 'financial_reports' THEN 'Generate Financial Reports'
        WHEN 'audit_logs' THEN 'View System Security Logs'
        WHEN 'system_backups' THEN 'Generate DB Backups'
        WHEN 'system_settings' THEN 'Global System Configuration'
        WHEN 'tech_support' THEN 'Resolve Support Tickets'
        ELSE CONCAT(UPPER(SUBSTRING(slug, 1, 1)), SUBSTRING(slug, 2))
    END,
    category = CASE slug
        WHEN 'member_directory' THEN 'Operations'
        WHEN 'loan_reviews' THEN 'Operations'
        WHEN 'welfare_cases' THEN 'Operations'
        WHEN 'investment_mgmt' THEN 'Operations'
        WHEN 'fleet_mgmt' THEN 'Operations'
        WHEN 'employee_mgmt' THEN 'Operations'
        WHEN 'member_registration' THEN 'Operations'
        WHEN 'revenue_tracking' THEN 'Finance'
        WHEN 'payment_processing' THEN 'Finance'
        WHEN 'expense_mgmt' THEN 'Finance'
        WHEN 'loan_disbursement' THEN 'Finance'
        WHEN 'financial_statements' THEN 'Finance'
        WHEN 'financial_reports' THEN 'Finance'
        WHEN 'audit_logs' THEN 'Maintenance'
        WHEN 'system_backups' THEN 'Maintenance'
        WHEN 'system_settings' THEN 'Maintenance'
        WHEN 'tech_support' THEN 'Maintenance'
        ELSE 'Operations'
    END
WHERE display_name IS NULL OR category IS NULL;

-- Ensure roles table has correct column names
SET @exist_role_name := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_NAME='roles' AND COLUMN_NAME='role_name' AND TABLE_SCHEMA=DATABASE());
SET @exist_name := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_NAME='roles' AND COLUMN_NAME='name' AND TABLE_SCHEMA=DATABASE());

-- If roles table has 'name' but needs 'role_name', rename it
SET @sql := IF(@exist_name > 0 AND @exist_role_name = 0, 
    'ALTER TABLE roles CHANGE COLUMN name role_name VARCHAR(50) NOT NULL', 
    'SELECT "role_name column OK" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure role_id column exists in roles table
SET @exist_role_id := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_NAME='roles' AND COLUMN_NAME='role_id' AND TABLE_SCHEMA=DATABASE());

SET @sql := IF(@exist_role_id = 0, 
    'ALTER TABLE roles CHANGE COLUMN id role_id INT AUTO_INCREMENT', 
    'SELECT "role_id column OK" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ================================================================================
-- SECTION 2: THE GOLDEN LEDGER (Transaction Table Hardening)
-- ================================================================================

-- Ensure member_id is NULLABLE for organization-level transactions
ALTER TABLE transactions MODIFY COLUMN member_id INT NULL;

-- Update related_table ENUM to include ALL transaction types
ALTER TABLE transactions MODIFY COLUMN related_table 
    ENUM('loans','shares','welfare','savings','fine','fines','investment','investments',
         'vehicle','vehicles','registration_fee','dividend','dividends','expense','expenses') 
    NOT NULL;

-- Add indexes for performance
CREATE INDEX IF NOT EXISTS idx_transactions_member ON transactions(member_id);
CREATE INDEX IF NOT EXISTS idx_transactions_type ON transactions(transaction_type);
CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(created_at);
CREATE INDEX IF NOT EXISTS idx_transactions_related ON transactions(related_table, related_id);

-- ================================================================================
-- SECTION 3: SYSTEM SETTINGS EXPANSION
-- ================================================================================

-- Ensure system_settings table exists (V10 should have created it)
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insert/Update system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('loan_interest_rate', '12.0', 'Default annual loan interest rate (%)'),
('registration_fee', '1000', 'Member registration fee (KES)'),
('min_guarantors', '2', 'Minimum number of loan guarantors required'),
('trial_balance_tolerance', '0.00', 'Maximum acceptable trial balance difference'),
('system_name', 'Umoja Drivers SACCO', 'Official SACCO name'),
('system_address', 'P.O. Box 12345, Nairobi, Kenya', 'Official address for reports'),
('system_phone', '+254 700 000 000', 'Contact phone number'),
('system_email', 'info@umojadriversacco.co.ke', 'Contact email'),
('enable_paygate', '1', 'Enable registration fee payment gate (1=Yes, 0=No)'),
('share_value', '500', 'Value per share (KES)'),
('min_shares', '10', 'Minimum shares required for membership'),
('max_loan_multiplier', '3', 'Maximum loan as multiple of savings')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description);

-- ================================================================================
-- SECTION 4: DATA INTEGRITY VERIFICATION
-- ================================================================================

-- Verify no orphaned role_permissions
DELETE rp FROM role_permissions rp
LEFT JOIN roles r ON rp.role_id = r.role_id
LEFT JOIN permissions p ON rp.permission_id = p.id
WHERE r.role_id IS NULL OR p.id IS NULL;

-- Verify no orphaned transactions (optional - commented out for safety)
-- DELETE t FROM transactions t
-- LEFT JOIN members m ON t.member_id = m.member_id
-- WHERE t.member_id IS NOT NULL AND m.member_id IS NULL;

-- ================================================================================
-- SECTION 5: VERIFICATION QUERIES
-- ================================================================================

-- Show updated permissions structure
SELECT 'Permissions Table Structure' AS verification;
DESCRIBE permissions;

-- Show updated transactions related_table enum
SELECT 'Transactions related_table ENUM' AS verification;
SHOW COLUMNS FROM transactions WHERE Field = 'related_table';

-- Show system settings
SELECT 'System Settings' AS verification;
SELECT setting_key, setting_value, description FROM system_settings ORDER BY setting_key;

-- Show roles and permission counts
SELECT 'Roles and Permission Counts' AS verification;
SELECT 
    r.role_id,
    r.role_name,
    COUNT(rp.permission_id) AS permission_count
FROM roles r
LEFT JOIN role_permissions rp ON r.role_id = rp.role_id
GROUP BY r.role_id, r.role_name
ORDER BY r.role_id;

SET FOREIGN_KEY_CHECKS = 1;

-- Migration Complete
SELECT 'V12 Migration Complete!' AS status, NOW() AS completed_at;
