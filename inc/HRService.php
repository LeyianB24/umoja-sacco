<?php
declare(strict_types=1);
/**
 * HRService.php
 * Enterprise HR Management Service
 * Handles employee lifecycle, onboarding, and HR operations
 */

class HRService {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Create new employee with auto-generated employee number
     */
    public function createEmployee(array $data): array {
        try {
            // Generate employee number: USMS-YYYY-####
            $employeeNo = $this->generateEmployeeNumber();
            
            // Generate company email
            $companyEmail = $this->generateCompanyEmail($data['full_name']);
            
            $stmt = $this->db->prepare("
                INSERT INTO employees (
                    employee_no, full_name, national_id, phone, personal_email, company_email,
                    job_title, grade_id, salary, kra_pin, nssf_no, sha_no,
                    bank_name, bank_account, hire_date, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            
            $stmt->bind_param("sssssssidssssss",
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
            );
            
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            
            $employeeId = $this->db->insert_id;
            
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
            $types = '';
            $values = [];
            
            $allowedFields = ['full_name', 'phone', 'job_title', 'salary', 'status', 'grade_id', 
                             'kra_pin', 'nssf_no', 'sha_no', 'bank_name', 'bank_account'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $types .= in_array($field, ['salary', 'grade_id']) ? 'd' : 's';
                    $values[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                return ['success' => false, 'error' => 'No fields to update'];
            }
            
            $types .= 'i';
            $values[] = $employeeId;
            
            $sql = "UPDATE employees SET " . implode(', ', $fields) . " WHERE employee_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($types, ...$values);
            
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            
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
        $types = '';
        
        if (!empty($filters['status'])) {
            $where[] = "e.status = ?";
            $types .= 's';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(e.full_name LIKE ? OR e.employee_no LIKE ? OR e.national_id LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $types .= 'sss';
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
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Change employee status (active/suspended/terminated)
     */
    public function changeStatus(int $employeeId, string $status, ?int $adminId = null): array {
        try {
            $validStatuses = ['active', 'suspended', 'terminated'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception("Invalid status");
            }
            
            $stmt = $this->db->prepare("UPDATE employees SET status = ? WHERE employee_id = ?");
            $stmt->bind_param("si", $status, $employeeId);
            
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            
            // If terminated, disable system access
            if ($status === 'terminated' && $adminId) {
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
        $stmt = $this->db->prepare("
            UPDATE admins SET status = 'inactive' 
            WHERE admin_id = (SELECT admin_id FROM employees WHERE employee_id = ?)
        ");
        $stmt->bind_param("i", $employeeId);
        $stmt->execute();
    }
    
    /**
     * Generate unique employee number: USMS-YYYY-####
     */
    private function generateEmployeeNumber(): string {
        $year = date('Y');
        $prefix = "USMS-{$year}-";
        
        // Get last employee number for this year
        $stmt = $this->db->prepare("
            SELECT employee_no FROM employees 
            WHERE employee_no LIKE ? 
            ORDER BY employee_no DESC LIMIT 1
        ");
        $pattern = $prefix . '%';
        $stmt->bind_param("s", $pattern);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
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
        $firstName = $parts[0];
        $lastName = end($parts);
        
        return "{$firstName}.{$lastName}@umojasacco.co.ke";
    }
    
    /**
     * Get salary grade details
     */
    public function getSalaryGrade(int $gradeId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM salary_grades WHERE id = ?");
        $stmt->bind_param("i", $gradeId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get all salary grades
     */
    public function getAllGrades(): array {
        $result = $this->db->query("SELECT * FROM salary_grades ORDER BY basic_salary DESC");
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get all job titles
     */
    public function getAllJobTitles(): array {
        $result = $this->db->query("SELECT * FROM job_titles ORDER BY title ASC");
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
