<?php
/**
 * Deep Database Audit - Find the Root Cause
 */
require_once __DIR__ . '/../config/db_connect.php';

$member_id = 8;

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║         DEEP DATABASE AUDIT - MEMBER ID: $member_id              ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 1. CONTRIBUTIONS - Raw Data
echo "1️⃣  CONTRIBUTIONS TABLE (RAW DATA):\n";
echo str_repeat("─", 60) . "\n";
$sql = "SELECT * FROM contributions WHERE member_id = $member_id ORDER BY created_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo sprintf(
            "ID: %d | Type: %-15s | Amount: %8.2f | Status: %-10s | Ref: %s | Date: %s\n",
            $row['contribution_id'],
            $row['contribution_type'],
            $row['amount'],
            $row['status'],
            $row['reference_no'] ?? 'NULL',
            $row['created_at']
        );
    }
    
    // Calculate totals
    $result->data_seek(0);
    $totals = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'active') {
            $type = $row['contribution_type'];
            $totals[$type] = ($totals[$type] ?? 0) + $row['amount'];
        }
    }
    
    echo "\n📊 ACTIVE CONTRIBUTION TOTALS:\n";
    foreach ($totals as $type => $amount) {
        echo "   $type: KES " . number_format($amount, 2) . "\n";
    }
} else {
    echo "❌ NO CONTRIBUTIONS FOUND!\n";
}

// 2. LEDGER ACCOUNTS - Current State
echo "\n\n2️⃣  LEDGER_ACCOUNTS TABLE (CURRENT STATE):\n";
echo str_repeat("─", 60) . "\n";
$sql = "SELECT * FROM ledger_accounts WHERE member_id = $member_id ORDER BY category";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo sprintf(
            "ID: %d | Category: %-10s | Balance: %10.2f | Type: %s\n",
            $row['account_id'],
            $row['category'],
            $row['current_balance'],
            $row['account_type']
        );
    }
} else {
    echo "❌ NO LEDGER ACCOUNTS FOUND!\n";
}

// 3. LEDGER ENTRIES - Transaction History
echo "\n\n3️⃣  LEDGER_ENTRIES (TRANSACTION HISTORY):\n";
echo str_repeat("─", 60) . "\n";
$sql = "SELECT le.*, la.category, lt.description 
        FROM ledger_entries le
        JOIN ledger_accounts la ON le.account_id = la.account_id
        LEFT JOIN ledger_transactions lt ON le.transaction_id = lt.transaction_id
        WHERE la.member_id = $member_id
        ORDER BY le.entry_id DESC
        LIMIT 20";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo sprintf(
            "Entry: %d | Category: %-10s | Debit: %8.2f | Credit: %8.2f | Balance After: %10.2f | Desc: %s\n",
            $row['entry_id'],
            $row['category'],
            $row['debit'],
            $row['credit'],
            $row['balance_after'],
            substr($row['description'] ?? 'N/A', 0, 30)
        );
    }
} else {
    echo "❌ NO LEDGER ENTRIES FOUND!\n";
}

// 4. TRANSACTIONS TABLE
echo "\n\n4️⃣  TRANSACTIONS TABLE:\n";
echo str_repeat("─", 60) . "\n";
$sql = "SELECT * FROM transactions WHERE member_id = $member_id ORDER BY created_at DESC LIMIT 10";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo sprintf(
            "ID: %d | Type: %-15s | Amount: %8.2f | Ref: %s | Date: %s\n",
            $row['transaction_id'],
            $row['transaction_type'],
            $row['amount'],
            $row['reference_no'] ?? 'NULL',
            $row['created_at']
        );
    }
} else {
    echo "❌ NO TRANSACTIONS FOUND!\n";
}

// 5. DIAGNOSIS
echo "\n\n5️⃣  DIAGNOSIS:\n";
echo str_repeat("─", 60) . "\n";

// Check if contributions exist but ledger is empty
$contrib_count = $conn->query("SELECT COUNT(*) as cnt FROM contributions WHERE member_id = $member_id AND status = 'active'")->fetch_assoc()['cnt'];
$ledger_entry_count = $conn->query("SELECT COUNT(*) as cnt FROM ledger_entries le JOIN ledger_accounts la ON le.account_id = la.account_id WHERE la.member_id = $member_id")->fetch_assoc()['cnt'];
$ledger_balance_sum = $conn->query("SELECT SUM(current_balance) as total FROM ledger_accounts WHERE member_id = $member_id")->fetch_assoc()['total'];

echo "Active Contributions: $contrib_count\n";
echo "Ledger Entries: $ledger_entry_count\n";
echo "Total Ledger Balance: KES " . number_format($ledger_balance_sum ?? 0, 2) . "\n\n";

if ($contrib_count > 0 && $ledger_entry_count == 0) {
    echo "🔴 CRITICAL: Contributions exist but NO ledger entries!\n";
    echo "   → Transactions were never processed through FinancialEngine\n";
    echo "   → Need to sync contributions to ledger\n";
} elseif ($contrib_count > 0 && $ledger_balance_sum == 0) {
    echo "🟡 WARNING: Ledger entries exist but balances are 0.00\n";
    echo "   → Ledger entries may be incorrect or cancelled out\n";
    echo "   → Need to recalculate balances from entries\n";
} elseif ($contrib_count == 0) {
    echo "🔵 INFO: No active contributions found\n";
    echo "   → Member has no transaction history\n";
} else {
    echo "🟢 OK: System appears normal\n";
}

echo "\n" . str_repeat("═", 60) . "\n";
echo "END OF AUDIT\n";
echo str_repeat("═", 60) . "\n";
