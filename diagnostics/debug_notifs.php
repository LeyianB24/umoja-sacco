 if (!defined('DIAG_MODE')) die('Forbidden'); ?>

require_once __DIR__ . '/../config/app.php';

$res = $conn->query("DESCRIBE notifications");
while ($row = $res->fetch_assoc()) {
    echo str_pad($row['Field'], 20) . " | " . $row['Type'] . "\n";
}

