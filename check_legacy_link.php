<?php
require_once __DIR__ . '/config/db_connect.php';

echo "--- Checking Legacy Link for Loans ---\n";

// Get a few disbursed loans
$res = $conn->query("SELECT loan_id FROM loans WHERE status='disbursed' LIMIT 3");
while($row = $res->fetch_assoc()) {
    $lid = $row['loan_id'];
    echo "Loan #$lid:\n";
    
    // Check 'transactions' table for this loan
    $t_res = $conn->query("SELECT * FROM transactions WHERE related_table = 'loans' AND related_id = $lid");
    if($t_res->num_rows > 0) {
        while($t = $t_res->fetch_assoc()) {
            print_r($t);
        }
    } else {
        echo "  No entry in 'transactions' table.\n";
    }
    
    // Check 'ledger_transactions' directly
    $lt_res = $conn->query("SELECT * FROM ledger_transactions WHERE related_table = 'loans' AND related_id = $lid");
    if($lt_res->num_rows > 0) {
        while($lt = $lt_res->fetch_assoc()) {
            echo "  Found in ledger_transactions directly: ID " . $lt['transaction_id'] . "\n";
        }
    } else {
        echo "  No direct entry in 'ledger_transactions' table.\n";
    }
    echo "-----------------\n";
}
