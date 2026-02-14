<?php
// declare(strict_types=1);

/**
 * PayrollService - Payroll Calculation & Financial Integration
 * Handles salary calculations, statutory deductions, and ledger posting
 */
class PayrollService {
    private $db;
    private $financialEngine;
    
    public function __construct($db, $financialEngine = null) {
        $this->db = $db;
        $this->financialEngine = $financialEngine;
    }
    
    /**
     * Calculate Gross Salary (Base + Allowances)
     */
    public function calculateGrossSalary(int $employeeId): float {
        $stmt = $this->db->prepare(
            "SELECT salary FROM employees WHERE employee_id = ?"
        );
        if (!$stmt) {
            die("Prepare failed in calculateGrossSalary: " . $this->db->error);
        }
        $stmt->bind_param("i", $employeeId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $baseSalary = $result ? (float)$result['salary'] : 0.0;
        
        // TODO: Add allowances from employee_allowances table
        // For now, just return base salary
        return $baseSalary;
    }
    
    /**
     * Calculate Statutory Deductions (Kenya)
     */
    public function calculateStatutoryDeductions(float $grossSalary): array {
        return [
            'paye' => $this->calculatePAYE($grossSalary),
            'nhif' => $this->calculateNHIF($grossSalary),
            'nssf' => $this->calculateNSSF($grossSalary),
            'housing_levy' => $this->calculateHousingLevy($grossSalary)
        ];
    }
    
    /**
     * Calculate PAYE (Kenya Tax Brackets 2026)
     */
    private function calculatePAYE(float $grossSalary): float {
        // Get active PAYE rules from database
        $stmt = $this->db->prepare(
            "SELECT min_amount, max_amount, value 
             FROM statutory_rules 
             WHERE name = 'PAYE' AND type = 'bracket' 
             AND is_active = 1 
             AND effective_from <= CURDATE() 
             AND (effective_to IS NULL OR effective_to >= CURDATE())
             ORDER BY min_amount ASC"
        );
        $stmt->execute();
        $brackets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if (empty($brackets)) {
            // Fallback to hardcoded 2026 rates if no DB rules
            $brackets = [
                ['min_amount' => 0, 'max_amount' => 24000, 'value' => 0.10],
                ['min_amount' => 24001, 'max_amount' => 32333, 'value' => 0.25],
                ['min_amount' => 32334, 'max_amount' => 500000, 'value' => 0.30],
                ['min_amount' => 500001, 'max_amount' => 800000, 'value' => 0.325],
                ['min_amount' => 800001, 'max_amount' => PHP_FLOAT_MAX, 'value' => 0.35]
            ];
        }
        
        $tax = 0.0;
        $remaining = $grossSalary;
        
        foreach ($brackets as $bracket) {
            $min = (float)$bracket['min_amount'];
            $max = (float)$bracket['max_amount'];
            $rate = (float)$bracket['value'];
            
            if ($remaining <= 0) break;
            
            $taxableInBracket = min($remaining, $max - $min + 1);
            $tax += $taxableInBracket * $rate;
            $remaining -= $taxableInBracket;
        }
        
        // Personal relief (Kenya 2026)
        $personalRelief = 2400;
        $tax = max(0, $tax - $personalRelief);
        
        return round($tax, 2);
    }
    
    /**
     * Calculate NHIF (Kenya Bracket-Based)
     */
    private function calculateNHIF($grossSalary) {
        $stmt = $this->db->prepare(
            "SELECT value FROM statutory_rules 
             WHERE name = 'NHIF' AND type = 'bracket' 
             AND is_active = 1 
             AND min_amount <= ? AND max_amount >= ?
             AND effective_from <= CURDATE() 
             AND (effective_to IS NULL OR effective_to >= CURDATE())
             LIMIT 1"
        );
        $stmt->bind_param("dd", $grossSalary, $grossSalary);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            return (float)$result['value'];
        }
        
        // Fallback to hardcoded 2026 rates
        if ($grossSalary < 6000) return 150.0;
        if ($grossSalary < 8000) return 300.0;
        if ($grossSalary < 12000) return 400.0;
        if ($grossSalary < 15000) return 500.0;
        if ($grossSalary < 20000) return 600.0;
        if ($grossSalary < 25000) return 750.0;
        if ($grossSalary < 30000) return 850.0;
        if ($grossSalary < 35000) return 900.0;
        if ($grossSalary < 40000) return 950.0;
        if ($grossSalary < 45000) return 1000.0;
        if ($grossSalary < 50000) return 1100.0;
        if ($grossSalary < 60000) return 1200.0;
        if ($grossSalary < 70000) return 1300.0;
        if ($grossSalary < 80000) return 1400.0;
        if ($grossSalary < 90000) return 1500.0;
        if ($grossSalary < 100000) return 1600.0;
        return 1700.0;
    }
    
