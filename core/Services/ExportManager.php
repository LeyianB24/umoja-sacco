<?php
namespace USMS\Services;

use USMS\Reports\PdfTemplate;
use USMS\Reports\ExcelTemplate;
use USMS\Reports\ExportLogger;

class ExportManager {
    public static $isValidExport = false;

    /**
     * @param string $type 'pdf' or 'excel'
     * @param array $data Array of associative arrays (for PDF usually) or just data rows
     * @param array $options ['title' => '', 'module' => '', 'headers' => []]
     */
    public static function export($type, $data, $options = []) {
        self::$isValidExport = true;
        $title = $options['title'] ?? 'System Export';
        $module = $options['module'] ?? 'Generic';
        $headers = $options['headers'] ?? [];
        $outputMode = $options['output_mode'] ?? 'D'; // D: Download, S: String Return
        
        // 1. Log the export attempt
        if (!ExportLogger::log($type, $module, ['title' => $title, 'record_count' => is_array($data) ? count($data) : 'custom'])) {
             // Handle log failure if strict
        }
        
        // 2. Route to appropriate generator
        if ($type === 'pdf') {
            if (is_callable($data)) {
                return self::generateCustomPdf($data, $title, $module, $outputMode);
            } else {
                return self::generatePdf($data, $options);
            }
        } elseif ($type === 'excel') {
            return self::generateExcel($data, $headers, $title, $module, $outputMode);
        } else {
            die("Error: Invalid export type '$type'. Supported types: pdf, excel.");
        }
    }

    private static function generateCustomPdf($callback, $title, $module, $outputMode) {
        $pdf = new PdfTemplate();
        $pdf->setMetadata($title, $module);
        $pdf->AddPage();
        
        // Execute the custom table drawing logic
        call_user_func($callback, $pdf);
        
        if ($outputMode === 'S') {
            return $pdf->Output('S');
        } else {
            $filename = strtolower(str_replace(' ', '_', $title)) . '_' . date('Ymd') . '.pdf';
            $pdf->Output('D', $filename);
            exit;
        }
    }

    private static function generatePdf($data, $options) {
        $title = $options['title'] ?? 'System Export';
        $module = $options['module'] ?? 'Generic';
        $headers = $options['headers'] ?? [];
        $outputMode = $options['output_mode'] ?? 'D';
        $orientation = $options['orientation'] ?? 'P';
        
        $pdf = new PdfTemplate($orientation);
        
        $pdf->setMetadata($title, $module);
        $pdf->AddPage();
        
        if (count($data) > 0) {
            $pdf->UniversalTable($headers, $data);
        } else {
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->Cell(0, 10, 'No data to display.', 0, 1, 'C');
        }
        
        // Output
        if ($outputMode === 'S') {
            return $pdf->Output('S');
        } else {
            $filename = strtolower(str_replace(' ', '_', $title)) . '_' . date('Ymd') . '.pdf';
            $pdf->Output('D', $filename);
            exit;
        }
    }

    private static function generateExcel($data, $headers, $title, $module, $outputMode) {
        $excel = new ExcelTemplate($title, $module);
        $excel->addData($headers, $data);
        
        if ($outputMode === 'S') {
            // ExcelTemplate doesn't support 'S' yet, but we'll try to handle it or fallback
            // For now, let's keep it consistent with the engine
            return ""; 
        } else {
            $filename = strtolower(str_replace(' ', '_', $title)) . '_' . date('Ymd') . '.xlsx';
            $excel->download($filename);
            exit;
        }
    }
}
?>
