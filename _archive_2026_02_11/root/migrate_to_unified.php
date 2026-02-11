<?php
/**
 * UNIFIED INVESTMENT MIGRATION SCRIPT
 * Consolidates 'vehicles' and 'expenses' into 'investments' and 'transactions'
 */
require_once __DIR__ . '/config/db_connect.php';

echo "--- STARTING UNIFIED MIGRATION ---\n";

$conn->begin_transaction();

try {
    $vehicle_to_investment_map = []; // old_vehicle_id => new_investment_id

    // 1. MIGRATE VEHICLES
    $res = $conn->query("SELECT * FROM vehicles");
    while ($v = $res->fetch_assoc()) {
        $old_id = $v['vehicle_id'];
        $reg_no = $v['reg_no'];
        
        // Check if this reg_no already exists in investments
        $check = $conn->prepare("SELECT investment_id FROM investments WHERE reg_no = ?");
        $check->bind_param("s", $reg_no);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        
        if ($existing) {
            $new_investment_id = $existing['investment_id'];
            echo "Vehicle $reg_no already exists in investments (ID: $new_investment_id). Mapping...\n";
        } else {
            // Create new investment record
            $title = $reg_no . " - " . ($v['model'] ?? 'Vehicle');
            $category = 'vehicle_fleet';
            $target_amount = floatval($v['target_daily_revenue'] ?? 0);
            $target_period = 'daily';
            $cost = floatval($v['purchase_cost'] ?? 0);
            $status = $v['status'] == 'disposed' ? 'disposed' : ($v['status'] == 'maintenance' ? 'maintenance' : 'active');
            
            $stmt = $conn->prepare("INSERT INTO investments (title, category, reg_no, model, capacity, assigned_route, purchase_cost, current_value, target_amount, target_period, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssdddsss", $title, $category, $reg_no, $v['model'], $v['capacity'], $v['assigned_route'], $cost, $cost, $target_amount, $target_period, $status, $v['created_at']);
            $stmt->execute();
            $new_investment_id = $stmt->insert_id;
            echo "Migrated standalone vehicle $reg_no to investments (New ID: $new_investment_id)\n";
        }
        $vehicle_to_investment_map[$old_id] = $new_investment_id;
    }

    // 2. UPDATE TRANSACTIONS (RE-LINKING)
    echo "\nUpdating transactions table...\n";
    $update_count = 0;
    foreach ($vehicle_to_investment_map as $old_veh_id => $new_inv_id) {
        $stmt = $conn->prepare("UPDATE transactions SET related_table = 'investments', related_id = ? WHERE related_table = 'vehicles' AND related_id = ?");
        $stmt->bind_param("ii", $new_inv_id, $old_veh_id);
        $stmt->execute();
        $update_count += $stmt->affected_rows;
    }
    echo "Updated $update_count transactions from 'vehicles' to 'investments'.\n";

    // 3. MIGRATE EXPENSES TABLE
    echo "\nMigrating legacy expenses table...\n";
    $res = $conn->query("SELECT * FROM expenses");
    $exp_count = 0;
    while ($e = $res->fetch_assoc()) {
        $amount = (float)$e['amount'];
        $notes = "[" . $e['category'] . "] " . $e['description'];
        $date = $e['expense_date'];
        $recorded_by = $e['recorded_by'];
        $inv_id = $e['investment_id']; // This is already an investment_id in the expenses table
        
        $stmt = $conn->prepare("INSERT INTO transactions (transaction_type, amount, type, category, related_id, related_table, recorded_by, transaction_date, notes, created_at) VALUES ('expense', ?, 'debit', ?, ?, 'investments', ?, ?, ?, ?)");
        $type = 'expense';
        $subcat = $e['category'];
        $rel_table = 'investments';
        $stmt->bind_param("dsiisss", $amount, $subcat, $inv_id, $recorded_by, $date, $notes, $e['created_at']);
        $stmt->execute();
        $exp_count++;
    }
    echo "Migrated $exp_count records from 'expenses' to 'transactions'.\n";

    // 4. PREVENT DUPLICATES (Constraint Check)
    // We already handled it during migration, but let's confirm no overlaps.

    // 5. DEPRECATE TABLES (Rename)
    echo "\nDeprecating vehicles and expenses tables...\n";
    $conn->query("RENAME TABLE vehicles TO legacy_vehicles_backup, expenses TO legacy_expenses_backup");

    $conn->commit();
    echo "\nMIGRATION COMPLETED SUCCESSFULLY!\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "\nFATAL ERROR DURING MIGRATION: " . $e->getMessage() . "\n";
    exit(1);
}
