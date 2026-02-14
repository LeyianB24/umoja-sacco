<?php
// verify_master_compliance.php
require_once 'config/db_connect.php';
require_once 'inc/HRService.php';
require_once 'inc/SystemUserService.php';
require_once 'inc/PayrollService.php';
require_once 'inc/FinancialEngine.php';

// Mock Session
$_SESSION['admin_id'] = 1;

$hr = new HRService($conn);
$sys = new SystemUserService($conn);
$fin = new FinancialEngine($conn);
$pay = new PayrollService($conn, $fin);

echo "=== MASTER COMPLIANCE VERIFICATION ===\n\n";

// TEST 1: Strict Onboarding Validation
echo "[TEST 1] Creating Employee with Missing Data (Should FAIL)...\n";
$badData = [
    'full_name' => 'Invalid User',
    'national_id' => '99999999',
    'phone' => '0700000000',
    'job_title' => 'Tester',
    'grade_id' => 1,
    'salary' => 0, // Invalid
    'hire_date' => date('Y-m-d')
    // Missing Statutory
];
$res = $hr->createEmployee($badData);
if ($res['success'] === false) {
    echo "✅ PASSED: Blocked invalid employee. Error: " . $res['error'] . "\n";
} else {
    echo "❌ FAILED: Created invalid employee! ID: " . $res['employee_id'] . "\n";
}

// TEST 2: Role Mapping Enforcement
echo "\n[TEST 2] Checking Role Mapping for 'Unknown Job' (Should be 0)...\n";
$roleId = $sys->getRoleIdForTitle('Underwater Basket Weaver');
if ($roleId === 0) {
    echo "✅ PASSED: Role ID is 0 for unknown job title.\n";
} else {
    echo "❌ FAILED: Role ID fallback is active! Got: $roleId\n";
}

// TEST 3: Payroll Pre-Run Validation
echo "\n[TEST 3] Starting Payroll with Invalid Active Employee (Should FAIL)...\n";
// Create a temporary valid employee then corrupt it to test validation
// Actually, let's just use validateEmployeeIntegrity on a known bad state if possible.
// Or just run startPayrollRun and see if it catches existing data.
// We'll try to find an active employee and ensure they are valid, or see if existing data fails.

// Let's create a technically valid employee first to ensure DB has one.
$goodData = [
    'full_name' => 'Valid Tester',
    'national_id' => 'TEST' . rand(1000,9999),
    'phone' => '0711' . rand(100000,999999),
    'job_title' => 'Driver', // Assuming 'Driver' exists
    'grade_id' => 1,
    'salary' => 50000,
    'kra_pin' => 'A123456789Z',
    'nssf_no' => '123456789',
    'nhif_no' => '123456789',
    'hire_date' => date('Y-m-d')
];
// Ensure we don't duplicate unique constraints in test
$check = $conn->query("SELECT * FROM employees WHERE national_id = '{$goodData['national_id']}'");
if ($check->num_rows == 0) {
    $hr->createEmployee($goodData);
}

// Now deliberately break one employee
$conn->query("UPDATE employees SET kra_pin = '' WHERE full_name = 'Valid Tester' LIMIT 1");

$pResult = $pay->startPayrollRun(date('Y-m-01'), date('Y-m-t'), "Test Run Compliance");
if ($pResult['success'] === false && strpos($pResult['error'], 'Pre-Run Validation Failed') !== false) {
    echo "✅ PASSED: Blocked Payroll Run due to missing KRA PIN.\n";
    echo "Error Details: " . json_encode($pResult['details'] ?? []) . "\n";
} else {
    echo "❌ FAILED: Payroll Run Started! (Or failed for wrong reason: " . $pResult['error'] . ")\n";
}

// Cleanup
$conn->query("DELETE FROM employees WHERE full_name = 'Valid Tester'");
$conn->query("DELETE FROM employees WHERE full_name = 'Invalid User'"); // Just in case

echo "\n=== VERIFICATION COMPLETE ===\n";
?>
