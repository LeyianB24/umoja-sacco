<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$user = 'root';
$pass = ''; 
$dbname = 'umoja_drivers_sacco';

$conn = mysqli_connect($host, $user, $pass, $dbname);
if (!$conn) die("Connection failed: " . mysqli_connect_error());

echo "--- DRILLDOWN: SACCO REVENUE ---\n";
$sql_id = "SELECT account_id FROM ledger_accounts WHERE account_name = 'SACCO Revenue' LIMIT 1";
$res_id = mysqli_query($conn, $sql_id);
if ($res_id && $row = mysqli_fetch_assoc($res_id)) {
    $acc_id = $row['account_id'];
    echo "Account ID: $acc_id\n";
    
    $sql = "SELECT description, SUM(credit) as total 
            FROM ledger_entries 
            WHERE account_id = $acc_id
            AND credit > 0
            GROUP BY description
            ORDER BY total DESC";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while($row = mysqli_fetch_assoc($res)) {
            $desc = $row['description'] ?? 'No Desc';
            $desc = str_replace(["\r", "\n"], ' ', $desc);
            echo str_pad(substr($desc, 0, 50), 55) . number_format($row['total'], 2) . "\n";
        }
    }
} else {
    echo "SACCO Revenue account not found.\n";
}

echo "\n--- SEARCH: ACCOUNTS WITH 'INVEST' ---\n";
$sql_inv = "SELECT * FROM ledger_accounts WHERE account_name LIKE '%Invest%' OR category LIKE '%invest%'";
$res_inv = mysqli_query($conn, $sql_inv);
if ($res_inv) {
    while($row = mysqli_fetch_assoc($res_inv)) {
        echo "ID: {$row['account_id']} | Name: {$row['account_name']} | Cat: {$row['category']} | Type: {$row['account_type']}\n";
    }
}

echo "\n--- SEARCH: ENTRIES WITH 'INVEST' (Limit 20) ---\n";
$sql_ent = "SELECT le.id, la.account_name, le.description, le.credit 
        FROM ledger_entries le 
        JOIN ledger_accounts la ON le.account_id = la.account_id
        WHERE le.description LIKE '%Invest%' AND le.credit > 0
        LIMIT 20";
$res_ent = mysqli_query($conn, $sql_ent);
if ($res_ent) {
    while($row = mysqli_fetch_assoc($res_ent)) {
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
$res_rep = mysqli_query($conn, $sql_rep);
if ($res_rep) {
    if (mysqli_num_rows($res_rep) == 0) echo "No misplaced repayments found.\n";
    while($row = mysqli_fetch_assoc($res_rep)) {
         echo "ID: {$row['id']} | Cat: {$row['category']} | Desc: {$row['description']} | Cr: {$row['credit']}\n";
    }
}
?>
