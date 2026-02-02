<?php
// inc/Report_Template.php
// V9 PDF Generator Class (Extends FPDF)

require_once __DIR__ . '/../fpdf/fpdf.php';

class SaccoPDF extends FPDF {
    private $reportTitle;

    public function __construct($title = "Report") {
        parent::__construct();
        $this->reportTitle = $title;
        $this->SetMargins(15, 15, 15);
        $this->SetAutoPageBreak(true, 15);
        $this->AliasNbPages();
    }

    // Page Header
    function Header() {
        // Logo
        $logoPath = __DIR__ . '/../public/assets/img/logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 15, 10, 30); // X, Y, Width
        }

        // Company Details
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(15, 46, 37); // Forest Green
        $this->Cell(0, 8, 'UMOJA DRIVERS SACCO', 0, 1, 'C');
        
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, 'P.O. Box 12345-00100, Nairobi | Tel: +254 700 000 000', 0, 1, 'C');
        $this->Cell(0, 5, 'Email: info@umojadriverssacco.co.ke | Website: www.umojadriverssacco.co.ke', 0, 1, 'C');
        
        // Horizontal Line
        $this->Ln(5);
        $this->SetDrawColor(208, 243, 93); // Lime Green
        $this->SetLineWidth(1);
        $this->Line(15, 35, 195, 35);
        
        // Report Title
        $this->Ln(10);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 10, strtoupper($this->reportTitle), 0, 1, 'C');
        
        // Generated Meta
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, 'Generated on: ' . date('d M Y, h:i A') . ' | By: System', 0, 1, 'R');
        $this->Ln(5);
    }

    // Page Footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb} - Umoja Drivers SACCO System V9', 0, 0, 'C');
    }

    // Table Generator
    function BasicTable($header, $data, $columnWidths = []) {
        // Colors, line width and bold font
        $this->SetFillColor(15, 46, 37); // Header Background
        $this->SetTextColor(255); // Header Text
        $this->SetDrawColor(220, 220, 220);
        $this->SetLineWidth(.3);
        $this->SetFont('Arial', 'B', 9);

        // Header
        $w = $columnWidths; 
        if(empty($w)) { 
            // Auto width if not provided (Naive approach)
            $w = array_fill(0, count($header), 190 / count($header)); 
        }

        for($i=0; $i<count($header); $i++) {
            $this->Cell($w[$i], 9, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();

        // Color and font restoration
        $this->SetFillColor(245, 245, 245);
        $this->SetTextColor(0);
        $this->SetFont('');
        
        // Data
        $fill = false;
        foreach($data as $row) {
            for($i=0; $i<count($row); $i++) {
                // Ensure text string
                $this->Cell($w[$i], 8, (string)$row[$i], 'LR', 0, 'L', $fill);
            }
            $this->Ln();
            $fill = !$fill; // Zebra striping
        }
        
        // Closing line
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}
