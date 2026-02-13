<?php
require 'config/db_connect.php';

echo "--- STARTING DATA MIGRATION: BALANCES TO UNITS ---\n";

$initial_price = 100.00;

// 1. Fetch all members with share balances
$sql = "SELECT member_id, SUM(debit - credit) as bal 
        FROM ledger_entries le 
        JOIN ledger_accounts la ON le.account_id = la.account_id 
        WHERE la.category = 'shares' 
        GROUP BY member_id";

$res = $conn->query($sql);
if (!$res) die("Error fetching balances: " . $conn->error);

$conn->begin_transaction();
try {
    while ($row = $res->fetch_assoc()) {
        $mid = (int)$row['member_id'];
        $amount = abs((float)$row['bal']);
        if ($amount <= 0) continue;

        $units = $amount / $initial_price;
        $ref = "MIGRATION-" . $mid . "-" . time();

        echo "Member $mid: KES " . number_format($amount, 2) . " -> " . number_format($units, 4) . " Units\n";

        // Create transaction record
        $stmt = $conn->prepare("INSERT INTO share_transactions (member_id, units, unit_price, total_value, transaction_type, reference_no) VALUES (?, ?, ?, ?, 'migration', ?)");
        $stmt->bind_param("iddds", $mid, $units, $initial_price, $amount, $ref);
        $stmt->execute();

        // Create/Update shareholdings
        $stmt = $conn->prepare("INSERT INTO member_shareholdings (member_id, units_owned, total_amount_paid, average_purchase_price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iddd", $mid, $units, $amount, $initial_price);
        $stmt->execute();
    }

    $conn->commit();
    echo "Migration Complete.\n";
} catch (Exception $e) {
    $conn->rollback();
    echo "Migration Failed: " . $e->getMessage() . "\n";
}
