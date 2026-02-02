<?php
// usms/inc/ExportHelper.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/app_config.php';

// Check for FPDF
if (file_exists(__DIR__ . '/../fpdf/fpdf.php')) {
    require_once __DIR__ . '/../fpdf/fpdf.php';
} else {
    // Fallback or error if FPDF is missing
    error_log("FPDF library not found in ../fpdf/fpdf.php");
}

class ExportHelper {
    
    /**
     * Generate Branded PDF from Data Array
     */
    public static function pdf($title, $headers, $data, $filename = 'report.pdf') {
        if (!class_exists('FPDF')) {
            die("PDF Generation Error: Library not found.");
        }

        $pdf = new FPDF();
        $pdf->AddPage();
        
        // 1. Corporate Header
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(15, 46, 37); // Forest Green #0F2E25
        $pdf->Cell(0, 10, strtoupper(SITE_NAME), 0, 1, 'C');
        
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(100);
        $pdf->Cell(0, 5, COMPANY_ADDRESS, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Email: ' . COMPANY_EMAIL . ' | Tel: ' . COMPANY_PHONE, 0, 1, 'C');
        $pdf->Ln(5);
        $pdf->Cell(0, 0, '', 'T', 1, 'C'); // Divider line
        $pdf->Ln(5);
        
        // 2. Report Title
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor(0);
        $pdf->Cell(0, 10, strtoupper($title), 0, 1, 'L');
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 5, 'Generated on: ' . date('d M Y, H:i'), 0, 1, 'L');
        $pdf->Ln(5);
        
        // 3. Table Headers
        $pdf->SetFillColor(15, 46, 37); // Forest Green
        $pdf->SetTextColor(255);
        $pdf->SetFont('Arial', 'B', 9);
        
        $colCount = count($headers);
        // Dynamic width calculation
        $pageWidth = $pdf->GetPageWidth() - 20; // 10mm margin each side
        $width = $pageWidth / ($colCount > 0 ? $colCount : 1);
        
        foreach ($headers as $h) {
            $pdf->Cell($width, 10, ' ' . strtoupper($h), 1, 0, 'L', true);
        }
        $pdf->Ln();
        
        // 4. Table Data
        $pdf->SetTextColor(0);
        $pdf->SetFont('Arial', '', 9);
        $fill = false;
        $pdf->SetFillColor(240, 248, 245); // Light mint for stripes
        
        foreach ($data as $row) {
            $i = 0;
            // Ensure row is array values
            $rowValues = array_values($row);
            
            // Calculate max height for row (simple multiline support could go here, but keeping simple for now)
            $cellHeight = 8;

            foreach ($rowValues as $cell) {
                // Limit to header count
                if ($i >= $colCount) break;
                
                $val = (string)$cell;
                // Simple truncation if too long
                if(strlen($val) > 30) $val = substr($val, 0, 27) . '...';

                $pdf->Cell($width, $cellHeight, ' ' . $val, 1, 0, 'L', $fill);
                $i++;
            }
            // Fill empty cells if row has fewer columns
            while ($i < $colCount) {
                 $pdf->Cell($width, $cellHeight, '', 1, 0, 'L', $fill);
                 $i++;
            }

            $pdf->Ln();
            $fill = !$fill;
        }
        
        // 5. Footer
        $pdf->SetY(-20);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(150);
        $pdf->Cell(0, 10, 'Page ' . $pdf->PageNo() . ' - ' . SITE_NAME . ' System Generated Report', 0, 0, 'C');
        
        $pdf->Output('D', $filename);
        exit;
    }
    
    /**
     * Generate CSV/Excel for any data set
     */
    public static function csv($filename, $headers, $data) {
        if (!preg_match('/\.csv$/i', $filename)) {
            $filename .= '.csv';
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // Brand Row
        fputcsv($output, [strtoupper(SITE_NAME) . ' - SYSTEM REPORT']);
        fputcsv($output, ['Generated on: ' . date('d M Y, H:i')]);
        fputcsv($output, []); // Empty row
        
        // Headers
        fputcsv($output, $headers);
        
        // Data
        foreach ($data as $row) {
            // Ensure we only output values
            fputcsv($output, array_values($row));
        }
        
        fclose($output);
        exit;
    }

    public static function exportToCSV($filename, $headers, $data) {
        return self::csv($filename, $headers, $data);
    }
}
