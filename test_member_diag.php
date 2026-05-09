<?php
require "config/app.php";
// Get first active member
$r = $conn->query("SELECT member_id, full_name, email, member_reg_no, kyc_status FROM members WHERE status='active' LIMIT 5");
echo "Members:\n";
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['member_id']} name={$row['full_name']} email={$row['email']} reg={$row['member_reg_no']} kyc={$row['kyc_status']}\n";
}

// Check php.ini upload settings
echo "\nPHP Upload Config:\n";
echo "  upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "  post_max_size: " . ini_get('post_max_size') . "\n";
echo "  max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "  memory_limit: " . ini_get('memory_limit') . "\n";

// Check if exports work
$r2 = $conn->query("SHOW TABLES LIKE 'transactions'");
echo "\ntransactions table: " . ($r2->num_rows > 0 ? 'exists' : 'NOT FOUND') . "\n";

// Test FinancialEngine
require_once "inc/FinancialEngine.php";
$engine = new FinancialEngine($conn);
$bal = $engine->getBalances(1);
echo "\nBalances for member_id=1:\n";
print_r($bal);
