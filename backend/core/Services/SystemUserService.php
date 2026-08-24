<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use Exception;
use PDO;

/**
 * USMS\Services\SystemUserService
 * System Access & RBAC Management
 * Handles creation of system users from employees and role assignment.
 */
class SystemUserService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }

    /**
     * Create system user from employee
     */
    public function createSystemUser(array $userData, int $roleId): array {
        try {
            $username = $userData['employee_no'];
            $email = $userData['company_email'];
            $fullName = $userData['full_name'];
            
            // Default password is employee number
            $password = password_hash($username, PASSWORD_BCRYPT);
            
            // Check if username already exists
            $stmt = $this->db->prepare("SELECT admin_id FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                throw new Exception("System user already exists");
            }
            
            // Create admin account
            $stmt = $this->db->prepare("
                INSERT INTO admins (username, password, email, full_name, role_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([$username, $password, $email, $fullName, $roleId]);
            $adminId = (int)$this->db->lastInsertId();
            
            return [
                'success' => true,
                'admin_id' => $adminId,
                'username' => $username,
                'default_password' => $userData['employee_no']
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get role ID for a job title based on mapping
     */
    public function getRoleIdForTitle(string $jobTitle): int {
        $stmt = $this->db->prepare("
            SELECT role_id FROM job_role_mapping WHERE job_title = ? LIMIT 1
        ");
        $stmt->execute([$jobTitle]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['role_id'] : 0;
    }

    /**
     * Assign role to existing system user
     */
    public function assignRole(int $adminId, int $roleId): array {
        try {
            $stmt = $this->db->prepare("UPDATE admins SET role_id = ? WHERE admin_id = ?");
            $stmt->execute([$roleId, $adminId]);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update system user details
     */
    public function updateSystemUser(int $adminId, array $data): array {
        try {
            $fields = [];
            $values = [];
            
            if (isset($data['full_name'])) {
                $fields[] = "full_name = ?";
                $values[] = $data['full_name'];
            }
            
            if (isset($data['email'])) {
                $fields[] = "email = ?";
                $values[] = $data['email'];
            }
            
            if (empty($fields)) {
                return ['success' => false, 'error' => 'No fields to update'];
            }
            
            $values[] = $adminId;
            $sql = "UPDATE admins SET " . implode(', ', $fields) . " WHERE admin_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            
            // Sync to employee record
            $this->syncToEmployee($adminId, $data);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync admin changes back to employee record
     */
    private function syncToEmployee(int $adminId, array $data): void {
        if (isset($data['full_name'])) {
            $stmt = $this->db->prepare("UPDATE employees SET full_name = ? WHERE admin_id = ?");
            $stmt->execute([$data['full_name'], $adminId]);
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(int $adminId, string $newPassword): array {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
            $stmt->execute([$hashedPassword, $adminId]);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get system users with filters
     */
    public function getSystemUsers(array $filters = []): array {
        $where = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where[] = "(full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        if (isset($filters['is_active']) && $filters['is_active'] !== 'all') {
            $where[] = "status = ?";
            $params[] = $filters['is_active'] ? 'active' : 'inactive';
        }
        
        $sql = "SELECT a.*, r.name as role_name, e.employee_no
                FROM admins a
                LEFT JOIN roles r ON a.role_id = r.id
                LEFT JOIN employees e ON a.admin_id = e.admin_id
                " . (!empty($where) ? "WHERE " . implode(' AND ', $where) : "") . "
                ORDER BY a.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Disable system access (for terminated employees)
     */
    public function disableAccess(int $adminId): array {
        try {
            $stmt = $this->db->prepare("UPDATE admins SET status = 'inactive' WHERE admin_id = ?");
            $stmt->execute([$adminId]);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Enable system access
     */
    public function enableAccess(int $adminId): array {
        try {
            $stmt = $this->db->prepare("UPDATE admins SET status = 'active' WHERE admin_id = ?");
            $stmt->execute([$adminId]);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
