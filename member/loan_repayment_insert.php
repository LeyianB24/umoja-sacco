<?php
include('../config/db_connect.php');
session_start();

if (!isset($_SESSION['member_id'])) {
    header('Location: ../public/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_SESSION['member_id'];
    $loan_id = $_POST['loan_id'];
    $amount = $_POST['amount'];
    $payment_method = "M-PESA";
    $payment_date = date('Y-m-d H:i:s');

    // 1️⃣ Insert into loan_repayments
    $insert_repay = "INSERT INTO loan_repayments (loan_id, member_id, amount_paid, payment_date, method)
                     VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_repay);
    $stmt->bind_param("iidss", $loan_id, $member_id, $amount, $payment_date, $payment_method);
    $stmt->execute();

    // 2️⃣ Insert into transactions table
    $transaction_type = "loan_repayment";
    $description = "Loan repayment via M-PESA";

    $insert_txn = "INSERT INTO transactions (member_id, transaction_type, amount, transaction_date, description)
                   VALUES (?, ?, ?, ?, ?)";
    $stmt2 = $conn->prepare($insert_txn);
    $stmt2->bind_param("isdss", $member_id, $transaction_type, $amount, $payment_date, $description);
    $stmt2->execute();

    // 3️⃣ Update loan balance if necessary (optional)
    $sum_paid_query = "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid FROM loan_repayments WHERE loan_id = ?";
    $stmt3 = $conn->prepare($sum_paid_query);
    $stmt3->bind_param("i", $loan_id);
    $stmt3->execute();
    $result = $stmt3->get_result()->fetch_assoc();
    $total_paid = $result['total_paid'];

    $loan_query = "SELECT total_payable FROM loans WHERE loan_id = ?";
    $stmt4 = $conn->prepare($loan_query);
    $stmt4->bind_param("i", $loan_id);
    $stmt4->execute();
    $loan = $stmt4->get_result()->fetch_assoc();

    if ($loan && $total_paid >= $loan['total_payable']) {
        $update_status = "UPDATE loans SET status = 'cleared' WHERE loan_id = ?";
        $stmt5 = $conn->prepare($update_status);
        $stmt5->bind_param("i", $loan_id);
        $stmt5->execute();
    }

    echo "<script>alert('Loan repayment recorded successfully!'); window.location.href='loans.php';</script>";
}
?>