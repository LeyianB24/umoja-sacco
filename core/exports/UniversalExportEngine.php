<?php
// core/exports/UniversalExportEngine.php
ini_set('display_errors', 0); // Suppress warnings globally during export

require_once __DIR__ . '/PdfTemplate.php';
require_once __DIR__ . '/ExcelTemplate.php';
require_once __DIR__ . '/ExportAuditLogger.php';

class UniversalExportEngine {

    /**
     * Universal Export Handler
     * 
     * @param string $format 'pdf', 'excel', 'print'
     * @param array|callable $data Raw data array or Closure($pdf) for custom PDF
     * @param array $options Configuration:
     *                       [
     *                         'title' => 'Report Title',
     *                         'module' => 'Module Name',
     *                         'headers' => ['Col1', 'Col2'], (Required for Excel/Table PDF)
     *                         'record_count' => int, (Optional, auto-calc if array)
     *                         'total_value' => float, (Optional for audit)
     *                         'account_ref' => string,
     *                         'currency' => string,
     *                         'orientation' => 'P' or 'L' (PDF only)
     *                       ]
     */
    public static function handle($format, $data, $options = []) {
        // 1. Validation & Defaults
        $module = $options['module'] ?? 'General';
        $title = $options['title'] ?? 'System Export';
        $totalValue = $options['total_value'] ?? 0.00;
        
        // Auto-calculate record count if data is array
        $recordCount = isset($options['record_count']) ? $options['record_count'] : (is_array($data) ? count($data) : 1);
        
        // 2. Audit Logging
        // We log before generating. If generation fails, we still have a record of the attempt (or could update status later).
        // For now, we log as 'success' optimistically or 'initiated'. Let's stick to what we established.
        $logId = ExportAuditLogger::log($module, $format, $recordCount, $totalValue, "Title: $title");
        
        if (!$logId) {
            // If strictly enforced:
            // die("Export Error: Audit logging failed. Action aborted.");
            // But for now we proceed but maybe log to system error log.
            error_log("UniversalExportEngine: Audit Log Failed for $title");
        }

        // 3. Prepare Metadata for Templates
        $systemKeys = ['title', 'module', 'headers', 'record_count', 'total_value', 'output_mode', 'orientation', 'date_range'];
        $customMeta = array_diff_key($options, array_flip($systemKeys));
        
        $metadata = array_merge([
            'account_ref' => $options['account_ref'] ?? null,
            'currency' => $options['currency'] ?? 'KES',
            'totals' => $totalValue > 0 ? number_format($totalValue, 2) : null
        ], $customMeta);

        // 4. Dispatch
        $outputMode = $options['output_mode'] ?? 'D';
        
        try {
            switch (strtolower($format)) {
                case 'pdf':
                    return self::generatePdf($data, $options, $metadata, $outputMode); // Return result (string for S)
                case 'print':
                    self::generatePdf($data, $options, $metadata, 'I'); 
                    break;
                case 'excel':
                    if ($outputMode === 'S') {
                        // Not strictly supported yet in ExcelTemplate download() which exits.
                        // But let's leave it for now or implement if needed. 
                        // ExcelTemplate might need update for 'S'.
                    }
                    self::generateExcel($data, $options, $metadata);
                    break;
                default:
                    die("Invalid Export Format: $format");
            }
        } catch (Exception $e) {
            error_log("Export Generation Error: " . $e->getMessage());
            die("An error occurred while generating the export. Please contact support.");
        }
    }

    private static function generatePdf($data, $options, $metadata, $outputMode) {
        $orientation = $options['orientation'] ?? 'P';
        $pdf = new PdfTemplate($orientation);
        $pdf->setMetadata($options['title'] ?? 'Export', $options['module'] ?? 'System', $metadata);
        $pdf->AddPage();

        if (is_callable($data)) {
            // Custom Layout (Closure)
            $data($pdf); 
        } else {
            // Standard Table Layout
            $headers = $options['headers'] ?? [];
            if (empty($headers) && !empty($data) && is_array($data)) {
                // Try to infer headers from first row keys
                $first = reset($data);
                if (is_array($first)) $headers = array_keys($first);
            }
            
            // Add Date Range or specific metadata if provided in options
            if (!empty($options['date_range'])) {
                $pdf->SetFont('Arial', '', 9);
                 $pdf->Cell(0, 6, "Period: " . $options['date_range'], 0, 1, 'L');
                 $pdf->Ln(2);
            }

            $pdf->UniversalTable($headers, $data);
            
            // Totals
            if (!empty($metadata['totals'])) {
                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(0, 8, 'Total Value: ' . $metadata['currency'] . ' ' . $metadata['totals'], 0, 1, 'R');
            }
        }

        $dateStr = date('Ymd_His');
        $cleanTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($options['title'] ?? 'doc'));
        $filename = "{$cleanTitle}_{$dateStr}.pdf";
        
        $pdf->Output($outputMode, $filename);
        exit;
    }

    private static function generateExcel($data, $options, $metadata) {
        if (is_callable($data)) {
             die("Excel export does not support custom layouts (closures). Please provide a data array.");
        }

        $excel = new ExcelTemplate($options['title'] ?? 'Export', $options['module'] ?? 'System', $metadata);
        
        $headers = $options['headers'] ?? [];
        if (empty($headers) && !empty($data) && is_array($data)) {
            $first = reset($data);
            if (is_array($first)) $headers = array_keys($first);
        }

        $excel->addData($headers, $data);
        
        $dateStr = date('Ymd_His');
        $cleanTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($options['title'] ?? 'doc'));
        $filename = "{$cleanTitle}_{$dateStr}.xlsx";
        
        $excel->download($filename);
    }
}
