<?php
require_once __DIR__ . '/config/db_connect.php';

$tables_to_migrate = [
    'vehicle_income' => 'income',
    'vehicle_expenses' => 'expense',
    'investments_income' => 'income',
    'investments_expenses' => 'expense'
];

$conn->begin_transaction();
try {
    foreach ($tables_to_migrate as $table => $type) {
        $res = $conn->query("SHOW TABLES LIKE '$table'");
        if ($res->num_rows > 0) {
            echo "Migrating $table...\n";
            $data = $conn->query("SELECT * FROM `$table` ");
            while ($row = $data->fetch_assoc()) {
                // Determine related ID
                $related_id = $row['vehicle_id'] ?? $row['investment_id'] ?? 0;
                $related_table = (isset($row['vehicle_id']) ? 'vehicles' : 'investments');
                
                // If it's a vehicle, we already have a map from the previous migration?
                // Actually, I'll just look up the investment_id by reg_no if it was a vehicle.
                if ($related_table === 'vehicles') {
                    // Find the reg_no of this old vehicle id
                    $v_res = $conn->query("SELECT reg_no FROM legacy_vehicles_backup WHERE vehicle_id = $related_id");
                    if ($v_res && ($v = $v_res->fetch_assoc())) {
                        $reg = $v['reg_no'];
                        // Find the new investment_id
                        $i_res = $conn->query("SELECT investment_id FROM investments WHERE reg_no = '$reg'");
                        if ($i_res && ($inv = $i_res->fetch_assoc())) {
                            $related_id = $inv['investment_id'];
                            $related_table = 'investments';
                        }
                    }
                }

                $amount = $row['amount'];
                $notes = $row['notes'] ?? $row['description'] ?? 'Migrated';
                $date = $row['transaction_date'] ?? $row['created_at'] ?? date('Y-m-d');
                $recorded_by = $row['recorded_by'] ?? null;

                $stmt = $conn->prepare("INSERT INTO transactions (transaction_type, amount, type, category, related_id, related_table, recorded_by, transaction_date, notes) VALUES (?, ?, ?, 'Migrated', ?, ?, ?, ?, ?)");
                $trans_type = ($type === 'income' ? 'revenue_inflow' : 'expense_outflow');
                $money_type = ($type === 'income' ? 'credit' : 'debit');
                $stmt->bind_param("sdsissss", $trans_type, $amount, $money_type, $related_id, $related_table, $recorded_by, $date, $notes);
                $stmt->execute();
            }
            echo "Finished $table. Renaming to legacy_backup...\n";
            $conn->query("RENAME TABLE `$table` TO `legacy_{$table}_backup` ");
        }
    }
    $conn->commit();
    echo "Migration V2 completed.\n";
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}
