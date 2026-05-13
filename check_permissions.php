<?php
require_once 'config/app.php';
require_once 'inc/auth.php';
require_once 'core/Database/Database.php';

$db = \USMS\Database\Database::getInstance()->getPdo();

// Check current permissions
$stmt = $db->query('SELECT slug, name FROM permissions WHERE slug IN ("revenue.php", "record_revenue", "support_view.php", "tech_support")');
echo "Current permissions:\n";
while ($row = $stmt->fetch()) {
    echo $row['slug'] . ' - ' . $row['name'] . "\n";
}

// Check if revenue.php permission exists
$stmt = $db->prepare('SELECT id FROM permissions WHERE slug = ?');
$stmt->execute(['revenue.php']);
if ($stmt->fetch()) {
    echo "\nrevenue.php permission exists\n";
} else {
    echo "\nrevenue.php permission missing - need to add\n";
}

// Check support_view.php
$stmt->execute(['support_view.php']);
if ($stmt->fetch()) {
    echo "support_view.php permission exists\n";
} else {
    echo "support_view.php permission missing - need to add\n";
}
?>