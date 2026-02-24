 if (!defined('DIAG_MODE')) die('Forbidden'); ?>

require_once __DIR__ . '/../config/app.php';

$res = $conn->query("SELECT slug FROM permissions");
while ($row = $res->fetch_assoc()) {
    echo $row['slug'] . "\n";
}

