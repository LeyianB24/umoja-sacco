<?php
require 'config/db_connect.php';
// Force update using integer index 2 (active)
$res = $conn->query("UPDATE welfare_cases SET status = 2 WHERE CAST(status AS UNSIGNED) = 0");
if (!$res) echo "Query Error: " . $conn->error;
echo "Updated " . $conn->affected_rows . " cases.";
?>
