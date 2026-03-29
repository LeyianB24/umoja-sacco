<?php
require_once 'config/app.php';
try {
    $sql = "INSERT INTO audit_logs (action, details, member_id, admin_id, user_id, user_type, severity, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute(['DATABASE_BACKUP_SUCCESS', 'SQL backup generated successfully', null, 1, 1, 'admin', 'success', '0.0.0.0', 'CLI']);
    echo "Audit log inserted successfully.";
} catch (Exception $e) {
    echo "Failed: ". $e->getMessage();
}
