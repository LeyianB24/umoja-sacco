<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing employees.php execution...\n\n";

// Capture any output
ob_start();

try {
    include __DIR__ . '/employees.php';
    $output = ob_get_clean();
    echo "✓ Page loaded successfully\n";
    echo "Output length: " . strlen($output) . " bytes\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "✗ Fatal error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
