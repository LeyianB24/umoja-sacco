-- Migration 010: Late Repayment System
-- Adds repayment tracking columns and creates the fines table.

-- 1. Update loans table
ALTER TABLE loans 
ADD COLUMN last_repayment_date DATETIME DEFAULT NULL AFTER disbursed_date,
ADD COLUMN next_repayment_date DATETIME DEFAULT NULL AFTER last_repayment_date;

-- 2. Create fines table
CREATE TABLE IF NOT EXISTS fines (
    fine_id       INT AUTO_INCREMENT PRIMARY KEY,
    loan_id       INT NOT NULL,
    amount        DECIMAL(12,2) NOT NULL,
    date_applied  DATE NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
    INDEX idx_loan_date (loan_id, date_applied)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Initialize next_repayment_date for existing disbursed loans
-- Default to 1 month after disbursement if not set
UPDATE loans 
SET next_repayment_date = DATE_ADD(disbursed_date, INTERVAL 1 MONTH)
WHERE status IN ('disbursed', 'active') 
AND disbursed_date IS NOT NULL 
AND next_repayment_date IS NULL;
