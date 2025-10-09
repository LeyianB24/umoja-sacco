<?php
session_start();
include('../config/db_connect.php');

if (isset($_GET['id'])) {
    $loan_id = intval($_GET['id']);

    // Update loan status to Rejected
    $sql = "UPDATE loans SET status = 'Rejected' WHERE id = $loan_id";
    if ($conn->query($sql)) {
        // Optionally, add a notification to the member
        $conn->query("
            INSERT INTO notifications (member_id, message, is_read, date_sent)
            SELECT member_id, 'Your loan request has been rejected.', 0, NOW() FROM loans WHERE id = $loan_id
        ");

        header("Location: manage_loans.php?success=Loan+rejected+successfully");
        exit;
    } else {
        echo "Error updating record: " . $conn->error;
    }
} else {
    header("Location: manage_loans.php");
    exit;
}
?>
