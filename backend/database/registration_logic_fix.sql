-- UMOJA SACCO: REGISTRATION LOGIC SCHEMA SYNC
-- Finalizes the Pay-Gate DB structure

-- 1. Ensure registration_fee_status ENUM exists and defaults correctly
ALTER TABLE members 
CHANGE COLUMN IF EXISTS registration_fee_status 
registration_fee_status ENUM('paid', 'unpaid') NOT NULL DEFAULT 'unpaid';

-- 2. Ensure transactions related_table includes registration_fee
ALTER TABLE transactions 
MODIFY COLUMN related_table ENUM(
    'loans', 'shares', 'welfare', 'savings', 'fine', 'investment', 'vehicle', 'registration_fee', 'unknown'
) DEFAULT 'unknown';
