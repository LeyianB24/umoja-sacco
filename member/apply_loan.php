<?php
// member/apply_loan.php
session_start();
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

// 1. Auth Check
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];

// 2. Process Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Sanitize Input
    $loan_type = filter_input(INPUT_POST, 'loan_type', FILTER_SANITIZE_STRING);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $duration = filter_input(INPUT_POST, 'repayment_period', FILTER_VALIDATE_INT);
    $purpose = filter_input(INPUT_POST, 'purpose', FILTER_SANITIZE_STRING);

    // B. Validation
    if (!$amount || $amount < 500) {
        $_SESSION['error'] = "Minimum loan amount is KES 500.";
        header("Location: loans.php");
        exit;
    }
    if (!$duration || $duration < 1) {
        $_SESSION['error'] = "Invalid repayment period.";
        header("Location: loans.php");
        exit;
    }

    // C. Determine Interest Rate based on Type
    // You can move these rates to a DB settings table later for easier management
    $interest_rate = match($loan_type) {
        'emergency' => 12.00,
        'development' => 14.00,
        'education' => 10.00,
        'school' => 10.00,
        'asset' => 15.00,
        'business' => 13.00,
        default => 12.00 // Fallback
    };

    // D. Calculate Financials
    // Simple Interest Formula: Total = Principal + (Principal * Rate / 100)
    // Note: This calculates flat interest for the whole term. Adjust if using per-annum logic.
    $interest_amount = $amount * ($interest_rate / 100);
    $total_payable = $amount + $interest_amount;
    
    // E. Insert into Database
    // We explicitly set 'current_balance' = 'total_payable' so repayments have a starting point.
    $sql = "INSERT INTO loans (
                member_id, 
                loan_type, 
                amount, 
                interest_rate, 
                duration_months, 
                status, 
                application_date, 
                notes,
                total_payable,
                current_balance
            ) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("isddisdd", 
            $member_id, 
            $loan_type, 
            $amount, 
            $interest_rate, 
            $duration, 
            $purpose,
            $total_payable,
            $total_payable // Initial balance matches total due
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = "Loan application submitted successfully! Status: Pending Approval.";
        } else {
            $_SESSION['error'] = "Database Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "System Error: Could not prepare statement.";
    }

    // F. Redirect
    header("Location: loans.php");
    exit;
} else {
    // Direct access not allowed
    header("Location: loans.php");
    exit;
}
?>