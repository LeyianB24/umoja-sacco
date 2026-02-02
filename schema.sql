
--- TABLE: vehicle_income ---
CREATE TABLE `vehicle_income` (
  `vehicle_income_id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `income_date` datetime DEFAULT current_timestamp(),
  `description` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`vehicle_income_id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `vehicle_income_ibfk_vehicle` (`vehicle_id`),
  CONSTRAINT `vehicle_income_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `vehicle_income_ibfk_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--- TABLE: transactions ---
CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) DEFAULT NULL,
  `transaction_type` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_table` enum('loans','shares','welfare','savings','fine','investment','vehicle','registration_fee','unknown') DEFAULT 'unknown',
  `recorded_by` int(11) DEFAULT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `payment_channel` varchar(80) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by_admin` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `mpesa_request_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`transaction_id`),
  KEY `created_by_admin` (`created_by_admin`),
  KEY `member_id` (`member_id`),
  KEY `transaction_date` (`transaction_date`),
  KEY `idx_transactions_date` (`transaction_date`),
  KEY `transactions_ibfk_2` (`recorded_by`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`),
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=325 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--- TABLE: admins ---
CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(80) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `temp_password` varchar(100) DEFAULT NULL,
  `role` enum('superadmin','manager','accountant','admin','clerk') NOT NULL DEFAULT 'clerk',
  `role_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `remember_expires` datetime DEFAULT NULL,
  `remember_ua` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--- TABLE: members ---
CREATE TABLE `members` (
  `member_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_reg_no` varchar(20) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `account_balance` decimal(15,2) DEFAULT 0.00,
  `national_id` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `remember_expires` datetime DEFAULT NULL,
  `remember_ua` varchar(255) DEFAULT NULL,
  `temp_password` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `join_date` date DEFAULT curdate(),
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `registration_fee_status` enum('paid','unpaid') DEFAULT 'unpaid',
  `reg_fee_paid` tinyint(1) DEFAULT 0,
  `profile_pic` longblob DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `gender` enum('male','female') DEFAULT 'male',
  PRIMARY KEY (`member_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `email_2` (`email`),
  UNIQUE KEY `email_3` (`email`),
  UNIQUE KEY `email_4` (`email`),
  UNIQUE KEY `email_5` (`email`),
  UNIQUE KEY `email_6` (`email`),
  UNIQUE KEY `email_7` (`email`),
  UNIQUE KEY `email_8` (`email`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `national_id` (`national_id`),
  UNIQUE KEY `national_id_2` (`national_id`),
  UNIQUE KEY `national_id_3` (`national_id`),
  UNIQUE KEY `national_id_4` (`national_id`),
  UNIQUE KEY `national_id_5` (`national_id`),
  UNIQUE KEY `national_id_6` (`national_id`),
  UNIQUE KEY `national_id_7` (`national_id`),
  UNIQUE KEY `national_id_8` (`national_id`),
  UNIQUE KEY `idx_member_reg_no` (`member_reg_no`),
  UNIQUE KEY `member_reg_no_unique` (`member_reg_no`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--- TABLE: vehicles ---
CREATE TABLE `vehicles` (
  `vehicle_id` int(11) NOT NULL AUTO_INCREMENT,
  `reg_no` varchar(50) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `year` year(4) DEFAULT NULL,
  `status` enum('active','maintenance','disposed') DEFAULT 'active',
  `investment_id` int(11) DEFAULT NULL,
  `purchase_cost` decimal(12,2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `assigned_route` varchar(20) NOT NULL,
  `capacity` varchar(20) NOT NULL,
  `target_daily_revenue` varchar(20) NOT NULL,
  PRIMARY KEY (`vehicle_id`),
  UNIQUE KEY `reg_no` (`reg_no`),
  KEY `investment_id` (`investment_id`),
  CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`investment_id`) REFERENCES `investments` (`investment_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--- TABLE: investments ---
CREATE TABLE `investments` (
  `investment_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `category` enum('farm','vehicle_fleet','petrol_station','apartments','land','other') DEFAULT 'other',
  `reg_no` varchar(50) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `capacity` varchar(50) DEFAULT NULL,
  `assigned_route` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(14,2) DEFAULT NULL,
  `current_value` decimal(14,2) DEFAULT NULL,
  `target_amount` decimal(14,2) DEFAULT 0.00,
  `target_period` enum('daily','monthly','yearly') DEFAULT 'monthly',
  `status` enum('active','disposed','maintenance') DEFAULT 'active',
  `sale_date` date DEFAULT NULL,
  `sale_price` decimal(14,2) DEFAULT NULL,
  `manager_admin_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`investment_id`),
  KEY `manager_admin_id` (`manager_admin_id`),
  CONSTRAINT `investments_ibfk_1` FOREIGN KEY (`manager_admin_id`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
