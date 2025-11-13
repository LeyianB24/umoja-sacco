<?php
session_start();
include('../config/db_connect.php');

if (!isset($_SESSION['member_id'])) {
    header('Location: ../public/login.php');
    exit();
}

$member_id = $_SESSION['member_id'];
$loan_id = $_GET['loan_id'] ?? null;

if (!$loan_id) {
    die("Invalid loan selection.");
}

$loan_query = $conn->prepare("SELECT * FROM loans WHERE loan_id = ? AND member_id = ?");
$loan_query->bind_param("ii", $loan_id, $member_id);
$loan_query->execute();
$loan = $loan_query->get_result()->fetch_assoc();

if (!$loan) die("Loan not found.");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Repay Loan | Umoja Sacco</title>
    <link rel="stylesheet" href="../inc/style.css">
</head>
<body>
<div class="container">
    <h2>Repay Loan #<?php echo $loan['loan_id']; ?></h2>
    <p>Loan Amount: Ksh <?php echo number_format($loan['total_payable'], 2); ?></p>

    <form method="POST" action="../member/mpesa_request.php">
        <input type="hidden" name="loan_id" value="<?php echo $loan['loan_id']; ?>">
        <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
        <input type="hidden" name="transaction_type" value="loan_repayment">
        <input type="hidden" name="description" value="Loan Repayment">

        <label>Enter Repayment Amount (Ksh):</label>
        <input type="number" name="amount" required>

        <button type="submit" class="btn btn-success">Pay with M-PESA</button>
    </form>
</div>
</body>
</html>
    <h2>Repay Loan #<?php echo $loan['loan_id']; ?></h2>
    <p>Loan Amount: Ksh <?php echo number_format($loan['total_payable'], 2); ?></p>

    <form method="POST" action="../member/mpesa_request.php">
        <input type="hidden" name="loan_id" value="<?php echo $loan['loan_id']; ?>">
        <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
        <input type="hidden" name="transaction_type" value="loan_repayment">
        <input type="hidden" name="description" value="Loan Repayment">

        <label>Enter Repayment Amount (Ksh):</label>
        <input type="number" name="amount" required>

        <button type="submit" class="btn btn-success">Pay with M-PESA</button>
    </form>