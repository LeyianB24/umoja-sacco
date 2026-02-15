<?php
// Debug script for Payroll View
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "Starting Payroll View Debug...\n\n";

try {
    echo "1. Loading Dependencies...\n";
    require_once __DIR__ . '/../../config/app_config.php';
    require_once __DIR__ . '/../../config/db_connect.php';
    require_once __DIR__ . '/../../inc/Auth.php';
    require_once __DIR__ . '/../../inc/LayoutManager.php';
    require_once __DIR__ . '/../../inc/HRService.php';
    require_once __DIR__ . '/../../inc/SystemUserService.php';
    require_once __DIR__ . '/../../inc/PayrollService.php';
    require_once __DIR__ . '/../../inc/PayrollEngine.php';
    require_once __DIR__ . '/../../inc/PayrollCalculator.php';
    require_once __DIR__ . '/../../inc/PayslipGenerator.php';
    require_once __DIR__ . '/../../inc/Mailer.php';
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    echo "✓ Dependencies Loaded\n\n";

    $db = $conn;

    echo "2. Initializing Services...\n";
    $hrService = new HRService($db);
    $systemUserService = new SystemUserService($db);
    $payrollService = new PayrollService($db);
    $payrollEngine = new PayrollEngine($db);
    echo "✓ Services Initialized\n\n";

    echo "3. Testing Database Tables...\n";
    $tables = ['employees', 'payroll_runs', 'payroll', 'salary_grades'];
    foreach ($tables as $table) {
        $check = $db->query("SHOW TABLES LIKE '$table'");
        if ($check->num_rows > 0) {
            echo "✓ Table '$table' exists\n";
        } else {
            echo "✗ Table '$table' MISSING\n";
        }
    }
    echo "\n";

    echo "4. Simulating Payroll View Logic...\n";
    $run_id = null;
    echo "Fetching active run (ORDER BY status='draft' DESC, month DESC LIMIT 1)...\n";
    $run_q = $db->query("SELECT * FROM payroll_runs ORDER BY status='draft' DESC, month DESC LIMIT 1");
    if (!$run_q) {
        throw new Exception("Database query failed: " . $db->error);
    }
    $active_run = $run_q->fetch_assoc();
    
    if ($active_run) {
        echo "✓ Found active run: Month=" . $active_run['month'] . " Status=" . $active_run['status'] . "\n";
        $run_id = $active_run['id'];
        
        echo "Fetching payroll items for run ID $run_id...\n";
        $pq = $db->query("SELECT p.*, e.full_name, e.employee_no, sg.grade_name 
                          FROM payroll p 
                          JOIN employees e ON p.employee_id = e.employee_id 
                          LEFT JOIN salary_grades sg ON e.grade_id = sg.id
                          WHERE p.payroll_run_id = $run_id 
                          ORDER BY e.employee_no ASC");
        
        if (!$pq) {
            throw new Exception("Payroll items query failed: " . $db->error);
        }
        echo "✓ Found " . $pq->num_rows . " payroll items\n";
    } else {
        echo "! No active payroll run found\n";
    }

    echo "\n5. Testing KPI Stats...\n";
    if ($active_run) {
        $gross = $active_run['total_gross'] ?? 0;
        $net = $active_run['total_net'] ?? 0;
        $ded = $gross - $net;
        echo "KPI 1 (Gross): " . $gross . "\n";
        echo "KPI 2 (Net): " . $net . "\n";
        echo "KPI 3 (Deductions): " . $ded . "\n";
    }

    echo "\n✓✓ ALL DEBUG STEPS PASSED ✓✓\n";

} catch (Throwable $e) {
    echo "\n✗✗✗ FATAL ERROR ✗✗✗\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>
