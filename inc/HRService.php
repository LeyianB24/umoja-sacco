<?php
declare(strict_types=1);

/**
 * HRService - Enterprise HR Management Service
 * Handles all employee-related business logic with security-first approach
 */
class HRService {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Generate Smart Employee Number
     * Format: USMS-{YYYY}-{SEQ} 
     * Example: USMS-2026-0001
     */
    public function generateEmployeeNo(): string {
        $year = date('Y');
        $prefix = "USMS-$year-";
        
        // Get the latest employee number for this year
        $stmt = $this->db->prepare("SELECT employee_no FROM employees WHERE employee_no LIKE CONCAT(?, '%') ORDER BY employee_no DESC LIMIT 1");
        $stmt->bind_param("s", $prefix);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Extract sequence
            $lastSeq = (int)substr($row['employee_no'], -4);
            $newSeq = $lastSeq + 1;
        } else {
            $newSeq = 1;
        }
        
        return $prefix . str_pad((string)$newSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate Professional Company Email
     * Format: firstname.lastname@umojadrivers.co.ke
     * Handles duplicates by appending numbers
     */
    public function generateEmail($fullName): string {
        // Clean name: remove special chars, extra spaces
        $cleanName = strtolower(trim(preg_replace('/[^a-zA-Z0-9 ]/', '', $fullName)));
        $parts = explode(' ', $cleanName);
        
        if (count($parts) >= 2) {
            $base = $parts[0] . '.' . end($parts);
        } else {
            $base = $parts[0];
        }
        
        $domain = 'umojadrivers.co.ke';
        $email = $base . '@' . $domain;
        $counter = 1;

        // Check for duplicates in both employees and admins tables
        while ($this->emailExists($email)) {
            $counter++;
            $email = $base . $counter . '@' . $domain;
        }
        
        return $email;
    }

    private function emailExists($email): bool {
        // Check employees
        $stmt = $this->db->prepare("SELECT 1 FROM employees WHERE company_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) return true;

        // Check admins
        $stmt = $this->db->prepare("SELECT 1 FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) return true;

        return false;
    }
    
    /**
     * Create Employee with full validation and audit trail
     */
    public function createEmployee(array $data): array {
        // MASTER ENFORCEMENT: Strict Validation of Critical Fields
        $required = [
            'full_name', 'national_id', 'phone', 'job_title', 'grade_id', 'salary', 
            'kra_pin', 'nssf_no', 'nhif_no', 'hire_date'
        ];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'error' => "Missing critical field: " . ucwords(str_replace('_', ' ', $field))];
            }
        }
        
        // Validate Salary
        if (!is_numeric($data['salary']) || $data['salary'] <= 0) {
            return ['success' => false, 'error' => 'Invalid Salary: Must be greater than zero'];
        }
        
        // Validate Email
        if (!empty($data['personal_email']) && !filter_var($data['personal_email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid personal email format'];
        }
        
        // Validate Statutory IDs
        if (!$this->validateKRA($data['kra_pin'])) {
            return ['success' => false, 'error' => 'Invalid KRA PIN format'];
        }
        if (!$this->validateNSSF($data['nssf_no'])) {
            return ['success' => false, 'error' => 'Invalid NSSF Number format'];
        }
        if (!$this->validateNHIF($data['nhif_no'])) {
            return ['success' => false, 'error' => 'Invalid NHIF Number format'];
        }
        
        // Generate identity
        $employeeNo = $this->generateEmployeeNo();
        $companyEmail = $this->generateEmail($data['full_name']);
        
        // Begin transaction
        $this->db->begin_transaction();
        
        try {
            // Insert employee
            $stmt = $this->db->prepare(
                "INSERT INTO employees 
                (employee_no, full_name, national_id, phone, company_email, personal_email, 
                 job_title, grade_id, salary, kra_pin, nssf_no, nhif_no, 
                 bank_name, bank_account, hire_date, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())"
            );
            
            $personalEmail = $data['personal_email'] ?? null;
            $kraPin = $data['kra_pin'] ?? null;
            $nssfNo = $data['nssf_no'] ?? null;
            $nhifNo = $data['nhif_no'] ?? null;
            $bankName = $data['bank_name'] ?? null;
            $bankAccount = $data['bank_account'] ?? null;

            $stmt->bind_param(
                "sssssssidssssss",
                $employeeNo,
                $data['full_name'],
                $data['national_id'],
                $data['phone'],
                $companyEmail,
                $personalEmail,
                $data['job_title'],
                $data['grade_id'],
                $data['salary'],
                $kraPin,
                $nssfNo,
                $nhifNo,
                $bankName,
                $bankAccount,
                $data['hire_date']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create employee: " . $stmt->error);
            }
            
            $employeeId = $this->db->insert_id;
            
            $this->db->commit();
            
            return [
                'success' => true,
                'employee_id' => $employeeId,
                'employee_no' => $employeeNo,
                'company_email' => $companyEmail
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Update Employee with sync to admin table
     */
    public function updateEmployee(int $employeeId, array $data): array {
        // Get current employee data for audit
        $current = $this->getEmployeeById($employeeId);
        if (!$current) {
            return ['success' => false, 'error' => 'Employee not found'];
        }
        
        $this->db->begin_transaction();
        
        try {
            // Update employee
            $stmt = $this->db->prepare(
                "UPDATE employees SET 
                 full_name = ?, phone = ?, job_title = ?, 
                 salary = ?, status = ?, updated_at = NOW()
                 WHERE employee_id = ?"
            );
            
            $stmt->bind_param(
                "sssdsi",
                $data['full_name'],
                $data['phone'],
                $data['job_title'],
                $data['salary'],
                $data['status'],
                $employeeId
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update employee: " . $stmt->error);
            }
            
            // Sync name to admin table if linked
            if ($current['admin_id']) {
                $this->syncAdminName($current['admin_id'], $data['full_name']);
            }
            
            // Record salary change if different
            if ($data['salary'] != $current['salary']) {
                $this->recordSalaryChange(
                    $employeeId,
                    $current['salary'],
                    $data['salary'],
                    $_SESSION['admin_id'] ?? 0,
                    'Manual update'
                );
            }
            
            $this->db->commit();
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Terminate Employee
     */
    public function terminateEmployee(int $employeeId, string $reason, int $adminId): array {
        $employee = $this->getEmployeeById($employeeId);
        if (!$employee) {
            return ['success' => false, 'error' => 'Employee not found'];
        }
        
        $this->db->begin_transaction();
        
        try {
            // Update employee status
            $stmt = $this->db->prepare(
                "UPDATE employees SET status = 'terminated', updated_at = NOW() 
                 WHERE employee_id = ?"
            );
            $stmt->bind_param("i", $employeeId);
            $stmt->execute();
            
            // Disable admin account if exists
            if ($employee['admin_id']) {
                $stmt = $this->db->prepare(
                    "UPDATE admins SET is_active = 0 WHERE admin_id = ?"
                );
                $stmt->bind_param("i", $employee['admin_id']);
                $stmt->execute();
            }
            
            // Log termination
            $stmt = $this->db->prepare(
                "INSERT INTO audit_logs 
                 (admin_id, action, entity_type, entity_id, details, ip_address, created_at) 
                 VALUES (?, 'terminate_employee', 'employee', ?, ?, ?, NOW())"
            );
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $details = "Terminated: {$employee['full_name']}. Reason: {$reason}";
            $stmt->bind_param("iiss", $adminId, $employeeId, $details, $ip);
            $stmt->execute();
            
            $this->db->commit();
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get Employee by ID
     */
    public function getEmployeeById(int $employeeId): ?array {
        $stmt = $this->db->prepare(
            "SELECT e.*, r.name as admin_role, sg.grade_name 
             FROM employees e 
             LEFT JOIN admins a ON e.admin_id = a.admin_id 
             LEFT JOIN roles r ON a.role_id = r.id 
             LEFT JOIN salary_grades sg ON e.grade_id = sg.id 
             WHERE e.employee_id = ?"
        );
        $stmt->bind_param("i", $employeeId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result ?: null;
    }
    
    /**
     * Sync employee name to admin table
     */
    public function syncAdminName(int $adminId, string $fullName): bool {
        $stmt = $this->db->prepare(
            "UPDATE admins SET full_name = ? WHERE admin_id = ?"
        );
        $stmt->bind_param("si", $fullName, $adminId);
        return $stmt->execute();
    }
    
    /**
     * Record salary change history
     */
    private function recordSalaryChange(int $employeeId, float $oldSalary, float $newSalary, int $changedBy, string $reason): void {
        $stmt = $this->db->prepare(
            "INSERT INTO employee_salary_history 
             (employee_id, old_salary, new_salary, reason, effective_date, changed_by, created_at) 
             VALUES (?, ?, ?, ?, CURDATE(), ?, NOW())"
        );
        $stmt->bind_param("iddsi", $employeeId, $oldSalary, $newSalary, $reason, $changedBy);
        $stmt->execute();
    }
    
    /**
     * Validate KRA PIN (Kenya format: A000000000A)
     */
    public function validateKRA(string $kra): bool {
        return preg_match('/^[A-Z]\d{9}[A-Z]$/', strtoupper($kra)) === 1;
    }
    
    /**
     * Validate NSSF Number
     */
    public function validateNSSF(string $nssf): bool {
        return preg_match('/^\d{9,12}$/', $nssf) === 1;
    }
    
    /**
     * Validate NHIF Number
     */
    public function validateNHIF(string $nhif): bool {
        return preg_match('/^\d{8,11}$/', $nhif) === 1;
    }
    
    /**
     * Get employees with filters (secure search)
     */
    public function getEmployees(array $filters = []): array {
        $where = [];
        $params = [];
        $types = "";
        
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = "e.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }
        
        if (!empty($filters['search'])) {
            $searchParam = "%{$filters['search']}%";
            $where[] = "(e.full_name LIKE ? OR e.national_id LIKE ? OR e.employee_no LIKE ?)";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "sss";
        }
        
        $whereSQL = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
        
        $sql = "SELECT e.*, r.name as admin_role, sg.grade_name 
                FROM employees e 
                LEFT JOIN admins a ON e.admin_id = a.admin_id 
                LEFT JOIN roles r ON a.role_id = r.id 
                LEFT JOIN salary_grades sg ON e.grade_id = sg.id 
                {$whereSQL} 
                ORDER BY e.full_name ASC";
        
        if (count($params) > 0) {
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->db->query($sql);
        }
        
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        
        return $employees;
    }
    /**
     * Terminate Employee
     */
    public function terminateStaff(int $employeeId, string $reason, int $adminId): array {
        $employee = $this->getEmployeeById($employeeId);
        if (!$employee) {
            return ['success' => false, 'error' => 'Employee not found'];
        }
        
        $this->db->begin_transaction();
        
        try {
            // Update employee status
            $stmt = $this->db->prepare(
                "UPDATE employees SET status = 'terminated', updated_at = NOW() 
                 WHERE employee_id = ?"
            );
            $stmt->bind_param("i", $employeeId);
            $stmt->execute();
            
            // Disable admin account if exists
            if (!empty($employee['admin_id'])) {
                $stmt = $this->db->prepare(
                    "UPDATE admins SET is_active = 0 WHERE admin_id = ?"
                );
                $stmt->bind_param("i", $employee['admin_id']);
                $stmt->execute();
            }
            
            // Log termination (using SystemUserService if available, or manual log)
            // For now, minimal log via audit_logs table direct insert if SystemUserService not injected
            // Assuming access to audit_logs table
            $stmt = $this->db->prepare(
                "INSERT INTO audit_logs 
                 (admin_id, action, entity_type, entity_id, details, ip_address, created_at) 
                 VALUES (?, 'terminate_employee', 'employee', ?, ?, ?, NOW())"
            );
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $details = "Terminated: {$employee['full_name']}. Reason: {$reason}";
            $stmt->bind_param("iiss", $adminId, $employeeId, $details, $ip);
            $stmt->execute();
            
            $this->db->commit();
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    /**
     * Validate Employee Data Integrity for Payroll
     * Checks if employee has all required fields for payroll processing
     */
    public function validateEmployeeIntegrity(int $employeeId): array {
        $employee = $this->getEmployeeById($employeeId);
        if (!$employee) {
            return ['isValid' => false, 'errors' => ['Employee not found']];
        }
        
        $errors = [];
        
        // 1. Check Salary
        if ($employee['salary'] <= 0) {
            $errors[] = "Salary is zero or negative";
        }
        
        // 2. Check Statutory IDs
        if (empty($employee['kra_pin'])) $errors[] = "Missing KRA PIN";
        if (empty($employee['nssf_no'])) $errors[] = "Missing NSSF Number";
        if (empty($employee['nhif_no'])) $errors[] = "Missing NHIF Number";
        
        // 3. Check Bank Details (Optional but recommended for payroll)
        // We might enforce this if payment method is bank
        // if (empty($employee['bank_account'])) $errors[] = "Missing Bank Account";
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors,
            'employee' => $employee
        ];
    }
}
?>
