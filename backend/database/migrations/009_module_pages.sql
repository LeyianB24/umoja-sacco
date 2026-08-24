-- Migration 009: Module Page Mapping
-- This allows linking specific PHP files/URL paths to modules for automatic permission checking

CREATE TABLE IF NOT EXISTS system_module_pages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_id INT NOT NULL,
    page_path VARCHAR(255) NOT NULL UNIQUE COMMENT 'Relative path from web root, e.g. admin/pages/users.php',
    is_entry_point BOOLEAN DEFAULT FALSE COMMENT 'Whether this is the main page for the module',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES system_modules(module_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed some obvious ones
INSERT IGNORE INTO system_module_pages (module_id, page_path, is_entry_point) 
SELECT module_id, 'admin/pages/dashboard.php', 1 FROM system_modules WHERE module_slug = 'dashboard';

INSERT IGNORE INTO system_module_pages (module_id, page_path, is_entry_point) 
SELECT module_id, 'admin/pages/members.php', 1 FROM system_modules WHERE module_slug = 'members';

INSERT IGNORE INTO system_module_pages (module_id, page_path, is_entry_point) 
SELECT module_id, 'admin/pages/loans.php', 1 FROM system_modules WHERE module_slug = 'loans';

INSERT IGNORE INTO system_module_pages (module_id, page_path, is_entry_point) 
SELECT module_id, 'admin/pages/shares.php', 1 FROM system_modules WHERE module_slug = 'shares';

INSERT IGNORE INTO system_module_pages (module_id, page_path, is_entry_point) 
SELECT module_id, 'admin/pages/finance.php', 1 FROM system_modules WHERE module_slug = 'finance';

-- Support tickets
INSERT IGNORE INTO system_module_pages (module_id, page_path, is_entry_point) 
SELECT module_id, 'admin/pages/support.php', 1 FROM system_modules WHERE module_slug = 'support';
