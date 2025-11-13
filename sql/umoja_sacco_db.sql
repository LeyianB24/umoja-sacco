-- -------------------------------------------------------
-- Umoja Drivers Sacco database (full schema + sample rows)
-- -------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `umoja_drivers_sacco` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `umoja_drivers_sacco`;

-- -------------------------------------------------------
-- roles & helper tables (if needed)
-- -------------------------------------------------------
-- (We will store role in admins.role enum but keep roles table for extension if needed)
CREATE TABLE IF NOT EXISTS roles (
  role_id TINYINT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO roles (role_name, description) VALUES
('superadmin','Full system access'),
('manager','Loan & investment manager'),
('accountant','Financial/accounting role'),
('admin','General admin');

-- -------------------------------------------------------
-- members
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS members (
  member_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(30) UNIQUE,
  national_id VARCHAR(50) UNIQUE,
  password VARCHAR(255) DEFAULT NULL,      -- will store bcrypt from PHP password_hash()
  temp_password VARCHAR(100) DEFAULT NULL,  -- temporary plaintext for bootstrap only; remove after hashing
  address VARCHAR(255),
  join_date DATE DEFAULT CURRENT_DATE,
  status ENUM('active','inactive','suspended') DEFAULT 'active',
  profile_pic VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- admins (includes accountant & manager roles)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
  admin_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) DEFAULT NULL,        -- bcrypt hash stored here
  temp_password VARCHAR(100) DEFAULT NULL,   -- temporary plaintext for bootstrap only
  role ENUM('superadmin','manager','accountant','admin') NOT NULL DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- contributions
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS contributions (
  contribution_id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  contribution_type ENUM('savings','shares','welfare','registration','monthly') NOT NULL DEFAULT 'savings',
  amount DECIMAL(12,2) NOT NULL,
  contribution_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  payment_method ENUM('cash','mpesa','bank') DEFAULT 'mpesa',
  reference_no VARCHAR(150),
  created_by_admin INT DEFAULT NULL,
  FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (created_by_admin) REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX (member_id),
  INDEX (contribution_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- loans
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS loans (
  loan_id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  loan_type ENUM('emergency','development','school','asset','business') DEFAULT 'development',
  amount DECIMAL(12,2) NOT NULL,
  interest_rate DECIMAL(5,2) DEFAULT 12.00,
  duration_months INT DEFAULT 12,
  status ENUM('pending','approved','rejected','disbursed','completed','written_off') DEFAULT 'pending',
  application_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  approved_by INT DEFAULT NULL,
  approval_date DATETIME DEFAULT NULL,
  disbursed_amount DECIMAL(12,2) DEFAULT NULL,
  disbursed_date DATETIME DEFAULT NULL,
  notes TEXT,
  FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX (member_id),
  INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- loan_repayments
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS loan_repayments (
  repayment_id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  amount_paid DECIMAL(12,2) NOT NULL,
  payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  payment_method ENUM('cash','mpesa','bank') DEFAULT 'mpesa',
  reference_no VARCHAR(150) DEFAULT NULL,
  remaining_balance DECIMAL(12,2) DEFAULT NULL,
  created_by_admin INT DEFAULT NULL,
  FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (created_by_admin) REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX (loan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- transactions (financial ledger entries)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
  transaction_id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  transaction_type ENUM('contribution','loan_disbursement','repayment','expense','income','share_purchase','welfare_payout') NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  related_id INT DEFAULT NULL, -- references contribution_id, loan_id, repayment_id, property_sale etc depending on type
  transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  payment_channel VARCHAR(80) DEFAULT NULL,
  notes TEXT,
  created_by_admin INT DEFAULT NULL,
  FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (created_by_admin) REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX (member_id),
  INDEX (transaction_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- notifications
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  admin_id INT DEFAULT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- messages (member <-> admin)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
  message_id INT AUTO_INCREMENT PRIMARY KEY,
  from_member_id INT DEFAULT NULL,
  to_admin_id INT DEFAULT NULL,
  from_admin_id INT DEFAULT NULL,
  to_member_id INT DEFAULT NULL,
  subject VARCHAR(150),
  body TEXT,
  is_read TINYINT(1) DEFAULT 0,
  sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (from_member_id) REFERENCES members(member_id) ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (to_member_id) REFERENCES members(member_id) ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (from_admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (to_admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- shares (member-owned sacco shares)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS shares (
  share_id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  share_units INT NOT NULL DEFAULT 0,
  unit_price DECIMAL(10,2) DEFAULT 1.00,
  total_value DECIMAL(12,2) GENERATED ALWAYS AS (share_units * unit_price) VIRTUAL,
  purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- welfare_support (emergency payouts, welfare)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS welfare_support (
  support_id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  granted_by INT DEFAULT NULL,
  date_granted DATETIME DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending','granted','rejected') DEFAULT 'pending',
  FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (granted_by) REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- audit_logs (activity logging)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
  audit_id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT DEFAULT NULL,
  member_id INT DEFAULT NULL,
  action VARCHAR(150) NOT NULL,
  details TEXT,
  ip_address VARCHAR(45),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- investments (generic investments owned by sacco)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS investments (
  investment_id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,              -- e.g. "Kajiado Farm #1", "Umoja Petrol Station"
  category ENUM('farm','vehicle_fleet','petrol_station','apartments','land','other') DEFAULT 'other',
  description TEXT,
  purchase_date DATE DEFAULT NULL,
  purchase_cost DECIMAL(14,2) DEFAULT NULL,
  current_value DECIMAL(14,2) DEFAULT NULL,
  status ENUM('active','disposed','maintenance') DEFAULT 'active',
  manager_admin_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (manager_admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mpesa_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NULL,
  phone VARCHAR(20) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  checkout_request_id VARCHAR(100) NOT NULL,
  status ENUM('pending','success','failed') DEFAULT 'pending',
  receipt VARCHAR(100) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL
);


-- -------------------------------------------------------
-- investment_income (track income from each investment)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS investment_income (
  income_id INT AUTO_INCREMENT PRIMARY KEY,
  investment_id INT NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  income_date DATE DEFAULT CURRENT_DATE,
  description VARCHAR(255),
  recorded_by INT DEFAULT NULL,
  FOREIGN KEY (investment_id) REFERENCES investments(investment_id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- vehicles (matatu fleet details)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS vehicles (
  vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
  reg_no VARCHAR(50) NOT NULL UNIQUE,
  model VARCHAR(100),
  year YEAR,
  status ENUM('active','maintenance','disposed') DEFAULT 'active',
  investment_id INT DEFAULT NULL,   -- optional link to investment row
  purchase_cost DECIMAL(12,2) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (investment_id) REFERENCES investments(investment_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- vehicle_income & expenses
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS vehicle_income (
  vehicle_income_id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  income_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  description VARCHAR(255),
  recorded_by INT DEFAULT NULL,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vehicle_expenses (
  vehicle_expense_id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  expense_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  category VARCHAR(100),
  description VARCHAR(255),
  recorded_by INT DEFAULT NULL,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- properties & property sales (land, apartments, plots)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS properties (
  property_id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  category ENUM('plot','apartment','commercial','other') DEFAULT 'plot',
  location VARCHAR(255),
  size_description VARCHAR(100),
  purchase_cost DECIMAL(14,2) DEFAULT NULL,
  asking_price DECIMAL(14,2) DEFAULT NULL,
  status ENUM('available','sold','development') DEFAULT 'available',
  investment_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (investment_id) REFERENCES investments(investment_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS property_sales (
  sale_id INT AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  buyer_name VARCHAR(150) NOT NULL,
  buyer_contact VARCHAR(80),
  sale_price DECIMAL(14,2) NOT NULL,
  sale_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  recorded_by INT DEFAULT NULL,
  FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- admin_password_reset_tokens (optional)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_reset_tokens (
  token_id INT AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('admin','member') NOT NULL,
  user_id INT NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Seed sample data (admins & a sample member)
-- Note: temp_password is plaintext only for initial import.
-- Run the provided PHP snippet to hash temp_password into password field securely.
-- -------------------------------------------------------

-- Admins: superadmin / manager / accountant
INSERT INTO admins (username, full_name, email, temp_password, role)
VALUES
('superadmin', 'System Super Admin', 'superadmin@umojadrivers.com', 'admin123', 'superadmin'),
('manager', 'Umoja Manager', 'manager@umojadrivers.com', 'manager123', 'manager'),
('accountant', 'Chief Accountant', 'accountant@umojadrivers.com', 'accountant123', 'accountant');

-- Sample member
INSERT INTO members (full_name, email, phone, national_id, temp_password)
VALUES ('John Mwangi', 'john.mwangi@example.com', '+254712345678', '12345678', 'member123');

-- Sample investment assets
INSERT INTO investments (title, category, description, purchase_date, purchase_cost, current_value, status)
VALUES
('Kajiado Farm #1','farm','20-acre horticulture farm in Kajiado','2020-06-10', 3500000, 4200000,'active'),
('Makueni Farm #2','farm','Livestock and fodder farm in Makueni','2019-03-20', 2200000, 2800000,'active'),
('Umoja Petrol Station','petrol_station','Retail fuel station near Umoja estate','2021-09-15', 5000000, 5600000,'active'),
('Umoja Matatu Fleet','vehicle_fleet','Matatu fleet for Umoja route','2018-07-01', 2500000, 3000000,'active'),
('Umoja Apartments Block A','apartments','Rental apartments in Umoja','2019-11-01', 4200000, 4800000,'active');

-- Sample properties
INSERT INTO properties (title, category, location, size_description, asking_price, status)
VALUES
('Plot Kajiado 50','plot','Kajiado County','50x100m', 500000, 'available'),
('Apartment Unit 2B','apartment','Umoja Estate','2 bed, 1 bath', 4500000, 'available');

-- Sample vehicles (part of fleet)
INSERT INTO vehicles (reg_no, model, year, investment_id, purchase_cost, status)
VALUES
('KBG-123A','Toyota Coaster',2017,4, 800000, 'active'),
('KCF-456B','Isuzu Forward',2018,4, 600000, 'active');

-- Sample contributions
INSERT INTO contributions (member_id, contribution_type, amount, payment_method, reference_no, created_by_admin)
VALUES
(1,'savings',5000,'mpesa','MPESA123',2),
(1,'shares',2000,'mpesa','MPESA124',2);

-- Sample loan
INSERT INTO loans (member_id, loan_type, amount, interest_rate, duration_months, status, notes)
VALUES
(1,'development',150000,10.00,24,'pending','Loan for small business expansion');

-- Sample investment_income
INSERT INTO investment_income (investment_id, amount, income_date, description, recorded_by)
VALUES
(3,45000,'2024-08-01','Monthly station commissions',2);

-- sample audit log entry
INSERT INTO audit_logs (admin_id, action, details, ip_address)
VALUES (2,'seed_import','Initial seed data import','127.0.0.1');

-- -------------------------------------------------------
-- Useful Indexes (improve reporting queries)
-- -------------------------------------------------------
CREATE INDEX idx_loans_member_status ON loans (member_id, status);
CREATE INDEX idx_contrib_member_date ON contributions (member_id, contribution_date);
CREATE INDEX idx_transactions_date ON transactions (transaction_date);

-- -------------------------------------------------------
-- End of SQL script
-- -------------------------------------------------------
