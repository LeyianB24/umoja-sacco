<?php
declare(strict_types=1);
namespace USMS\Reports;

use FPDF;

/**
 * @method void AddPage(string $orientation = '', mixed $size = '', int $rotation = 0)
 * @method void SetFont(string $family, string $style = '', float $size = 0)
 * @method void Cell(float $w, float $h = 0, string $txt = '', mixed $border = 0, int $ln = 0, string $align = '', bool $fill = false, mixed $link = '')
 * @method void Ln(float $h = null)
 * @method void SetTextColor(int $r, int $g = null, int $b = null)
 * @method void SetFillColor(int $r, int $g = null, int $b = null)
 * @method void SetDrawColor(int $r, int $g = null, int $b = null)
 * @method float GetY()
 * @method float GetX()
 * @method void SetXY(float $x, float $y)
 * @method void MultiCell(float $w, float $h, string $txt, mixed $border = 0, string $align = 'J', bool $fill = false)
 * @method void Image(string $file, float $x = null, float $y = null, float $w = 0, float $h = 0, string $type = '', mixed $link = '')
 * @method string Output(string $dest = '', string $name = '', bool $isUTF8 = false)
 */
class PdfTemplate extends FPDF {
    protected $docTitle;
    protected $docModule;
    protected $metadata;
    protected $primaryColor = [27, 94, 32];   // Default Sacco Green
    protected $secondaryColor = [244, 196, 48]; // Default Gold

    public function __construct($orientation='P', $unit='mm', $size='A4') {
        parent::__construct($orientation, $unit, $size);
        $this->SetMargins(15, 20, 15);
        $this->SetAutoPageBreak(true, 20);
        $this->AliasNbPages();
        $this->loadTheme();
    }

    public function setMetadata($title, $module, $extraData = []) {
        $this->docTitle = $title;
        $this->docModule = $module;
        $this->metadata = $extraData; // e.g. ['account_ref' => '...', 'currency' => 'KES']
        
        $this->SetTitle($title);
        $this->SetAuthor(SITE_NAME . " Export Engine");
        $this->SetCreator("Universal Export Engine");
        $this->SetSubject($module);
    }

    protected function loadTheme() {
        global $theme; 
        if (isset($theme['primary'])) {
            $col = $this->hexToRgb($theme['primary']);
            if ($col) $this->primaryColor = $col;
        }
        if (isset($theme['accent'])) {
            $col = $this->hexToRgb($theme['accent']);
            if ($col) $this->secondaryColor = $col;
        }
    }

