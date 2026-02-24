 if (!defined('DIAG_MODE')) die('Forbidden'); ?>

require_once __DIR__ . '/../config/app.php';
$res = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE assigned_role_id IS NULL OR assigned_role_id = 0");
$row = $res->fetch_assoc();
echo "Remaining Unassigned: " . $row['count'] . "\n";

