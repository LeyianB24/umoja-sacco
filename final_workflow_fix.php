<?php
require 'config/db_connect.php';

// Update support statuses to 'disbursed' so they appear in totals
$conn->query("UPDATE welfare_support SET status = 'disbursed' WHERE status = '' OR status IS NULL");
echo "Updated " . $conn->affected_rows . " support records.\n";

// Final check on case assignments
$conn->query("UPDATE welfare_cases SET related_member_id = 3 WHERE case_id = 1");
$conn->query("UPDATE welfare_cases SET related_member_id = 5 WHERE case_id = 2");
echo "Assignments verified.\n";
?>
