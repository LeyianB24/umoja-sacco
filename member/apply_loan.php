<?php
session_start();
include('../config/db_connect.php');

if (!isset($_SESSION['member_id'])) {
    header('Location: ../public/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_SESSION['member_id'];
    $amount = $_POST['amount'];
    $interest_rate = $_POST['interest_rate'];

    // Calculate total payable
    $total_payable = $amount + ($amount * $interest_rate / 100);
    $status = "pending";
    $created_at = date('Y-m-d H:i:s');

    $sql = "INSERT INTO loans (member_id, amount, interest_rate, total_payable, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iddsss", $member_id, $amount, $interest_rate, $total_payable, $status, $created_at);

    if ($stmt->execute()) {
        echo "<script>alert('Loan application submitted successfully.'); window.location.href='loans.php';</script>";
    } else {
        echo "<script>alert('Error submitting loan application. Please try again.'); window.location.href='loans.php';</script>";
    }
}
?>