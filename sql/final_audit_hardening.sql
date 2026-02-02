-- UMOJA SACCO V3: FINAL AUDIT & HARDENING SCRIPT
-- Objective: Remove Enums, Finalize RBAC, Normalize Ledger

-- 1. ADMIN TABLE CLEANUP
-- Map old roles to new IDs (1:Superadmin, 2:Manager, 3:Accountant, 4:Clerk)
UPDATE admins SET role_id = (
    CASE 
        WHEN role = 'superadmin' THEN 1
        WHEN role = 'manager' THEN 2
        WHEN role = 'accountant' THEN 3
        WHEN role = 'clerk' THEN 4
        ELSE 4 -- Default to lowest permission
    END
) WHERE role_id IS NULL OR role_id = 0;

-- Drop legacy column
ALTER TABLE admins DROP COLUMN IF EXISTS role;

-- Enforce Foreign Key
ALTER TABLE admins 
ADD CONSTRAINT fk_admin_role 
FOREIGN KEY (role_id) REFERENCES roles(role_id) 
ON DELETE RESTRICT;


-- 2. TRANSACTIONS TABLE NORMALIZATION
-- Update related_table ENUM to include all V3 modules
ALTER TABLE transactions 
MODIFY COLUMN related_table ENUM(
    'loans','shares','welfare','savings','fine','investment','vehicle','registration_fee','unknown'
) DEFAULT 'unknown';


-- 3. MEMBERS TABLE ENFORCEMENT
-- Ensure registration status exists
ALTER TABLE members 
ADD COLUMN IF NOT EXISTS registration_fee_status ENUM('paid', 'unpaid') DEFAULT 'unpaid';


-- 4. FINANCIAL HISTORY INTEGRITY
-- Restrict deletions of members with history
ALTER TABLE transactions DROP FOREIGN KEY IF EXISTS fk_txn_member;
ALTER TABLE transactions ADD CONSTRAINT fk_txn_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE RESTRICT;

ALTER TABLE contributions DROP FOREIGN KEY IF EXISTS fk_contrib_member;
ALTER TABLE contributions ADD CONSTRAINT fk_contrib_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE RESTRICT;

ALTER TABLE loans DROP FOREIGN KEY IF EXISTS fk_loan_member;
ALTER TABLE loans ADD CONSTRAINT fk_loan_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE RESTRICT;
