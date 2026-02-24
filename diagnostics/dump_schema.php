 if (!defined('DIAG_MODE')) die('Forbidden'); ?>

require_once __DIR__ . '/../config/app.php';

$out = "";
$res = $conn->query("DESCRIBE support_tickets");
while ($row = $res->fetch_assoc()) {
    $out .= implode(" | ", $row) . "\n";
}
file_put_contents(__DIR__ . '/support_tickets_schema.txt', $out);
echo "Dumped to support_tickets_schema.txt\n";

