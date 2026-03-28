<?php
require_once __DIR__ . '/config/app.php';
$res = $conn->query("SELECT category, status, COUNT(*) as cnt FROM support_tickets GROUP BY category, status ORDER BY category, status");
while ($r = $res->fetch_assoc()) {
    echo $r['category'] . ' | ' . $r['status'] . ' | count:' . $r['cnt'] . PHP_EOL;
}
echo PHP_EOL;
// Also show distinct categories
$res2 = $conn->query("SELECT DISTINCT category FROM support_tickets ORDER BY category");
echo "--- DISTINCT CATEGORIES ---" . PHP_EOL;
while ($r = $res2->fetch_assoc()) {
    echo $r['category'] . PHP_EOL;
}
