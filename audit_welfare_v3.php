<?php
// audit_welfare_v3.php
$config_path = 'c:\xampp\htdocs\usms\config\db_connect.php';
if (!file_exists($config_path)) {
    die("Error: Config not found at $config_path\n");
}
require $config_path;

if (!isset($conn)) {
    die("Error: Database connection \$conn not set in $config_path\n");
}

function ksh($num) { return "KES " . number_format((float)$num, 2); }

echo "--- Auditing Expenses ---\n";
$res = $conn->query("SELECT * FROM expenses WHERE description LIKE '%welfare%' OR category LIKE '%welfare%'");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo "[Expense #{$row['expense_id']}] {$row['description']} | Amt: " . ksh($row['amount']) . " | Date: {$row['expense_date']}\n";
    }
} else {
    echo "Query failed: " . $conn->error . "\n";
}

echo "\n--- Auditing Transactions (action_type: welfare_payout) ---\n";
$res = $conn->query("SELECT * FROM transactions WHERE action_type = 'welfare_payout' OR notes LIKE '%welfare%'");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo "[Txn #{$row['transaction_id']}] {$row['action_type']} | Amt: " . ksh($row['amount']) . " | Note: {$row['notes']} | Member: {$row['member_id']}\n";
    }
}

echo "\n--- Auditing Current Welfare Support ---\n";
$res = $conn->query("SELECT ws.*, m.full_name FROM welfare_support ws LEFT JOIN members m ON ws.member_id = m.member_id");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo "[Support #{$row['support_id']}] Member: {$row['full_name']} | Amt: " . ksh($row['amount']) . " | Case: " . ($row['case_id'] ?? 'NULL') . " | Status: {$row['status']}\n";
    }
}
