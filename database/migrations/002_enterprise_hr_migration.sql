-- Enterprise HR & Payroll System - Database Migration
-- Run this script to add required tables and indexes

-- 1. Job-Role Mapping Table
CREATE TABLE IF NOT EXISTS job_role_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_title VARCHAR(100) NOT NULL UNIQUE,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed common job titles
INSERT IGNORE INTO job_role_mapping (job_title, role_id) VALUES
('Manager', 3),
('Accountant', 4),
('Driver', 2),
('Receptionist', 2),
('IT Officer', 2),
('Loan Officer', 2),
('Cashier', 2);

-- 2. Employee Salary History Table
CREATE TABLE IF NOT EXISTS employee_salary_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    old_salary DECIMAL(12,2),
    new_salary DECIMAL(12,2),
    grade_id INT,
    reason VARCHAR(255),
    effective_date DATE NOT NULL,
    changed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES admins(admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Statutory Rules Table (PAYE, NHIF, NSSF, Housing Levy)
CREATE TABLE IF NOT EXISTS statutory_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    type ENUM('percentage', 'fixed', 'bracket') NOT NULL,
    value DECIMAL(10,4),
    min_amount DECIMAL(12,2) DEFAULT 0,
    max_amount DECIMAL(12,2),
    effective_from DATE NOT NULL,
    effective_to DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name_active (name, is_active),
    INDEX idx_effective_dates (effective_from, effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Kenya 2026 Statutory Rates

-- PAYE Tax Brackets
INSERT IGNORE INTO statutory_rules (name, type, min_amount, max_amount, value, effective_from) VALUES
('PAYE', 'bracket', 0, 24000, 0.10, '2026-01-01'),
('PAYE', 'bracket', 24001, 32333, 0.25, '2026-01-01'),
('PAYE', 'bracket', 32334, 500000, 0.30, '2026-01-01'),
('PAYE', 'bracket', 500001, 800000, 0.325, '2026-01-01'),
('PAYE', 'bracket', 800001, 9999999999, 0.35, '2026-01-01');

-- NHIF Brackets
INSERT IGNORE INTO statutory_rules (name, type, min_amount, max_amount, value, effective_from) VALUES
('NHIF', 'bracket', 0, 5999, 150, '2026-01-01'),
('NHIF', 'bracket', 6000, 7999, 300, '2026-01-01'),
('NHIF', 'bracket', 8000, 11999, 400, '2026-01-01'),
('NHIF', 'bracket', 12000, 14999, 500, '2026-01-01'),
('NHIF', 'bracket', 15000, 19999, 600, '2026-01-01'),
('NHIF', 'bracket', 20000, 24999, 750, '2026-01-01'),
('NHIF', 'bracket', 25000, 29999, 850, '2026-01-01'),
('NHIF', 'bracket', 30000, 34999, 900, '2026-01-01'),
('NHIF', 'bracket', 35000, 39999, 950, '2026-01-01'),
('NHIF', 'bracket', 40000, 44999, 1000, '2026-01-01'),
('NHIF', 'bracket', 45000, 49999, 1100, '2026-01-01'),
('NHIF', 'bracket', 50000, 59999, 1200, '2026-01-01'),
('NHIF', 'bracket', 60000, 69999, 1300, '2026-01-01'),
('NHIF', 'bracket', 70000, 79999, 1400, '2026-01-01'),
('NHIF', 'bracket', 80000, 89999, 1500, '2026-01-01'),
('NHIF', 'bracket', 90000, 99999, 1600, '2026-01-01'),
('NHIF', 'bracket', 100000, 9999999999, 1700, '2026-01-01');

-- NSSF (6% of gross salary, capped at KES 2,160)
INSERT IGNORE INTO statutory_rules (name, type, value, effective_from) VALUES
('NSSF', 'percentage', 0.06, '2026-01-01');

-- Housing Levy (1.5% of gross salary)
INSERT IGNORE INTO statutory_rules (name, type, value, effective_from) VALUES
('Housing Levy', 'percentage', 0.015, '2026-01-01');

-- 4. Add Indexes to Employees Table
ALTER TABLE employees 
ADD UNIQUE INDEX idx_employee_no (employee_no),
ADD INDEX idx_admin_id (admin_id),
ADD INDEX idx_status (status),
ADD INDEX idx_hire_date (hire_date),
ADD INDEX idx_company_email (company_email);

-- 5. Add Indexes to Admins Table
ALTER TABLE admins 
ADD INDEX idx_email (email),
ADD INDEX idx_username (username),
ADD INDEX idx_role_id (role_id),
ADD INDEX idx_is_active (is_active);

-- 6. Add Indexes to Payroll Table
ALTER TABLE payroll 
ADD INDEX idx_employee_id (employee_id),
ADD INDEX idx_run_id (run_id),
ADD INDEX idx_payment_date (payment_date),
ADD INDEX idx_status (status);

-- 7. Update Audit Logs Table (if needed)
ALTER TABLE audit_logs 
ADD COLUMN entity_type VARCHAR(50) AFTER action,
ADD COLUMN entity_id INT AFTER entity_type,
ADD COLUMN before_snapshot TEXT AFTER details,
ADD COLUMN after_snapshot TEXT AFTER before_snapshot,
ADD COLUMN user_agent VARCHAR(255) AFTER ip_address,
ADD INDEX idx_entity (entity_type, entity_id),
ADD INDEX idx_admin_action (admin_id, action),
ADD INDEX idx_created_at (created_at);

-- Migration Complete
SELECT 'Enterprise HR & Payroll Database Migration Completed Successfully!' as Status;
