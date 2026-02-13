<?php
// welfare_migration.php
$config = 'c:\xampp\htdocs\usms\config\db_connect.php';
if (!file_exists($config)) die("Config not found\n");
require $config;

if (!isset($conn)) die("Conn not set\n");

echo "### Starting Welfare Migration ###\n";

// 1. Migrate from 'legacy_expenses_backup' table
echo "\n--- Scanning Legacy Expenses ---\n";
// We check if legacy_expenses_backup exists first
$table_check = $conn->query("SHOW TABLES LIKE 'legacy_expenses_backup'");
if ($table_check->num_rows > 0) {
    $res = $conn->query("SELECT * FROM legacy_expenses_backup WHERE description LIKE '%welfare%' OR category LIKE '%welfare%'");
    while($row = $res->fetch_assoc()) {
        $desc = $conn->real_escape_string($row['description']);
        $amt = (float)$row['amount'];
        $date = $row['expense_date'] ?? $row['created_at'] ?? date('Y-m-d H:i:s');
        
        $check = $conn->query("SELECT case_id FROM welfare_cases WHERE title = 'Legacy Expense: $desc' AND approved_amount = $amt");
        if ($check->num_rows == 0) {
            $sql = "INSERT INTO welfare_cases (member_id, title, description, requested_amount, approved_amount, total_disbursed, status, created_at) 
                    VALUES (0, 'Legacy Expense: $desc', 'Migrated from legacy_expenses_backup', $amt, $amt, $amt, 'closed', '$date')";
            if ($conn->query($sql)) {
                echo "[Migrated] Legacy Expense #{$row['expense_id']} -> Case #{$conn->insert_id}\n";
            }
        } else {
            echo "[Skip] Legacy Expense #{$row['expense_id']} already migrated.\n";
        }
    }
} else {
    echo "[Skip] legacy_expenses_backup table not found.\n";
}

// 2. Migrate from transactions (contributions vs payouts)
echo "\n--- Scanning Transactions (Welfare Payouts) ---\n";
// Transactions might have different ways to identify welfare. We check action_type, notes, or category if exists.
$cat_check = $conn->query("SHOW COLUMNS FROM transactions LIKE 'category'");
$query = "SELECT * FROM transactions WHERE action_type = 'welfare_payout'";
if ($cat_check->num_rows > 0) {
    $query .= " OR (category = 'welfare' AND amount < 0)";
}
$res = $conn->query($query);
while($row = $res->fetch_assoc()) {
    $txn_id = $row['transaction_id'];
    $mid = $row['member_id'];
    $amt = abs((float)$row['amount']);
    $note = $conn->real_escape_string($row['notes']);
    $date = $row['transaction_date'];
    
    // Check if linked to support
    $sup_check = $conn->query("SELECT support_id, case_id FROM welfare_support WHERE related_id = $txn_id");
    if ($sup_check->num_rows > 0) {
        $sup = $sup_check->fetch_assoc();
        if (!$sup['case_id']) {
            $sql = "INSERT INTO welfare_cases (member_id, title, description, requested_amount, approved_amount, total_disbursed, status, created_at) 
                    VALUES ($mid, 'Historical Payout: $note', 'Migrated from transactions', $amt, $amt, $amt, 'closed', '$date')";
            $conn->query($sql);
            $case_id = $conn->insert_id;
            $conn->query("UPDATE welfare_support SET case_id = $case_id WHERE support_id = {$sup['support_id']}");
            echo "[Migrated] Txn #$txn_id -> Linked to Support #{$sup['support_id']} -> Case #$case_id\n";
        }
    } else {
        $sql = "INSERT INTO welfare_cases (member_id, title, description, requested_amount, approved_amount, total_disbursed, status, created_at) 
                VALUES ($mid, 'Legacy Payout: $note', 'Migrated from direct transaction', $amt, $amt, $amt, 'closed', '$date')";
        if ($conn->query($sql)) {
            echo "[Migrated] Direct Txn #$txn_id -> Case #{$conn->insert_id}\n";
        }
    }
}

// 3. Welfare Donations (Legacy field check)
echo "\n--- Scanning Welfare Donations ---\n";
$table_check = $conn->query("SHOW TABLES LIKE 'welfare_donations'");
if ($table_check->num_rows > 0) {
    $res = $conn->query("SELECT * FROM welfare_donations");
    while($row = $res->fetch_assoc()) {
        // These might be contributions, but if they are payouts (negative or marked as payout)
        $amt = (float)$row['amount'];
        if ($amt < 0) {
            $amt = abs($amt);
            $mid = $row['member_id'];
            $note = $conn->real_escape_string($row['notes'] ?? 'Legacy Donation Payout');
            $date = $row['created_at'];

            $sql = "INSERT INTO welfare_cases (member_id, title, description, requested_amount, approved_amount, total_disbursed, status, created_at) 
                    VALUES ($mid, 'Legacy Donation: $note', 'Migrated from welfare_donations', $amt, $amt, $amt, 'closed', '$date')";
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
    $date = $row['created_at'];
    
    $sql = "INSERT INTO welfare_cases (member_id, title, description, requested_amount, approved_amount, total_disbursed, status, created_at) 
            VALUES ($mid, 'Support Payout: $reason', 'Migrated from welfare_support', $amt, $amt, $amt, 'closed', '$date')";
    if ($conn->query($sql)) {
        $case_id = $conn->insert_id;
        $conn->query("UPDATE welfare_support SET case_id = $case_id WHERE support_id = {$row['support_id']}");
        echo "[Linked] Support #{$row['support_id']} -> Case #$case_id\n";
    }
}

echo "\n### Migration Complete ###\n";
?>
