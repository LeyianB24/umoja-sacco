<?php
/**
 * rebalance_ledger.php
 * Script to fix imbalanced ledger transactions by offsetting differences to a Suspense Account.
 */
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

echo "Starting Ledger Rebalancing...\n";

// 1. Correct "Opening Balance Equity" type
$conn->query("UPDATE ledger_accounts SET account_type = 'equity' WHERE account_id = 28 AND account_name = 'Opening Balance Equity'");
if ($conn->affected_rows > 0) {
    echo "Corrected 'Opening Balance Equity' account type to 'equity'.\n";
}

// 2. Ensure Suspense Account exists
$suspense_name = "Accounting Adjustment (Suspense)";
$res = $conn->query("SELECT account_id FROM ledger_accounts WHERE account_name = '$suspense_name'");
if ($res->num_rows === 0) {
    $conn->query("INSERT INTO ledger_accounts (account_name, account_type, category) VALUES ('$suspense_name', 'equity', 'system')");
    $suspense_id = (int)$conn->insert_id;
    echo "Created Suspense Account (ID: $suspense_id).\n";
} else {
    $suspense_id = (int)$res->fetch_assoc()['account_id'];
    echo "Using existing Suspense Account (ID: $suspense_id).\n";
}

// 3. Find imbalanced transactions
$sql = "SELECT transaction_id, SUM(debit) as total_debit, SUM(credit) as total_credit 
        FROM ledger_entries 
        GROUP BY transaction_id 
        HAVING ABS(total_debit - total_credit) > 0.001";
$res = $conn->query($sql);

$imbalanced = [];
while ($row = $res->fetch_assoc()) $imbalanced[] = $row;

echo "Found " . count($imbalanced) . " imbalanced transactions to fix.\n";

$conn->begin_transaction();
try {
    foreach ($imbalanced as $txn) {
        $tid = (int)$txn['transaction_id'];
        $debit = (float)$txn['total_debit'];
        $credit = (float)$txn['total_credit'];
        $diff = $credit - $debit; // Balance needed on Debit side if positive, Credit if negative

        $adj_debit = 0;
        $adj_credit = 0;

        if ($diff > 0) {
            $adj_debit = $diff;
        } else {
            $adj_credit = abs($diff);
        }

        // Add balancing entry
        $stmt = $conn->prepare("INSERT INTO ledger_entries (transaction_id, account_id, debit, credit, balance_after, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $stmt->bind_param("iidd", $tid, $suspense_id, $adj_debit, $adj_credit);
        $stmt->execute();

        // Update transaction description
        $conn->query("UPDATE ledger_transactions SET description = CONCAT(description, ' [Auto-Balanced]') WHERE transaction_id = $tid");

        echo "Fixed Transaction #$tid: Offset " . ($adj_debit > 0 ? "Debit" : "Credit") . " of KES " . number_format(max($adj_debit, $adj_credit), 2) . "\n";
    }

    // 4. Recalculate account balances to be sure
    echo "Recalculating all ledger account balances...\n";
    $conn->query("UPDATE ledger_accounts SET current_balance = 0");
    
    // Fetch all entries ordered by date to reconstruct balances
    $entries_res = $conn->query("SELECT * FROM ledger_entries ORDER BY entry_id ASC");
    $acc_balances = [];
    
    while ($e = $entries_res->fetch_assoc()) {
        $aid = (int)$e['account_id'];
        if (!isset($acc_balances[$aid])) {
            $acc_res = $conn->query("SELECT account_type FROM ledger_accounts WHERE account_id = $aid");
            $acc_balances[$aid] = ['type' => $acc_res->fetch_assoc()['account_type'], 'bal' => 0.0];
        }
        
        $debit = (float)$e['debit'];
        $credit = (float)$e['credit'];
        
        if ($acc_balances[$aid]['type'] === 'asset' || $acc_balances[$aid]['type'] === 'expense') {
            $acc_balances[$aid]['bal'] += ($debit - $credit);
        } else {
            $acc_balances[$aid]['bal'] += ($credit - $debit);
        }
        
        // Update balance_after in the entry for audit integrity
        $conn->query("UPDATE ledger_entries SET balance_after = " . $acc_balances[$aid]['bal'] . " WHERE entry_id = " . $e['entry_id']);
    }
    
    foreach ($acc_balances as $aid => $data) {
        $conn->query("UPDATE ledger_accounts SET current_balance = " . $data['bal'] . " WHERE account_id = $aid");
    }

    $conn->commit();
    echo "SUCCESS: Ledger is now balanced.\n";
} catch (Exception $e) {
    $conn->rollback();
    echo "ERROR: " . $e->getMessage() . "\n";
}
