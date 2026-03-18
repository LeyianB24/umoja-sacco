<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use PDO;

/**
 * USMS\Services\EmployeeService
 * Handles core employee logic, numbering, and system integration.
 */
class EmployeeService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }

    /**
     * Generate next Employee Number: UDS-EMP-YYYY-XXXX
     */
    public function generateEmployeeNo(): string {
        $year = date('Y');
        $prefix = "UDS-EMP-$year-";
        
        $stmt = $this->db->prepare("SELECT employee_no FROM employees WHERE employee_no LIKE ? ORDER BY employee_no DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetch();
        
        if ($last) {
            $seq = intval(substr($last['employee_no'], -4));
            $next = $seq + 1;
        } else {
            $next = 1;
        }
        
        return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate Company Email: firstname.lastname@umojasacco.co.ke
     */
    public function generateEmail(string $fullName): string {
        $parts = explode(' ', strtolower(trim($fullName)));
        $fname = $parts[0] ?? 'employee';
        $lname = end($parts) ?? 'staff';
        $base = "$fname.$lname@umojasacco.co.ke";
        
        $email = $base;
        $count = 1;
        
        while (true) {
            $stmt = $this->db->prepare("SELECT 1 FROM employees WHERE company_email = ?");
            $stmt->execute([$email]);
            if (!$stmt->fetch()) break;
            
            $email = "$fname.$lname$count@umojasacco.co.ke";
            $count++;
        }
        
        return $email;
    }

    /**
     * Map Job Title to Role ID
     */
    public function getRoleIdForTitle(string $title): int {
        $stmt = $this->db->prepare("SELECT role_id FROM job_titles WHERE title = ?");
        $stmt->execute([$title]);
        $res = $stmt->fetch();
        
        if ($res) {
            return (int)$res['role_id'];
        }
        
        // Fallback: Default to Staff (Role ID 2)
        return 2; 
    }

    /**
     * Create System User (Admin) for Employee
     */
    public function createSystemUser(array $empData, int $roleId): int|bool {
        $password = password_hash($empData['employee_no'], PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("INSERT INTO admins (username, password, email, full_name, role_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $success = $stmt->execute([
            $empData['employee_no'], 
            $password, 
            $empData['company_email'], 
            $empData['full_name'], 
            $roleId
        ]);
        
        if ($success) {
            return (int)$this->db->lastInsertId();
        }
        return false;
    }
}
