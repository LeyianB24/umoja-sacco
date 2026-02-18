<?php
require_once __DIR__ . '/config/db_connect.php';

echo "--- Loans Counter ---\n";

$res = $conn->query("SELECT status, COUNT(*) as c FROM loans GROUP BY status");
while($row = $res->fetch_assoc()) {
    printf("Status: %s, Count: %d\n", $row['status'], $row['c']);
}

$res = $conn->query("SELECT m.full_name, l.status, l.loan_id FROM loans l JOIN members m ON l.member_id = m.member_id LIMIT 5");
while($row = $res->fetch_assoc()) {
    printf("Loan #%d (%s) - %s\n", $row['loan_id'], $row['status'], $row['full_name']);
}
