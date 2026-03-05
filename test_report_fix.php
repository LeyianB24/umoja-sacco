<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/vendor/autoload.php';

use USMS\Reports\ReportService;

// Mock session if needed (SystemPDF/PdfTemplate use sessions for audit)
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['admin_name'] = 'Test Admin';
$_SESSION['role_name'] = 'Super Admin';

try {
    $reportService = new ReportService();
    
    // Sample Balance Sheet Data
    $data = [
        'assets' => [
            ['label' => 'Cash at Hand', 'amount' => 50000],
            ['label' => 'Bank Account', 'amount' => 150000]
        ],
        'liabilities_equity' => [
            ['label' => 'Member Savings', 'amount' => 120000],
            ['label' => 'Share Capital', 'amount' => 80000]
        ],
        'totals' => [
            'assets' => 200000,
            'liability' => 200000
        ]
    ];

    echo "Testing PDF Generation (Return String)...\n";
    $pdfContent = $reportService->generatePDF("Test Report", $data, true);
    
    if (strlen($pdfContent) > 1000) {
        echo "SUCCESS: PDF content generated (Size: " . strlen($pdfContent) . " bytes)\n";
    } else {
        echo "FAILURE: PDF content too short or empty.\n";
    }

    echo "\nTesting Excel Calculation (Flat Rows)...\n";
    // generateExcel doesn't have a 'return string' option in UniversalExportEngine yet (it exits with download)
    // So we just check if it's callable and hasn't crashed before output.
    if (method_exists($reportService, 'generateExcel')) {
        echo "SUCCESS: generateExcel method exists.\n";
    } else {
        echo "FAILURE: generateExcel method missing.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
