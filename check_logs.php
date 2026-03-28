<?php
require_once 'config/app.php';
$res = $conn->query("SELECT * FROM audit_logs WHERE action LIKE '%BACKUP%' ORDER BY id DESC LIMIT 20");

echo "--- Backup Audit Logs ---\n";
while ($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']} | Action: {$row['action']} | Details: {$row['details']} | Admin: {$row['admin_id']} | Date: {$row['created_at']}\n";
}
if ($res->num_rows === 0) echo "No backup logs found.\n";
