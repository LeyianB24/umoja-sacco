<?php
/**
 * Ledger Account Diagnostic Tool
 * Verifies that all members have proper ledger_accounts records
 * and that balances are correctly calculated.
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/FinancialEngine.php';

echo "=== Ledger Account Diagnostic Tool ===\n\n";

// 1. Check for members without ledger accounts
echo "1. Checking for members without ledger accounts...\n";
$sql = "SELECT m.member_id, m.full_name, m.email 
        FROM members m
        LEFT JOIN ledger_accounts la ON m.member_id = la.member_id
        WHERE la.account_id IS NULL
        GROUP BY m.member_id";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "   ⚠️  Found " . $result->num_rows . " members without ledger accounts:\n";
    while ($row = $result->fetch_assoc()) {
        echo "   - ID: {$row['member_id']}, Name: {$row['full_name']}, Email: {$row['email']}\n";
    }
} else {
    echo "   ✅ All members have ledger accounts\n";
}

// 2. Check for missing category accounts
echo "\n2. Checking for missing category accounts...\n";
$categories = ['wallet', 'savings', 'shares', 'welfare', 'loans'];
$sql = "SELECT m.member_id, m.full_name
        FROM members m
        WHERE m.member_id NOT IN (
            SELECT DISTINCT member_id 
            FROM ledger_accounts 
            WHERE category IN ('" . implode("','", $categories) . "')
            GROUP BY member_id
            HAVING COUNT(DISTINCT category) = " . count($categories) . "
        )";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "   ⚠️  Found " . $result->num_rows . " members with incomplete ledger accounts:\n";
    while ($row = $result->fetch_assoc()) {
        echo "   - ID: {$row['member_id']}, Name: {$row['full_name']}\n";
        
        // Show which categories are missing
        $check_sql = "SELECT category FROM ledger_accounts WHERE member_id = {$row['member_id']}";
        $check_result = $conn->query($check_sql);
        $existing = [];
        while ($cat = $check_result->fetch_assoc()) {
            $existing[] = $cat['category'];
        }
        $missing = array_diff($categories, $existing);
        echo "     Missing: " . implode(', ', $missing) . "\n";
    }
} else {
    echo "   ✅ All members have complete ledger accounts\n";
}

// 3. Verify balance calculations for a sample member
echo "\n3. Verifying balance calculations (sample check)...\n";
$sample_sql = "SELECT member_id FROM members WHERE status = 'active' LIMIT 1";
$sample_result = $conn->query($sample_sql);

if ($sample_result && $sample_row = $sample_result->fetch_assoc()) {
    $member_id = $sample_row['member_id'];
    echo "   Testing member ID: $member_id\n";
    
    $engine = new FinancialEngine($conn);
    $balances = $engine->getBalances($member_id);
    
    echo "   Balances from FinancialEngine:\n";
    foreach ($balances as $cat => $amount) {
        echo "   - $cat: KES " . number_format($amount, 2) . "\n";
    }
    
    // Cross-check with direct query
    echo "\n   Cross-checking with direct ledger_accounts query:\n";
    $check_sql = "SELECT category, current_balance FROM ledger_accounts WHERE member_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    while ($row = $check_result->fetch_assoc()) {
        echo "   - {$row['category']}: KES " . number_format($row['current_balance'], 2) . "\n";
    }
    
    echo "   ✅ Balance verification complete\n";
}

// 4. Check for orphaned transactions
echo "\n4. Checking for transactions without ledger entries...\n";
$orphan_sql = "SELECT COUNT(*) as count 
               FROM transactions t
               LEFT JOIN ledger_transactions lt ON t.reference_no = lt.reference_no
               WHERE lt.transaction_id IS NULL 
               AND t.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
$orphan_result = $conn->query($orphan_sql);
$orphan_row = $orphan_result->fetch_assoc();

if ($orphan_row['count'] > 0) {
    echo "   ⚠️  Found {$orphan_row['count']} transactions without ledger entries (last 30 days)\n";
} else {
    echo "   ✅ All recent transactions have ledger entries\n";
}

// 5. Summary
echo "\n=== Diagnostic Summary ===\n";
echo "Diagnostic complete. Review the output above for any issues.\n";
echo "If issues were found, consider:\n";
echo "1. Running ledger account initialization for affected members\n";
echo "2. Checking M-Pesa callback logs for failed transactions\n";
echo "3. Verifying database triggers are active\n\n";
