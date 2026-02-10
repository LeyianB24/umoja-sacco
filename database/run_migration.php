<?php
/**
 * Run Production Readiness Migration
 */
require_once __DIR__ . '/../config/db_connect.php';

echo "Running Production Readiness Migration...\n";
echo str_repeat("=", 60) . "\n\n";

$sql_file = __DIR__ . '/migrations/production_readiness.sql';
$sql = file_get_contents($sql_file);

// Split by semicolon and filter out comments
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^--/', $stmt);
    }
);

$success = 0;
$errors = 0;

foreach ($statements as $stmt) {
    // Skip if it's just whitespace or comments
    if (empty(trim($stmt))) continue;
    
    try {
        if ($conn->query($stmt)) {
            $success++;
            // Extract table name if CREATE TABLE
            if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $stmt, $matches)) {
                echo "✓ Created table: {$matches[1]}\n";
            } elseif (preg_match('/ALTER TABLE.*?`?(\w+)`?/i', $stmt, $matches)) {
                echo "✓ Altered table: {$matches[1]}\n";
            } else {
                echo "✓ Executed statement\n";
            }
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        $errors++;
        echo "✗ Error: {$e->getMessage()}\n";
        // Continue with other statements
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Migration Complete!\n";
echo "Success: $success\n";
echo "Errors: $errors\n";

if ($errors == 0) {
    echo "\n✅ All migrations executed successfully!\n";
} else {
    echo "\n⚠️  Some migrations failed. Check errors above.\n";
}
