 if (!defined('DIAG_MODE')) die('Forbidden'); ?>

require_once __DIR__ . '/../config/app.php';
$table = 'support_tickets';
$res = $conn->query("DESCRIBE $table");
while ($row = $res->fetch_assoc()) {
    if ($row['Field'] === 'admin_id') {
        print_r($row);
    }
}

