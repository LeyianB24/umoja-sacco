-- sql/kyc_schema.sql
-- Table to store member KYC documents
-- Linked to members table

CREATE TABLE IF NOT EXISTS `member_documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `document_type` enum('national_id_front', 'national_id_back', 'passport_photo', 'kra_pin', 'signature', 'other') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending', 'verified', 'rejected') DEFAULT 'pending',
  `verification_notes` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`document_id`),
  KEY `member_id` (`member_id`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `docs_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `docs_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
