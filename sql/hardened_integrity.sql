-- USMS V3 Final Data Integrity Hardening
-- Enforce ON DELETE RESTRICT on all financial history relationships

-- 1. Transactions Ledger (Critical)
ALTER TABLE transactions DROP FOREIGN KEY IF EXISTS fk_txn_member;
ALTER TABLE transactions 
ADD CONSTRAINT fk_txn_member 
FOREIGN KEY (member_id) REFERENCES members(member_id) 
ON DELETE RESTRICT; -- Cannot delete member if transactions exist

-- 2. Contributions history
ALTER TABLE contributions DROP FOREIGN KEY IF EXISTS fk_contrib_member;
ALTER TABLE contributions 
ADD CONSTRAINT fk_contrib_member 
FOREIGN KEY (member_id) REFERENCES members(member_id) 
ON DELETE RESTRICT;

-- 3. Loan records
ALTER TABLE loans DROP FOREIGN KEY IF EXISTS fk_loan_member;
ALTER TABLE loans 
ADD CONSTRAINT fk_loan_member 
FOREIGN KEY (member_id) REFERENCES members(member_id) 
ON DELETE RESTRICT;

-- 4. Admin Audit Logs
-- Keep logs even if admin is deleted? Or prevent deletion? 
-- Let's set to RESTRICT for safety.
ALTER TABLE audit_logs DROP FOREIGN KEY IF EXISTS fk_audit_admin;
ALTER TABLE audit_logs 
ADD CONSTRAINT fk_audit_admin 
FOREIGN KEY (admin_id) REFERENCES admins(admin_id) 
ON DELETE RESTRICT;

-- 5. Role Permissions
ALTER TABLE role_permissions DROP FOREIGN KEY IF EXISTS fk_rp_role;
ALTER TABLE role_permissions 
ADD CONSTRAINT fk_rp_role 
FOREIGN KEY (role_id) REFERENCES roles(id) 
ON DELETE CASCADE; -- If role is deleted, mapping should go too

ALTER TABLE role_permissions DROP FOREIGN KEY IF EXISTS fk_rp_perm;
ALTER TABLE role_permissions 
ADD CONSTRAINT fk_rp_perm 
FOREIGN KEY (permission_id) REFERENCES permissions(id) 
ON DELETE CASCADE;