    /**
     * Calculate NSSF (6% of gross, capped)
     */
    private function calculateNSSF(float $grossSalary): float {
        $stmt = $this->db->prepare(
            "SELECT value FROM statutory_rules 
             WHERE name = 'NSSF' AND type = 'percentage' 
             AND is_active = 1 
             AND effective_from <= CURDATE() 
             AND (effective_to IS NULL OR effective_to >= CURDATE())
             LIMIT 1"
        );
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $rate = $result ? (float)$result['value'] : 0.06;
        $nssf = $grossSalary * $rate;
        
        // Cap at 2,160 (as per Kenya 2026 rules)
        return min($nssf, 2160);
    }
    
    /**
     * Calculate Housing Levy (1.5% of gross)
     */
    private function calculateHousingLevy(float $grossSalary): float {
        $stmt = $this->db->prepare(
            "SELECT value FROM statutory_rules 
             WHERE name = 'Housing Levy' AND type = 'percentage' 
             AND is_active = 1 
             AND effective_from <= CURDATE() 
             AND (effective_to IS NULL OR effective_to >= CURDATE())
             LIMIT 1"
        );
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $rate = $result ? (float)$result['value'] : 0.015;
        return round($grossSalary * $rate, 2);
    }
    
    /**
     * Calculate Net Salary
     */
    public function calculateNetSalary(float $grossSalary, array $deductions): float {
        $totalDeductions = array_sum($deductions);
        return max(0, $grossSalary - $totalDeductions);
    }
    
    /**
     * Generate Payslip for Employee
     */
    public function generatePayslip(int $employeeId, int $payrollRunId): array {
        $gross = $this->calculateGrossSalary($employeeId);
        $deductions = $this->calculateStatutoryDeductions($gross);
        $net = $this->calculateNetSalary($gross, $deductions);
        
        return [
            'employee_id' => $employeeId,
            'payroll_run_id' => $payrollRunId,
            'gross_salary' => $gross,
            'paye' => $deductions['paye'],
            'nhif' => $deductions['nhif'],
            'nssf' => $deductions['nssf'],
            'housing_levy' => $deductions['housing_levy'],
            'total_deductions' => array_sum($deductions),
            'net_salary' => $net
        ];
    }
    
    /**
     * Start a new Payroll Run
     */
    public function startPayrollRun(string $startDate, string $endDate, string $name = null): array {
        $name = $name ?? "Payroll Run " . date('M Y', strtotime($startDate));
        
        // 1. MASTER ENFORCEMENT: Idempotency Check
        // Prevent duplicate payroll runs for the same period
        $check = $this->db->prepare("SELECT payroll_run_id FROM payroll_runs WHERE period_start = ? AND period_end = ? AND status != 'cancelled'");
        $check->bind_param("ss", $startDate, $endDate);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            return ['success' => false, 'error' => "A payroll run for this period ($startDate to $endDate) already exists."];
        }
        
