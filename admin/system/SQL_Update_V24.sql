-- ============================================
-- V24 "Perfect Mirror" RBAC System
-- Permission Slug Inventory & Consistency Check
-- ============================================

-- Ensure all required permission slugs exist
-- This script is idempotent (safe to run multiple times)

INSERT INTO permissions (slug, name, category, description) VALUES
-- OPERATIONS (Core Business Functions)
('view_members', 'View Members', 'operations', 'Access member list and profiles'),
('view_loans', 'View Loans', 'operations', 'Access loan applications and records'),
('view_financials', 'View Financials', 'operations', 'Access revenue and financial data'),
('view_welfare', 'View Welfare', 'operations', 'Access welfare cases and donations'),
('view_reports', 'View Reports', 'operations', 'Access financial reports and analytics'),
('manage_tickets', 'Manage Support Tickets', 'operations', 'View and resolve support tickets'),

-- SYSTEM (Administrative Functions - Restricted)
('manage_users', 'Manage Admin Users', 'system', 'Create, edit, and delete admin accounts'),
('manage_roles', 'Manage Roles & Permissions', 'system', 'Configure role-based access control'),
('manage_settings', 'Manage System Settings', 'system', 'Configure global system settings'),
('view_audit_logs', 'View Audit Logs', 'system', 'Access system audit trail')

ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    category = VALUES(category),
    description = VALUES(description);

-- ============================================
-- VERIFICATION QUERY
-- Run this to confirm all slugs are present:
-- ============================================
-- SELECT slug, name, category FROM permissions ORDER BY category, slug;
