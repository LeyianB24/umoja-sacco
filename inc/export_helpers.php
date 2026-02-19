<?php
// inc/export_helpers.php
// Unified Export Functions for PDF and Excel

require_once __DIR__ . '/../config/app_config.php';

/**
 * Export data to PDF
 * @param array $data - Array of associative arrays (table rows)
 * @param array $headers - Column headers
 * @param string $title - Document title
 * @param string $filename - Output filename
 */
function export_to_pdf($data, $headers, $title, $filename = 'export.pdf') {
    // Require TCPDF library (install via composer: composer require tecnickcom/tcpdf)
    // For now, using simple HTML to PDF approach
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Use mPDF or TCPDF - Simple HTML output for now
    // This is a placeholder - you should install a proper PDF library
    
    $html = generate_export_html($data, $headers, $title);
    
    // For production, replace with:
    // require_once 'vendor/autoload.php';
    // $mpdf = new \Mpdf\Mpdf();
    // $mpdf->WriteHTML($html);
    // $mpdf->Output($filename, 'D');
    
    // Temporary: Just output HTML
    echo $html;
    exit;
}

/**
 * Export data to Excel (CSV format for simplicity)
 * @param array $data - Array of associative arrays
 * @param array $headers - Column headers
 * @param string $filename - Output filename
 */
function export_to_excel($data, $headers, $filename = 'export.xlsx') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data rows
    foreach ($data as $row) {
        $row_data = [];
        foreach ($headers as $key => $header) {
            $row_data[] = $row[$key] ?? '';
        }
        fputcsv($output, $row_data);
    }
    
    fclose($output);
    exit;
}

/**
 * Generate HTML for export
 */
function generate_export_html($data, $headers, $title) {
    $html = '<!DOCTYPE html>
    <html>
    <head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
        <meta charset="utf-8">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { margin: 0; color: #0F392B; }
            .header p { margin: 5px 0; color: #666; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background: #0F392B; color: white; padding: 10px; text-align: left; }
            td { border: 1px solid #ddd; padding: 8px; }
            tr:nth-child(even) { background: #f9f9f9; }
            .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>' . SITE_NAME . '</h1>
            <p>' . COMPANY_ADDRESS . ' | ' . COMPANY_PHONE . '</p>
            <p>' . COMPANY_EMAIL . '</p>
            <h2>' . htmlspecialchars($title) . '</h2>
            <p>Generated: ' . date('d M Y, h:i A') . '</p>
        </div>
        <table>
            <thead><tr>';
    
    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    
    $html .= '</tr></thead><tbody>';
    
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($headers as $key => $header) {
            $html .= '<td>' . htmlspecialchars($row[$key] ?? '') . '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>
        <div class="footer">
            <p>© ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
        </div>
    </body>
    </html>';
    
    return $html;
}
