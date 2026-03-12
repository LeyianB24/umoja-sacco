-- Migration 008: Module Architecture and Permission Refactor
-- This migration implements the system_modules and admin_module_permissions tables

-- 1. System Modules (The "Features" of the app)
CREATE TABLE IF NOT EXISTS system_modules (
    module_id INT PRIMARY KEY AUTO_INCREMENT,
    module_name VARCHAR(100) NOT NULL UNIQUE,
    module_slug VARCHAR(50) NOT NULL UNIQUE COMMENT 'e.g. member_management, loans, financial',
    module_icon VARCHAR(50) DEFAULT 'bi-box',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Module Permissions (RBAC Layer)
-- This replaces/complements role_permissions for module-level access
CREATE TABLE IF NOT EXISTS admin_module_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    module_id INT NOT NULL,
    can_view BOOLEAN DEFAULT FALSE,
    can_create BOOLEAN DEFAULT FALSE,
    can_edit BOOLEAN DEFAULT FALSE,
    can_delete BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_role_module (role_id, module_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES system_modules(module_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Base Modules Seeding
INSERT IGNORE INTO system_modules (module_name, module_slug, module_icon) VALUES
('Dashboard', 'dashboard', 'bi-speedometer2'),
('Member Management', 'members', 'bi-people'),
('Loan Management', 'loans', 'bi-bank'),
('Shares & Dividends', 'shares', 'bi-pie-chart'),
('Financial Management', 'finance', 'bi-cash-coin'),
('Savings & Welfare', 'savings', 'bi-piggy-bank'),
('Payroll & HR', 'payroll', 'bi-person-badge'),
('System Settings', 'settings', 'bi-gear'),
('Support Tickets', 'support', 'bi-headset');

-- 4. Audit Log for Module Changes
CREATE TABLE IF NOT EXISTS module_audit_trail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT,
    action VARCHAR(50),
    module_id INT,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Add module_id to support_tickets for automatic routing
ALTER TABLE support_tickets 
ADD COLUMN module_id INT AFTER support_id,
ADD INDEX idx_module_id (module_id),
ADD FOREIGN KEY (module_id) REFERENCES system_modules(module_id) ON DELETE SET NULL;
