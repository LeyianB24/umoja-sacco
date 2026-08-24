-- ============================================
-- Investment Target System - Schema Update
-- Run this in phpMyAdmin or MySQL CLI
-- ============================================

-- Step 1: Add target tracking columns
ALTER TABLE `investments` 
ADD COLUMN IF NOT EXISTS `target_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Expected revenue target',
ADD COLUMN IF NOT EXISTS `target_period` VARCHAR(20) DEFAULT 'monthly' COMMENT 'daily, monthly, annually',
ADD COLUMN IF NOT EXISTS `target_start_date` DATE DEFAULT NULL COMMENT 'When target tracking begins',
ADD COLUMN IF NOT EXISTS `viability_status` VARCHAR(20) DEFAULT 'pending' COMMENT 'viable, underperforming, loss_making, pending',
ADD COLUMN IF NOT EXISTS `last_viability_check` DATETIME DEFAULT NULL COMMENT 'Last calculation timestamp';

-- Step 2: Create performance indexes
CREATE INDEX IF NOT EXISTS `idx_investment_viability` ON `investments`(`viability_status`, `status`);
CREATE INDEX IF NOT EXISTS `idx_investment_targets` ON `investments`(`target_period`, `target_start_date`);

-- Step 3: Set default viability status for existing investments
UPDATE `investments` 
SET `viability_status` = 'pending' 
WHERE `viability_status` IS NULL OR `viability_status` = '';

-- Step 4: Verify changes
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    COLUMN_DEFAULT, 
    IS_NULLABLE, 
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'investments'
  AND COLUMN_NAME IN ('target_amount', 'target_period', 'target_start_date', 'viability_status', 'last_viability_check');

-- Success message
SELECT 'Schema updated successfully! All target tracking columns added.' AS Status;
