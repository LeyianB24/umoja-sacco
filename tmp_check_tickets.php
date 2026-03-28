<?php
require_once __DIR__ . '/config/app.php';

echo "=== ALL TICKETS BY CATEGORY + STATUS ===\n";
$res = $conn->query("
    SELECT category, status, COUNT(*) as cnt, GROUP_CONCAT(support_id ORDER BY support_id) as ids
    FROM support_tickets
    GROUP BY category, status
    ORDER BY category, status
");
while ($r = $res->fetch_assoc()) {
    printf("  %-22s | %-10s | %d ticket(s) [IDs: %s]\n",
        $r['category'], $r['status'], $r['cnt'], $r['ids']);
}

echo "\n=== MEMBER SUPPORT FORM — available categories (from member/pages/support.php) ===\n";
echo "  (pulling from SUPPORT_ROUTING_MAP in config/app.php)\n";
if (defined('SUPPORT_ROUTING_MAP')) {
    foreach (SUPPORT_ROUTING_MAP as $cat => $role) {
        echo "  category key: '$cat'  =>  role: '$role'\n";
    }
}

echo "\n=== DISTINCT RAW CATEGORIES IN DB ===\n";
$res2 = $conn->query("SELECT DISTINCT category, COUNT(*) as c FROM support_tickets GROUP BY category ORDER BY category");
while ($r = $res2->fetch_assoc()) {
    echo "  '$r[category]'  ({$r['c']} ticket(s))\n";
}
