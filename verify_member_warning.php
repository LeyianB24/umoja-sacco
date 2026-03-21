<?php
// Mocking the environment
$_SERVER['REQUEST_METHOD'] = 'GET';
session_start();
$_SESSION['member_id'] = 5;

// Buffer the output of the loans page
ob_start();
include 'c:/xampp/htdocs/usms/member/pages/loans.php';
$html = ob_get_clean();

if (strpos($html, 'Overdue Repayment Detected') !== false) {
    echo "SUCCESS: Overdue warning found in HTML.\n";
    // Check if it also shows the due date
    if (preg_match('/Your loan repayment was due on <strong>(.*?)<\/strong>/', $html, $matches)) {
        echo "Found Due Date: " . $matches[1] . "\n";
    }
} else {
    echo "FAILURE: Overdue warning NOT found in HTML.\n";
    // Debug: check if active_loan was found
    if (strpos($html, 'Active Loan #') !== false) {
        echo "Active loan was found, but no warning.\n";
    } else {
        echo "Active loan NOT found in output.\n";
    }
}
