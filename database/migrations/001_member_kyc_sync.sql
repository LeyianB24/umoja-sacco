-- Add missing fields to members table for KYC and Profile synchronization
ALTER TABLE members 
ADD COLUMN dob DATE NULL AFTER phone,
ADD COLUMN next_of_kin_name VARCHAR(150) NULL AFTER address,
ADD COLUMN next_of_kin_phone VARCHAR(30) NULL AFTER next_of_kin_name,
ADD COLUMN occupation VARCHAR(100) NULL AFTER next_of_kin_phone,
ADD COLUMN kyc_status ENUM('not_submitted', 'pending', 'approved', 'rejected') DEFAULT 'not_submitted' AFTER registration_fee_status,
ADD COLUMN kyc_notes TEXT NULL AFTER kyc_status;
