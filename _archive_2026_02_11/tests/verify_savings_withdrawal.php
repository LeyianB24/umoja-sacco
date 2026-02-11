<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/FinancialEngine.php';
require_once __DIR__ . '/../inc/TransactionHelper.php';

// Fetch a valid member
$q = $conn->query("SELECT member_id FROM members LIMIT 1");
if (!$q || $q->num_rows === 0) die("No member");
$member_id = $q->fetch_assoc()['member_id'];

echo "<h3>Testing Withdrawal for Member ID: $member_id</h3>";

$engine = new FinancialEngine($conn);
$initial = $engine->getCategoryWithdrawals($member_id, 'savings');
echo "<p>Initial Savings Withdrawals: " . number_format($initial, 2) . "</p>";

// Simulate Withdrawal
$amount = 150.00;
echo "<p>Simulating Withdrawal of KES $amount from Savings...</p>";

TransactionHelper::record([
    'member_id' => $member_id,
    'amount' => $amount,
    'type' => 'withdrawal',
    'category' => 'savings', // This triggers source_cat = savings
    'ref_no' => 'TEST-W-' . time(),
    'notes' => 'Test Savings Withdrawal'
]);

// Check New Total
$final = $engine->getCategoryWithdrawals($member_id, 'savings');
echo "<p>Final Savings Withdrawals: " . number_format($final, 2) . "</p>";

if (round($final - $initial, 2) == $amount) {
    echo "<h2 style='color:green'>SUCCESS</h2>";
} else {
    echo "<h2 style='color:red'>FAILURE</h2>";
}
?>
