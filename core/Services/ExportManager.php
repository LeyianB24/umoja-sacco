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
                return self::generatePdf($data, $headers, $title, $module, $outputMode);
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

    private static function generatePdf($data, $headers, $title, $module, $outputMode) {
        $pdf = new PdfTemplate();
        $pdf->setMetadata($title, $module);
        $pdf->AddPage();
        
        // Default Table Rendering
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(240, 240, 240); // Light Gray
        $pdf->SetDrawColor(200, 200, 200);
        
        // Calculate Column Widths
        // Assuming $headers matches keys of $data items
        $pageWidth = $pdf->GetPageWidth() - 30; // 15mm margins left/right
        $colCount = count($headers);
        
        if ($colCount > 0) {
            $colWidth = $pageWidth / $colCount;
            
            // Header Row
            $pdf->SetFillColor(27, 94, 32); // Primary Green
            $pdf->SetTextColor(255, 255, 255);
            foreach ($headers as $h) {
                $pdf->Cell($colWidth, 8, strtoupper($h), 1, 0, 'C', true);
            }
            $pdf->Ln();
            
            // Data Rows
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(0, 0, 0);
            $fill = false;
            
            foreach ($data as $row) {
                 $row = is_array($row) ? array_values($row) : [];
                 
                 // Dynamic height check
                 $pdf->SetFillColor(245, 248, 245); // Zebra stripe
                 
                 for ($i = 0; $i < $colCount; $i++) {
                     $val = isset($row[$i]) ? $row[$i] : '';
                     
                     // Truncate if too long (basic handling)
                     if (strlen($val) > 30) $val = substr($val, 0, 27) . '...';
                     
                     $align = is_numeric($val) ? 'R' : 'L';
                     $pdf->Cell($colWidth, 7, $val, 1, 0, $align, $fill);
                 }
                 $pdf->Ln();
                 $fill = !$fill;
            }
        } else {
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
