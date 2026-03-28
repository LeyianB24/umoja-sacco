<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';

// Security Check
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$since_id = (int)($_GET['since_id'] ?? 0);
$limit = 50;

if ($since_id > 0) {
    $query = "SELECT a.*, ad.username, r.name as role, ad.full_name 
              FROM audit_logs a 
              LEFT JOIN admins ad ON a.admin_id = ad.admin_id 
              LEFT JOIN roles r ON ad.role_id = r.id 
              WHERE a.audit_id > ? 
              ORDER BY a.created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $since_id, $limit);
} else {
    $query = "SELECT a.*, ad.username, r.name as role, ad.full_name 
              FROM audit_logs a 
              LEFT JOIN admins ad ON a.admin_id = ad.admin_id 
              LEFT JOIN roles r ON ad.role_id = r.id 
              ORDER BY a.created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
}

$stmt->execute();
$result = $stmt->get_result();
$logs = [];

while ($row = $result->fetch_assoc()) {
    // Helper function for initials (mirroring live_monitor.php)
    $name = trim($row['full_name'] ?? $row['username'] ?? 'System');
    $initials = '';
    if (str_contains($name, ' ')) {
        $p = explode(' ', $name);
        $initials = strtoupper(substr($p[0], 0, 1) . substr(end($p), 0, 1));
    } else {
        $initials = strtoupper(substr($name, 0, 1));
    }

    $logs[] = [
        'id'         => $row['audit_id'],
        'time'       => date('H:i:s', strtotime($row['created_at'])),
        'date'       => date('M d', strtotime($row['created_at'])),
        'action'     => htmlspecialchars((string)($row['action'] ?? '')),
        'user_type'  => htmlspecialchars((string)($row['user_type'] ?? '')),
        'severity'   => strtolower((string)($row['severity'] ?? 'info')),
        'details'    => htmlspecialchars((string)($row['details'] ?? '')),
        'ip_address' => htmlspecialchars((string)($row['ip_address'] ?? '')),
        'actor'      => $name,
        'role'       => ucfirst($row['role'] ?? 'System'),
        'initials'   => $initials
    ];
}

header('Content-Type: application/json');
echo json_encode(['logs' => $logs]);
exit;
