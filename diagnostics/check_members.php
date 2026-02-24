 if (!defined('DIAG_MODE')) die('Forbidden'); ?>

include 'c:/xampp/htdocs/usms/config/app.php';
$q = $conn->query('SELECT member_id, full_name, email, phone FROM members LIMIT 5');
if (!$q) {
    echo "Query failed: " . $conn->error . "\n";
    exit;
}
while($r = $q->fetch_assoc()) {
    print_r($r);
}
?>

