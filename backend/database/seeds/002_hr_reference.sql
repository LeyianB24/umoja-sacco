-- =============================================
-- USMS Sacco - Seed: HR Reference Data
-- database/seeds/002_hr_reference.sql
-- =============================================
-- Extracted from employee_architecture.sql
-- Uses INSERT IGNORE to be idempotent

-- Sample Job Titles
INSERT IGNORE INTO job_titles (title, department, description) VALUES
('General Manager', 'Management',      'Overall operations management'),
('Accountant',      'Finance',         'Financial management and reporting'),
('Loan Officer',    'Operations',      'Loan processing and member services'),
('Teller',          'Operations',      'Cash handling and member transactions'),
('Driver',          'Transport',       'Vehicle operations'),
('Security Officer','Security',        'Premises security'),
('IT Officer',      'Technology',      'System administration'),
('HR Officer',      'Human Resources', 'Staff management');

-- Salary Grades
INSERT IGNORE INTO salary_grades (grade_name, basic_salary, house_allowance, transport_allowance) VALUES
('Executive', 120000, 30000, 15000),
('Senior',     80000, 20000, 10000),
('Mid-Level',  50000, 10000,  5000),
('Junior',     30000,  5000,  3000),
('Entry',      20000,  3000,  2000);

-- Job → Role Mappings (role_id matches your roles table)
-- 1=Superadmin, 2=Admin, 3=Finance Manager, 4=Loan Officer, 5=Teller
INSERT IGNORE INTO job_role_mapping (job_title, role_id) VALUES
('General Manager',   1),
('Accountant',        3),
('Loan Officer',      4),
('Teller',            5),
('IT Officer',        2),
('HR Officer',        2);
