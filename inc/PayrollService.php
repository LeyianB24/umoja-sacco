<?php
/**
 * PayrollService.php
 * Enterprise Payroll Management Service
 * Handles payroll runs, statutory calculations, and financial integration
 */

class PayrollService {
    private $db;
    private $financialEngine;
    
    public function __construct($db, $financialEngine = null) {
        $this->db = $db;
        $this->financialEngine = $financialEngine;
    }
    
    /**
     * Create new payroll run for a given period
     */
    public function createPayrollRun(string $period, int $processedBy): array {
        try {
            // Validate period format (YYYY-MM)
            if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
                throw new Exception("Invalid period format. Use YYYY-MM");
            }
            
            // Check for duplicate
            $check = $this->db->prepare("SELECT id FROM payroll_runs WHERE period = ?");
            $check->bind_param("s", $period);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception("Payroll for {$period} already exists");
            }
            
            // Create draft payroll run
            $stmt = $this->db->prepare("
                INSERT INTO payroll_runs (period, status, processed_by)
                VALUES (?, 'draft', ?)
            ");
            $stmt->bind_param("si", $period, $processedBy);
            
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            
            $payrollRunId = $this->db->insert_id;
            
            // Generate payroll items for all active employees
            $this->generatePayrollItems($payrollRunId, $period);
            
            // Update totals
            $this->updatePayrollTotals($payrollRunId);
            
            return ['success' => true, 'payroll_run_id' => $payrollRunId];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate payroll items for all active employees
     */
    private function generatePayrollItems(int $payrollRunId, string $period): void {
        // Get all active employees
        $employees = $this->db->query("
            SELECT e.*, g.house_allowance, g.transport_allowance, g.other_allowances
            FROM employees e
            LEFT JOIN salary_grades g ON e.grade_id = g.id
            WHERE e.status = 'active'
        ");
        
        while ($emp = $employees->fetch_assoc()) {
            $this->addPayrollItem($payrollRunId, $emp);
        }
    }
    
    /**
     * Add single payroll item with statutory calculations
     */
    private function addPayrollItem(int $payrollRunId, array $employee, float $bonus = 0, float $otherDeductions = 0): void {
        // Get allowances from grade
        $basic = (float)$employee['salary'];
        $house = (float)($employee['house_allowance'] ?? 0);
        $transport = (float)($employee['transport_allowance'] ?? 0);
        $other = (float)($employee['other_allowances'] ?? 0);
        
        // Calculate gross
        $gross = $basic + $house + $transport + $other + $bonus;
        
        // Calculate statutory deductions
        $nssf = $this->calculateNSSF($gross);
        $sha = $this->calculateSHA($gross);
        $taxable = $gross - $nssf;
        $paye = $this->calculatePAYE($taxable);
        $housingLevy = $this->calculateHousingLevy($gross);
        
        // Calculate net
        $totalDeductions = $nssf + $sha + $paye + $housingLevy + $otherDeductions;
        $netPay = $gross - $totalDeductions;
        
        // Insert payroll item
        $stmt = $this->db->prepare("
            INSERT INTO payroll_items (
                payroll_run_id, employee_id, employee_no, employee_name,
                basic_salary, house_allowance, transport_allowance, other_allowances, bonus,
                gross_pay, paye, nssf, tax_sha, housing_levy, other_deductions,
                total_deductions, net_pay
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("iissddddddddddddd",
            $payrollRunId,
            $employee['employee_id'],
            $employee['employee_no'],
            $employee['full_name'],
            $basic, $house, $transport, $other, $bonus,
            $gross, $paye, $nssf, $sha, $housingLevy, $otherDeductions,
            $totalDeductions, $netPay
        );
        
        $stmt->execute();
    }
    
    /**
     * Calculate PAYE (Kenya progressive tax)
     */
    private function calculatePAYE(float $taxableIncome): float {
        $rules = $this->getStatutoryRules('PAYE');
        $tax = 0;
        $previousMax = 0;
        $relief = 2400; // Personal relief
        
        foreach ($rules as $rule) {
            $min = (float)$rule['bracket_min'];
            $max = $rule['bracket_max'] ? (float)$rule['bracket_max'] : PHP_FLOAT_MAX;
            $rate = (float)$rule['rate'];
            
            if ($taxableIncome > $min) {
                $taxableInBracket = min($taxableIncome, $max) - $min;
                $tax += $taxableInBracket * $rate;
            }
            
            if ($taxableIncome <= $max) break;
        }
        
        // Apply personal relief
        $tax = max(0, $tax - $relief);
        
        return round($tax, 2);
    }
    
    /**
     * Calculate NSSF (Fixed Tier II for 2024)
     */
    private function calculateNSSF(float $gross): float {
        $rules = $this->getStatutoryRules('NSSF');
        if (!empty($rules)) {
            return (float)$rules[0]['fixed_amount'];
        }
        return 1080; // Fallback
    }
    
    /**
     * Calculate SHA (Bracket-based)
     */
    private function calculateSHA(float $gross): float {
        $rules = $this->getStatutoryRules('SHA');
        
        foreach ($rules as $rule) {
            $min = (float)$rule['bracket_min'];
            $max = $rule['bracket_max'] ? (float)$rule['bracket_max'] : PHP_FLOAT_MAX;
            
            if ($gross >= $min && $gross <= $max) {
                return (float)$rule['fixed_amount'];
            }
        }
        
        return 0; // Default safety
    }
    
    /**
     * Calculate Housing Levy (1.5% of gross)
     */
    private function calculateHousingLevy(float $gross): float {
        $rules = $this->getStatutoryRules('HOUSING_LEVY');
        if (!empty($rules)) {
            $rate = (float)$rules[0]['rate'];
            return round($gross * $rate, 2);
        }
        return round($gross * 0.015, 2); // Fallback
    }
    
    /**
     * Get active statutory rules by type
     */
    private function getStatutoryRules(string $type): array {
        $stmt = $this->db->prepare("
            SELECT * FROM statutory_rules 
            WHERE rule_type = ? 
            AND is_active = 1 
            AND effective_from <= CURDATE()
            AND (effective_to IS NULL OR effective_to >= CURDATE())
            ORDER BY bracket_min ASC
        ");
        $stmt->bind_param("s", $type);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Update payroll run totals
     */
    private function updatePayrollTotals(int $payrollRunId): void {
        $stmt = $this->db->prepare("
            UPDATE payroll_runs pr
            SET 
                total_gross = (SELECT SUM(gross_pay) FROM payroll_items WHERE payroll_run_id = ?),
                total_deductions = (SELECT SUM(total_deductions) FROM payroll_items WHERE payroll_run_id = ?),
                total_net = (SELECT SUM(net_pay) FROM payroll_items WHERE payroll_run_id = ?),
                employee_count = (SELECT COUNT(*) FROM payroll_items WHERE payroll_run_id = ?)
            WHERE id = ?
        ");
        $stmt->bind_param("iiiii", $payrollRunId, $payrollRunId, $payrollRunId, $payrollRunId, $payrollRunId);
        $stmt->execute();
    }
    
    /**
     * Approve payroll run
     */
    public function approvePayroll(int $payrollRunId, int $approvedBy): array {
        try {
            $stmt = $this->db->prepare("
                UPDATE payroll_runs 
                SET status = 'approved', approved_by = ?, approved_at = NOW()
                WHERE id = ? AND status = 'draft'
            ");
            $stmt->bind_param("ii", $approvedBy, $payrollRunId);
            
            if (!$stmt->execute() || $stmt->affected_rows === 0) {
                throw new Exception("Payroll not found or already approved");
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Mark payroll as paid and post to ledger
     */
    public function markAsPaid(int $payrollRunId): array {
        try {
            // Get payroll run details
            $stmt = $this->db->prepare("SELECT * FROM payroll_runs WHERE id = ? AND status = 'approved'");
            $stmt->bind_param("i", $payrollRunId);
            $stmt->execute();
            $payroll = $stmt->get_result()->fetch_assoc();
            
            if (!$payroll) {
                throw new Exception("Payroll not found or not approved");
            }
            
            // Update status
            $this->db->prepare("UPDATE payroll_runs SET status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$payrollRunId]);
            
            // Post to ledger if FinancialEngine available
            if ($this->financialEngine) {
                $this->financialEngine->transact([
                    'action_type' => 'salary_expense',
                    'amount' => (float)$payroll['total_net'],
                    'method' => 'bank_transfer',
                    'reference' => "PAYROLL-{$payroll['period']}",
                    'description' => "Payroll disbursement for {$payroll['period']} ({$payroll['employee_count']} employees)",
                    'related_table' => 'payroll_runs',
                    'related_id' => $payrollRunId
                ]);
            }
            
            // Generate payslips
            $this->generatePayslips($payrollRunId);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate payslips for all items in payroll run
     */
    private function generatePayslips(int $payrollRunId): void {
        $items = $this->db->query("
            SELECT * FROM payroll_items WHERE payroll_run_id = {$payrollRunId}
        ");
        
        while ($item = $items->fetch_assoc()) {
            $this->db->prepare("
                INSERT INTO payslips (payroll_item_id, employee_id, period)
                SELECT id, employee_id, ? FROM payroll_items WHERE id = ?
            ")->execute([$item['period'] ?? date('Y-m'), $item['id']]);
        }
    }
    
    /**
     * Get payroll run details
     */
    public function getPayrollRun(int $payrollRunId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM payroll_runs WHERE id = ?");
        $stmt->bind_param("i", $payrollRunId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get payroll items for a run
     */
    public function getPayrollItems(int $payrollRunId): array {
        $stmt = $this->db->prepare("SELECT * FROM payroll_items WHERE payroll_run_id = ? ORDER BY employee_name");
        $stmt->bind_param("i", $payrollRunId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
