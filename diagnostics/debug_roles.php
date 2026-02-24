 if (!defined('DIAG_MODE')) die('Forbidden'); ?>

require_once __DIR__ . '/../config/app.php';

$res = $conn->query("DESCRIBE roles");
while ($row = $res->fetch_assoc()) {
    echo str_pad($row['Field'], 20) . " | " . $row['Type'] . "\n";
}
echo "\n";

$res = $conn->query("SELECT * FROM roles");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

