<?php
/**
 * Deep Database Audit with File Output
 */
require_once __DIR__ . '/../config/db_connect.php';

$member_id = 8;
$output = "";

$output .= "DEEP DATABASE AUDIT - MEMBER ID: $member_id\n\n";

// 1. CONTRIBUTIONS
$output .= "1. CONTRIBUTIONS TABLE:\n";
$sql = "SELECT * FROM contributions WHERE member_id = $member_id ORDER BY created_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $totals = [];
    while ($row = $result->fetch_assoc()) {
        $output .= sprintf(
            "ID: %d | Type: %-15s | Amount: %8.2f | Status: %-10s | Ref: %s\n",
            $row['contribution_id'],
            $row['contribution_type'],
            $row['amount'],
            $row['status'],
            $row['reference_no'] ?? 'NULL'
        );
        if ($row['status'] === 'active') {
            $type = $row['contribution_type'];
            $totals[$type] = ($totals[$type] ?? 0) + $row['amount'];
        }
    }
    $output .= "\nACTIVE TOTALS:\n";
    foreach ($totals as $type => $amount) {
        $output .= "  $type: KES " . number_format($amount, 2) . "\n";
    }
} else {
    $output .= "NO CONTRIBUTIONS FOUND!\n";
}

// 2. LEDGER ACCOUNTS
$output .= "\n2. LEDGER_ACCOUNTS TABLE:\n";
$sql = "SELECT * FROM ledger_accounts WHERE member_id = $member_id";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $output .= sprintf(
            "ID: %d | Category: %-10s | Balance: %10.2f | Type: %s\n",
            $row['account_id'],
            $row['category'],
            $row['current_balance'],
            $row['account_type']
        );
    }
} else {
    $output .= "NO LEDGER ACCOUNTS FOUND!\n";
}

// 3. LEDGER ENTRIES
$output .= "\n3. LEDGER_ENTRIES:\n";
$sql = "SELECT le.*, la.category 
        FROM ledger_entries le
        JOIN ledger_accounts la ON le.account_id = la.account_id
        WHERE la.member_id = $member_id
        ORDER BY le.entry_id DESC
        LIMIT 10";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $output .= sprintf(
            "Entry: %d | Category: %-10s | Debit: %8.2f | Credit: %8.2f | Balance: %10.2f\n",
            $row['entry_id'],
            $row['category'],
            $row['debit'],
            $row['credit'],
            $row['balance_after']
        );
    }
} else {
    $output .= "NO LEDGER ENTRIES FOUND!\n";
}

// 4. DIAGNOSIS
$output .= "\n4. DIAGNOSIS:\n";
$contrib_count = $conn->query("SELECT COUNT(*) as cnt FROM contributions WHERE member_id = $member_id AND status = 'active'")->fetch_assoc()['cnt'];
$ledger_entry_count = $conn->query("SELECT COUNT(*) as cnt FROM ledger_entries le JOIN ledger_accounts la ON le.account_id = la.account_id WHERE la.member_id = $member_id")->fetch_assoc()['cnt'];

$output .= "Active Contributions: $contrib_count\n";
$output .= "Ledger Entries: $ledger_entry_count\n\n";

if ($contrib_count > 0 && $ledger_entry_count == 0) {
    $output .= "CRITICAL: Contributions exist but NO ledger entries!\n";
    $output .= "ROOT CAUSE: Transactions never processed through FinancialEngine\n";
    $output .= "SOLUTION: Need to sync contributions to ledger\n";
}

// Write to file
file_put_contents(__DIR__ . '/audit_output.txt', $output);
echo $output;
echo "\n✅ Output saved to tests/audit_output.txt\n";
