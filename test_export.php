<?php
require "config/app.php";
$r = $conn->query("SHOW TABLES LIKE 'export_logs'");
echo "export_logs: " . ($r->num_rows > 0 ? 'exists' : 'NOT FOUND') . "\n";

if ($r->num_rows > 0) {
    $r2 = $conn->query("DESCRIBE export_logs");
    while($row = $r2->fetch_assoc()) echo "  " . $row['Field'] . " " . $row['Type'] . "\n";
} else {
    echo "Need to create export_logs table\n";
}

// Test ExcelTemplate
require_once "vendor/autoload.php";
echo "\nPhpSpreadsheet: " . (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet') ? 'OK' : 'NOT FOUND') . "\n";

// Test FPDF
$fpdf = class_exists('FPDF') ? 'OK' : 'NOT FOUND';
echo "FPDF: $fpdf\n";

// Test Dompdf
$dompdf = class_exists('\Dompdf\Dompdf') ? 'OK' : 'NOT FOUND';
echo "DomPDF: $dompdf\n";
