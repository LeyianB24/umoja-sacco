<?php
require 'config/db_connect.php';
// Set approved (2) to active (3) for better visibility
$conn->query("UPDATE welfare_cases SET status = 3 WHERE CAST(status AS UNSIGNED) = 2");
echo "Updated " . $conn->affected_rows . " cases to active.";
?>
