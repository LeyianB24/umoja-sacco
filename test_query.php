<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require 'config/db_connect.php';

echo "Testing Welfare Cases Query...\n";

$sql = "SELECT c.*, m.full_name as member_name, m.member_id as m_id
        FROM welfare_cases c 
        LEFT JOIN members m ON c.related_member_id = m.member_id
        ORDER BY FIELD(status, 'pending', 'active', 'approved', 'funded', 'closed'), c.created_at DESC";

try {
    $res = $conn->query($sql);
    echo "QUERY SUCCESS. FOUND " . $res->num_rows . " ROWS.\n";
} catch (mysqli_sql_exception $e) {
    echo "QUERY FAILED EXCEPTION: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "GENERAL ERROR: " . $e->getMessage() . "\n";
}
