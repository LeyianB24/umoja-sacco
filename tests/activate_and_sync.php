<?php
/**
 * CRITICAL FIX: Activate Pending Contributions and Sync to Ledger
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/FinancialEngine.php';

echo "══════════════════════════════════════════════════════════\n";
echo "  ACTIVATE PENDING CONTRIBUTIONS & SYNC TO LEDGER\n";
echo "══════════════════════════════════════════════════════════\n\n";

// Step 1: Find all pending contributions
$pending_sql = "SELECT c.*, m.full_name 
                FROM contributions c
                JOIN members m ON c.member_id = m.member_id
                WHERE c.status = 'pending'
                ORDER BY c.created_at ASC";

$result = $conn->query($pending_sql);

if (!$result || $result->num_rows == 0) {
    echo "No pending contributions found.\n";
    exit;
}

echo "Found {$result->num_rows} pending contributions.\n\n";

$activated = 0;
$processed = 0;
$errors = 0;

$conn->begin_transaction();

try {
    while ($contrib = $result->fetch_assoc()) {
        $contrib_id = $contrib['contribution_id'];
        $member_id = $contrib['member_id'];
        $type = $contrib['contribution_type'];
        $amount = (float)$contrib['amount'];
        $ref = $contrib['reference_no'] ?? "ACTIVATE-{$contrib_id}";
        
        echo sprintf(
            "Processing: ID %d | Member %d (%s) | %s | KES %s\n",
            $contrib_id,
            $member_id,
            $contrib['full_name'],
            $type,
            number_format($amount, 2)
        );
        
        // Step 1: Activate the contribution
        $update_sql = "UPDATE contributions SET status = 'active' WHERE contribution_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("i", $contrib_id);
        $stmt->execute();
        $activated++;
        
        echo "   ✅ Activated contribution\n";
        
        // Step 2: Process through FinancialEngine
        try {
            $engine = new FinancialEngine($conn);
            
            // Map contribution type to action
            $action_map = [
                'savings' => 'savings_deposit',
                'shares' => 'share_purchase',
                'welfare' => 'welfare_contribution',
                'registration' => 'revenue_inflow'
            ];
            
            $action = $action_map[$type] ?? 'savings_deposit';
            
            $txn_id = $engine->transact([
                'member_id' => $member_id,
                'amount' => $amount,
                'action_type' => $action,
                'reference' => $ref,
                'notes' => "Activated and synced contribution ID {$contrib_id}",
                'method' => 'mpesa'
            ]);
            
            echo "   ✅ Synced to ledger (Txn ID: $txn_id)\n\n";
            $processed++;
            
        } catch (Exception $e) {
            echo "   ⚠️  Ledger sync failed: {$e->getMessage()}\n\n";
            $errors++;
        }
    }
    
    $conn->commit();
    
    echo "══════════════════════════════════════════════════════════\n";
    echo "COMPLETE!\n";
    echo "Activated: $activated\n";
    echo "Synced to Ledger: $processed\n";
    echo "Errors: $errors\n";
    echo "══════════════════════════════════════════════════════════\n\n";
    
    // Show final balances
    echo "FINAL BALANCES:\n";
    $members_sql = "SELECT DISTINCT member_id FROM contributions WHERE status = 'active'";
    $members_result = $conn->query($members_sql);
    
    while ($member_row = $members_result->fetch_assoc()) {
        $mid = $member_row['member_id'];
        $engine = new FinancialEngine($conn);
        $balances = $engine->getBalances($mid);
        
        $total = array_sum($balances);
        if ($total > 0) {
            echo "\nMember ID $mid:\n";
            foreach ($balances as $cat => $bal) {
                if ($bal > 0) {
                    echo "  - " . ucfirst($cat) . ": KES " . number_format($bal, 2) . "\n";
                }
            }
        }
    }
    
    echo "\n✅ ALL DONE! Please refresh your dashboard (Ctrl+Shift+R)\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
}
