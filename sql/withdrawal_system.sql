-- sql/withdrawal_system.sql
-- Tracking for SACCO withdrawals with state management

CREATE TABLE IF NOT EXISTS `withdrawal_requests` (
  `withdrawal_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `ref_no` varchar(50) NOT NULL, -- Our internal unique reference (OriginatorConversationID)
  `amount` decimal(15,2) NOT NULL,
  `source_ledger` varchar(50) DEFAULT 'savings', -- savings, wallet, etc.
  `phone_number` varchar(30) NOT NULL,
  `status` enum('initiated','pending','completed','failed','reversed') DEFAULT 'initiated',
  `mpesa_conversation_id` varchar(100) DEFAULT NULL, -- M-Pesa's ID
  `mpesa_receipt` varchar(50) DEFAULT NULL,
  `result_code` int(11) DEFAULT NULL,
  `result_desc` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `callback_received_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`withdrawal_id`),
  UNIQUE KEY `ref_no` (`ref_no`),
  KEY `member_id` (`member_id`),
  KEY `status` (`status`),
  CONSTRAINT `withdrawal_requests_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add mpesa_request_id to transactions if missing (checked schema earlier, it was there but good to ensure index)
-- ALTER TABLE `transactions` ADD INDEX `idx_mpesa_request_id` (`mpesa_request_id`);
