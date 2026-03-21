<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT notification_id, title, metadata, created_at FROM notifications ORDER BY notification_id DESC LIMIT 5");
echo "Last 5 Notifications:\n";
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['notification_id'] . " | Title: " . $row['title'] . " | Created: " . $row['created_at'] . "\n";
    echo "Metadata: " . $row['metadata'] . "\n\n";
}
?>
