-- =====================================================
-- UMOJA SACCO - ENTERPRISE EMPLOYEE ARCHITECTURE
-- Migration Script - Complete 9-Phase Implementation
-- =====================================================

-- Phase 1: HR Identity Layer
-- =====================================================

CREATE TABLE IF NOT EXISTS job_titles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) UNIQUE NOT NULL,
    department VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS salary_grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grade_name VARCHAR(50) UNIQUE NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL,
    house_allowance DECIMAL(12,2) DEFAULT 0,
    transport_allowance DECIMAL(12,2) DEFAULT 0,
    other_allowances DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Modify existing employees table or create if not exists
CREATE TABLE IF NOT EXISTS employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_no VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    national_id VARCHAR(50) UNIQUE NOT NULL,
    phone VARCHAR(20),
    personal_email VARCHAR(255),
    company_email VARCHAR(255),
    job_title VARCHAR(100),
    grade_id INT,
    salary DECIMAL(12,2) DEFAULT 0,
    kra_pin VARCHAR(20),
    nssf_no VARCHAR(50),
    nhif_no VARCHAR(50),
    bank_name VARCHAR(100),
    bank_account VARCHAR(50),
    hire_date DATE,
    status ENUM('active', 'suspended', 'terminated') DEFAULT 'active',
    admin_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (grade_id) REFERENCES salary_grades(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Phase 2: RBAC Integration
-- =====================================================

CREATE TABLE IF NOT EXISTS job_role_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_title VARCHAR(100) NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_job_role (job_title, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Phase 3: Payroll Engine
-- =====================================================

CREATE TABLE IF NOT EXISTS payroll_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period VARCHAR(7) NOT NULL COMMENT 'YYYY-MM format',
    status ENUM('draft', 'approved', 'paid') DEFAULT 'draft',
    total_gross DECIMAL(15,2) DEFAULT 0,
    total_deductions DECIMAL(15,2) DEFAULT 0,
    total_net DECIMAL(15,2) DEFAULT 0,
    employee_count INT DEFAULT 0,
    processed_by INT NOT NULL,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_period (period),
    FOREIGN KEY (processed_by) REFERENCES admins(admin_id),
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payroll_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_run_id INT NOT NULL,
    employee_id INT NOT NULL,
    employee_no VARCHAR(20) NOT NULL,
    employee_name VARCHAR(255) NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL,
    house_allowance DECIMAL(12,2) DEFAULT 0,
    transport_allowance DECIMAL(12,2) DEFAULT 0,
    other_allowances DECIMAL(12,2) DEFAULT 0,
    bonus DECIMAL(12,2) DEFAULT 0,
    gross_pay DECIMAL(12,2) NOT NULL,
    paye DECIMAL(12,2) DEFAULT 0,
    nssf DECIMAL(12,2) DEFAULT 0,
    nhif DECIMAL(12,2) DEFAULT 0,
    housing_levy DECIMAL(12,2) DEFAULT 0,
    other_deductions DECIMAL(12,2) DEFAULT 0,
    total_deductions DECIMAL(12,2) NOT NULL,
    net_pay DECIMAL(12,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_run (payroll_run_id, employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS statutory_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_type ENUM('PAYE', 'NSSF', 'NHIF', 'HOUSING_LEVY') NOT NULL,
    bracket_min DECIMAL(12,2) DEFAULT 0,
    bracket_max DECIMAL(12,2) NULL COMMENT 'NULL means no upper limit',
    rate DECIMAL(5,4) NULL COMMENT 'Percentage as decimal (e.g., 0.15 for 15%)',
    fixed_amount DECIMAL(12,2) NULL,
    relief_amount DECIMAL(12,2) DEFAULT 0 COMMENT 'For PAYE personal relief',
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rule_type (rule_type),
    INDEX idx_effective (effective_from, effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Phase 4: Payslip Management
-- =====================================================

CREATE TABLE IF NOT EXISTS payslips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_item_id INT NOT NULL,
    employee_id INT NOT NULL,
    period VARCHAR(7) NOT NULL,
    pdf_path VARCHAR(255),
    email_sent BOOLEAN DEFAULT FALSE,
    email_sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payroll_item_id) REFERENCES payroll_items(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    INDEX idx_period (period),
    INDEX idx_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- SEED DATA: Kenya 2024 Statutory Rates
-- =====================================================

-- PAYE Brackets (Kenya 2024)
INSERT INTO statutory_rules (rule_type, bracket_min, bracket_max, rate, relief_amount, effective_from) VALUES
('PAYE', 0, 24000, 0.10, 2400, '2024-01-01'),
('PAYE', 24001, 32333, 0.25, 2400, '2024-01-01'),
('PAYE', 32334, 500000, 0.30, 2400, '2024-01-01'),
('PAYE', 500001, 800000, 0.325, 2400, '2024-01-01'),
('PAYE', 800001, NULL, 0.35, 2400, '2024-01-01');

-- NSSF (Tier II - Fixed for 2024)
INSERT INTO statutory_rules (rule_type, fixed_amount, effective_from) VALUES
('NSSF', 1080, '2024-01-01');

-- NHIF Brackets (Kenya 2024)
INSERT INTO statutory_rules (rule_type, bracket_min, bracket_max, fixed_amount, effective_from) VALUES
('NHIF', 0, 5999, 150, '2024-01-01'),
('NHIF', 6000, 7999, 300, '2024-01-01'),
('NHIF', 8000, 11999, 400, '2024-01-01'),
('NHIF', 12000, 14999, 500, '2024-01-01'),
('NHIF', 15000, 19999, 600, '2024-01-01'),
('NHIF', 20000, 24999, 750, '2024-01-01'),
('NHIF', 25000, 29999, 850, '2024-01-01'),
('NHIF', 30000, 34999, 900, '2024-01-01'),
('NHIF', 35000, 39999, 950, '2024-01-01'),
('NHIF', 40000, 44999, 1000, '2024-01-01'),
('NHIF', 45000, 49999, 1100, '2024-01-01'),
('NHIF', 50000, 59999, 1200, '2024-01-01'),
('NHIF', 60000, 69999, 1300, '2024-01-01'),
('NHIF', 70000, 79999, 1400, '2024-01-01'),
('NHIF', 80000, 89999, 1500, '2024-01-01'),
('NHIF', 90000, 99999, 1600, '2024-01-01'),
('NHIF', 100000, NULL, 1700, '2024-01-01');

-- Housing Levy (1.5% of gross)
INSERT INTO statutory_rules (rule_type, rate, effective_from) VALUES
('HOUSING_LEVY', 0.015, '2024-01-01');

-- =====================================================
-- SEED DATA: Sample Job Titles & Grades
-- =====================================================

INSERT INTO job_titles (title, department, description) VALUES
('General Manager', 'Management', 'Overall operations management'),
('Accountant', 'Finance', 'Financial management and reporting'),
('Loan Officer', 'Operations', 'Loan processing and member services'),
('Teller', 'Operations', 'Cash handling and member transactions'),
('Driver', 'Transport', 'Vehicle operations'),
('Security Officer', 'Security', 'Premises security'),
('IT Officer', 'Technology', 'System administration'),
('HR Officer', 'Human Resources', 'Staff management');

INSERT INTO salary_grades (grade_name, basic_salary, house_allowance, transport_allowance) VALUES
('Executive', 120000, 30000, 15000),
('Senior', 80000, 20000, 10000),
('Mid-Level', 50000, 10000, 5000),
('Junior', 30000, 5000, 3000),
('Entry', 20000, 3000, 2000);

-- =====================================================
-- SEED DATA: Job-Role Mapping (Assumes roles exist)
-- =====================================================

-- Map job titles to roles (adjust role_id based on your roles table)
INSERT IGNORE INTO job_role_mapping (job_title, role_id) VALUES
('General Manager', 1),      -- Superadmin
('Accountant', 3),            -- Finance Manager
('Loan Officer', 4),          -- Loan Officer
('Teller', 5),                -- Teller
('IT Officer', 2),            -- Admin
('HR Officer', 2);            -- Admin

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================
