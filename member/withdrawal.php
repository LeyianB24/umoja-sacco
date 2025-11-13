<?php
session_start();
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_config.php';

if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);

    // --- Get member's available balance ---
    $sqlBalance = "
        SELECT 
            COALESCE(SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN transaction_type = 'withdrawal' THEN amount ELSE 0 END), 0) AS balance
        FROM savings
        WHERE member_id = ?
    ";
    $stmt = $conn->prepare($sqlBalance);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $balanceResult = $stmt->get_result()->fetch_assoc();
    $balance = (float) ($balanceResult['balance'] ?? 0);
    $stmt->close();

    // --- Validate input ---
    if ($amount <= 0) {
        $error = "Invalid withdrawal amount.";
    } elseif ($amount > $balance) {
        $error = "Insufficient balance. Your available balance is KSh " . number_format($balance, 2);
    } else {
        try {
            // --- Generate reference number ---
            $reference_no = 'WDR-' . strtoupper(uniqid());

            // --- Insert into savings table (record withdrawal) ---
            $sqlInsert = "
                INSERT INTO savings (member_id, amount, reference_no, transaction_type, description, created_at)
                VALUES (?, ?, ?, 'withdrawal', ?, NOW())
            ";
            $stmtInsert = $conn->prepare($sqlInsert);
            $stmtInsert->bind_param("idss", $member_id, $amount, $reference_no, $description);
            $stmtInsert->execute();
            $related_id = $conn->insert_id; // ID for linking to transactions
            $stmtInsert->close();

            // --- Insert into transactions table (for reporting & summary) ---
            $transaction_type = 'withdrawal';
            $payment_channel = 'account'; // Or 'manual'/'cash' depending on your flow
            $note = 'Member withdrawal processed manually';

            $stmtT = $conn->prepare("
                INSERT INTO transactions (member_id, transaction_type, amount, related_id, payment_channel, notes, created_at, reference_no)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmtT->bind_param("isdssss", $member_id, $transaction_type, $amount, $related_id, $payment_channel, $note, $reference_no);
            $stmtT->execute();
            $stmtT->close();

            $_SESSION['success'] = "Withdrawal of KSh " . number_format($amount, 2) . " successful!";
            header("Location: " . BASE_URL . "/member/savings.php");
            exit;

        } catch (Exception $e) {
            $error = "Error processing withdrawal: " . $e->getMessage();
        }
    }
}

// --- Redirect with error if any ---
if (isset($error)) {
    $_SESSION['error'] = $error;
    header("Location: " . BASE_URL . "/member/savings.php");
    exit;
}
?>