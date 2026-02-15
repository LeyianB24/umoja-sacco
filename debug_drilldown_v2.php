<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db_connect.php';

function safe_query($conn, $sql) {
    if (!$conn) { global $conn; }
    if (!$conn) die("Database connection missing.");
    $res = $conn->query($sql);
    if (!$res) {
        echo "Query Failed: " . $conn->error . "\nSQL: $sql\n";
        return false;
    }
    return $res;
}

echo "--- DRILLDOWN: SACCO REVENUE ---\n";
// Get ID first
$id_res = safe_query($conn, "SELECT account_id FROM ledger_accounts WHERE account_name = 'SACCO Revenue' LIMIT 1");
if ($id_res && $row = $id_res->fetch_assoc()) {
    $acc_id = $row['account_id'];
    echo "Account ID: $acc_id\n";
    
    $sql = "SELECT description, SUM(credit) as total 
            FROM ledger_entries 
            WHERE account_id = $acc_id
            AND credit > 0
            GROUP BY description
            ORDER BY total DESC";
    $res = safe_query($conn, $sql);
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $desc = $row['description'] ?? 'No Desc';
            echo str_pad(substr($desc, 0, 50), 55) . number_format($row['total'], 2) . "\n";
        }
    }
} else {
    echo "SACCO Revenue account not found.\n";
}

echo "\n--- SEARCH: ACCOUNTS WITH 'INVEST' ---\n";
$res = safe_query($conn, "SELECT * FROM ledger_accounts WHERE account_name LIKE '%Invest%' OR category LIKE '%invest%'");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo "ID: {$row['account_id']} | Name: {$row['account_name']} | Cat: {$row['category']} | Type: {$row['account_type']}\n";
    }
}

echo "\n--- SEARCH: ENTRIES WITH 'INVEST' (First 20) ---\n";
$sql = "SELECT le.id, la.account_name, le.description, le.credit 
        FROM ledger_entries le 
        JOIN ledger_accounts la ON le.account_id = la.account_id
        WHERE le.description LIKE '%Invest%' AND le.credit > 0
        LIMIT 20";
$res = safe_query($conn, $sql);
if ($res) {
    while($row = $res->fetch_assoc()) {
         echo "ID: {$row['id']} | Acc: {$row['account_name']} | Desc: {$row['description']} | Cr: {$row['credit']}\n";
    }
}

echo "\n--- SEARCH: ENTRIES WITH 'REPAY' (OUTSIDE LOANS CATEGORY) ---\n";
$sql = "SELECT le.id, la.account_name, la.category, le.description, le.credit 
        FROM ledger_entries le 
        JOIN ledger_accounts la ON le.account_id = la.account_id
        WHERE le.description LIKE '%repay%' 
        AND la.category != 'loans'
        AND le.credit > 0
        LIMIT 20";
$res = safe_query($conn, $sql);
if ($res) {
    if ($res->num_rows == 0) echo "No misplaced repayments found.\n";
    while($row = $res->fetch_assoc()) {
         echo "ID: {$row['id']} | Cat: {$row['category']} | Desc: {$row['description']} | Cr: {$row['credit']}\n";
    }
}
?>
