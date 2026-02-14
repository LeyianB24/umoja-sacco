<?php
// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch any output
ob_start();

try {
    echo "Starting include...\n";
    include __DIR__ . '/admin/pages/employees.php';
    echo "\nInclude completed.\n";
} catch (Throwable $e) {
    echo "\n\n=== FATAL ERROR ===\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n" . $e->getTraceAsString() . "\n";
}

$output = ob_get_clean();
file_put_contents(__DIR__ . '/error_output.txt', $output);
echo $output;
?>
