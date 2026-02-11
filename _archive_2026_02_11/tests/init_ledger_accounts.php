<?php
/**
 * Initialize Ledger Accounts for Member
 * Creates missing ledger_accounts records and syncs existing transaction data
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/FinancialEngine.php';

$member_id = 8; // bezaleltomaka@gmail.com

echo "=== Initializing Ledger Accounts for Member ID: $member_id ===\n\n";

// Start transaction
$conn->begin_transaction();

try {
    // 1. Create ledger accounts for all categories
    $categories = [
        'wallet' => 'liability',
        'savings' => 'liability',
        'shares' => 'equity',
        'welfare' => 'liability',
        'loans' => 'asset'
    ];
    
    echo "1. Creating ledger accounts...\n";
    foreach ($categories as $category => $account_type) {
        // Check if account exists
        $check = $conn->prepare("SELECT account_id FROM ledger_accounts WHERE member_id = ? AND category = ?");
        $check->bind_param("is", $member_id, $category);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows == 0) {
            // Create account
            $account_name = "Member $member_id - " . ucfirst($category);
            $insert = $conn->prepare("INSERT INTO ledger_accounts (member_id, account_name, category, account_type, current_balance) VALUES (?, ?, ?, ?, 0.00)");
            $insert->bind_param("isss", $member_id, $account_name, $category, $account_type);
            $insert->execute();
            echo "   ✅ Created $category account (ID: {$conn->insert_id})\n";
        } else {
            echo "   ℹ️  $category account already exists\n";
        }
    }
    
    // 2. Sync existing contributions to ledger
    echo "\n2. Syncing existing contributions to ledger...\n";
    $contrib_sql = "SELECT contribution_id, contribution_type, amount, created_at, reference_no
                    FROM contributions 
                    WHERE member_id = ? AND status = 'active'
                    ORDER BY created_at ASC";
    $stmt = $conn->prepare($contrib_sql);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $contribs = $stmt->get_result();
    
    $engine = new FinancialEngine($conn);
    
    while ($contrib = $contribs->fetch_assoc()) {
        $type = $contrib['contribution_type'];
        $amount = (float)$contrib['amount'];
        $ref = $contrib['reference_no'];
        
        // Map contribution type to action
        $action = match($type) {
            'savings' => 'savings_deposit',
            'shares' => 'share_purchase',
            'welfare' => 'welfare_contribution',
            default => 'savings_deposit'
        };
        
        try {
            // Use opening_balance action to avoid double-entry issues
            $target_ledger = match($type) {
                'shares' => FinancialEngine::CAT_SHARES,
                'welfare' => FinancialEngine::CAT_WELFARE,
                default => FinancialEngine::CAT_SAVINGS
            };
            
            $engine->transact([
                'member_id' => $member_id,
                'amount' => $amount,
                'action_type' => 'opening_balance',
                'target_ledger' => $target_ledger,
                'reference' => $ref ?? "SYNC-{$contrib['contribution_id']}",
                'notes' => "Synced from existing contribution (ID: {$contrib['contribution_id']})",
                'method' => 'mpesa'
            ]);
            
            echo "   ✅ Synced $type: KES " . number_format($amount, 2) . " (Ref: $ref)\n";
        } catch (Exception $e) {
            echo "   ⚠️  Error syncing contribution {$contrib['contribution_id']}: {$e->getMessage()}\n";
        }
    }
    
    // 3. Display final balances
    echo "\n3. Final Balances:\n";
    $balances = $engine->getBalances($member_id);
    foreach ($balances as $cat => $bal) {
        echo "   - " . ucfirst($cat) . ": KES " . number_format($bal, 2) . "\n";
    }
    
    $conn->commit();
    echo "\n✅ Ledger initialization complete!\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
