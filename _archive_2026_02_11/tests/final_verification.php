<?php
// Final comprehensive verification
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/FinancialEngine.php';

$member_id = 8;

echo "═══════════════════════════════════════════════════════════\n";
echo "           FINAL SYSTEM VERIFICATION - Member $member_id\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// 1. Contributions Status
echo "1️⃣  CONTRIBUTIONS STATUS:\n";
$contrib_sql = "SELECT status, COUNT(*) as cnt, SUM(amount) as total
                FROM contributions 
                WHERE member_id = ?
                GROUP BY status";
$stmt = $conn->prepare($contrib_sql);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo sprintf(
        "   %s: %d contributions, KES %s\n",
        ucfirst($row['status']),
        $row['cnt'],
        number_format($row['total'], 2)
    );
}

// 2. Ledger Entries
echo "\n2️⃣  LEDGER ENTRIES:\n";
$entry_sql = "SELECT COUNT(*) as cnt
              FROM ledger_entries le
              JOIN ledger_accounts la ON le.account_id = la.account_id
              WHERE la.member_id = ?";
$stmt2 = $conn->prepare($entry_sql);
$stmt2->bind_param("i", $member_id);
$stmt2->execute();
$entry_count = $stmt2->get_result()->fetch_assoc()['cnt'];
echo "   Total ledger entries: $entry_count\n";

// 3. Balances via FinancialEngine
echo "\n3️⃣  BALANCES (via FinancialEngine::getBalances()):\n";
$engine = new FinancialEngine($conn);
$balances = $engine->getBalances($member_id);

$grand_total = 0;
foreach ($balances as $category => $amount) {
    if ($amount > 0) {
        echo sprintf("   %-10s: KES %s\n", ucfirst($category), number_format($amount, 2));
        $grand_total += $amount;
    }
}

if ($grand_total == 0) {
    echo "   (All balances are 0.00)\n";
}

echo "\n   GRAND TOTAL: KES " . number_format($grand_total, 2) . "\n";

// 4. System Health Check
echo "\n4️⃣  SYSTEM HEALTH:\n";

$all_good = true;

if ($entry_count == 0) {
    echo "   ❌ No ledger entries - transactions not being processed\n";
    $all_good = false;
} else {
    echo "   ✅ Ledger entries exist\n";
}

if ($grand_total == 0) {
    echo "   ⚠️  All balances are 0.00\n";
    $all_good = false;
} else {
    echo "   ✅ Balances are non-zero\n";
}

// Check if active contributions match ledger
$active_total_sql = "SELECT SUM(amount) as total FROM contributions WHERE member_id = ? AND status = 'active'";
$stmt3 = $conn->prepare($active_total_sql);
$stmt3->bind_param("i", $member_id);
$stmt3->execute();
$active_total = $stmt3->get_result()->fetch_assoc()['total'] ?? 0;

if (abs($active_total - $grand_total) < 0.01) {
    echo "   ✅ Ledger balances match contributions\n";
} else {
    echo "   ⚠️  Mismatch: Contributions = KES " . number_format($active_total, 2) . ", Ledger = KES " . number_format($grand_total, 2) . "\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";

if ($all_good && $grand_total > 0) {
    echo "✅ SYSTEM IS HEALTHY!\n";
    echo "🎉 Balances are displaying correctly.\n";
    echo "🔄 Please HARD REFRESH your browser (Ctrl+Shift+R)\n";
} else {
    echo "⚠️  SYSTEM STILL HAS ISSUES\n";
    echo "Further investigation required.\n";
}

echo "═══════════════════════════════════════════════════════════\n";
