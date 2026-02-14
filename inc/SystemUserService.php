<?php
declare(strict_types=1);

/**
 * SystemUserService - Admin Account & Access Control Management
 * Handles system user creation, role assignment, and permission management
 */
class SystemUserService {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Create System User (Admin) for Employee
     */
    public function createSystemUser(array $employeeData, int $roleId): array {
        // Validate required fields
        if (empty($employeeData['employee_no']) || empty($employeeData['company_email']) || empty($employeeData['full_name'])) {
            return ['success' => false, 'error' => 'Missing required employee data'];
        }
        
        // Check if username already exists
        $stmt = $this->db->prepare("SELECT admin_id FROM admins WHERE username = ?");
        $stmt->bind_param("s", $employeeData['employee_no']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'error' => 'Username already exists'];
        }
        
        // Default password = employee number
        $password = password_hash($employeeData['employee_no'], PASSWORD_DEFAULT);
        
        $this->db->begin_transaction();
        
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO admins 
                 (username, password, email, full_name, role_id, is_active, created_at) 
                 VALUES (?, ?, ?, ?, ?, 1, NOW())"
            );
            
            $stmt->bind_param(
                "ssssi",
                $employeeData['employee_no'],
                $password,
                $employeeData['company_email'],
                $employeeData['full_name'],
                $roleId
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create system user: " . $stmt->error);
            }
            
            $adminId = $this->db->insert_id;
            
            // Log creation
            $this->logAudit(
                $_SESSION['admin_id'] ?? 0,
                'create_system_user',
                'admin',
                $adminId,
                "Created system user for {$employeeData['full_name']}"
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'admin_id' => $adminId,
                'username' => $employeeData['employee_no'],
                'default_password' => $employeeData['employee_no']
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Update System User (Admin) with Sync
     */
    public function updateSystemUser(int $adminId, array $data): array {
        $this->db->begin_transaction();
        
        try {
            // Update admin record
            $stmt = $this->db->prepare(
                "UPDATE admins SET full_name = ?, email = ?, updated_at = NOW() WHERE admin_id = ?"
            );
            $stmt->bind_param("ssi", $data['full_name'], $data['email'], $adminId);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update admin: " . $stmt->error);
            }
            
            // Sync to employee record if linked
            // (Assuming 1:1 relationship where employee.admin_id = admin.admin_id)
            $stmt = $this->db->prepare(
                "UPDATE employees SET full_name = ?, company_email = ? WHERE admin_id = ?"
            );
            $stmt->bind_param("ssi", $data['full_name'], $data['email'], $adminId);
            $stmt->execute();
            
            // Log update
            $this->logAudit(
                $_SESSION['admin_id'] ?? 0,
                'update_system_user',
                'admin',
                $adminId,
                "Updated system user: {$data['full_name']}"
            );
            
            $this->db->commit();
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Assign or Update Role
     */
    public function assignRole(int $adminId, int $roleId): array {
        $stmt = $this->db->prepare(
            "UPDATE admins SET role_id = ?, updated_at = NOW() WHERE admin_id = ?"
        );
        $stmt->bind_param("ii", $roleId, $adminId);
        
        if ($stmt->execute()) {
            // Log role change
            $this->logAudit(
                $_SESSION['admin_id'] ?? 0,
                'assign_role',
                'admin',
                $adminId,
                "Assigned role ID: {$roleId}"
            );
            
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => $stmt->error];
    }
    
    /**
     * Reset Password
     */
    public function resetPassword(int $adminId, string $newPassword): array {
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'error' => 'Password must be at least 6 characters'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare(
            "UPDATE admins SET password = ?, updated_at = NOW() WHERE admin_id = ?"
        );
        $stmt->bind_param("si", $hashedPassword, $adminId);
        
        if ($stmt->execute()) {
            $this->logAudit(
                $_SESSION['admin_id'] ?? 0,
                'reset_password',
                'admin',
                $adminId,
                "Password reset for admin ID: {$adminId}"
            );
            
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => $stmt->error];
    }
    
    /**
     * Disable Account (for terminated employees)
     */
    public function disableAccount(int $adminId): array {
        $stmt = $this->db->prepare(
            "UPDATE admins SET is_active = 0, updated_at = NOW() WHERE admin_id = ?"
        );
        $stmt->bind_param("i", $adminId);
        
        if ($stmt->execute()) {
            $this->logAudit(
                $_SESSION['admin_id'] ?? 0,
                'disable_account',
                'admin',
                $adminId,
                "Disabled admin account ID: {$adminId}"
            );
            
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => $stmt->error];
    }
    
    /**
     * Enable Account
     */
    public function enableAccount(int $adminId): array {
        $stmt = $this->db->prepare(
            "UPDATE admins SET is_active = 1, updated_at = NOW() WHERE admin_id = ?"
        );
        $stmt->bind_param("i", $adminId);
        
        if ($stmt->execute()) {
            $this->logAudit(
                $_SESSION['admin_id'] ?? 0,
                'enable_account',
                'admin',
                $adminId,
                "Enabled admin account ID: {$adminId}"
            );
            
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => $stmt->error];
    }
    
    /**
     * Get Role Matrix (all roles with permissions)
     */
    public function getRoleMatrix(): array {
        $sql = "SELECT r.id, r.name, r.description, 
                GROUP_CONCAT(p.name SEPARATOR ', ') as permissions
                FROM roles r
                LEFT JOIN role_permissions rp ON r.id = rp.role_id
                LEFT JOIN permissions p ON rp.permission_id = p.id
                GROUP BY r.id
                ORDER BY r.id ASC";
        
        $result = $this->db->query($sql);
        $roles = [];
        
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
        
        return $roles;
    }
    
    /**
     * Get Role ID for Job Title (with mapping table)
     */
    public function getRoleIdForTitle(string $jobTitle): int {
        $stmt = $this->db->prepare(
            "SELECT role_id FROM job_role_mapping WHERE job_title = ?"
        );
        $stmt->bind_param("s", $jobTitle);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        // MASTER ENFORCEMENT: Strict Role Mapping
        // Must return 0 or null if no mapping exists to block creation
        return $result ? (int)$result['role_id'] : 0;
    }
    
    /**
     * Get System Users with filters
     */
    public function getSystemUsers(array $filters = []): array {
        $where = [];
        $params = [];
        $types = "";
        
        if (!empty($filters['role_id'])) {
            $where[] = "a.role_id = ?";
            $params[] = $filters['role_id'];
            $types .= "i";
        }
        
        if (!empty($filters['is_active']) && $filters['is_active'] !== 'all') {
            $where[] = "a.is_active = ?";
            $params[] = ($filters['is_active'] === 'active') ? 1 : 0;
            $types .= "i";
        }
        
        if (!empty($filters['search'])) {
            $searchParam = "%{$filters['search']}%";
            $where[] = "(a.full_name LIKE ? OR a.username LIKE ? OR a.email LIKE ?)";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "sss";
        }
        
        $whereSQL = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
        
        $sql = "SELECT a.*, r.name as role_name, e.employee_no 
                FROM admins a 
                LEFT JOIN roles r ON a.role_id = r.id 
                LEFT JOIN employees e ON a.admin_id = e.admin_id 
                {$whereSQL} 
                ORDER BY a.full_name ASC";
        
        if (count($params) > 0) {
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->db->query($sql);
        }
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        return $users;
    }
    
    /**
     * Log audit trail
     */
    private function logAudit(int $adminId, string $action, string $entityType, int $entityId, string $details): void {
        $stmt = $this->db->prepare(
            "INSERT INTO audit_logs 
             (admin_id, action, entity_type, entity_id, details, ip_address, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt->bind_param("ississ", $adminId, $action, $entityType, $entityId, $details, $ip);
        $stmt->execute();
    }
}
?>