    private function pdfText($value): string {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (!is_scalar($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
            $value = $encoded === false ? '' : $encoded;
        }

        $text = str_replace(["\r\n", "\r"], "\n", (string)$value);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                return $converted;
            }
        }

        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $text) ?? '';
    }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '') {
        return parent::Cell($w, $h, $this->pdfText($txt), $border, $ln, $align, $fill, $link);
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false) {
        return parent::MultiCell($w, $h, $this->pdfText($txt), $border, $align, $fill);
    }

    public function Write($h, $txt, $link = '') {
        return parent::Write($h, $this->pdfText($txt), $link);
    }

    protected function hexToRgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) != 6) return null;
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        ];
    }

    public function Header() {
        // 1. Logo (with fallback on Railway/production)
        try {
            $logoPath = BASE_PATH . '/public/assets/images/people_logo.png';
            // Don't use realpath() - it fails on some cloud environments
            if (is_file($logoPath)) {
                $this->Image($logoPath, 15, 10, 20);
            }
        } catch (\Throwable $e) {
            // Silently skip logo on error - don't crash the whole PDF
            error_log("[PdfTemplate] Logo loading failed (non-fatal): " . $e->getMessage());
        }

        // 2. Organization Details (Right Aligned)
        $this->SetXY(40, 10);
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Cell(0, 8, strtoupper(SITE_NAME), 0, 1, 'R');

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 5, SITE_TAGLINE, 0, 1, 'R');
        $this->Cell(0, 5, COMPANY_ADDRESS, 0, 1, 'R');
        $this->Cell(0, 5, COMPANY_PHONE . " | " . COMPANY_EMAIL, 0, 1, 'R');

        // 3. Document Title Bar
        $this->Ln(5);
        $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 12);
        
        $title = $this->docTitle ?: 'SYSTEM REPORT';
        $module = $this->docModule ? " | " . strtoupper($this->docModule) : '';
        
        $this->Cell(0, 10, $title . $module, 0, 1, 'C', true);
        
        // 4. Metadata Line (User, Date, Account)
        $this->SetFillColor(245, 245, 245);
        $this->SetTextColor(50, 50, 50);
        $this->SetFont('Arial', '', 8);
        
        $user = isset($_SESSION['user_full_name']) ? $_SESSION['user_full_name'] : (
                isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : (
                isset($_SESSION['username']) ? $_SESSION['username'] : (
                isset($_SESSION['member_name']) ? $_SESSION['member_name'] : 'System'
                )));
                
        $date = date('d M Y h:i A');
        
        // Build Meta Text
        $metaParts = ["Generated By: $user", "Date: $date"];
        if (!empty($this->metadata['account_ref'])) $metaParts[] = "Ref: " . $this->metadata['account_ref'];
        if (!empty($this->metadata['currency'])) $metaParts[] = "Currency: " . $this->metadata['currency'];
        
        $metaText = implode("  |  ", $metaParts);
        $this->Cell(0, 6, $metaText, 'B', 1, 'C', true);
        
        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-20);
        
        // Divider
        $this->SetDrawColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        
        // Audit Hash & Page
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(128, 128, 128);
        
        $hash = md5($this->docTitle . date('YmdHis')); // Simple unique hash for this export session
        $this->Cell(100, 5, "Audit ID: " . substr(strtoupper($hash), 0, 16) . " | OFFICIAL DOCUMENT", 0, 0, 'L');
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 1, 'R');
    }

    // Helper for Tables with Text Wrapping and Pagination
    public function UniversalTable($headers, $data) {
        if (empty($headers)) return;

        try {
            // Table Header
            $this->SetFillColor(230, 230, 230);
            $this->SetTextColor(0, 0, 0);
            $this->SetDrawColor(200, 200, 200);
            $this->SetLineWidth(0.3);
            $this->SetFont('Arial', 'B', 9);

            // Calculate widths
            $pageWidth = $this->GetPageWidth() - 30; // Margins 15+15
            $colCount = count($headers);
            $w = $pageWidth / $colCount;
            $widths = array_fill(0, $colCount, $w);
            $aligns = array_fill(0, $colCount, 'L');

            // Header Row
            foreach ($headers as $i => $header) {
                $this->Cell($widths[$i], 8, strtoupper($header), 1, 0, 'C', true);
            }
            $this->Ln();

            // Data
            $this->SetFont('Arial', '', 9);
            $fill = false;
            
            foreach ($data as $row) {
                $this->SetFillColor(250, 250, 250); // Zebra
                
                // Ensure row is indexed array matching headers
                $rowValues = array_values($row);
                $rowValues = array_pad(array_slice($rowValues, 0, $colCount), $colCount, '');
                
                // Calculate alignments based on content type
                for ($i = 0; $i < $colCount; $i++) {
                    $colValue = (string)($rowValues[$i] ?? '');
                    if (is_numeric(str_replace([',', ' ', 'KES'], '', $colValue))) {
                        $aligns[$i] = 'R';
                    } else {
                        $aligns[$i] = 'L';
                    }
                }

                // Use the Row helper which handles MultiCell and Page Breaks
                $this->Row($rowValues, $widths, $aligns, 5, $fill);
                
                $fill = !$fill;
            }
        } catch (\Throwable $e) {
            // Log error but don't crash - try simple fallback
            error_log("[PdfTemplate] UniversalTable Error: " . $e->getMessage());
            // Try simple fallback - just output all data as plain text
            $this->SetFont('Arial', '', 10);
            foreach ($data as $row) {
                foreach ($row as $cell) {
                    $this->Cell(0, 8, (string)$cell . '  |  ', 0, 0);
                }
                $this->Ln();
            }
        }
    }

    /**
     * Draw a row with MultiCell support and Pagination
     */
    public function Row($data, $widths, $aligns, $lineHeight=5, $fill=false) {
        $data = array_map(fn($value) => $this->pdfText($value), $data);

        // Calculate the height of the row
        $nb = 0;
        for($i=0; $i<count($data); $i++) {
            $nb = max($nb, $this->NbLines($widths[$i], (string)$data[$i]));
        }
        $h = $lineHeight * $nb;

        // Issue a page break first if needed
        $this->CheckPageBreak($h, $widths, $aligns);

        // Save the current position
        $x = $this->GetX();
        $y = $this->GetY();

        // Draw the cells of the row
        for($i=0; $i<count($data); $i++) {
            $w = $widths[$i];
            $a = isset($aligns[$i]) ? $aligns[$i] : 'L';
            
            // Output the cell border and background
            if ($fill) {
                $this->Rect($x, $y, $w, $h, 'DF');
            } else {
                $this->Rect($x, $y, $w, $h, 'D');
            }
            
            // Print the text
            $this->SetXY($x, $y);
            $this->MultiCell($w, $lineHeight, (string)$data[$i], 0, $a);
            
            // Advance x position
            $x += $w;
        }
        // Go to the next line
        $this->SetXY($this->lMargin, $y + $h);
    }

    /**
     * Check if page break is needed
     */
    public function CheckPageBreak($h, $widths = null, $aligns = null) {
        // If the height h would cause an overflow, add a new page immediately
        if($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }
    }

    /**
     * Compute the number of lines a MultiCell of width w will take
     */
    public function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        $txt = $this->pdfText($txt);
        if($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string)$txt); 
        $nb = strlen($s);
        if($nb > 0 && $s[$nb - 1] == "\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while($i < $nb) {
            $c = $s[$i];
            if($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if($c == ' ')
                $sep = $i;
            $l += $cw[$c] ?? 0;
            if($l > $wmax) {
                if($sep == -1) {
                    if($i == $j)
                        $i++;
                } else
                    $i = $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else
                $i++;
        }
        return $nl;
    }

    // Alias for backward compatibility
    public function FinancialTable($headers, $data) {
        $this->UniversalTable($headers, $data);
    }
}
