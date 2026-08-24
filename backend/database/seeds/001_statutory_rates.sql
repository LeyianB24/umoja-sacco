-- =============================================
-- USMS Sacco - Seed: Kenya Statutory Rates
-- database/seeds/001_statutory_rates.sql
-- =============================================
-- Extracted from employee_architecture.sql
-- Uses INSERT IGNORE to be idempotent

-- Kenya 2024/2025 PAYE Brackets
INSERT IGNORE INTO statutory_rules (rule_type, bracket_min, bracket_max, rate, relief_amount, effective_from) VALUES
('PAYE', 0,      24000,  0.10,  2400, '2024-01-01'),
('PAYE', 24001,  32333,  0.25,  2400, '2024-01-01'),
('PAYE', 32334,  500000, 0.30,  2400, '2024-01-01'),
('PAYE', 500001, 800000, 0.325, 2400, '2024-01-01'),
('PAYE', 800001, NULL,   0.35,  2400, '2024-01-01');

-- NSSF (Tier II - Fixed, Kenya 2024)
INSERT IGNORE INTO statutory_rules (rule_type, fixed_amount, effective_from) VALUES
('NSSF', 1080, '2024-01-01');

-- NHIF Brackets (Kenya 2024)
INSERT IGNORE INTO statutory_rules (rule_type, bracket_min, bracket_max, fixed_amount, effective_from) VALUES
('NHIF', 0,      5999,  150,  '2024-01-01'),
('NHIF', 6000,   7999,  300,  '2024-01-01'),
('NHIF', 8000,   11999, 400,  '2024-01-01'),
('NHIF', 12000,  14999, 500,  '2024-01-01'),
('NHIF', 15000,  19999, 600,  '2024-01-01'),
('NHIF', 20000,  24999, 750,  '2024-01-01'),
('NHIF', 25000,  29999, 850,  '2024-01-01'),
('NHIF', 30000,  34999, 900,  '2024-01-01'),
('NHIF', 35000,  39999, 950,  '2024-01-01'),
('NHIF', 40000,  44999, 1000, '2024-01-01'),
('NHIF', 45000,  49999, 1100, '2024-01-01'),
('NHIF', 50000,  59999, 1200, '2024-01-01'),
('NHIF', 60000,  69999, 1300, '2024-01-01'),
('NHIF', 70000,  79999, 1400, '2024-01-01'),
('NHIF', 80000,  89999, 1500, '2024-01-01'),
('NHIF', 90000,  99999, 1600, '2024-01-01'),
('NHIF', 100000, NULL,  1700, '2024-01-01');

-- Housing Levy (1.5% of gross)
INSERT IGNORE INTO statutory_rules (rule_type, rate, effective_from) VALUES
('HOUSING_LEVY', 0.015, '2024-01-01');
