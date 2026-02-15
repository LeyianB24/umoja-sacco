<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure we are in the right directory context if needed, but absolute path is safer
require_once __DIR__ . '/config/db_connect.php';

if (!isset($conn) || $conn->connect_error) {
    die("Connection failed: " . ($conn->connect_error ?? 'Variable not set'));
}

echo "--- DRILLDOWN: SACCO REVENUE ---\n";

$sql_id = "SELECT account_id FROM ledger_accounts WHERE account_name = 'SACCO Revenue' LIMIT 1";
$res_id = $conn->query($sql_id);
if ($res_id && $row = $res_id->fetch_assoc()) {
    $acc_id = $row['account_id'];
    echo "Account ID: $acc_id\n";
    
    $sql = "SELECT description, SUM(credit) as total 
            FROM ledger_entries 
            WHERE account_id = $acc_id
            AND credit > 0
            GROUP BY description
            ORDER BY total DESC";
    $res = $conn->query($sql);
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $desc = $row['description'] ?? 'No Desc';
            // Clean formatting
            $desc = str_replace(["\r", "\n"], ' ', $desc);
            echo str_pad(substr($desc, 0, 50), 55) . number_format($row['total'], 2) . "\n";
        }
    } else {
        echo "Query Error: " . $conn->error . "\n";
    }
} else {
    echo "SACCO Revenue account not found.\n";
}

echo "\n--- SEARCH: ACCOUNTS WITH 'INVEST' ---\n";
$sql_inv = "SELECT * FROM ledger_accounts WHERE account_name LIKE '%Invest%' OR category LIKE '%invest%'";
$res_inv = $conn->query($sql_inv);
if ($res_inv) {
    while($row = $res_inv->fetch_assoc()) {
        echo "ID: {$row['account_id']} | Name: {$row['account_name']} | Cat: {$row['category']} | Type: {$row['account_type']}\n";
    }
} else {
    echo "Query Error: " . $conn->error . "\n";
}


echo "\n--- SEARCH: ENTRIES WITH 'INVEST' (Limit 20) ---\n";
$sql_ent = "SELECT le.id, la.account_name, le.description, le.credit 
        FROM ledger_entries le 
        JOIN ledger_accounts la ON le.account_id = la.account_id
        WHERE le.description LIKE '%Invest%' AND le.credit > 0
        LIMIT 20";
$res_ent = $conn->query($sql_ent);
if ($res_ent) {
    while($row = $res_ent->fetch_assoc()) {
         echo "ID: {$row['id']} | Acc: {$row['account_name']} | Desc: {$row['description']} | Cr: {$row['credit']}\n";
    }
}

echo "\n--- SEARCH: ENTRIES WITH 'REPAY' (OUTSIDE LOANS CATEGORY) ---\n";
$sql_rep = "SELECT le.id, la.account_name, la.category, le.description, le.credit 
        FROM ledger_entries le 
        JOIN ledger_accounts la ON le.account_id = la.account_id
        WHERE le.description LIKE '%repay%' 
        AND la.category != 'loans'
        AND le.credit > 0
        LIMIT 20";
$res_rep = $conn->query($sql_rep);
if ($res_rep) {
    if ($res_rep->num_rows == 0) echo "No misplaced repayments found.\n";
    while($row = $res_rep->fetch_assoc()) {
         echo "ID: {$row['id']} | Cat: {$row['category']} | Desc: {$row['description']} | Cr: {$row['credit']}\n";
    }
}

?>
