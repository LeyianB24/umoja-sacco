-- SQL_Refinement.sql
-- Umoja SACCO V28 - Granular Permissions for Workflow Logic

-- 1. Add Granular Permissions
INSERT IGNORE INTO permissions (slug, name, category) VALUES
('view_loans', 'View Loan Applications', 'operations'),
('approve_loans', 'Approve/Reject Loans (Manager)', 'operations'),
('disburse_loans', 'Disburse Loan Funds (Accountant)', 'operations'),
('manage_members', 'Manage Member Records (Clerk)', 'operations');

-- 2. Ensure Superadmin has these new permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE slug IN ('approve_loans', 'disburse_loans', 'manage_members');

-- 3. Map common roles to these permissions (Manager & Accountant)
-- Manager (Role ID 2) gets approval
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE slug IN ('view_loans', 'approve_loans', 'view_members', 'view_reports');

-- Accountant (Role ID 3) gets disbursement
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE slug IN ('view_loans', 'disburse_loans', 'view_financials', 'view_reports');

-- Clerk (Role ID 4) gets management
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE slug IN ('view_members', 'manage_members', 'view_loans');
