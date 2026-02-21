<?php
// inc/Report_Template.php
/**
 * @deprecated Use FinancialExportEngine and PdfFinancialTemplate.
 */
// Refactored to use the new Systemwide Template. 
// SystemPDF is now in core/Reports/ and autoloaded via Composer.
if (!class_exists('SystemPDF')) {
    // Fallback: require directly if autoloader isn't available
    $systemPdfPath = __DIR__ . '/../core/Reports/SystemPDF.php';
    if (file_exists($systemPdfPath)) require_once $systemPdfPath;
}

class SaccoPDF extends \USMS\Reports\SystemPDF {
    // SaccoPDF now inherits all branded header/footer logic from SystemPDF
    
    /**
     * Legacy support for Table Generator
     */
    function BasicTable($header, $data, $columnWidths = []) {
        $w = $columnWidths; 
        if(empty($w)) { 
            $w = array_fill(0, count($header), 180 / count($header)); 
        }

        $this->StyledTableHeader($header, $w);

        $fill = false;
        foreach($data as $row) {
            $rowData = [];
            foreach($row as $cell) $rowData[] = (string)$cell;
            $this->StyledRow($rowData, $w, $fill);
            $fill = !$fill;
        }
        
        // Closing line
        $this->Cell(array_sum($w), 0, '', 'T');
        $this->Ln(5);
    }
}

