<?php
session_start();
include('../config/db_connect.php');

if (isset($_GET['id'])) {
    $loan_id = intval($_GET['id']);

    $sql = "DELETE FROM loans WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $loan_id);

    if ($stmt->execute()) {
        header("Location: manage_loans.php?success=Loan+deleted+successfully");
        exit;
    } else {
        echo "Error deleting loan: " . $conn->error;
    }
} else {
    header("Location: manage_loans.php");
    exit;
}
?>
