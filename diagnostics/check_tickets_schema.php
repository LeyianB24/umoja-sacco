 if (!defined('DIAG_MODE')) die('Forbidden'); ?>

require_once __DIR__ . '/../config/app.php';
$res = $conn->query("DESCRIBE support_tickets");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}

