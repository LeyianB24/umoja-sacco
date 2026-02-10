<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');
// member/apply_loan.php
session_start();
require_once __DIR__ . '/../../inc/SettingsHelper.php';
require_once __DIR__ . '/../../inc/functions.php';

// 1. Auth & Eligibility Check
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];

// Check if member is active
$stmt_check = $conn->prepare("SELECT status FROM members WHERE member_id = ?");
$stmt_check->bind_param("i", $member_id);
$stmt_check->execute();
$m_status = $stmt_check->get_result()->fetch_assoc()['status'] ?? '';
$stmt_check->close();

if ($m_status !== 'active') {
    $_SESSION['error'] = "Only active members can apply for loans.";
    header("Location: loans.php");
    exit;
}

// Check for existing pending or active loans
$stmt_existing = $conn->prepare("SELECT COUNT(*) as count FROM loans WHERE member_id = ? AND status IN ('pending', 'approved', 'disbursed')");
$stmt_existing->bind_param("i", $member_id);
$stmt_existing->execute();
if ($stmt_existing->get_result()->fetch_assoc()['count'] > 0) {
    $_SESSION['error'] = "You already have a pending or active loan. Please complete it first.";
    header("Location: loans.php");
    exit;
}
$stmt_existing->close();

// 2. Process Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Sanitize Input
    $loan_type = filter_input(INPUT_POST, 'loan_type', FILTER_SANITIZE_STRING);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $duration = filter_input(INPUT_POST, 'duration_months', FILTER_VALIDATE_INT);
    $purpose = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    $g1 = filter_input(INPUT_POST, 'guarantor_1', FILTER_VALIDATE_INT);
    $g2 = filter_input(INPUT_POST, 'guarantor_2', FILTER_VALIDATE_INT);

    // B. Validation
    $total_savings = getMemberSavings($member_id, $conn);
    $max_limit = $total_savings * 3;

    if ($amount > $max_limit) {
        if ($total_savings <= 0) {
            $_SESSION['error'] = "Your loan limit is currently KES 0 because you haven't made any savings yet. Please make a deposit to your savings account to qualify for a loan (Limit is 3x your savings).";
        } else {
            $_SESSION['error'] = "Loan limit exceeded. Your maximum limit is KES " . number_format($max_limit) . " based on your savings of KES " . number_format($total_savings) . ". Try a smaller amount or increase your savings.";
        }
        header("Location: loans.php");
        exit;
    }

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
    if (!$g1 || !$g2 || $g1 == $g2) {
        $_SESSION['error'] = "Please select two different guarantors.";
        header("Location: loans.php");
        exit;
    }

    // C. Determine Interest Rate
    $interest_rate = (float)SettingsHelper::get('loan_interest_rate', 12.00);
    $specific_rate = SettingsHelper::get('loan_interest_rate_' . strtolower($loan_type));
    if ($specific_rate !== null) {
        $interest_rate = (float)$specific_rate;
    }

    // D. Calculate Financials
    $interest_amount = $amount * ($interest_rate / 100);
    $total_payable = $amount + $interest_amount;
    $lock_per_guarantor = $amount / 2;
    
    // E. Insert into Database
    $conn->begin_transaction();
    try {
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
        $stmt->bind_param("isddisdd", 
            $member_id, 
            $loan_type, 
            $amount, 
            $interest_rate, 
            $duration, 
            $purpose,
            $total_payable,
            $total_payable
        );

        if (!$stmt->execute()) throw new Exception($stmt->error);
        $loan_id = $conn->insert_id;
        $stmt->close();

        // F. Insert Guarantors
        $stmt_g = $conn->prepare("INSERT INTO loan_guarantors (loan_id, member_id, amount_locked, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
        
        // Guarantor 1
        $stmt_g->bind_param("iid", $loan_id, $g1, $lock_per_guarantor);
        $stmt_g->execute();
        
        // Guarantor 2
        $stmt_g->bind_param("iid", $loan_id, $g2, $lock_per_guarantor);
        $stmt_g->execute();
        $stmt_g->close();

        $conn->commit();
        $_SESSION['success'] = "Loan application submitted successfully! It is now pending guarantor and admin review.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Application failed: " . $e->getMessage();
    }

    // G. Redirect
    header("Location: loans.php");
    exit;
} else {
    header("Location: loans.php");
    exit;
}
?>



