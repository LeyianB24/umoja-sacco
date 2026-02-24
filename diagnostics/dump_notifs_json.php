 if (!defined('DIAG_MODE')) die('Forbidden'); ?>

require_once __DIR__ . '/../config/app.php';

$res = $conn->query("DESCRIBE notifications");
$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
echo json_encode($rows, JSON_PRETTY_PRINT);

