<?php
require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../config/db_connect.php';

echo "--- STARTING MIGRATION ---\n";

// 1. Update support_tickets table
// Check if assigned_role_id exists
$check_col = $conn->query("SHOW COLUMNS FROM support_tickets LIKE 'assigned_role_id'");
if ($check_col->num_rows == 0) {
    $sql = "ALTER TABLE support_tickets 
            MODIFY COLUMN category ENUM('loans', 'savings', 'shares', 'welfare', 'withdrawals', 'technical', 'profile', 'investments', 'general', 'loan', 'accounting', 'tech') DEFAULT 'general',
            ADD COLUMN assigned_role_id INT NULL AFTER category,
            ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at";
    if ($conn->query($sql)) {
        echo "✓ Table support_tickets updated successfully.\n";
    } else {
        echo "⚠ Error updating support_tickets: " . $conn->error . "\n";
    }
} else {
    // Just update enum if needed
    $conn->query("ALTER TABLE support_tickets MODIFY COLUMN category ENUM('loans', 'savings', 'shares', 'welfare', 'withdrawals', 'technical', 'profile', 'investments', 'general', 'loan', 'accounting', 'tech') DEFAULT 'general'");
    echo "✓ Support tickets category enum expanded.\n";
}

// 2. Add new permissions for support categories if they don't exist
$categories = ['loans', 'savings', 'shares', 'welfare', 'withdrawals', 'technical', 'profile', 'investments'];
foreach ($categories as $cat) {
    // Support Permissions
    $slug = "support_" . $cat;
    $check = $conn->query("SELECT id FROM permissions WHERE slug = '$slug'");
    if ($check->num_rows == 0) {
        $name = ucfirst($cat) . " Ticket Management";
        $conn->query("INSERT INTO permissions (name, slug) VALUES ('$name', '$slug')");
        echo "✓ Permission $slug added.\n";
    }

    // Dashboard Stat Permissions
    $stat_slug = "view_" . $cat . "_stats";
    $check_stat = $conn->query("SELECT id FROM permissions WHERE slug = '$stat_slug'");
    if ($check_stat->num_rows == 0) {
        $name = ucfirst($cat) . " Dashboard Stats";
        $conn->query("INSERT INTO permissions (name, slug) VALUES ('$name', '$stat_slug')");
        echo "✓ Permission $stat_slug added.\n";
    }
}

// 3. Optional: Assign permissions to existing roles based on their name/intent
// Loans Admin -> support_loans
// Welfare Admin -> support_welfare
// etc.
// For now, we will let the SuperAdmin handle this in the UI, or we can auto-assign if roles match.

echo "--- MIGRATION COMPLETE ---\n";
