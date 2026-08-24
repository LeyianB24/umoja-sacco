<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Simulate proper server environment
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/usms/member/pages/loans.php';
$_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';
session_start();
$_SESSION['member_id'] = 1;
$_SESSION['member_name'] = 'Test Member';

// Test loans
ob_start();
try {
    require 'c:/xampp/htdocs/usms/member/pages/loans.php';
    $out = ob_get_clean();
    echo "[loans] LOADED OK (" . strlen($out) . " bytes)\n";
    // Check if it has the expected content
    if (strpos($out, 'loan') !== false || strpos($out, 'Loan') !== false) {
        echo "[loans] ✓ Contains loan content\n";
    }
} catch (Throwable $e) {
    ob_get_clean();
    echo "[loans] ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
}
