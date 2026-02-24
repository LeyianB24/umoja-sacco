<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use Exception;
use PDO;

/**
 * USMS\Services\HRService
 * Enterprise HR Management Service
 * Handles employee lifecycle, onboarding, and HR operations.
 */
class HRService {
    
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }

    /**
     * Create new employee with auto-generated employee number
     */
    public function createEmployee(array $data): array {
        try {
            $employeeNo = $this->generateEmployeeNumber();
            $companyEmail = $this->generateCompanyEmail($data['full_name']);
            
            $sql = "INSERT INTO employees (
                        employee_no, full_name, national_id, phone, personal_email, company_email,
                        job_title, grade_id, salary, kra_pin, nssf_no, sha_no,
                        bank_name, bank_account, hire_date, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $employeeNo,
                $data['full_name'],
                $data['national_id'],
                $data['phone'],
                $data['personal_email'] ?? null,
                $companyEmail,
                $data['job_title'],
                $data['grade_id'],
                $data['salary'],
                $data['kra_pin'] ?? null,
                $data['nssf_no'] ?? null,
                $data['sha_no'] ?? null,
                $data['bank_name'] ?? null,
                $data['bank_account'] ?? null,
                $data['hire_date']
            ]);
            
            $employeeId = (int)$this->db->lastInsertId();
            
            return [
                'success' => true,
                'employee_id' => $employeeId,
                'employee_no' => $employeeNo,
                'company_email' => $companyEmail
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update employee details
     */
    public function updateEmployee(int $employeeId, array $data): array {
        try {
            $fields = [];
            $values = [];
            
            $allowedFields = ['full_name', 'phone', 'job_title', 'salary', 'status', 'grade_id', 
                             'kra_pin', 'nssf_no', 'sha_no', 'bank_name', 'bank_account'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                return ['success' => false, 'error' => 'No fields to update'];
            }
            
            $values[] = $employeeId;
            $sql = "UPDATE employees SET " . implode(', ', $fields) . " WHERE employee_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get employees with optional filters
     */
    public function getEmployees(array $filters = []): array {
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "e.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(e.full_name LIKE ? OR e.employee_no LIKE ? OR e.national_id LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql = "SELECT e.*, g.grade_name, g.basic_salary as grade_basic
                FROM employees e
                LEFT JOIN salary_grades g ON e.grade_id = g.id
                " . (!empty($where) ? "WHERE " . implode(' AND ', $where) : "") . "
                ORDER BY e.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Change employee status
     */
    public function changeStatus(int $employeeId, string $status, ?int $adminId = null): array {
        try {
            $validStatuses = ['active', 'suspended', 'terminated'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception("Invalid status");
            }
            
            $stmt = $this->db->prepare("UPDATE employees SET status = ? WHERE employee_id = ?");
            $stmt->execute([$status, $employeeId]);
            
            if ($status === 'terminated') {
                $this->disableSystemAccess($employeeId);
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Disable system access for terminated employee
     */
    private function disableSystemAccess(int $employeeId): void {
        $sql = "UPDATE admins SET status = 'inactive' 
                WHERE admin_id = (SELECT admin_id FROM employees WHERE employee_id = ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId]);
    }

    /**
     * Generate unique employee number: USMS-YYYY-####
     */
    private function generateEmployeeNumber(): string {
        $year = date('Y');
        $prefix = "USMS-{$year}-";
        
        $stmt = $this->db->prepare("
            SELECT employee_no FROM employees 
            WHERE employee_no LIKE ? 
            ORDER BY employee_no DESC LIMIT 1
        ");
        $stmt->execute([$prefix . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $lastNum = (int)substr($result['employee_no'], -4);
            $newNum = $lastNum + 1;
        } else {
            $newNum = 1;
        }
        
        return $prefix . str_pad((string)$newNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate company email from full name
     */
    private function generateCompanyEmail(string $fullName): string {
        $parts = explode(' ', strtolower(trim($fullName)));
        $firstName = $parts[0] ?? 'employee';
        $lastName = end($parts) ?? 'staff';
        
        return "{$firstName}.{$lastName}@umojasacco.co.ke";
    }

    /**
     * Get salary grade details
     */
    public function getSalaryGrade(int $gradeId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM salary_grades WHERE id = ?");
        $stmt->execute([$gradeId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Get all salary grades
     */
    public function getAllGrades(): array {
        $stmt = $this->db->query("SELECT * FROM salary_grades ORDER BY basic_salary DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all job titles
     */
    public function getAllJobTitles(): array {
        $stmt = $this->db->query("SELECT * FROM job_titles ORDER BY title ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
