<?php
define('DIAG_MODE', true);
require_once __DIR__ . '/../config/app.php';

echo "=== Migration Status ===\n";
$res = $conn->query("SHOW TABLES LIKE '_migrations'");
if ($res->num_rows > 0) {
    $migs = $conn->query("SELECT * FROM _migrations ORDER BY id DESC");
    while($row = $migs->fetch_assoc()) {
        echo "[{$row['batch']}] {$row['filename']} - {$row['applied_at']}\n";
    }
} else {
    echo "Tracking table _migrations does not exist.\n";
}

echo "\n=== Custom Tables Check ===\n";
$tables = ['system_modules', 'admin_module_permissions', 'system_module_pages', 'module_audit_trail'];
foreach ($tables as $t) {
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    echo "$t: " . ($res->num_rows > 0 ? "EXISTS" : "MISSING") . "\n";
}
?>
