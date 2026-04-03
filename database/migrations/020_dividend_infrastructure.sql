-- usms/database/migrations/020_dividend_infrastructure.sql

CREATE TABLE IF NOT EXISTS `dividend_periods` (
  `period_id` int(11) NOT NULL AUTO_INCREMENT,
  `fiscal_year` int(4) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `total_pool` decimal(20,2) DEFAULT 0.00,
  `rate_percentage` decimal(10,4) DEFAULT 0.0000,
  `declared_by` int(11) DEFAULT NULL,
  `status` enum('draft','declared','processed') DEFAULT 'draft',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`period_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dividend_payouts` (
  `payout_id` int(11) NOT NULL AUTO_INCREMENT,
  `period_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `share_capital_snapshot` decimal(20,2) DEFAULT 0.00,
  `weighted_units` decimal(20,4) DEFAULT 0.0000,
  `gross_amount` decimal(20,2) DEFAULT 0.00,
  `wht_tax` decimal(20,2) DEFAULT 0.00,
  `net_amount` decimal(20,2) DEFAULT 0.00,
  `status` enum('pending','processed','failed') DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payout_id`),
  KEY `period_id` (`period_id`),
  KEY `member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
