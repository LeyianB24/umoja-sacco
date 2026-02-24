-- UMOJA SACCO V5: MODERNIZATION & AUTOMATION
-- --------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;

-- 1. SYSTEM SETTINGS TABLE
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `last_updated_by` INT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`last_updated_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Seed initial data
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('loan_interest_rate', '12.00', 'Flat interest rate percentage for standard loans.'),
('late_payment_fine_daily', '50.00', 'Daily fine amount for overdue loans.'),
('registration_fee_amount', '1000.00', 'One-time registration fee for new members.'),
('min_guarantor_count', '2', 'Minimum number of guarantors required for a loan.'),
('company_name', 'Umoja Drivers SACCO', 'Official name of the SACCO.');

-- 2. FINES TRACKING TABLE
CREATE TABLE IF NOT EXISTS `fines` (
    `fine_id` INT AUTO_INCREMENT PRIMARY KEY,
    `loan_id` INT NOT NULL,
    `amount` DECIMAL(15, 2) NOT NULL,
    `date_applied` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`loan_id`) REFERENCES `loans`(`loan_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. ENHANCEMENT: ADD next_repayment_date TO loans IF NOT EXISTS
-- (Assuming standard monthly repayments)
ALTER TABLE `loans` ADD COLUMN IF NOT EXISTS `next_repayment_date` DATE DEFAULT NULL;

SET FOREIGN_KEY_CHECKS = 1;
