<?php
/**
 * verify_payroll_flow.php
 * Comprehensive test of the HR-Payroll-Ledger pipeline
 */

require_once 'config/db_connect.php';
require_once 'inc/FinancialEngine.php';
require_once 'inc/HRService.php';
require_once 'inc/SystemUserService.php';
require_once 'inc/PayrollService.php';

// Mock Admin Session
$_SESSION['admin_id'] = 1; 

$financialEngine = new FinancialEngine($conn);
$hrService = new HRService($conn);
$payrollService = new PayrollService($conn, $financialEngine);

echo "<h2>Starting Payroll Flow Verification</h2>";

// 1. Create Test Employee
echo "<h3>1. Creating Test Employee...</h3>";
$empData = [
    'full_name' => 'Test Employee ' . date('His'),
    'national_id' => 'ID' . date('His'),
    'phone' => '07' . date('His'),
    'job_title' => 'Manager',
    'grade_id' => 1, // Assuming grade 1 exists
    'personal_email' => 'test@example.com',
    'salary' => 50000.00,
    'hire_date' => date('Y-m-d'),
    'kra_pin' => 'A' . rand(100000000, 999999999) . 'Z'
];

$empResult = $hrService->createEmployee($empData);
if (!$empResult['success']) {
    die("❌ Failed to create employee: " . $empResult['error']);
}
$employeeId = $empResult['employee_id'];
$employeeNo = $empResult['employee_no'];
echo "✅ Employee created: $employeeNo (ID: $employeeId)<br>";

// 2. Start Payroll Run
echo "<h3>2. Starting Payroll Run...</h3>";
$periodStart = date('Y-m-01');
$periodEnd = date('Y-m-t');
$runResult = $payrollService->startPayrollRun($periodStart, $periodEnd, "Test Run " . date('Y-m-d H:i:s'));

if (!$runResult['success']) {
    die("❌ Failed to start payroll run: " . $runResult['error']);
}
$runId = $runResult['payroll_run_id'];
echo "✅ Payroll Run started: ID $runId<br>";

// 3. Calculate Payroll
echo "<h3>3. Calculating Payroll...</h3>";
$calcResult = $payrollService->calculatePayroll($runId);
if (!$calcResult['success']) {
    die("❌ Failed to calculate payroll: " . $calcResult['error']);
}
echo "✅ Payroll Calculated. Items processed: " . $calcResult['processed_count'] . "<br>";

// Verify Calculation Details
$item = $conn->query("SELECT * FROM payroll WHERE payroll_run_id = $runId AND employee_id = $employeeId")->fetch_assoc();
if ($item) {
    echo "<ul>
        <li>Basic Salary: KES " . number_format($item['basic_salary'], 2) . "</li>
        <li>PAYE: KES " . number_format($item['paye'], 2) . "</li>
        <li>NHIF: KES " . number_format($item['nhif'], 2) . "</li>
        <li>NSSF: KES " . number_format($item['nssf'], 2) . "</li>
        <li>Housing Levy: KES " . number_format($item['housing_levy'], 2) . "</li>
        <li><strong>Net Salary: KES " . number_format($item['net_salary'], 2) . "</strong></li>
    </ul>";
} else {
    echo "⚠️ Warning: Test employee not found in payroll items.<br>";
}

// 4. Approve Payroll
echo "<h3>4. Approving Payroll...</h3>";
$appResult = $payrollService->approvePayroll($runId, 1); // Approved by Admin 1
if (!$appResult['success']) {
    die("❌ Failed to approve payroll: " . $appResult['error']);
}
echo "✅ Payroll Approved.<br>";

// 5. Post to Ledger
echo "<h3>5. Disbursing & Posting to Ledger...</h3>";
$postResult = $payrollService->postPayrollToLedger($runId);
if (!$postResult['success']) {
    die("❌ Failed to post to ledger: " . $postResult['error']);
}
echo "✅ Payroll Posted to Ledger.<br>";

// 6. Verify Ledger Entries
echo "<h3>6. Verifying Ledger Entries...</h3>";
// Check for expense outflows related to this run
$refPattern = "PAYROLL-$runId-$employeeNo";
$ledgerCheck = $conn->query("SELECT * FROM ledger_transactions WHERE reference_no = '$refPattern'");
if ($ledgerCheck->num_rows > 0) {
    $txn = $ledgerCheck->fetch_assoc();
    echo "✅ Ledger Transaction Found: ID " . $txn['ledger_transaction_id'] . "<br>";
    echo "   Amount: KES " . number_format($txn['amount'] ?? 0, 2) . "<br>";
    echo "   Action: " . $txn['action_type'] . "<br>";
} else {
    echo "❌ No ledger transaction found for reference: $refPattern<br>";
}

echo "<hr><h3>🎉 Verification Complete!</h3>";
?>
