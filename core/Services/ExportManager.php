<?php
declare(strict_types=1);
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
        $title      = $options['title']  ?? 'System Export';
        $module     = $options['module'] ?? 'Generic';
        $headers    = $options['headers'] ?? [];
        $outputMode = $options['output_mode'] ?? 'D'; // D: Download, S: String Return

        // 1. Log the export attempt — wrapped so a missing table never kills exports
        try {
            ExportLogger::log($type, $module, [
                'title'        => $title,
                'record_count' => is_array($data) ? count($data) : 'custom'
            ]);
        } catch (\Throwable $e) {
            error_log("[ExportManager] Logger failed (non-fatal): " . $e->getMessage());
        }

        // 2. Route to appropriate generator
        if ($type === 'pdf') {
            return is_callable($data)
                ? self::generateCustomPdf($data, $title, $module, $outputMode)
                : self::generatePdf($data, $options);
        } elseif ($type === 'excel') {
            return self::generateExcel($data, $headers, $title, $module, $outputMode);
        } else {
            \USMS\Http\ErrorHandler::abort(400, "Invalid export type '{$type}'. Supported types: pdf, excel.");
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
            while (ob_get_level() > 0) { ob_end_clean(); }
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
        
        try {
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
                // Drain ALL output buffer levels so headers can be sent cleanly
                while (ob_get_level() > 0) { ob_end_clean(); }
                $pdf->Output('D', $filename);
                exit;
            }
        } catch (\Throwable $e) {
            error_log("[ExportManager] PDF Generation Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            
            // Show error only in development
            if (defined('APP_ENV') && APP_ENV === 'development') {
                throw $e;
            }
            
            // Production: Return generic error page
            while (ob_get_level() > 0) { ob_end_clean(); }
            http_response_code(500);
            header('Content-Type: text/plain');
            echo "PDF Export Error: Unable to generate document. Please try again later.\n";
            echo "[Debug: " . (defined('APP_ENV') && APP_ENV === 'production' ? 'Check server logs' : $e->getMessage()) . "]\n";
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

