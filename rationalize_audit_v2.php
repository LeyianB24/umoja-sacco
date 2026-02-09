<?php
require_once __DIR__ . '/config/db_connect.php';

$audit = [
    'legacy_tables' => [],
    'structures' => [],
    'counts' => []
];

// 1. Check for legacy tables
$tables_to_check = ['investments_expenses', 'investments_income', 'vehicles_income', 'vehicles_expenses', 'expenses'];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_row()) {
    if (in_array($row[0], $tables_to_check)) {
        $audit['legacy_tables'][] = $row[0];
    }
}

// 2. Audit regular and legacy tables
$tables_to_audit = array_unique(array_merge(['investments', 'vehicles', 'transactions'], $audit['legacy_tables']));
foreach ($tables_to_audit as $t) {
    $res = $conn->query("DESCRIBE $t");
    if ($res) {
        $audit['structures'][$t] = [];
        while ($row = $res->fetch_assoc()) {
            $audit['structures'][$t][] = $row;
        }
        
        $count_res = $conn->query("SELECT COUNT(*) FROM $t");
        $audit['counts'][$t] = $count_res->fetch_row()[0];
    }
}

file_put_contents('audit_data.json', json_encode($audit, JSON_PRETTY_PRINT));
echo "Audit complete. Data saved to audit_data.json\n";
