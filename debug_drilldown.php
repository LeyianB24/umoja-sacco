<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db_connect.php';

echo "--- DRILLDOWN: SACCO REVENUE ---\n";
// 1. Analyze SACCO Revenue breakdown
$sql = "SELECT description, SUM(credit) as total 
        FROM ledger_entries 
        WHERE account_id = (SELECT account_id FROM ledger_accounts WHERE account_name = 'SACCO Revenue' LIMIT 1)
        AND credit > 0
        GROUP BY description
        ORDER BY total DESC";
$res = $conn->query($sql);
if ($res) {
    while($row = $res->fetch_assoc()) {
         echo str_pad(substr($row['description'] ?? 'No Desc', 0, 50), 55) . number_format($row['total'], 2) . "\n";
    }
}

echo "\n--- SEARCH: ACCOUNTS WITH 'INVEST' ---\n";
$sql = "SELECT * FROM ledger_accounts WHERE account_name LIKE '%Invest%' OR category LIKE '%invest%'";
$res = $conn->query($sql);
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['account_id']} | Name: {$row['account_name']} | Cat: {$row['category']} | Type: {$row['account_type']}\n";
}

echo "\n--- SEARCH: ENTRIES WITH 'INVEST' ---\n";
$sql = "SELECT le.id, la.account_name, le.description, le.credit 
        FROM ledger_entries le 
        JOIN ledger_accounts la ON le.account_id = la.account_id
        WHERE le.description LIKE '%Invest%' AND le.credit > 0
        LIMIT 20";
$res = $conn->query($sql);
while($row = $res->fetch_assoc()) {
     echo "ID: {$row['id']} | Acc: {$row['account_name']} | Desc: {$row['description']} | Cr: {$row['credit']}\n";
}

echo "\n--- SEARCH: ENTRIES WITH 'REPAY' (OUTSIDE LOANS CATEGORY) ---\n";
$sql = "SELECT le.id, la.account_name, la.category, le.description, le.credit 
        FROM ledger_entries le 
        JOIN ledger_accounts la ON le.account_id = la.account_id
        WHERE le.description LIKE '%repay%' 
        AND la.category != 'loans'
        AND le.credit > 0
        LIMIT 20";
$res = $conn->query($sql);
if ($res->num_rows == 0) echo "No misplaced repayments found.\n";
while($row = $res->fetch_assoc()) {
     echo "ID: {$row['id']} | Cat: {$row['category']} | Desc: {$row['description']} | Cr: {$row['credit']}\n";
}
?>
