<?php
// Simple raw query to see what's actually in the database
require_once __DIR__ . '/../config/db_connect.php';

echo "RAW DATABASE QUERY - NO PROCESSING\n";
echo str_repeat("=", 60) . "\n\n";

// 1. Count all contributions
$total_contrib = $conn->query("SELECT COUNT(*) as cnt FROM contributions")->fetch_assoc()['cnt'];
echo "Total contributions in database: $total_contrib\n\n";

// 2. Count active contributions
$active_contrib = $conn->query("SELECT COUNT(*) as cnt FROM contributions WHERE status = 'active'")->fetch_assoc()['cnt'];
echo "Active contributions: $active_contrib\n\n";

// 3. Show all contributions for member 8
echo "All contributions for member 8:\n";
$result = $conn->query("SELECT * FROM contributions WHERE member_id = 8");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "NONE FOUND\n";
}

echo "\n";

// 4. Show all members
echo "All members in database:\n";
$members = $conn->query("SELECT member_id, full_name, email, status FROM members LIMIT 10");
while ($m = $members->fetch_assoc()) {
    echo "ID: {$m['member_id']} | Name: {$m['full_name']} | Email: {$m['email']} | Status: {$m['status']}\n";
}

echo "\n";

// 5. Check if FinancialEngine can be loaded
echo "Testing FinancialEngine load:\n";
try {
    require_once __DIR__ . '/../inc/FinancialEngine.php';
    $engine = new FinancialEngine($conn);
    echo "✅ FinancialEngine loaded successfully\n";
    
    // Try to get balances
    $balances = $engine->getBalances(8);
    echo "Balances for member 8: " . json_encode($balances) . "\n";
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
}
