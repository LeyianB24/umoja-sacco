<?php
/**
 * api/v1/search_members.php
 * Secure AJAX member lookup
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/auth.php';

// 1. Auth Guard
try {
    require_admin();
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Admin access required']);
    exit;
}

// 2. Fetch Query
$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$search = "%$q%";
$stmt = $conn->prepare("
    SELECT member_id, full_name, national_id, phone 
    FROM members 
    WHERE (full_name LIKE ? OR national_id LIKE ? OR phone LIKE ?) 
    AND status = 'active'
    LIMIT 10
");
$stmt->bind_param("sss", $search, $search, $search);
$stmt->execute();
$res = $stmt->get_result();

$results = [];
while ($row = $res->fetch_assoc()) {
    $results[] = $row;
}

echo json_encode($results);
exit;
