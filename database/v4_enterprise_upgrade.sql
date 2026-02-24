-- UMOJA SACCO V4: ENTERPRISE SCALE-UP
-- High-Performance Core Banking Features

SET FOREIGN_KEY_CHECKS = 0;

-- 1. LOAN GUARANTORS (Guarantees against Free Shares)
CREATE TABLE IF NOT EXISTS `loan_guarantors` (
    `guarantor_id` INT AUTO_INCREMENT PRIMARY KEY,
    `loan_id` INT NOT NULL,
    `member_id` INT NOT NULL, -- The member who is guaranteeing
    `guaranteed_amount` DECIMAL(15, 2) NOT NULL,
    `status` ENUM('pending', 'accepted', 'rejected', 'released') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`loan_id`) REFERENCES `loans`(`loan_id`) ON DELETE CASCADE,
    FOREIGN KEY (`member_id`) REFERENCES `members`(`member_id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 2. NEXT OF KIN (Legal Inheritance)
CREATE TABLE IF NOT EXISTS `next_of_kin` (
    `kin_id` INT AUTO_INCREMENT PRIMARY KEY,
    `member_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `relationship` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `id_number` VARCHAR(50) NOT NULL,
    `allocation_percent` INT DEFAULT 100, -- Sum must be 100% per member
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`member_id`) REFERENCES `members`(`member_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. DIVIDENDS MANAGEMENT
CREATE TABLE IF NOT EXISTS `dividend_periods` (
    `period_id` INT AUTO_INCREMENT PRIMARY KEY,
    `fiscal_year` YEAR NOT NULL UNIQUE,
    `rate_percentage` DECIMAL(5, 2) NOT NULL,
    `status` ENUM('declared', 'payout_in_progress', 'paid') DEFAULT 'declared',
    `declared_by` INT,
    `declared_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`declared_by`) REFERENCES `admins`(`admin_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `dividend_payouts` (
    `payout_id` INT AUTO_INCREMENT PRIMARY KEY,
    `period_id` INT NOT NULL,
    `member_id` INT NOT NULL,
    `share_capital_snapshot` DECIMAL(15, 2) NOT NULL, -- Value at time of declaration
    `gross_amount` DECIMAL(15, 2) NOT NULL,
    `wht_tax` DECIMAL(15, 2) NOT NULL, -- Withholding Tax (Usually 5%)
    `net_amount` DECIMAL(15, 2) NOT NULL,
    `status` ENUM('pending', 'processed') DEFAULT 'pending',
    `paid_at` DATETIME DEFAULT NULL,
    FOREIGN KEY (`period_id`) REFERENCES `dividend_periods`(`period_id`),
    FOREIGN KEY (`member_id`) REFERENCES `members`(`member_id`)
) ENGINE=InnoDB;

-- 4. DOCUMENT MANAGEMENT (KYC Compliance)
CREATE TABLE IF NOT EXISTS `documents` (
    `doc_id` INT AUTO_INCREMENT PRIMARY KEY,
    `member_id` INT NOT NULL,
    `doc_type` ENUM('id_front', 'id_back', 'kra_pin', 'logbook', 'passport_photo', 'bank_statement') NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `verified_by` INT DEFAULT NULL,
    `notes` TEXT,
    FOREIGN KEY (`member_id`) REFERENCES `members`(`member_id`) ON DELETE CASCADE,
    FOREIGN KEY (`verified_by`) REFERENCES `admins`(`admin_id`)
) ENGINE=InnoDB;

-- 5. ENHANCED SYSTEM AUDIT
CREATE TABLE IF NOT EXISTS `system_logs` (
    `log_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT,
    `action` VARCHAR(100) NOT NULL,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `details` TEXT, -- JSON payload if needed
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`admin_id`) REFERENCES `admins`(`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
