<?php
/**
 * V24 Quick Setup Script
 * Run this ONCE to initialize the Perfect Mirror RBAC system
 */

require_once __DIR__ . '/../../config/db_connect.php';
session_start();

// Security: Only Superadmin can run this
if (!isset($_SESSION['admin_id']) || $_SESSION['role_id'] != 1) {
    die("<h1>403 Forbidden</h1><p>Superadmin access required.</p>");
}

$success_count = 0;
$error_count = 0;
$messages = [];

// Step 1: Ensure all V24 permission slugs exist
$permissions = [
    ['slug' => 'view_members', 'name' => 'View Members', 'category' => 'operations'],
    ['slug' => 'view_loans', 'name' => 'View Loans', 'category' => 'operations'],
    ['slug' => 'view_financials', 'name' => 'View Financials', 'category' => 'operations'],
    ['slug' => 'manage_expenses', 'name' => 'Manage Expenses', 'category' => 'operations'],
    ['slug' => 'investment_mgmt', 'name' => 'Investment Management', 'category' => 'operations'],
    ['slug' => 'view_welfare', 'name' => 'View Welfare', 'category' => 'operations'],
    ['slug' => 'manage_staff', 'name' => 'HR & Staff', 'category' => 'operations'],
    ['slug' => 'view_reports', 'name' => 'View Reports', 'category' => 'operations'],
    ['slug' => 'manage_tickets', 'name' => 'Manage Support Tickets', 'category' => 'operations'],
    ['slug' => 'manage_users', 'name' => 'Manage Admin Users', 'category' => 'system'],
    ['slug' => 'manage_roles', 'name' => 'Manage Roles & Permissions', 'category' => 'system'],
    ['slug' => 'manage_settings', 'name' => 'Manage System Settings', 'category' => 'system'],
    ['slug' => 'view_audit_logs', 'name' => 'View Audit Logs', 'category' => 'system'],
];

foreach ($permissions as $perm) {
    $check = $conn->prepare("SELECT id FROM permissions WHERE slug = ?");
    $check->bind_param("s", $perm['slug']);
    $check->execute();
    
    if ($check->get_result()->num_rows == 0) {
        $insert = $conn->prepare("INSERT INTO permissions (slug, name, category) VALUES (?, ?, ?)");
        $insert->bind_param("sss", $perm['slug'], $perm['name'], $perm['category']);
        
        if ($insert->execute()) {
            $messages[] = "✓ Added permission: <code>{$perm['slug']}</code>";
            $success_count++;
        } else {
            $messages[] = "✗ Failed to add: <code>{$perm['slug']}</code>";
            $error_count++;
        }
    } else {
        $messages[] = "→ Already exists: <code>{$perm['slug']}</code>";
    }
}

// Step 2: Ensure Superadmin role has ALL permissions
$superadmin_role_id = 1;
$all_perms = $conn->query("SELECT id FROM permissions");

while ($p = $all_perms->fetch_assoc()) {
    $check = $conn->prepare("SELECT * FROM role_permissions WHERE role_id = ? AND permission_id = ?");
    $check->bind_param("ii", $superadmin_role_id, $p['id']);
    $check->execute();
    
    if ($check->get_result()->num_rows == 0) {
        $insert = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        $insert->bind_param("ii", $superadmin_role_id, $p['id']);
        $insert->execute();
    }
}

$messages[] = "<strong>✓ Superadmin role updated with all permissions</strong>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>V24 Setup Complete</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0F2E25 0%, #134e3b 100%); min-height: 100vh; }
        .setup-card { max-width: 800px; margin: 50px auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="setup-card">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-success text-white">
                    <h3 class="mb-0"><i class="bi bi-check-circle-fill me-2"></i> V24 Perfect Mirror RBAC Setup</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <strong>Setup Complete!</strong> The V24 "Perfect Mirror" RBAC system is now active.
                    </div>
                    
                    <h5 class="mt-4">Setup Summary:</h5>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($messages as $msg): ?>
                            <li class="list-group-item"><?= $msg ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <div class="alert alert-info mt-4">
                        <h6><strong>What Changed:</strong></h6>
                        <ol class="mb-0">
                            <li><strong>Login System:</strong> Now generates a "Permission Passport" for each user</li>
                            <li><strong>Sidebar:</strong> Completely permission-driven - no hardcoded role checks</li>
                            <li><strong>Superadmin:</strong> Treated as a user with ALL permissions (not a bypass)</li>
                        </ol>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><strong>Next Steps:</strong></h6>
                        <ol class="mb-0">
                            <li>Go to <strong>Roles & Permissions</strong></li>
                            <li>Edit each role (Manager, Clerk, etc.)</li>
                            <li>Check the boxes for permissions they should have</li>
                            <li>Log in as that role to test the sidebar visibility</li>
                        </ol>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <a href="roles.php" class="btn btn-success btn-lg">
                            <i class="bi bi-grid-3x3-gap me-2"></i> Configure Roles & Permissions
                        </a>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-house me-2"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>


