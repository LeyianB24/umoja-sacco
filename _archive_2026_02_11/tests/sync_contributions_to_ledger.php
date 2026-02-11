<?php
/**
 * CRITICAL FIX: Sync All Contributions to Ledger
 * This processes existing contributions through FinancialEngine
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/FinancialEngine.php';

echo "═══════════════════════════════════════════════════════════\n";
echo "  LEDGER SYNC - Processing Contributions Through Engine\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Get all active contributions that haven't been processed
$sql = "SELECT c.*, m.full_name 
        FROM contributions c
        JOIN members m ON c.member_id = m.member_id
        WHERE c.status = 'active'
        ORDER BY c.created_at ASC";

$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    echo "❌ No active contributions found to sync.\n";
    exit;
}

echo "Found {$result->num_rows} active contributions to process.\n\n";

$processed = 0;
$errors = 0;

while ($contrib = $result->fetch_assoc()) {
    $member_id = $contrib['member_id'];
    $type = $contrib['contribution_type'];
    $amount = (float)$contrib['amount'];
    $ref = $contrib['reference_no'] ?? "SYNC-{$contrib['contribution_id']}";
    
    echo sprintf(
        "Processing: Member %d (%s) | %s | KES %s | Ref: %s\n",
        $member_id,
        $contrib['full_name'],
        $type,
        number_format($amount, 2),
        $ref
    );
    
    try {
        $engine = new FinancialEngine($conn);
        
        // Map contribution type to FinancialEngine action
        $action_map = [
            'savings' => 'savings_deposit',
            'shares' => 'share_purchase',
            'welfare' => 'welfare_contribution',
            'registration' => 'revenue_inflow'
        ];
        
        $action = $action_map[$type] ?? 'savings_deposit';
        
        // Process through FinancialEngine
        $txn_id = $engine->transact([
            'member_id' => $member_id,
            'amount' => $amount,
            'action_type' => $action,
            'reference' => $ref,
            'notes' => "Synced from contribution ID {$contrib['contribution_id']} - {$type}",
            'method' => 'mpesa'
        ]);
        
        echo "   ✅ SUCCESS - Transaction ID: $txn_id\n\n";
        $processed++;
        
    } catch (Exception $e) {
        echo "   ❌ ERROR: {$e->getMessage()}\n\n";
        $errors++;
    }
}

echo "═══════════════════════════════════════════════════════════\n";
echo "SYNC COMPLETE\n";
echo "Processed: $processed\n";
echo "Errors: $errors\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Show final balances for all members
echo "FINAL BALANCES BY MEMBER:\n";
echo str_repeat("─", 60) . "\n";

$members_sql = "SELECT DISTINCT member_id FROM contributions WHERE status = 'active'";
$members_result = $conn->query($members_sql);

while ($member_row = $members_result->fetch_assoc()) {
    $mid = $member_row['member_id'];
    $engine = new FinancialEngine($conn);
    $balances = $engine->getBalances($mid);
    
    echo "Member ID $mid:\n";
    foreach ($balances as $cat => $bal) {
        if ($bal > 0) {
            echo "  - " . ucfirst($cat) . ": KES " . number_format($bal, 2) . "\n";
        }
    }
    echo "\n";
}

echo "✅ All contributions have been synced to the ledger!\n";
echo "🔄 Please refresh your dashboard to see updated balances.\n";
