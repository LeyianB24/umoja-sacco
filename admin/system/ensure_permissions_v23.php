<?php
/**
 * V23 Permission Consistency Check
 * Ensures all required permission slugs exist in the database
 * Run this once to prepare the system for granular sidebar visibility
 */

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_permission();

// Only Superadmin can run this
if (!isset($_SESSION['admin_id']) || $_SESSION['role_id'] != 1) {
    die("Superadmin access required.");
}

$required_permissions = [
    // Membership
    ['slug' => 'view_members', 'name' => 'View Members', 'category' => 'membership'],
    ['slug' => 'register_member', 'name' => 'Register New Members', 'category' => 'membership'],
    ['slug' => 'edit_members', 'name' => 'Edit Member Details', 'category' => 'membership'],
    ['slug' => 'delete_members', 'name' => 'Delete Members', 'category' => 'membership'],
    
    // Loans
    ['slug' => 'view_loans', 'name' => 'View Loans', 'category' => 'loans'],
    ['slug' => 'approve_loans', 'name' => 'Approve/Reject Loans', 'category' => 'loans'],
    ['slug' => 'disburse_loans', 'name' => 'Disburse Loan Funds', 'category' => 'loans'],
    ['slug' => 'manage_loans', 'name' => 'Full Loan Management', 'category' => 'loans'],
    
    // Finance
    ['slug' => 'view_financials', 'name' => 'View Financial Data', 'category' => 'finance'],
    ['slug' => 'manage_revenue', 'name' => 'Manage Revenue', 'category' => 'finance'],
    ['slug' => 'manage_expenses', 'name' => 'Manage Expenses', 'category' => 'finance'],
    
    // Reports
    ['slug' => 'view_reports', 'name' => 'View Reports', 'category' => 'reports'],
    ['slug' => 'export_reports', 'name' => 'Export Reports', 'category' => 'reports'],
    
    // Welfare
    ['slug' => 'view_welfare', 'name' => 'View Welfare Cases', 'category' => 'welfare'],
    ['slug' => 'manage_welfare', 'name' => 'Manage Welfare Cases', 'category' => 'welfare'],
    
    // Support
    ['slug' => 'view_tickets', 'name' => 'View Support Tickets', 'category' => 'support'],
    ['slug' => 'solve_tickets', 'name' => 'Resolve Support Tickets', 'category' => 'support'],
    
    // System
    ['slug' => 'manage_settings', 'name' => 'Manage System Settings', 'category' => 'system'],
    ['slug' => 'manage_users', 'name' => 'Manage Admin Users', 'category' => 'system'],
    ['slug' => 'manage_roles', 'name' => 'Manage Roles & Permissions', 'category' => 'system'],
    ['slug' => 'view_audit_logs', 'name' => 'View Audit Logs', 'category' => 'system'],
];

$added = 0;
$existing = 0;

echo "<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <title>V23 Permission Setup</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
<div class='container py-5'>
    <div class='card shadow-sm'>
        <div class='card-header bg-success text-white'>
            <h4 class='mb-0'>V23 Permission Consistency Check</h4>
        </div>
        <div class='card-body'>
            <table class='table table-sm'>
                <thead>
                    <tr>
                        <th>Slug</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>";

foreach ($required_permissions as $perm) {
    $slug = $perm['slug'];
    $name = $perm['name'];
    $category = $perm['category'];
    
    // Check if exists
    $check = $conn->prepare("SELECT id FROM permissions WHERE slug = ?");
    $check->bind_param("s", $slug);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        echo "<tr>
                <td><code>$slug</code></td>
                <td>$name</td>
                <td><span class='badge bg-secondary'>$category</span></td>
                <td><span class='badge bg-info'>Already Exists</span></td>
              </tr>";
        $existing++;
    } else {
        // Insert
        $insert = $conn->prepare("INSERT INTO permissions (slug, name, category) VALUES (?, ?, ?)");
        $insert->bind_param("sss", $slug, $name, $category);
        if ($insert->execute()) {
            echo "<tr>
                    <td><code>$slug</code></td>
                    <td>$name</td>
                    <td><span class='badge bg-secondary'>$category</span></td>
                    <td><span class='badge bg-success'>✓ Added</span></td>
                  </tr>";
            $added++;
        } else {
            echo "<tr>
                    <td><code>$slug</code></td>
                    <td>$name</td>
                    <td><span class='badge bg-secondary'>$category</span></td>
                    <td><span class='badge bg-danger'>✗ Failed</span></td>
                  </tr>";
        }
    }
}

echo "      </tbody>
            </table>
            <div class='alert alert-success mt-3'>
                <strong>Summary:</strong> $added permissions added, $existing already existed.
            </div>
            <a href='roles.php' class='btn btn-primary'>Go to Roles & Permissions</a>
        </div>
    </div>
</div>
</body>
</html>";


