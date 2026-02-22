-- =============================================
-- USMS Sacco - Schema Snapshot
-- database/schema.sql
-- =============================================
-- This is a REFERENCE SNAPSHOT of all table definitions.
-- It is NOT executed automatically. Use run_migration.php for incremental changes.
-- Last updated: 2026-02-21
-- =============================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ── Core Identity ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    permission  VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    role_id       INT NOT NULL,
    permission    VARCHAR(100) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_perm (role_id, permission),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Admin & Staff ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS admins (
    admin_id    INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100) UNIQUE NOT NULL,
    full_name   VARCHAR(255) NOT NULL,
    email       VARCHAR(255) UNIQUE NOT NULL,
    phone       VARCHAR(20),
    password    VARCHAR(255) NOT NULL,
    role_id     INT NOT NULL,
    is_active   TINYINT(1) DEFAULT 1,
    last_login  TIMESTAMP NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role_id (role_id),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Member Tables ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS members (
    member_id           INT AUTO_INCREMENT PRIMARY KEY,
    full_name           VARCHAR(255) NOT NULL,
    national_id         VARCHAR(50) UNIQUE NOT NULL,
    phone               VARCHAR(20),
    dob                 DATE NULL,
    address             TEXT,
    next_of_kin_name    VARCHAR(150) NULL,
    next_of_kin_phone   VARCHAR(30) NULL,
    occupation          VARCHAR(100) NULL,
    email               VARCHAR(255),
    password            VARCHAR(255),
    savings_balance     DECIMAL(15,2) DEFAULT 0,
    shares_balance      DECIMAL(15,2) DEFAULT 0,
    wallet_balance      DECIMAL(15,2) DEFAULT 0,
    kyc_status          ENUM('not_submitted','pending','approved','rejected') DEFAULT 'not_submitted',
    kyc_notes           TEXT NULL,
    registration_fee_status ENUM('unpaid','paid') DEFAULT 'unpaid',
    status              ENUM('active','suspended','exited') DEFAULT 'active',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_national_id (national_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Financial Core ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS transactions (
    transaction_id  INT AUTO_INCREMENT PRIMARY KEY,
    member_id       INT NOT NULL,
    action_type     VARCHAR(50) NOT NULL,
    amount          DECIMAL(15,2) NOT NULL,
    reference       VARCHAR(100),
    method          VARCHAR(50) DEFAULT 'cash',
    related_table   VARCHAR(50),
    related_id      INT,
    notes           TEXT,
    created_by      INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_member (member_id),
    INDEX idx_action (action_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contributions (
    contribution_id     INT AUTO_INCREMENT PRIMARY KEY,
    member_id           INT NOT NULL,
    amount              DECIMAL(15,2) NOT NULL,
    type                ENUM('savings','shares') DEFAULT 'savings',
    status              ENUM('pending','completed','failed') DEFAULT 'pending',
    reference           VARCHAR(100),
    method              VARCHAR(50) DEFAULT 'mpesa',
    callback_received_at TIMESTAMP NULL,
    processing_error    TEXT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_member (member_id),
    INDEX idx_status (status),
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loans (
    loan_id         INT AUTO_INCREMENT PRIMARY KEY,
    member_id       INT NOT NULL,
    loan_type       VARCHAR(50) DEFAULT 'personal',
    amount          DECIMAL(15,2) NOT NULL,
    interest_rate   DECIMAL(5,2) DEFAULT 10.00,
    duration_months INT DEFAULT 12,
    current_balance DECIMAL(15,2) DEFAULT 0,
    status          ENUM('pending','approved','disbursed','rejected','settled') DEFAULT 'pending',
    reference_no    VARCHAR(100),
    approved_by     INT NULL,
    approval_date   TIMESTAMP NULL,
    disbursement_date TIMESTAMP NULL,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── HR & Payroll ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS job_titles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(100) UNIQUE NOT NULL,
    department  VARCHAR(100),
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS salary_grades (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    grade_name          VARCHAR(50) UNIQUE NOT NULL,
    basic_salary        DECIMAL(12,2) NOT NULL,
    house_allowance     DECIMAL(12,2) DEFAULT 0,
    transport_allowance DECIMAL(12,2) DEFAULT 0,
    other_allowances    DECIMAL(12,2) DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employees (
    employee_id     INT AUTO_INCREMENT PRIMARY KEY,
    employee_no     VARCHAR(20) UNIQUE NOT NULL,
    full_name       VARCHAR(255) NOT NULL,
    national_id     VARCHAR(50) UNIQUE NOT NULL,
    phone           VARCHAR(20),
    personal_email  VARCHAR(255),
    company_email   VARCHAR(255),
    job_title       VARCHAR(100),
    grade_id        INT,
    salary          DECIMAL(12,2) DEFAULT 0,
    kra_pin         VARCHAR(20),
    nssf_no         VARCHAR(50),
    nhif_no         VARCHAR(50),
    bank_name       VARCHAR(100),
    bank_account    VARCHAR(50),
    hire_date       DATE,
    status          ENUM('active','suspended','terminated') DEFAULT 'active',
    admin_id        INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_hire_date (hire_date),
    INDEX idx_company_email (company_email),
    FOREIGN KEY (grade_id) REFERENCES salary_grades(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS statutory_rules (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    rule_type       ENUM('PAYE','NSSF','NHIF','HOUSING_LEVY') NOT NULL,
    bracket_min     DECIMAL(12,2) DEFAULT 0,
    bracket_max     DECIMAL(12,2) NULL,
    rate            DECIMAL(5,4) NULL,
    fixed_amount    DECIMAL(12,2) NULL,
    relief_amount   DECIMAL(12,2) DEFAULT 0,
    effective_from  DATE NOT NULL,
    effective_to    DATE NULL,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rule_type (rule_type),
    INDEX idx_effective (effective_from, effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payroll_runs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    period          VARCHAR(7) NOT NULL COMMENT 'YYYY-MM format',
    status          ENUM('draft','approved','paid') DEFAULT 'draft',
    total_gross     DECIMAL(15,2) DEFAULT 0,
    total_deductions DECIMAL(15,2) DEFAULT 0,
    total_net       DECIMAL(15,2) DEFAULT 0,
    employee_count  INT DEFAULT 0,
    processed_by    INT NOT NULL,
    approved_by     INT NULL,
    approved_at     TIMESTAMP NULL,
    paid_at         TIMESTAMP NULL,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_period (period),
    FOREIGN KEY (processed_by) REFERENCES admins(admin_id),
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Audit & Monitoring ────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS audit_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    admin_id        INT,
    action          VARCHAR(100) NOT NULL,
    entity_type     VARCHAR(50),
    entity_id       INT,
    details         TEXT,
    before_snapshot TEXT,
    after_snapshot  TEXT,
    ip_address      VARCHAR(45),
    user_agent      VARCHAR(255),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_admin_action (admin_id, action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transaction_alerts (
    alert_id        INT AUTO_INCREMENT PRIMARY KEY,
    alert_type      VARCHAR(50) NOT NULL,
    severity        ENUM('info','warning','critical') DEFAULT 'warning',
    contribution_id INT NOT NULL,
    member_id       INT NULL,
    message         TEXT NOT NULL,
    acknowledged    TINYINT(1) DEFAULT 0,
    acknowledged_at TIMESTAMP NULL,
    acknowledged_by INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contribution (contribution_id),
    INDEX idx_acknowledged (acknowledged),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS callback_logs (
    log_id                INT AUTO_INCREMENT PRIMARY KEY,
    callback_type         VARCHAR(50) NOT NULL,
    raw_payload           TEXT NOT NULL,
    merchant_request_id   VARCHAR(100),
    checkout_request_id   VARCHAR(100),
    result_code           INT,
    result_desc           VARCHAR(255),
    processed             BOOLEAN DEFAULT FALSE,
    processing_attempts   INT DEFAULT 0,
    last_error            TEXT,
    member_id             INT,
    amount                DECIMAL(10,2),
    mpesa_receipt_number  VARCHAR(100),
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at          TIMESTAMP NULL,
    INDEX idx_merchant_request (merchant_request_id),
    INDEX idx_checkout_request (checkout_request_id),
    INDEX idx_processed (processed),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Migration Tracking ────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS _migrations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    filename    VARCHAR(255) NOT NULL UNIQUE,
    batch       INT NOT NULL DEFAULT 1,
    applied_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ── End of Schema Snapshot ────────────────────────────────────────────────────
