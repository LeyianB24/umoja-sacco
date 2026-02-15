<?php
// debug_pdf_gen.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/inc/ReportGenerator.php';
require_once __DIR__ . '/vendor/autoload.php';

// Mock Data
$data = [
    'assets' => [
        ['label' => 'Test Asset 1', 'amount' => 50000.00],
        ['label' => 'Test Asset 2', 'amount' => 12500.50]
    ],
    'liabilities_equity' => [
        ['label' => 'Test Liability', 'amount' => 10000.00],
        ['label' => 'Test Equity', 'amount' => 52500.50]
    ],
    'totals' => [
        'assets' => 62500.50,
        'liability' => 62500.50
    ]
];

echo "--- Starting PDF Generation Test ---\n";

try {
    $gen = new ReportGenerator($conn);
    
    // We can't easily mock the internal db calls of ReportGenerator unless we subclass it or use the data pass-through.
    // However, generatePDF accepts $data directly!
    // public function generatePDF($title, $data, $returnString = false)
    
    $start = microtime(true);
    $pdfContent = $gen->generatePDF("Debug Report", $data, true);
    $end = microtime(true);
    
    echo "Generation Time: " . round($end - $start, 4) . "s\n";
    echo "Content Length: " . strlen($pdfContent) . " bytes\n";
    
    if (strpos($pdfContent, '%PDF-') === 0) {
        echo "SUCCESS: Valid PDF Header detected.\n";
        file_put_contents('debug_test.pdf', $pdfContent);
        echo "Saved to debug_test.pdf\n";
    } else {
        echo "FAILURE: Invalid PDF Header.\n";
        echo "First 100 chars: " . substr($pdfContent, 0, 100) . "\n";
    }

} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
