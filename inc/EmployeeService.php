<?php
// inc/EmployeeService.php

class EmployeeService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Generate next Employee Number: UDS-EMP-YYYY-XXXX
     */
    public function generateEmployeeNo() {
        $year = date('Y');
        $prefix = "UDS-EMP-$year-";
        
        $stmt = $this->db->query("SELECT employee_no FROM employees WHERE employee_no LIKE '$prefix%' ORDER BY employee_no DESC LIMIT 1");
        $last = $stmt->fetch_assoc();
        
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
    public function generateEmail($fullName) {
        $parts = explode(' ', strtolower(trim($fullName)));
        $fname = $parts[0];
        $lname = end($parts);
        $base = "$fname.$lname@umojasacco.co.ke";
        
        // De-dupe
        $email = $base;
        $count = 1;
        
        while (true) {
            $check = $this->db->prepare("SELECT 1 FROM employees WHERE company_email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows === 0) break;
            
            $email = "$fname.$lname$count@umojasacco.co.ke";
            $count++;
        }
        
        return $email;
    }

    /**
     * Map Job Title to Role ID
     */
    /**
     * Map Job Title to Role ID using job_titles table
     */
    public function getRoleIdForTitle($title) {
        // Try strict match first
        $stmt = $this->db->prepare("SELECT role_id FROM job_titles WHERE title = ?");
        $stmt->bind_param("s", $title);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        
        if ($res) {
            return $res['role_id'];
        }
        
        // Fallback: Default to Staff (Role ID 2 usually, need to be safe)
        // Ideally we should enforce that job titles come from the DB.
        return 2; 
    }

    /**
     * Create System User (Admin) for Employee
     */
    public function createSystemUser($empData, $roleId) {
        // password = empNo initially
        $password = password_hash($empData['employee_no'], PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("INSERT INTO admins (username, password, email, full_name, role_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssi", $empData['employee_no'], $password, $empData['company_email'], $empData['full_name'], $roleId);
        
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return false;
    }
}
?>
