<?php
require_once __DIR__ . '/config/app.php';
$res = $conn->query("SELECT id, slug, name, category FROM permissions WHERE slug LIKE '%monitor%' OR slug LIKE '%audit%' OR slug LIKE '%health%'");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row) . PHP_EOL;
}
