<?php
namespace USMS\Reports;

use FPDF;

class SystemPDF extends FPDF {
    protected $documentTitle;
    protected $documentSource;
    protected $primaryColor = [27, 94, 32];   // #1b5e20 (Sacco Green)
    protected $secondaryColor = [244, 196, 48]; // #f4c430 (Sacco Gold)
    protected $textColor = [33, 37, 41];
    protected $mutedColor = [107, 114, 128];

    /**
     * @param string $title Header Display Title
     * @param string $source Module/Source (e.g., Loans Report, Savings Statement)
     * @param string $orientation 'P' or 'L'
     */
    public function __construct($title = "Document", $source = "System Export", $orientation = 'P') {
        parent::__construct($orientation, 'mm', 'A4');
        $this->documentTitle = $title;
        $this->documentSource = $source;
        
        // Load colors from app_config if available
        global $theme;
        if (isset($theme['primary'])) {
            $hex = ltrim($theme['primary'], '#');
            $this->primaryColor = [
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2))
            ];
        }
        if (isset($theme['accent'])) {
            $hex = ltrim($theme['accent'], '#');
            $this->secondaryColor = [
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2))
            ];
        }

        $this->SetMargins(15, 20, 15);
        $this->SetAutoPageBreak(true, 25);
        $this->AliasNbPages();
    }

    /**
     * GLOBAL HEADER: Branding & Identity
     */
    public function Header() {
        // 1. Logo (Top-Left)
        $logoPath = __DIR__ . '/../public/assets/images/people_logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 15, 10, 25);
        }

        // 2. System/Site Name & Branding
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Cell(0, 10, strtoupper(SITE_NAME), 0, 1, 'R');
        
        $this->SetFont('Arial', 'I', 9);
        $this->SetTextColor($this->mutedColor[0], $this->mutedColor[1], $this->mutedColor[2]);
        $this->Cell(0, 5, SITE_TAGLINE, 0, 1, 'R');
        
        // 3. Document Title
        $this->Ln(10);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 10, strtoupper($this->documentTitle), 0, 1, 'C');

        // 4. Header Divider (Brand Colors)
        $this->Ln(2);
        $this->SetDrawColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetLineWidth(0.8);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(5);
    }

    /**
     * GLOBAL FOOTER: Metadata & Audit Traceability
     */
    public function Footer() {
        // Position at 1.5 cm from bottom
        $this->SetY(-25);

        // 1. Footer Divider
        $this->SetDrawColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(2);

        // 2. Contact Details (Static from config)
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor($this->mutedColor[0], $this->mutedColor[1], $this->mutedColor[2]);
        $contactStr = COMPANY_OFFICE . ", " . COMPANY_ADDRESS . " | Tel: " . COMPANY_PHONE . " | Email: " . COMPANY_EMAIL;
        $this->Cell(0, 4, $contactStr, 0, 1, 'C');

        // 3. Audit Metadata (Logged-in user details)
        $printedBy = "Guest";
        if (isset($_SESSION['admin_name'])) {
            $role = $_SESSION['role_name'] ?? 'Staff';
            $printedBy = $_SESSION['admin_name'] . " ($role)";
        } elseif (isset($_SESSION['member_name'])) {
            $printedBy = $_SESSION['member_name'] . " (Member)";
        }

        $printDate = date('d M Y, h:i:s A');
        $auditStr = "Printed By: $printedBy | Date: $printDate | Source: " . $this->documentSource;
        $docHash = "REF: " . strtoupper(substr(md5($auditStr . time()), 0, 8));

        $this->SetFont('Arial', 'B', 7);
        $this->Cell(100, 5, $auditStr, 0, 0, 'L');
        $this->Cell(90, 5, $docHash, 0, 1, 'R');

        // 4. Page Numbers
        $this->SetY(-10);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }

    /**
     * Standardized Table Header
     */
    public function StyledTableHeader($header, $widths) {
        $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetLineWidth(.3);
        $this->SetFont('Arial', 'B', 9);

        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($widths[$i], 10, " " . strtoupper($header[$i]), 1, 0, 'L', true);
        }
        $this->Ln();
        
        // Reset for rows
        $this->SetTextColor(0);
        $this->SetFont('Arial', '', 9);
    }

    /**
     * Zebra Striped Row
     */
    public function StyledRow($data, $widths, $fill = false) {
        $this->SetFillColor(245, 245, 245);
        $max_h = 7;
        for ($i = 0; $i < count($data); $i++) {
            $align = (is_numeric($data[$i]) || strpos($data[$i], 'KES') !== false) ? 'R' : 'L';
            $this->Cell($widths[$i], $max_h, $data[$i] . " ", 1, 0, $align, $fill);
        }
        $this->Ln();
    }
}
