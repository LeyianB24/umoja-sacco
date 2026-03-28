<?php
require_once __DIR__ . '/config/app.php';

echo "=== Support Tickets Categories in Database ===\n";
$res = $conn->query("SELECT category, COUNT(*) as count FROM support_tickets GROUP BY category");
$db_categories = [];
while ($row = $res->fetch_assoc()) {
    echo "- " . $row['category'] . " (" . $row['count'] . " tickets)\n";
    $db_categories[] = $row['category'];
}

echo "\n=== Support Page Map in config/app.php ===\n";
foreach (SUPPORT_PAGE_MAP as $cat => $page) {
    echo "- $cat => $page\n";
}

echo "\n=== Orphaned Categories (In DB but not in Map) ===\n";
foreach ($db_categories as $db_cat) {
    if (!isset(SUPPORT_PAGE_MAP[$db_cat])) {
        echo "- $db_cat\n";
    }
}
