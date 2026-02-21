<?php
declare(strict_types=1);
// inc/PayrollEngine.php

require_once __DIR__ . '/FinancialEngine.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/PayslipGenerator.php';
require_once __DIR__ . '/../core/exports/UniversalExportEngine.php';

class PayrollEngine {
    private $db;
    private $financial;
    private $deductions_config = [];

    public function __construct($db) {
        $this->db = $db;
        $this->financial = new FinancialEngine($db);
        $this->loadDeductionRules();
    }

    /**
     * Load Statutory Rules from DB (Cached)
     */
    private function loadDeductionRules() {
        $q = $this->db->query("SELECT * FROM statutory_deductions WHERE is_active = 1");
        while ($row = $q->fetch_assoc()) {
            $this->deductions_config[$row['name']] = $row;
        }
    }

    /**
     * Step 1: Start a New Payroll Run (Draft)
     */
    public function startRun($month, $userId) {
        // Check if exists
        $check = $this->db->query("SELECT id FROM payroll_runs WHERE month = '$month'");
        if ($check->num_rows > 0) {
            throw new Exception("Payroll run for $month already exists.");
        }

        $stmt = $this->db->prepare("INSERT INTO payroll_runs (month, status, created_by) VALUES (?, 'draft', ?)");
        $stmt->bind_param("si", $month, $userId);
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        throw new Exception("Failed to create payroll run.");
    }

