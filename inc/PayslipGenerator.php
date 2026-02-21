<?php
declare(strict_types=1);
// inc/PayslipGenerator.php

class PayslipGenerator {
    
    /**
     * Renders simple payslip layout onto the provided PDF instance.
     * @param FPDF $pdf
     * @param array $data Contains 'employee' and 'payroll' arrays
     */
    public static function render($pdf, $data) {
        $emp = $data['employee'];
        $pay = $data['payroll'];
        
        // Ensure font
        $pdf->SetFont('Arial', '', 10);
        
        // ---------------------------------------------------------
        // 1. Employee Details Box
        // ---------------------------------------------------------
        $pdf->Ln(5);
        $yStart = $pdf->GetY();
        
        // Left Column: Personal Info
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(30, 6, "Employee Info:", 0, 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(30, 5, "Name:", 0, 0); $pdf->Cell(60, 5, strtoupper($emp['full_name']), 0, 1);
        $pdf->Cell(30, 5, "Staff ID:", 0, 0); $pdf->Cell(60, 5, $emp['employee_no'], 0, 1);
        $pdf->Cell(30, 5, "Job Title:", 0, 0); $pdf->Cell(60, 5, $emp['job_title'], 0, 1);
        $pdf->Cell(30, 5, "Grade:", 0, 0); $pdf->Cell(60, 5, $emp['grade_name'] ?? 'N/A', 0, 1);
        
        // Right Column: Tax/Bank Info
        $pdf->SetXY(110, $yStart);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(30, 6, "Payment Details:", 0, 1);
        $pdf->SetXY(110, $pdf->GetY());
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(30, 5, "Bank:", 0, 0); $pdf->Cell(50, 5, $emp['bank_name'] ?? 'N/A', 0, 1);
        $pdf->SetXY(110, $pdf->GetY());
        $pdf->Cell(30, 5, "Account:", 0, 0); $pdf->Cell(50, 5, $emp['bank_account'] ?? 'N/A', 0, 1);
        $pdf->SetXY(110, $pdf->GetY());
        $pdf->Cell(30, 5, "KRA PIN:", 0, 0); $pdf->Cell(50, 5, $emp['kra_pin'] ?? 'N/A', 0, 1);
        $pdf->SetXY(110, $pdf->GetY());
        $pdf->Cell(30, 5, "NSSF / SHA:", 0, 0); $pdf->Cell(50, 5, ($emp['nssf_no'] ?? '-') . ' / ' . ($emp['sha_no'] ?? '-'), 0, 1);

        $pdf->Ln(10);
        
        // ---------------------------------------------------------
        // 2. Financial Table (Earnings vs Deductions)
        // ---------------------------------------------------------
        // Headers
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(90, 8, "EARNINGS", 1, 0, 'L', true);
        $pdf->Cell(5, 8, "", 0, 0); // Gap
        $pdf->Cell(90, 8, "DEDUCTIONS", 1, 1, 'L', true);
        
        $yTable = $pdf->GetY();
        
        // EARNINGS LIST
        $pdf->SetXY(15, $yTable);
        $pdf->SetFont('Arial', '', 9);
        
        // Basic
        self::row($pdf, "Basic Salary", $pay['basic_salary']);
        // Allowances
        self::row($pdf, "House Allowance", $pay['house_allowance'] ?? 0); // Need to pass breakdown if available
        self::row($pdf, "Transport Allowance", $pay['transport_allowance'] ?? 0);
        self::row($pdf, "Other Allowances", $pay['other_allowances'] ?? 0); // Placeholder
        
        // DEDUCTIONS LIST
        $pdf->SetXY(110, $yTable);
        
        self::row($pdf, "PAYE Tax", $pay['tax_paye'], 110);
        self::row($pdf, "NSSF", $pay['tax_nssf'], 110);
        self::row($pdf, "SHA", $pay['tax_nhif'], 110);
        self::row($pdf, "Housing Levy", $pay['tax_housing'], 110);
        // Add other deductions if present in future
        
        $pdf->Ln(20); // Spacing after lists
        
        // ---------------------------------------------------------
        // 3. Totals
        // ---------------------------------------------------------
        $pdf->SetDrawColor(100, 100, 100);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(2);
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(60, 8, "Total Gross Pay:", 0, 0);
        $pdf->Cell(30, 8, number_format((float)$pay['gross_pay'], 2), 0, 0, 'R');
        
        $pdf->Cell(65, 8, "Total Deductions:", 0, 0, 'R');
        $pdf->Cell(30, 8, "(" . number_format((float)$pay['deductions'], 2) . ")", 0, 1, 'R');
        
        $pdf->Ln(2);
        $pdf->SetFillColor(27, 94, 32); // Sacco Green
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(130, 12, "NET PAYABLE", 1, 0, 'R', true);
        $pdf->Cell(55, 12, "KES " . number_format((float)$pay['net_pay'], 2), 1, 1, 'C', true);
        
        // Reset colors
        $pdf->SetTextColor(0,0,0);
        
        // ---------------------------------------------------------
        // 4. Signatures
        // ---------------------------------------------------------
        $pdf->Ln(25);
        
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, "__________________________", 0, 0, 'C');
        $pdf->Cell(65, 5, "", 0, 0);
        $pdf->Cell(60, 5, "__________________________", 0, 1, 'C');
        
        $pdf->Cell(60, 5, "Authorized Signature", 0, 0, 'C');
        $pdf->Cell(65, 5, "", 0, 0);
        $pdf->Cell(60, 5, "Employee Signature", 0, 1, 'C');
        
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 5, "This payslip was generated electronically by UDS System.", 0, 1, 'C');
    }
    
    private static function row($pdf, $label, $amount, $xOffset = 15) {
        $pdf->SetX($xOffset);
        $pdf->Cell(60, 6, $label, 'B', 0, 'L');
        $pdf->Cell(30, 6, number_format((float)$amount, 2), 'B', 1, 'R');
    }
}
?>
