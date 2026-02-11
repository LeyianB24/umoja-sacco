ALTER TABLE investments ADD COLUMN target_amount DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE investments ADD COLUMN target_period VARCHAR(20) DEFAULT 'monthly';
ALTER TABLE investments ADD COLUMN target_start_date DATE DEFAULT NULL;
ALTER TABLE investments ADD COLUMN viability_status VARCHAR(20) DEFAULT 'pending';
ALTER TABLE investments ADD COLUMN last_viability_check DATETIME DEFAULT NULL;
