<?php
// audit_historical_welfare.php
require 'config/db_connect.php';

echo "### Auditing Expenses for Welfare keywords ###\n";
$res = $conn->query("SELECT * FROM expenses WHERE description LIKE '%welfare%' OR category LIKE '%welfare%'");
while($row = $res->fetch_assoc()) {
    echo "[Expense] ID: {$row['expense_id']}, Amt: {$row['amount']}, Desc: {$row['description']}, Date: {$row['expense_date']}\n";
}

echo "\n### Auditing Transactions for Welfare actions ###\n";
$res = $conn->query("SELECT * FROM transactions WHERE action_type LIKE '%welfare%' OR category LIKE '%welfare%'");
while($row = $res->fetch_assoc()) {
    echo "[Txn] ID: {$row['transaction_id']}, Amt: {$row['amount']}, Action: {$row['action_type']}, Ref: {$row['reference_no']}, Date: {$row['transaction_date']}\n";
}

echo "\n### Auditing existing Welfare Support table ###\n";
$res = $conn->query("SELECT * FROM welfare_support");
while($row = $res->fetch_assoc()) {
    echo "[Support] ID: {$row['support_id']}, Member: {$row['member_id']}, Amt: {$row['amount']}, Case: " . ($row['case_id'] ?? 'NULL') . ", Status: {$row['status']}\n";
}
