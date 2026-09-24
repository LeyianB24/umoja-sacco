<?php
include('../config/db_connect.php');
session_start();

if (!isset($_SESSION['member_id'])) {
    header('Location: ../public/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_SESSION['member_id']; // Kept for session check, but not inserted into repayments
    $loan_id = intval($_POST['loan_id']);
    $amount = floatval($_POST['amount']);
    $payment_method = "mpesa"; 
    $payment_date = date('Y-m-d H:i:s');
    $reference_no = "MANUAL-" . time(); // Generated ref since DB requires it or allows NULL

    // 1️ CALCULATE REMAINING BALANCE
    // We need the total payable and current paid amount to calculate the new balance
    $calc_sql = "SELECT 
                    l.total_payable,
                    COALESCE(SUM(lr.amount_paid), 0) as total_paid_so_far
                 FROM loans l
                 LEFT JOIN loan_repayments lr ON l.loan_id = lr.loan_id
                 WHERE l.loan_id = ?";
    
    $stmt_calc = $conn->prepare($calc_sql);
    $stmt_calc->bind_param("i", $loan_id);
    $stmt_calc->execute();
    $calc_res = $stmt_calc->get_result()->fetch_assoc();
    
    $total_payable = floatval($calc_res['total_payable']);
    $paid_so_far = floatval($calc_res['total_paid_so_far']);
    
    // Calculate what the balance WILL be after this payment
    $new_total_paid = $paid_so_far + $amount;
    $remaining_balance = max(0, $total_payable - $new_total_paid);

    // 2️ INSERT INTO loan_repayments
    // Corrected columns: removed 'member_id', changed 'method' to 'payment_method'
    $insert_repay = "INSERT INTO loan_repayments 
                     (loan_id, amount_paid, payment_date, payment_method, reference_no, remaining_balance, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'Completed')";
    
    $stmt = $conn->prepare($insert_repay);
    $stmt->bind_param("idsssd", $loan_id, $amount, $payment_date, $payment_method, $reference_no, $remaining_balance);
    
    if ($stmt->execute()) {
        // 3️⃣ UPDATE LOAN STATUS
        // Corrected status: changed 'cleared' to 'completed' to match your DB Enum
        if ($remaining_balance <= 0) {
            $update_status = "UPDATE loans SET status = 'completed' WHERE loan_id = ?";
            $stmt5 = $conn->prepare($update_status);
            $stmt5->bind_param("i", $loan_id);
            $stmt5->execute();
        }

        echo "<script>alert('Loan repayment recorded successfully! Remaining Balance: " . number_format($remaining_balance, 2) . "'); window.location.href='loans.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>