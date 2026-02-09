<?php
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/inc/InvestmentViabilityEngine.php';

$engine = new InvestmentViabilityEngine($conn);

// 1. Create a dummy investment if doesn't exist
$title = "Test Asset #".time();
$sql = "INSERT INTO investments (title, category, purchase_cost, current_value, target_amount, target_period, target_start_date, status, viability_status) 
        VALUES ('$title', 'other', 100000, 100000, 50000, 'monthly', '".date('Y-m-01')."', 'active', 'pending')";
$conn->query($sql);
$inv_id = $conn->insert_id;

echo "Created test investment #$inv_id\n";

// 2. Add some revenue
$conn->query("INSERT INTO transactions (transaction_type, amount, related_table, related_id, transaction_date) 
              VALUES ('income', 60000, 'investments', $inv_id, '".date('Y-m-d')."')");
echo "Added KES 60,000 revenue\n";

// 3. Add some expenses
$conn->query("INSERT INTO transactions (transaction_type, amount, related_table, related_id, transaction_date) 
              VALUES ('expense', 10000, 'investments', $inv_id, '".date('Y-m-d')."')");
echo "Added KES 10,000 expense\n";

// 4. Calculate performance
$perf = $engine->calculatePerformance($inv_id);
echo "Performance Results:\n";
print_r($perf);

// 5. Update status
$engine->updateViabilityStatus($inv_id);
$res = $conn->query("SELECT viability_status FROM investments WHERE investment_id = $inv_id");
$row = $res->fetch_assoc();
echo "Updated Viability Status: " . $row['viability_status'] . "\n";

// Cleanup
$conn->query("DELETE FROM transactions WHERE related_table = 'investments' AND related_id = $inv_id");
$conn->query("DELETE FROM investments WHERE investment_id = $inv_id");
echo "Cleaned up test data.\n";
?>
