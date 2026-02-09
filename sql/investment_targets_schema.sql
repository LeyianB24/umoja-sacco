-- Investment Target System Schema Update
-- Add target tracking fields to investments table

ALTER TABLE investments 
ADD COLUMN IF NOT EXISTS target_amount DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Expected revenue target',
ADD COLUMN IF NOT EXISTS target_period ENUM('daily', 'monthly', 'annually') DEFAULT 'monthly' COMMENT 'Target evaluation period',
ADD COLUMN IF NOT EXISTS target_start_date DATE DEFAULT NULL COMMENT 'When target tracking begins',
ADD COLUMN IF NOT EXISTS viability_status ENUM('viable', 'underperforming', 'loss_making', 'pending') DEFAULT 'pending' COMMENT 'Auto-calculated economic status',
ADD COLUMN IF NOT EXISTS last_viability_check DATETIME DEFAULT NULL COMMENT 'Last time viability was calculated';

-- Create index for performance queries
CREATE INDEX IF NOT EXISTS idx_investment_viability ON investments(viability_status, status);
CREATE INDEX IF NOT EXISTS idx_investment_targets ON investments(target_period, target_start_date);

-- Update existing investments to have pending status
UPDATE investments SET viability_status = 'pending' WHERE viability_status IS NULL;
