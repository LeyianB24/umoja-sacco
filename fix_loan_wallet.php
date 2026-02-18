<?php
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/inc/FinancialEngine.php';

echo "--- Fixing Legacy Loan Disbursements ---\n";

// 1. Find Disbursed Loans with 0 Wallet Balance (Legacy Logic Candidates)
$sql = "SELECT l.loan_id, l.member_id, l.amount, l.disbursed_date 
        FROM loans l 
        WHERE l.status = 'disbursed'";
$res = $conn->query($sql);
if (!$res) {
    die("Main query failed: " . $conn->error . "\n");
}

$engine = new FinancialEngine($conn);

$fixed_count = 0;

while($row = $res->fetch_assoc()) {
    $lid = $row['loan_id'];
    $mid = $row['member_id'];
    $amt = (float)$row['amount'];
    
    // Check Wallet Balance
    $bals = $engine->getBalances($mid);
    if ($bals['wallet'] > 100) {
        echo "Loan #$lid (Member $mid): Wallet has balance (" . $bals['wallet'] . "). Skipping.\n";
        continue;
    }

    // Check Transaction via Ledger Transactions Directly
    $t_res = $conn->query("SELECT transaction_id FROM ledger_transactions WHERE related_table = 'loans' AND related_id = $lid AND action_type = 'loan_disbursement'");
    if (!$t_res || $t_res->num_rows == 0) {
        echo "  No ledger transaction found for loan #$lid. Skipping.\n";
        continue;
    }
    
    $txn = $t_res->fetch_assoc();
    $tid = $txn['transaction_id'];

    if (!$tid) {
        echo "  Transaction ID is invalid. Skipping.\n";
        continue;
    }

    
    // Check Entries Count (Legacy has 4, Modern has 2)
    $e_res = $conn->query("SELECT COUNT(*) as c FROM ledger_entries WHERE transaction_id = $tid");
    if (!$e_res) {
         echo "  Entries query failed: " . $conn->error . "\n";
         continue;
    }
    $cnt = $e_res->fetch_assoc()['c'];

    
    if ($cnt < 3) {
        echo "Loan #$lid: Seems modern (entries=$cnt). Skipping.\n";
        continue;
    }

    echo "Loan #$lid (Legacy detected): Fixing...\n";

    // Identifying the System Account used (The one that was CREDITED but NOT the Wallet)
    // Wallet ID
    $wallet_id = $engine->getMemberAccount($mid, 'wallet');
    if (!$wallet_id) {
        echo "  Could not retrieve/create wallet account for member $mid. Skipping.\n";
        continue;
    }
    
    // Find the system account that was credited
    $q_sys = "SELECT account_id, credit FROM ledger_entries WHERE transaction_id = $tid AND account_id != $wallet_id AND credit > 0";
    $s_res = $conn->query($q_sys);
    
    if (!$s_res) {
        echo "  System entry query failed: " . $conn->error . "\n";
        continue;
    }
    
    $sys_entry = $s_res->fetch_assoc();
    
    if (!$sys_entry) {
        echo "  Could not identify system account (No credit entry found distinct from wallet). Skipping.\n";
        continue;
    }
    
    $sys_acc_id = $sys_entry['account_id'];
    $credit_amt = $sys_entry['credit'];
    
    echo "  System Acc ID: $sys_acc_id, Amount: $credit_amt\n";

    // perform FIX: Debit System, Credit Wallet
    $conn->begin_transaction();
    try {
        $fix_ref = "FIX-LOAN-$lid-" . time();
        $st = $conn->prepare("INSERT INTO ledger_transactions (reference_no, transaction_date, description, action_type, related_table, related_id) VALUES (?, NOW(), 'Fix: Legacy Loan Disbursement Correction', 'adjustment', 'loans', ?)");
        $st->bind_param("si", $fix_ref, $lid);
        $st->execute();
        $fix_tid = $conn->insert_id;
        
        // 1. Debit System (Put money back "virtually")
        // Update balance
        $conn->query("UPDATE ledger_accounts SET current_balance = current_balance + $credit_amt WHERE account_id = $sys_acc_id"); // Asset Debit increases balance? Yes. Wait.
        // Asset: Debit +, Credit -.
        // Account Type?
        $a_q = $conn->query("SELECT account_type FROM ledger_accounts WHERE account_id = $sys_acc_id");
        if (!$a_q || $a_q->num_rows == 0) {
             throw new Exception("Could not determine account type for system account $sys_acc_id");
        }
        $a_type = $a_q->fetch_assoc()['account_type'];
        
        $bal_change = $credit_amt; // Debit increases asset
        if ($a_type !== 'asset' && $a_type !== 'expense') $bal_change = -$credit_amt; // Invert for liability? No, we are debiting.
        // Wait, Asset/Expense Debit = Increase. Liability/Equity/Revenue Debit = Decrease.
        // We want to REVERSE a Credit.
        // Original Credit Decreased the Asset.
        // So Debit Increases the Asset.
        // Correct.
        
        // 2. Credit Wallet (Give money to member)
        // Liability: Credit = Increase.
        $conn->query("UPDATE ledger_accounts SET current_balance = current_balance + $credit_amt WHERE account_id = $wallet_id");
        
        // Insert Entries
        $ist = $conn->prepare("INSERT INTO ledger_entries (transaction_id, account_id, debit, credit, balance_after) VALUES (?, ?, ?, ?, 0)");
        
        $d = $credit_amt; $c = 0;
        $ist->bind_param("iidd", $fix_tid, $sys_acc_id, $d, $c); // Debit System
        $ist->execute();
        
        $d = 0; $c = $credit_amt;
        $ist->bind_param("iidd", $fix_tid, $wallet_id, $d, $c); // Credit Wallet
        $ist->execute();
        
        $conn->commit();
        echo "  Fixed! Wallet updated.\n";
        $fixed_count++;
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "  Error: " . $e->getMessage() . "\n";
    }
}
echo "Done. Fixed $fixed_count loans.\n";