        // 2. MASTER ENFORCEMENT: Pre-Run Validation Gate
        // Instantiate HRService (Late binding to avoid constructor breaking changes)
        require_once __DIR__ . '/HRService.php';
        $hrService = new HRService($this->db);
        
        // Get all active employees
        $res = $this->db->query("SELECT employee_id, full_name, status FROM employees WHERE status = 'active'");
        $activeEmployees = $res->fetch_all(MYSQLI_ASSOC);
        
        if (empty($activeEmployees)) {
            return ['success' => false, 'error' => "No active employees found to process."];
        }
        
        $validationErrors = [];
        foreach ($activeEmployees as $emp) {
            $check = $hrService->validateEmployeeIntegrity($emp['employee_id']);
            if (!$check['isValid']) {
                $validationErrors[] = "{$emp['full_name']} (ID: {$emp['employee_id']}): " . implode(", ", $check['errors']);
            }
        }
        
        if (!empty($validationErrors)) {
            // Block the run!
            return [
                'success' => false, 
                'error' => "Pre-Run Validation Failed. Please fix the following employee records before starting payroll:", 
                'details' => $validationErrors
            ];
        }

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO payroll_runs (run_date, period_start, period_end, run_name, status, created_by) 
                 VALUES (CURDATE(), ?, ?, ?, 'draft', ?)"
            );
            
            if (!$stmt) {
                 throw new Exception("Prepare failed: " . $this->db->error);
            }

            $createdBy = $_SESSION['admin_id'] ?? 0;
            $stmt->bind_param("sssi", $startDate, $endDate, $name, $createdBy);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $runId = $this->db->insert_id;
            
            return [
                'success' => true,
                'payroll_run_id' => $runId
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Calculate Payroll for a Run
     */
    public function calculatePayroll(int $runId): array {
        // 1. Verify Run exists and is in draft/pending state
        $check = $this->db->query("SELECT status FROM payroll_runs WHERE payroll_run_id = $runId");
        if ($check->num_rows === 0) return ['success' => false, 'error' => 'Run not found'];
        
        // 2. Get Active Employees
        $res = $this->db->query("SELECT employee_id, salary FROM employees WHERE status = 'active'");
        $employees = $res->fetch_all(MYSQLI_ASSOC);
        
        $processed = 0;
        $this->db->begin_transaction();
        
        try {
            // Clear existing calculation for this run if any
            $this->db->query("DELETE FROM payroll WHERE payroll_run_id = $runId");
            
            foreach ($employees as $emp) {
                // Calculation Logic
                $gross = (float)$emp['salary']; // TODO: Add allowances
                $deductions = $this->calculateStatutoryDeductions($gross);
                $net = $this->calculateNetSalary($gross, $deductions);
                
                // Insert Record
                $stmt = $this->db->prepare(
                    "INSERT INTO payroll 
                    (payroll_run_id, employee_id, basic_salary, paye, nssf, nhif, housing_levy, net_salary, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft')"
                );
                
                $stmt->bind_param(
                    "iidddddd",
                    $runId,
                    $emp['employee_id'],
                    $gross,
                    $deductions['paye'],
                    $deductions['nssf'],
                    $deductions['nhif'],
                    $deductions['housing_levy'],
                    $net
                );
                $stmt->execute();
                $processed++;
            }
            
            // Update Run Status
            $this->db->query("UPDATE payroll_runs SET status = 'pending_approval' WHERE payroll_run_id = $runId");
            
            $this->db->commit();
            return ['success' => true, 'processed_count' => $processed];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Approve Payroll Run
     */
    public function approvePayroll(int $runId, int $approverId): array {
        $this->db->begin_transaction();
        try {
            // Update Run
            $stmt = $this->db->prepare("UPDATE payroll_runs SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE payroll_run_id = ?");
            $stmt->bind_param("ii", $approverId, $runId);
            $stmt->execute();
            
            // Update Items
            $stmt = $this->db->prepare("UPDATE payroll SET status = 'approved' WHERE payroll_run_id = ?");
            $stmt->bind_param("i", $runId);
            $stmt->execute();
            
            $this->db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Post Payroll to Financial Ledger & Disburse
     * Master Enforcement: 
     * 1. Single Bulk Ledger Transaction
     * 2. Mark as Paid
     * 3. Auto-Send Payslips
     */
    public function postPayrollToLedger(int $payrollRunId): array {
        if (!$this->financialEngine) {
            return ['success' => false, 'error' => 'Financial engine not initialized'];
        }
        
        // Check if already paid to enforce Idempotency
        $runCheck = $this->db->query("SELECT status, run_name FROM payroll_runs WHERE payroll_run_id = $payrollRunId");
        $runData = $runCheck->fetch_assoc();
        if ($runData['status'] === 'paid') {
            return ['success' => false, 'error' => 'Payroll Run is already marked as paid.'];
        }
        
        // Get all payroll items for this run
        $stmt = $this->db->prepare(
            "SELECT p.*, e.full_name, e.employee_no, e.company_email, e.personal_email, e.employee_id 
             FROM payroll p 
             JOIN employees e ON p.employee_id = e.employee_id 
             WHERE p.payroll_run_id = ? AND p.status = 'approved'"
        );
        $stmt->bind_param("i", $payrollRunId);
        $stmt->execute();
        $payrollItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if (empty($payrollItems)) {
            return ['success' => false, 'error' => 'No approved payroll items found'];
        }
        
        $totalNetPay = 0.0;
        foreach ($payrollItems as $item) {
            $totalNetPay += (float)$item['net_salary'];
        }
        
        $this->db->begin_transaction();
        
        try {
            // 1. Post ONE Bulk Transaction
            $this->financialEngine->transact([
                'action_type' => 'expense_outflow',
                'amount' => $totalNetPay,
                'method' => 'bank', 
                'reference' => "PAYROLL-RUN-{$payrollRunId}",
                'notes' => "Bulk Salary Payment for " . $runData['run_name'],
                'recorded_by' => $_SESSION['admin_id'] ?? 0
            ]);
            
            // 2. Update Run Status and Item Status
            $this->db->query("UPDATE payroll_runs SET status = 'paid', paid_at = NOW() WHERE payroll_run_id = $payrollRunId");
            
            $updateStmt = $this->db->prepare("UPDATE payroll SET status = 'paid', paid_at = NOW() WHERE id = ?");
            
            $emailsSent = 0;
            
            foreach ($payrollItems as $item) {
                // Update item status
                $updateStmt->bind_param("i", $item['id']);
                $updateStmt->execute();
                
                // 3. Auto-Send Payslip (Best Effort - Don't fail transaction on email fail)
                // We call the email method but wrap it so it doesn't break the loop
                // In a real system, this would go to a queue. Here we do it inline.
                try {
                    $this->sendPayslipEmail($item['employee_id'], $item['id']);
                    $emailsSent++;
                } catch (Exception $emailEx) {
                    // Log error but continue
                    error_log("Failed to send payslip to {$item['employee_no']}: " . $emailEx->getMessage());
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true, 
                'total_paid' => $totalNetPay, 
                'items_processed' => count($payrollItems),
                'emails_sent' => $emailsSent
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send Payslip Email
     */
    public function sendPayslipEmail(int $employeeId, int $payrollId): array {
        // Get employee details
        $stmt = $this->db->prepare(
            "SELECT e.*, p.*, p.net_salary as net_pay, p.basic_salary, 
                    p.house_allowance, p.transport_allowance, p.other_allowances,
                    p.paye as tax_paye, p.nssf as tax_nssf, p.nhif as tax_nhif, p.housing_levy as tax_housing,
                    (p.basic_salary + IFNULL(p.house_allowance,0) + IFNULL(p.transport_allowance,0) + IFNULL(p.other_allowances,0)) as gross_pay,
                    (p.paye + p.nssf + p.nhif + p.housing_levy) as deductions
             FROM employees e 
             JOIN payroll p ON e.employee_id = p.employee_id 
             WHERE e.employee_id = ? AND p.id = ?"
        );
        $stmt->bind_param("ii", $employeeId, $payrollId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        
        if (!$data) {
            return ['success' => false, 'error' => 'Employee or payroll not found'];
        }
        
        // Prepare attachments
        $attachments = [];
        
        // Generate PDF
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        }
        
        if (class_exists('FPDF')) {
            require_once __DIR__ . '/PayslipGenerator.php';
            $pdf = new \FPDF();
            $pdf->AddPage();
            
            // Map data to generator structure
            $renderData = [
                'employee' => $data,
                'payroll' => $data // The query aliases match the generator's expected keys (tax_paye, etc)
            ];
            
            \PayslipGenerator::render($pdf, $renderData);
            
            $pdfContent = $pdf->Output('S');
            $attachments[] = [
                'content' => $pdfContent,
                'name' => 'Payslip_' . date('M_Y', strtotime($data['run_date'] ?? 'now')) . '.pdf'
            ];
        } else {
            // Fallback: Text file if FPDF missing
            $txtContent = "PAYSLIP\nName: {$data['full_name']}\nNet Pay: " . number_format($data['net_pay'], 2);
            $attachments[] = [
                'content' => $txtContent,
                'name' => 'Payslip.txt'
            ];
        }
        
        // Send email
        require_once __DIR__ . '/Mailer.php';
        $mailer = new Mailer();
        
        $subject = "Your Payslip - " . date('F Y', strtotime($data['run_date'] ?? 'now'));
        $body = $this->getPayslipEmailTemplate($data);
        
        $recipients = [$data['company_email']];
        if (!empty($data['personal_email'])) {
            $recipients[] = $data['personal_email'];
        }
        
        // Mock sending if Mailer config missing or local env
        // valid check: use $mailer->send(...)
        // For verification script, we assume it works or returns error
        $result = $mailer->send($recipients[0], $subject, $body, $attachments);
        
        return $result ? ['success' => true] : ['success' => false, 'error' => 'Email delivery failed'];
    }
    
    /**
     * Get Payslip Email Template
     */
    private function getPayslipEmailTemplate(array $data): string {
        $month = date('F Y', strtotime($data['payment_date']));
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Payslip for {$month}</h2>
            <p>Dear {$data['full_name']},</p>
            <p>Please find attached your payslip for {$month}.</p>
            <table style='border-collapse: collapse; width: 100%; max-width: 500px;'>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ddd;'><strong>Gross Salary:</strong></td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>KES " . number_format($data['gross_salary'], 2) . "</td>
                </tr>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ddd;'><strong>Total Deductions:</strong></td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>KES " . number_format($data['total_deductions'], 2) . "</td>
                </tr>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ddd;'><strong>Net Salary:</strong></td>
                    <td style='padding: 8px; border: 1px solid #ddd;'><strong>KES " . number_format($data['net_salary'], 2) . "</strong></td>
                </tr>
            </table>
            <p>If you have any questions, please contact HR.</p>
            <p>Best regards,<br>Umoja SACCO HR Team</p>
        </body>
        </html>
        ";
    }
    
    /**
     * Get Statutory Rules for Admin UI
     */
    public function getStatutoryRules(string $name = null): array {
        if ($name) {
            $stmt = $this->db->prepare(
                "SELECT * FROM statutory_rules WHERE name = ? ORDER BY effective_from DESC"
            );
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->db->query(
                "SELECT * FROM statutory_rules ORDER BY name, effective_from DESC"
            );
        }
        
        $rules = [];
        while ($row = $result->fetch_assoc()) {
            $rules[] = $row;
        }
        
        return $rules;
    }
}
?>
