CREATE TABLE IF NOT EXISTS `financial_export_logs` (
  `export_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_role` varchar(50) DEFAULT NULL,
  `financial_module` varchar(100) NOT NULL,
  `export_format` enum('PDF','Excel') NOT NULL,
  `export_date` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `record_count` int(11) DEFAULT 0,
  `total_value` decimal(20,2) DEFAULT 0.00,
  `status` enum('success','failed') DEFAULT 'success',
  PRIMARY KEY (`export_id`),
  KEY `user_id` (`user_id`),
  KEY `export_date` (`export_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
