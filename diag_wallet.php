<?php
require_once __DIR__ . '/config/db_connect.php';

echo "--- Disbursed Loans & Wallet Balances ---\n";

$sql = "SELECT m.member_id, m.full_name, l.loan_id, l.amount, l.disbursed_date 
        FROM loans l 
        JOIN members m ON l.member_id = m.member_id 
        WHERE l.status = 'disbursed' 
        ORDER BY l.disbursed_date DESC";

$res = $conn->query($sql);
while($row = $res->fetch_assoc()) {
    $mid = $row['member_id'];
    
    // Get Wallet Balance
    $w_res = $conn->query("SELECT current_balance, account_id FROM ledger_accounts WHERE member_id = $mid AND category = 'wallet'");
    $w_row = $w_res->fetch_assoc();
    $wallet_bal = $w_row['current_balance'] ?? 0;
    $acc_id = $w_row['account_id'] ?? 0;

    printf("Member: %s (ID: %d)\n", $row['full_name'], $mid);
    printf("  Loan #%d: KES %.2f (Disbursed: %s)\n", $row['loan_id'], $row['amount'], $row['disbursed_date']);
    printf("  Current Wallet Balance: KES %.2f\n", $wallet_bal);

    if ($acc_id) {
        $q = "SELECT transaction_id, credit, debit FROM ledger_entries WHERE account_id = $acc_id ORDER BY transaction_id DESC LIMIT 1";
        $t_res = $conn->query($q);
        if ($t_res && $t_row = $t_res->fetch_assoc()) {
             printf("  Last Entry: Txn #%d (Credit: %.2f, Debit: %.2f)\n", 
                $t_row['transaction_id'], $t_row['credit'], $t_row['debit']);
        }
    }
    echo "--------------------------------------------------\n";
}


