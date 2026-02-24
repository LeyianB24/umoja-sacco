 if (!defined('DIAG_MODE')) die('Forbidden'); ?>

include 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query('DESCRIBE loans');
while($row = $res->fetch_assoc()) {
    echo "Field: " . $row['Field'] . "\n";
}
?>

