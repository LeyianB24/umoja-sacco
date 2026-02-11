<?php
/**
 * tests/unit/sanity_check.php
 * Verify core system health matches expectations in TEST_PLAN.md
 * 
 * Usage: php tests/unit/sanity_check.php
 */

// 1. Setup Environment
define('ASSET_BASE', 'http://localhost/usms'); // Mock base URL
$_SESSION['role_id'] = 1; // Mock Superadmin
$_SESSION['admin_id'] = 999; // Mock Admin ID

// Include Core Files
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/FinancialEngine.php';
require_once __DIR__ . '/../../inc/LoanHelper.php';
require_once __DIR__ . '/../../inc/Auth.php';

// Logging Setup
$logFile = __DIR__ . '/../logs/execution_log.csv';
if (!file_exists(dirname($logFile))) mkdir(dirname($logFile), 0777, true);

function logResult($testId, $result, $comment) {
    global $logFile;
    $tester = "System Bot";
    $date = date('Y-m-d H:i:s');
    $entry = "[$date] [$tester] [$testId] [$result] [$comment]\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
    echo $entry;
}

echo "Starting Sanity Checks...\n";

// TEST 1: Database Connectivity
try {
    if ($conn->ping()) {
        logResult('TC-SYS-01', 'Pass', 'Database connection established');
    } else {
        logResult('TC-SYS-01', 'Fail', 'Database ping failed');
    }
} catch (Exception $e) {
    logResult('TC-SYS-01', 'Fail', 'DB Error: ' . $e->getMessage());
}

// TEST 2: Financial Engine Instantiation & System Accounts
try {
    $fe = new FinancialEngine($conn);
    $cashId = $fe->getSystemAccount('cash');
    if ($cashId > 0) {
        logResult('TC-UNIT-FE-01', 'Pass', "FinancialEngine loaded. System 'cash' account ID: $cashId");
    } else {
        logResult('TC-UNIT-FE-01', 'Fail', "Could not retrieve system 'cash' account");
    }
} catch (Exception $e) {
    logResult('TC-UNIT-FE-01', 'Fail', 'FinancialEngine Error: ' . $e->getMessage());
}

// TEST 3: LoanHelper Instantiation
try {
    $lh = new LoanHelper($conn);
    // Just check if class is usable, strictly read-only
    if (method_exists($lh, 'getFreeShares')) {
        logResult('TC-UNIT-LH-01', 'Pass', 'LoanHelper loaded and getFreeShares method exists');
    } else {
        logResult('TC-UNIT-LH-01', 'Fail', 'LoanHelper missing getKey method');
    }
} catch (Exception $e) {
    logResult('TC-UNIT-LH-01', 'Fail', 'LoanHelper Error: ' . $e->getMessage());
}

// TEST 4: Auth Class
try {
    // Auth::can requires session, which we mocked
    // We'll just check if the class exists and static methods are callable
    if (class_exists('Auth') && method_exists('Auth', 'can')) {
        // Mock session permissions for the test
        $_SESSION['permissions'] = ['dashboard.php'];
        $canAccess = Auth::can('dashboard.php');
        
        if ($canAccess) {
             logResult('TC-UNIT-AUTH-01', 'Pass', 'Auth::can() verified permission correctly');
        } else {
             logResult('TC-UNIT-AUTH-01', 'Fail', 'Auth::can() returned false despite mocked permission');
        }
    } else {
        logResult('TC-UNIT-AUTH-01', 'Fail', 'Auth class or method missing');
    }
} catch (Exception $e) {
    logResult('TC-UNIT-AUTH-01', 'Fail', 'Auth Error: ' . $e->getMessage());
}

echo "Sanity Checks Completed. See " . realpath($logFile) . "\n";
?>
