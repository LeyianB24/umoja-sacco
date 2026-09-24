<?php
session_start();
include('../config/db_connect.php');

if (isset($_GET['id']) && isset($_GET['action'])) {
    $loan_id = intval($_GET['id']);
    $action = $_GET['action'];

    $status = ($action === 'approve') ? 'Approved' : 'Rejected';
    $message = ($status === 'Approved') ? 'Your loan request has been approved.' : 'Your loan request has been rejected.';

    // Update loan status
    $sql = "UPDATE loans SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $loan_id);

    if ($stmt->execute()) {
        // Send notification to member
        $conn->query("
            INSERT INTO notifications (member_id, message, is_read, date_sent)
            SELECT member_id, '$message', 0, NOW() FROM loans WHERE id = $loan_id
        ");

        header("Location: manage_loans.php?success=Loan+$status+successfully");
        exit;
    } else {
        echo "Error updating record: " . $conn->error;
    }
} else {
    header("Location: manage_loans.php");
    exit;
}
?>
