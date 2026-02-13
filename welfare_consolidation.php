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

echo "\n### Starting Welfare Fund Consolidation ###\n";

$res = $conn->query("SELECT m.member_id, m.full_name, la.current_balance, la.account_id 
                   FROM ledger_accounts la 
                   JOIN members m ON la.member_id = m.member_id 
                   WHERE la.category = 'welfare' AND la.current_balance > 0");

if (!$res) die("Error fetching balances: " . $conn->error . "\n");

$count = 0;
while($row = $res->fetch_assoc()) {
    $mid = (int)$row['member_id'];
    $amt = (float)$row['current_balance'];
    $acc_id = (int)$row['account_id'];
    
    echo "Processing: {$row['full_name']} | Amount: $amt\n";
    
    try {
        $ref = "CONS-" . strtoupper(uniqid());
        $engine->transact([
            'member_id'     => $mid,
            'amount'        => $amt,
            'action_type'   => 'welfare_pool_consolidation',
            'reference'     => $ref,
            'notes'         => "Welfare Consolidation: Mapping personal balance to social pool.",
            'method'        => 'internal'
        ]);
        echo "   [SUCCESS] Consolidated KES " . number_format($amt, 2) . " to Pool.\n";
        $count++;
    } catch (Exception $e) {
        echo "   [ERROR] " . $e->getMessage() . "\n";
    }
}

echo "\nTotal Processed: $count records.\n";
echo "### Consolidation Complete ###\n";
?>
