<?php
require_once __DIR__ . '/config/app.php';

echo "=== TICKET ALLOCATION AUDIT ===\n";
$res = $conn->query("SELECT support_id, category, subject, status FROM support_tickets WHERE status != 'Closed'");
$orphans = [];

while ($row = $res->fetch_assoc()) {
    $cat = $row['category'];
    $page = SUPPORT_PAGE_MAP[$cat] ?? 'MISSING_MAPPING';
    
    if ($page === 'MISSING_MAPPING') {
        $orphans[] = $row;
    }
}

if (empty($orphans)) {
    echo "SUCCESS: All active tickets are correctly mapped to admin pages via SUPPORT_PAGE_MAP.\n";
} else {
    echo "WARNING: There are " . count($orphans) . " tickets with unmapped categories:\n";
    foreach ($orphans as $o) {
        echo "- ID: {$o['support_id']} | Category: '{$o['category']}' | Sub: '{$o['subject']}'\n";
    }
}

echo "\nGlobal Mapping Review:\n";
foreach (SUPPORT_PAGE_MAP as $cat => $page) {
    echo "- '$cat' -> admin/pages/$page\n";
}
