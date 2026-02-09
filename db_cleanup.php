<?php
/**
 * Database Cleanup & Migration Script
 * Fixes orphaned transactions and backfills missing targets
 */

require_once __DIR__ . '/config/db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Require admin authentication
if (!isset($_SESSION['admin_id'])) {
    die("ERROR: Must be logged in as admin to run migrations\n");
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  DATABASE CLEANUP & MIGRATION\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$fixes_applied = 0;
$errors = 0;

// FIX 1: Backfill Missing Investment Targets
echo "FIX 1: Backfilling Missing Investment Targets\n";
echo "─────────────────────────────────────────────────────────────\n";

$no_target_investments = $conn->query("
    SELECT investment_id, title, category, created_at, purchase_date
    FROM investments 
    WHERE target_amount IS NULL OR target_amount = 0
");

$target_defaults = [
    'vehicle_fleet' => ['amount' => 50000, 'period' => 'monthly'],
    'farm' => ['amount' => 100000, 'period' => 'monthly'],
    'apartments' => ['amount' => 150000, 'period' => 'monthly'],
    'petrol_station' => ['amount' => 200000, 'period' => 'monthly'],
];

while ($inv = $no_target_investments->fetch_assoc()) {
    $defaults = $target_defaults[$inv['category']] ?? ['amount' => 50000, 'period' => 'monthly'];
    $start_date = $inv['purchase_date'] ?: $inv['created_at'];
    
    $stmt = $conn->prepare("
        UPDATE investments 
        SET target_amount = ?, 
            target_period = ?, 
            target_start_date = ?
        WHERE investment_id = ?
    ");
    $stmt->bind_param("dssi", $defaults['amount'], $defaults['period'], $start_date, $inv['investment_id']);
    
    if ($stmt->execute()) {
        echo "  ✓ Updated: {$inv['title']} → Target: KES " . number_format($defaults['amount']) . " ({$defaults['period']})\n";
        $fixes_applied++;
    } else {
        echo "  ✗ Failed: {$inv['title']}\n";
        $errors++;
    }
}

echo "\n";

// FIX 2: Handle Orphaned Revenue
echo "FIX 2: Analyzing Orphaned Revenue\n";
echo "─────────────────────────────────────────────────────────────\n";

$orphan_revenue = $conn->query("
    SELECT transaction_id, amount, transaction_date, notes, category
    FROM transactions 
    WHERE transaction_type IN ('income', 'revenue_inflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
");

echo "Found " . $orphan_revenue->num_rows . " orphaned revenue records:\n\n";

while ($trans = $orphan_revenue->fetch_assoc()) {
    echo "  Transaction ID: {$trans['transaction_id']}\n";
    echo "  Amount: KES " . number_format($trans['amount']) . "\n";
    echo "  Date: {$trans['transaction_date']}\n";
    echo "  Category: {$trans['category']}\n";
    echo "  Notes: {$trans['notes']}\n";
    echo "  ---\n";
}

echo "\nACTION REQUIRED: Review these transactions and either:\n";
echo "  A) Link them to specific investments manually\n";
echo "  B) Mark them as general income (related_table='general', related_id=0)\n\n";

// FIX 3: Handle Orphaned Expenses
echo "FIX 3: Analyzing Orphaned Expenses\n";
echo "─────────────────────────────────────────────────────────────\n";

$orphan_expenses = $conn->query("
    SELECT transaction_id, amount, transaction_date, notes, category
    FROM transactions 
    WHERE transaction_type IN ('expense', 'expense_outflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
");

echo "Found " . $orphan_expenses->num_rows . " orphaned expense records:\n\n";

while ($trans = $orphan_expenses->fetch_assoc()) {
    echo "  Transaction ID: {$trans['transaction_id']}\n";
    echo "  Amount: KES " . number_format($trans['amount']) . "\n";
    echo "  Date: {$trans['transaction_date']}\n";
    echo "  Category: {$trans['category']}\n";
    echo "  Notes: {$trans['notes']}\n";
    echo "  ---\n";
}

echo "\nACTION REQUIRED: Review these transactions and either:\n";
echo "  A) Link them to specific investments manually\n";
echo "  B) Mark them as operational expenses (related_table='general', related_id=0)\n\n";

// SUMMARY
echo "═══════════════════════════════════════════════════════════════\n";
echo "  MIGRATION SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n\n";
echo "Fixes Applied: $fixes_applied\n";
echo "Errors: $errors\n";
echo "\nOrphaned Transactions: Require manual review (see above)\n";
echo "\n═══════════════════════════════════════════════════════════════\n";
