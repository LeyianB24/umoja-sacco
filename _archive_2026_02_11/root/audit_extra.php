<?php
require_once __DIR__ . '/config/db_connect.php';
$tables = ['vehicle_income', 'vehicle_expenses'];
$audit = [];
foreach ($tables as $t) {
    $res = $conn->query("DESCRIBE $t");
    if ($res) {
        $audit[$t] = ['structure' => [], 'count' => 0];
        while ($row = $res->fetch_assoc()) {
            $audit[$t]['structure'][] = $row;
        }
        $count_res = $conn->query("SELECT COUNT(*) FROM $t");
        $audit[$t]['count'] = $count_res->fetch_row()[0];
    }
}
echo json_encode($audit, JSON_PRETTY_PRINT);
