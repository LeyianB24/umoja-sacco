<?php
// welfare_migration_v2.php
require 'c:\xampp\htdocs\usms\config\db_connect.php';

function col_exists($table, $col) {
    global $conn;
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return ($res && $res->num_rows > 0);
}

echo "### Starting Robust Welfare Migration ###\n";

// 1. legacy_expenses_backup
if ($conn->query("SHOW TABLES LIKE 'legacy_expenses_backup'")->num_rows > 0) {
    echo "\n--- Scanning Legacy Expenses ---\n";
    $res = $conn->query("SELECT * FROM legacy_expenses_backup WHERE description LIKE '%welfare%' OR category LIKE '%welfare%'");
    while($row = $res->fetch_assoc()) {
        $desc = $conn->real_escape_string($row['description']);
        $amt = abs((float)$row['amount']);
        $date = $row['expense_date'] ?? $row['created_at'] ?? date('Y-m-d H:i:s');
        
        $check = $conn->query("SELECT case_id FROM welfare_cases WHERE title = 'Legacy Expense: $desc' AND approved_amount = $amt");
        if ($check->num_rows == 0) {
            $sql = "INSERT INTO welfare_cases (related_member_id, title, description, requested_amount, approved_amount, target_amount, total_raised, total_disbursed, status, created_by, created_at) 
                    VALUES (0, 'Legacy Expense: $desc', 'Migrated from legacy_expenses_backup', $amt, $amt, $amt, $amt, $amt, 'closed', 1, '$date')";
            if ($conn->query($sql)) {
                echo "[Migrated] Legacy Expense #{$row['expense_id']} -> Case #{$conn->insert_id}\n";
            }
        }
    }
}

// 2. transactions
echo "\n--- Scanning Transactions ---\n";
$has_action = col_exists('transactions', 'action_type');
$has_notes = col_exists('transactions', 'notes');
$has_cat = col_exists('transactions', 'category');

$where = [];
if ($has_action) $where[] = "action_type = 'welfare_payout'";
if ($has_notes) $where[] = "notes LIKE '%welfare%' AND amount < 0";
if ($has_cat) $where[] = "category = 'welfare' AND amount < 0";

if (!empty($where)) {
    $res = $conn->query("SELECT * FROM transactions WHERE " . implode(" OR ", $where));
    while($row = $res->fetch_assoc()) {
        $txn_id = $row['transaction_id'];
        $mid = $row['member_id'] ?? 0;
        $amt = abs((float)$row['amount']);
        $note = $has_notes ? $conn->real_escape_string($row['notes']) : "Transaction #$txn_id";
        $date = $row['transaction_date'] ?? date('Y-m-d H:i:s');

        // Link check
        $sup_check = $conn->query("SELECT support_id, case_id FROM welfare_support WHERE related_id = $txn_id");
        if ($sup_check && $sup_check->num_rows > 0) {
            $sup = $sup_check->fetch_assoc();
            if (!$sup['case_id']) {
                $sql = "INSERT INTO welfare_cases (related_member_id, title, description, requested_amount, approved_amount, target_amount, total_raised, total_disbursed, status, created_by, created_at) 
                        VALUES ($mid, 'Historical Payout: $note', 'Migrated from transactions', $amt, $amt, $amt, $amt, $amt, 'closed', 1, '$date')";
                $conn->query($sql);
                $case_id = $conn->insert_id;
                $conn->query("UPDATE welfare_support SET case_id = $case_id WHERE support_id = {$sup['support_id']}");
                echo "[Migrated] Txn #$txn_id -> Linked to Support #{$sup['support_id']} -> Case #$case_id\n";
            }
        } else {
            $sql = "INSERT INTO welfare_cases (related_member_id, title, description, requested_amount, approved_amount, target_amount, total_raised, total_disbursed, status, created_by, created_at) 
                    VALUES ($mid, 'Legacy Payout: $note', 'Migrated from direct transaction', $amt, $amt, $amt, $amt, $amt, 'closed', 1, '$date')";
            if ($conn->query($sql)) {
                echo "[Migrated] Direct Txn #$txn_id -> Case #{$conn->insert_id}\n";
            }
        }
    }
}

// 3. welfare_donations
if ($conn->query("SHOW TABLES LIKE 'welfare_donations'")->num_rows > 0) {
    echo "\n--- Scanning Welfare Donations ---\n";
    $res = $conn->query("SELECT * FROM welfare_donations");
    while($row = $res->fetch_assoc()) {
        $amt = (float)$row['amount'];
        if ($amt < 0) {
            $amt = abs($amt);
            $mid = $row['member_id'];
            $note = $conn->real_escape_string($row['notes'] ?? 'Legacy Donation Payout');
            $date = $row['created_at'];

            $sql = "INSERT INTO welfare_cases (related_member_id, title, description, requested_amount, approved_amount, target_amount, total_raised, total_disbursed, status, created_by, created_at) 
                    VALUES ($mid, 'Legacy Donation: $note', 'Migrated from welfare_donations', $amt, $amt, $amt, $amt, $amt, 'closed', 1, '$date')";
            if ($conn->query($sql)) {
                echo "[Migrated] Donation #{$row['donation_id']} -> Case #{$conn->insert_id}\n";
            }
        }
    }
}

// 4. Link orphaned support
echo "\n--- Linking Orphaned Support ---\n";
$res = $conn->query("SELECT * FROM welfare_support WHERE case_id IS NULL OR case_id = 0");
while($row = $res->fetch_assoc()) {
    $mid = $row['member_id'];
    $amt = (float)$row['amount'];
    $reason = $conn->real_escape_string($row['notes'] ?? 'Support Payout');
    $date = $row['created_at'] ?? date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO welfare_cases (related_member_id, title, description, requested_amount, approved_amount, target_amount, total_raised, total_disbursed, status, created_by, created_at) 
            VALUES ($mid, 'Support Payout: $reason', 'Migrated from welfare_support', $amt, $amt, $amt, $amt, $amt, 'closed', 1, '$date')";
    if ($conn->query($sql)) {
        $case_id = $conn->insert_id;
        $conn->query("UPDATE welfare_support SET case_id = $case_id WHERE support_id = {$row['support_id']}");
        echo "[Linked] Support #{$row['support_id']} -> Case #$case_id\n";
    } else {
        echo "[ERROR] SQL: $sql\n";
        echo "[ERROR] Error: " . $conn->error . "\n";
    }
}

echo "\n### Robust Migration Complete ###\n";
?>
