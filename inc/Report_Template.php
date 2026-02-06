<?php
// inc/Report_Template.php
/**
 * @deprecated Use FinancialExportEngine and PdfFinancialTemplate.
 */
// Refactored to use the new Systemwide Template

require_once __DIR__ . '/SystemPDF.php';

class SaccoPDF extends SystemPDF {
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

