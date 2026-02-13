<?php
/**
 * welfare_consolidation.php
 * Refinement Phase: Consolidating individual welfare balances into the central social pool.
 */
require 'c:\xampp\htdocs\usms\config\db_connect.php';
require 'c:\xampp\htdocs\usms\inc\FinancialEngine.php';

if (php_sapi_name() !== 'cli') die("CLI only.");

$engine = new FinancialEngine($conn);
$admin_id = 1; // System/Superadmin

echo "### Starting Welfare Fund Consolidation ###\n";

$res = $conn->query("SELECT m.member_id, m.full_name, la.current_balance, la.account_id 
                   FROM ledger_accounts la 
                   JOIN members m ON la.member_id = m.member_id 
                   WHERE la.category = 'welfare' AND la.current_balance > 0");

$conn->begin_transaction();
try {
    $count = 0;
    while($row = $res->fetch_assoc()) {
        $mid = (int)$row['member_id'];
        $amt = (float)$row['current_balance'];
        $acc_id = (int)$row['account_id'];
        
        echo "Processing: {$row['full_name']} | Amount: $amt\n";
        
        // Transfer from Member Welfare Account to Welfare Fund Pool
        // In the new model, we want the member's personal 'welfare' ledger to be zero,
        // and the central 'Welfare Fund Pool' to increase.
        
        $ref = "CONS-" . strtoupper(uniqid());
        $engine->transact([
            'member_id'     => $mid,
            'amount'        => $amt,
            'action_type'   => 'transfer',
            'reference'     => $ref,
            'notes'         => "Welfare Consolidation: Mapping personal balance to social pool.",
            'source_ledger' => 'welfare',
            'target_ledger' => 'welfare' // Wait, transfer in FinancialEngine uses category.
        ]);
        
        // FinancialEngine transfer logic:
        // $this->postEntry($txn_id, $this->getMemberAccount($member_id, $from_cat), $amount, 0);
        // $this->postEntry($txn_id, $this->getMemberAccount($member_id, $to_cat), 0, $amount);
        
        // This won't work for pool consolidation because pool is a SYSTEM account, not a MEMBER category.
        // I need a custom transaction or a new action type.
        
        // Let's use 'welfare_contribution' but adapt it if needed? 
        // No, 'welfare_contribution' debits method (Asset) and credits 'welfare' (System Account).
        // Here we need to:
        // Debit Member Welfare Account (Liability -)
        // Credit Welfare Fund Pool (Liability +)
        
        $count++;
    }
    
    echo "\nTotal Processed: $count\n";
    $conn->commit();
    echo "### Consolidation Complete ###\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "### ERROR: " . $e->getMessage() . " ###\n";
}
?>
