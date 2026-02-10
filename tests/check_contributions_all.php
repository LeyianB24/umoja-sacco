<?php
// Check ALL contributions regardless of status
require_once __DIR__ . '/../config/db_connect.php';

echo "CONTRIBUTIONS TABLE ANALYSIS\n";
echo str_repeat("=", 60) . "\n\n";

// 1. Total count
$total = $conn->query("SELECT COUNT(*) as cnt FROM contributions")->fetch_assoc()['cnt'];
echo "Total contributions (all statuses): $total\n\n";

// 2. By status
echo "Breakdown by status:\n";
$by_status = $conn->query("SELECT status, COUNT(*) as cnt FROM contributions GROUP BY status");
while ($row = $by_status->fetch_assoc()) {
    echo "  {$row['status']}: {$row['cnt']}\n";
}

echo "\n";

// 3. Show ALL contributions for member 8 (any status)
echo "ALL contributions for member 8 (any status):\n";
$result = $conn->query("SELECT contribution_id, contribution_type, amount, status, reference_no, created_at FROM contributions WHERE member_id = 8 ORDER BY created_at DESC");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo sprintf(
            "ID: %d | Type: %-12s | Amount: %8.2f | Status: %-10s | Ref: %s | Date: %s\n",
            $row['contribution_id'],
            $row['contribution_type'],
            $row['amount'],
            $row['status'],
            $row['reference_no'] ?? 'NULL',
            $row['created_at']
        );
    }
} else {
    echo "NO CONTRIBUTIONS FOUND FOR MEMBER 8!\n";
}

echo "\n";

// 4. Check recent contributions for ANY member
echo "Recent contributions (any member, last 10):\n";
$recent = $conn->query("SELECT member_id, contribution_type, amount, status, created_at FROM contributions ORDER BY created_at DESC LIMIT 10");
if ($recent && $recent->num_rows > 0) {
    while ($row = $recent->fetch_assoc()) {
        echo sprintf(
            "Member: %d | Type: %-12s | Amount: %8.2f | Status: %-10s | Date: %s\n",
            $row['member_id'],
            $row['contribution_type'],
            $row['amount'],
            $row['status'],
            $row['created_at']
        );
    }
} else {
    echo "NO CONTRIBUTIONS IN ENTIRE TABLE!\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "DIAGNOSIS:\n";

if ($total == 0) {
    echo "❌ CRITICAL: Contributions table is COMPLETELY EMPTY!\n";
    echo "   This means no transactions have ever been recorded.\n";
    echo "   Need to create test transactions or check if data was deleted.\n";
} else {
    echo "ℹ️  Contributions exist but may have wrong status.\n";
    echo "   Check if they are 'pending' instead of 'active'.\n";
}
