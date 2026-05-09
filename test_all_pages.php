<?php
// Test if the pages load cleanly
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
$_SESSION['member_id'] = 1;
$_SESSION['member_name'] = 'Test Member';

$test_pages = [
    'loans' => 'c:/xampp/htdocs/usms/member/pages/loans.php',
    'mpesa' => 'c:/xampp/htdocs/usms/member/pages/mpesa_request.php',
    'withdraw' => 'c:/xampp/htdocs/usms/member/pages/withdraw.php',
    'profile' => 'c:/xampp/htdocs/usms/member/pages/profile.php',
];

foreach ($test_pages as $name => $path) {
    ob_start();
    try {
        require_once $path;
        $out = ob_get_clean();
        echo "[$name] LOADED OK (" . strlen($out) . " bytes)\n";
    } catch (Throwable $e) {
        ob_get_clean();
        echo "[$name] ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}
