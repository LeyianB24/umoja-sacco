<?php
ini_set('display_errors', 0);
// core/finance/FinancialExportEngine.php
// Wrapper for the Universal Export Engine to maintain backward compatibility
// and inject financial-specific logic.

require_once __DIR__ . '/../exports/UniversalExportEngine.php';

class FinancialExportEngine {

    public static function export($format, $data, $config) {
        $options = $config;
        // Map any differing keys if necessary (most are aligned now)
        
        // Handle Financial Specifics for PDF
        if ($format === 'pdf' && is_array($data) && !is_callable($data)) {
            // If we have specific financial metadata that needs display around the table
            if (isset($config['opening_balance']) || isset($config['closing_balance']) || isset($config['total_label'])) {
                
                // Convert data to Closure to inject financial context
                $originalData = $data;
                $headers = $config['headers'] ?? [];
                
                $data = function($pdf) use ($originalData, $headers, $config) {
                    // Opening Balance
                    if (isset($config['opening_balance']) && !is_null($config['opening_balance'])) {
                        $pdf->SetFont('Arial', 'B', 10);
                        $pdf->Cell(0, 8, 'Opening Balance: KES ' . number_format((float)$config['opening_balance'], 2), 0, 1, 'L');
                        $pdf->Ln(2);
                    }

                    // Table
                    $pdf->UniversalTable($headers, $originalData);

                    // Totals & Closing
                    $pdf->Ln(5);
                    $pdf->SetFont('Arial', 'B', 10);
                    $pdf->SetFillColor(240, 240, 240);
                    
                    $currency = $config['currency'] ?? 'KES';
                    $totalVal = $config['total_value'] ?? 0;
                    $lbl = $config['total_label'] ?? 'Total Value';
                    
                    $pdf->Cell(0, 8, "$lbl: $currency " . number_format((float)$totalVal, 2), 0, 1, 'R', true);

                    if (isset($config['closing_balance']) && !is_null($config['closing_balance'])) {
                        $pdf->Cell(0, 8, 'Closing Balance: KES ' . number_format((float)$config['closing_balance'], 2), 0, 1, 'R', true);
                    }
                };
            }
        }
        
        // Delegate to Universal Engine
        return UniversalExportEngine::handle($format, $data, $options);
    }
}
