-- Production Readiness: M-Pesa Callback Logging
-- This migration adds comprehensive callback tracking

-- 1. Callback Logs Table
CREATE TABLE IF NOT EXISTS callback_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    callback_type VARCHAR(50) NOT NULL COMMENT 'STK_PUSH, B2C, etc.',
    raw_payload TEXT NOT NULL COMMENT 'Complete JSON payload from M-Pesa',
    merchant_request_id VARCHAR(100) COMMENT 'M-Pesa MerchantRequestID',
    checkout_request_id VARCHAR(100) COMMENT 'M-Pesa CheckoutRequestID',
    result_code INT COMMENT 'M-Pesa ResultCode',
    result_desc VARCHAR(255) COMMENT 'M-Pesa ResultDesc',
    processed BOOLEAN DEFAULT FALSE COMMENT 'Whether callback was successfully processed',
    processing_attempts INT DEFAULT 0 COMMENT 'Number of processing attempts',
    last_error TEXT COMMENT 'Last error message if processing failed',
    member_id INT COMMENT 'Associated member if identified',
    amount DECIMAL(10,2) COMMENT 'Transaction amount',
    mpesa_receipt_number VARCHAR(100) COMMENT 'M-Pesa receipt number',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_merchant_request (merchant_request_id),
    INDEX idx_checkout_request (checkout_request_id),
    INDEX idx_processed (processed),
    INDEX idx_created_at (created_at),
    INDEX idx_member_id (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='M-Pesa callback audit trail';

-- 2. Email Queue Table
CREATE TABLE IF NOT EXISTS email_queue (
    queue_id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    priority TINYINT DEFAULT 5 COMMENT '1=highest, 10=lowest',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    last_error TEXT,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scheduled_for TIMESTAMP NULL COMMENT 'For delayed sending',
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_for),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Email delivery queue';

-- 3. SMS Queue Table
CREATE TABLE IF NOT EXISTS sms_queue (
    queue_id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_name VARCHAR(255),
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    priority TINYINT DEFAULT 5,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    last_error TEXT,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scheduled_for TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_for),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='SMS delivery queue';

-- 4. Financial Integrity Checks Table
CREATE TABLE IF NOT EXISTS integrity_checks (
    check_id INT PRIMARY KEY AUTO_INCREMENT,
    check_type VARCHAR(100) NOT NULL COMMENT 'ledger_balance, contribution_match, etc.',
    status ENUM('passed', 'failed', 'warning') NOT NULL,
    details TEXT COMMENT 'JSON with check details',
    affected_records TEXT COMMENT 'IDs of affected records',
    resolved BOOLEAN DEFAULT FALSE,
    resolved_by INT COMMENT 'Admin user ID who resolved',
    resolved_at TIMESTAMP NULL,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_resolved (resolved),
    INDEX idx_checked_at (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Financial integrity audit log';

-- 5. Transaction Monitoring Table
CREATE TABLE IF NOT EXISTS transaction_alerts (
    alert_id INT PRIMARY KEY AUTO_INCREMENT,
    alert_type VARCHAR(50) NOT NULL COMMENT 'stuck_pending, callback_failed, etc.',
    severity ENUM('info', 'warning', 'critical') DEFAULT 'warning',
    contribution_id INT,
    transaction_id INT,
    member_id INT,
    message TEXT NOT NULL,
    acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by INT,
    acknowledged_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_acknowledged (acknowledged),
    INDEX idx_severity (severity),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (contribution_id) REFERENCES contributions(contribution_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Transaction monitoring alerts';

-- 6. Add callback_log_id to mpesa_requests for tracking
ALTER TABLE mpesa_requests 
ADD COLUMN callback_log_id INT COMMENT 'Link to callback_logs table',
ADD INDEX idx_callback_log (callback_log_id);

-- 7. Add processing metadata to contributions
ALTER TABLE contributions
ADD COLUMN callback_received_at TIMESTAMP NULL COMMENT 'When M-Pesa callback was received',
ADD COLUMN processing_error TEXT COMMENT 'Last processing error if any',
ADD INDEX idx_callback_received (callback_received_at);