    /**
     * Step 2: Calculate Payroll for All Active Employees
     */
    public function calculateRun($run_id) {
        $run = $this->getRun($run_id);
        if ($run['status'] !== 'draft') throw new Exception("Run is not in draft status.");

        $month = $run['month'];
        $year_num = explode('-', $month)[0];

        $this->db->begin_transaction();
        try {
            // Clear existing for idempotency
            $this->db->query("DELETE FROM payroll WHERE payroll_run_id = $run_id");

            $emps = $this->db->query("SELECT * FROM employees WHERE status = 'active'");
            $count = 0;
            $total_gross = 0;
            $total_net = 0;

            while ($emp = $emps->fetch_assoc()) {
                $calc = $this->calculateEmployeeConfigured($emp);
                
                // Insert Record
                $stmt = $this->db->prepare(
                    "INSERT INTO payroll 
                    (payroll_run_id, employee_id, month, year, 
                    basic_salary, allowances, 
                    gross_pay, 
                    deductions, tax_paye, tax_nssf, tax_sha, tax_housing, 
                    net_pay, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
                );
                
                $allowances_total = $calc['house_allowance'] + $calc['transport_allowance'];
                
                $stmt->bind_param("iissddddddddd", 
                    $run_id, $emp['employee_id'], $month, $year_num,
                    $calc['basic_salary'], 
                    $allowances_total, 
                    $calc['gross_pay'],
                    $calc['total_deductions'], $calc['tax_paye'], $calc['tax_nssf'], $calc['tax_sha'], $calc['tax_housing'],
                    $calc['net_pay']
                );
                
                $stmt->execute();
                
                $total_gross += $calc['gross_pay'];
                $total_net += $calc['net_pay'];
                $count++;
            }

            // Update Run Totals
            $this->db->query("UPDATE payroll_runs SET total_gross = $total_gross, total_net = $total_net WHERE id = $run_id");
            
            $this->db->commit();
            return $count;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Step 3: Approve Run (Lock it)
     */
    public function approveRun($run_id, $approverId) {
        $run = $this->getRun($run_id);
        if ($run['status'] !== 'draft') throw new Exception("Run must be in draft to approve.");

        // Move to Approved
        $this->db->query("UPDATE payroll_runs SET status = 'approved', processed_by = $approverId WHERE id = $run_id");
        return true;
    }

    /**
     * Step 4: Disburse Run (Financial Integration)
     */
    public function disburseRun($run_id) {
        $run = $this->getRun($run_id);
        if ($run['status'] !== 'approved') throw new Exception("Run must be approved before disbursement.");

        $this->db->begin_transaction();
        try {
            $items = $this->db->query("SELECT * FROM payroll WHERE payroll_run_id = $run_id AND status = 'pending'");
            $count = 0;

            while ($p = $items->fetch_assoc()) {
                // Post Expense to Ledger
                // Debit: Payroll Expense, Credit: Bank
                $ref = $this->financial->transact([
                    'action_type' => 'expense_outflow',
                    'amount' => $p['net_pay'],
                    'notes' => "Salary {$p['month']} - Emp #{$p['employee_id']}",
                    'related_table' => 'payroll',
                    'related_id' => $p['id'],
                    'reference' => 'PAY-' . $run_id . '-' . $p['employee_id'], // Idempotency key
                    'method' => 'bank' 
                ]);

                // Mark Item as Paid
                $pay_date = date('Y-m-d');
                $this->db->query("UPDATE payroll SET status = 'paid', payment_date = '$pay_date' WHERE id = {$p['id']}");
                $count++;
            }

            // Mark Run as Paid
            $this->db->query("UPDATE payroll_runs SET status = 'paid' WHERE id = $run_id");
            
            $this->db->commit();
            return $count;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Helper: Calculate Single Employee (Configured)
     */
    private function calculateEmployeeConfigured($employee) {
        $basic = (float)$employee['salary'];
        
        // Fetch Grade Allowances
        $house = 0;
        $transport = 0;
        if (!empty($employee['grade_id'])) {
            $g_q = $this->db->query("SELECT * FROM salary_grades WHERE id = {$employee['grade_id']}");
            $grade = $g_q->fetch_assoc();
            if ($grade) {
                $house = (float)$grade['house_allowance'];
                $transport = (float)$grade['transport_allowance'];
            }
        }
        
        $gross = $basic + $house + $transport;
        
        // Apply Configured Rules
        $nssf = $this->applyRule('NSSF', $gross);
        $sha = $this->applyRule('SHA', $gross);
        $housing = $this->applyRule('Housing Levy', $gross);
        
        $taxable = $gross - $nssf; // Pension is tax deductible
        $paye = $this->applyRule('PAYE', $taxable);
        $paye = max(0, $paye - 2400); // Personal Relief Hardcoded for now (standard)

        $total_deductions = $nssf + $sha + $housing + $paye;
        $net = $gross - $total_deductions;

        return [
            'basic_salary' => $basic,
            'house_allowance' => $house,
            'gross_pay' => $gross,
            'tax_nssf' => $nssf,
            'tax_sha' => $sha,
            'tax_housing' => $housing,
            'tax_paye' => $paye,
            'total_deductions' => $total_deductions,
            'net_pay' => $net
        ];
    }

    private function applyRule($name, $amount) {
        $rule = $this->deductions_config[$name] ?? null;
        if (!$rule) return 0; // Default safe

        // 1. Fixed Value
        if ($rule['type'] === 'fixed') {
            return (float)$rule['value'];
        }

        // 2. Percentage
        if ($rule['type'] === 'percentage') {
            return $amount * ((float)$rule['value'] / 100);
        }

        // 3. Bracket (JSON)
        if ($rule['type'] === 'bracket') {
            $brackets = json_decode($rule['value'], true);
            ksort($brackets); // Sort by limit
            
            // PAYE logic fits here (cumulative)? Or NHIF logic (lookup)?
            // Current JSON structure suggests lookup (NHIF) or Band Limit (PAYE).
            // Let's assume NHIF style for simple 'bracket' (look up range)
            // And 'progressive' for PAYE. 
            // For now, let's implement the specific logic for standard names if needed, 
            // or generalize if the JSON format is strict.
            // The JSON currently is: "Limit": "Rate/Amount".
            
            if ($name === 'PAYE') {
                return $this->calcProgressive($amount, $brackets);
            } else {
                return $this->calcLookup($amount, $brackets);
            }
        }
        return 0;
    }

    private function calcLookup($amount, $brackets) {
        $deduction = 0;
        foreach ($brackets as $limit => $val) {
            if ($amount <= $limit) return $val;
            $deduction = $val;
        }
        return $deduction;
    }

    private function calcProgressive($amount, $brackets) {
        $tax = 0;
        $prev_limit = 0;
        foreach ($brackets as $limit => $rate) {
            $limit = (float)$limit; 
            // 9999999 is infinity
            
            $taxable_in_band = min($amount, $limit) - $prev_limit;
            if ($taxable_in_band <= 0) break;
            
            $tax += $taxable_in_band * ($rate / 100);
            $prev_limit = $limit;
            
            if ($amount <= $limit) break;
        }
        return $tax;
    }

    private function getRun($id) {
        $q = $this->db->query("SELECT * FROM payroll_runs WHERE id = " . intval($id));
        return $q->fetch_assoc();
    }
}
?>
