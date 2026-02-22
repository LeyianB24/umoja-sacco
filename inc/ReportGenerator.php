<?php
declare(strict_types=1);
// usms/inc/ReportGenerator.php
require_once __DIR__ . '/ExportHelper.php';

class ReportGenerator {
    private $db;

    public function __construct($conn) {
        $this->db = $conn;
    }

    /**
     * Generates a "Statement of Financial Position" dataset
     */
    /**
     * Calculates the dynamic Net Asset Value (NAV) per share
     */
    public function getShareValuation() {
        // 1. Calculate Total Assets (using logic from getBalanceSheetData)
        $endDate = date('Y-m-d');
        
        $getBal = function($category) use ($endDate) {
            $sql = "SELECT SUM(le.debit - le.credit) as bal FROM ledger_entries le JOIN ledger_accounts la ON le.account_id = la.account_id WHERE la.category = ? AND DATE(le.created_at) <= ?";
            $st = $this->db->prepare($sql);
            $st->bind_param("ss", $category, $endDate);
            $st->execute();
            return (float)($st->get_result()->fetch_assoc()['bal'] ?? 0);
        };

        $getSysBal = function($name) use ($endDate) {
            $sql = "SELECT SUM(le.debit - le.credit) as bal FROM ledger_entries le JOIN ledger_accounts la ON le.account_id = la.account_id WHERE la.account_name = ? AND DATE(le.created_at) <= ?";
            $st = $this->db->prepare($sql);
            $st->bind_param("ss", $name, $endDate);
            $st->execute();
            return (float)($st->get_result()->fetch_assoc()['bal'] ?? 0);
        };

        $loans = $getBal('loans');
        $cash  = $getSysBal('Cash at Hand') + $getSysBal('M-Pesa Float') + $getSysBal('Bank Account') + $getSysBal('Paystack Clearing Account');
        $investments = (float)($this->db->query("SELECT SUM(current_value) FROM investments WHERE status = 'active'")->fetch_row()[0] ?? 0);
        
        $totalAssets = $loans + $cash + $investments;

        // 2. Calculate External Liabilities (Savings & Welfare)
        $savings = -$getBal('savings'); 
        $welfare = -$getBal('welfare');
        $liabilities = $savings + $welfare;

        // 3. Net Equity (Owner's Equity)
        $netEquity = $totalAssets - $liabilities;

        // 4. Total Units Issued
        $res = $this->db->query("SELECT SUM(share_units) FROM shares");
        $totalUnits = (float)($res->fetch_row()[0] ?? 0);

        // 5. Valuation (NAV)
        $price = ($totalUnits > 0) ? ($netEquity / $totalUnits) : 100.00;
        
        // Base floor to prevent negative or zero value if sacco is insolvent (highly unlikely but safe)
        if ($price < 1.0) $price = 100.00; 

        return [
            'price' => round($price, 2),
            'equity' => $netEquity,
            'total_units' => $totalUnits,
            'total_assets' => $totalAssets,
            'liabilities' => $liabilities
        ];
    }

    public function getBalanceSheetData($startDate, $endDate) {
        $data = [
            'assets' => [],
            'liabilities_equity' => [],
            'totals' => ['assets' => 0, 'liability' => 0]
        ];

        // Helper to get balance of an account category as of a date
        $getBal = function($category, $isAsset) use ($endDate) {
            $sql = "SELECT SUM(le.debit - le.credit) as bal 
                    FROM ledger_entries le 
                    JOIN ledger_accounts la ON le.account_id = la.account_id 
                    WHERE la.category = ? AND DATE(le.created_at) <= ?";
            $st = $this->db->prepare($sql);
            $st->bind_param("ss", $category, $endDate);
            $st->execute();
            $val = (float)($st->get_result()->fetch_assoc()['bal'] ?? 0);
            return $isAsset ? $val : -$val;
        };

        // Helper for system accounts by name (Cash, etc)
        $getSysBal = function($name, $isAsset) use ($endDate) {
            $sql = "SELECT SUM(le.debit - le.credit) as bal 
                    FROM ledger_entries le 
                    JOIN ledger_accounts la ON le.account_id = la.account_id 
                    WHERE la.account_name = ? AND DATE(le.created_at) <= ?";
            $st = $this->db->prepare($sql);
            $st->bind_param("ss", $name, $endDate);
            $st->execute();
            $val = (float)($st->get_result()->fetch_assoc()['bal'] ?? 0);
            return $isAsset ? $val : -$val;
        };

        // 1. Assets: Loans Receivable
        $loanPrincipal = $getBal('loans', true);
        $data['assets'][] = ['label' => 'Loans Receivable (Principal)', 'amount' => $loanPrincipal];

        // 2. Assets: Liquidity (Cash, M-Pesa, Bank, Paystack)
        $cash = $getSysBal('Cash at Hand', true) + 
                $getSysBal('M-Pesa Float', true) + 
                $getSysBal('Bank Account', true) + 
                $getSysBal('Paystack Clearing Account', true);
        $data['assets'][] = ['label' => 'Cash & Bank Balances', 'amount' => $cash];

        // 3. Assets: Sacco Investments
        $totalInvestments = (float)($this->db->query("SELECT SUM(current_value) FROM investments WHERE status = 'active'")->fetch_row()[0] ?? 0);
        
        $data['assets'][] = ['label' => 'Strategic Investment Portfolio', 'amount' => $totalInvestments];

        $data['totals']['assets'] = $loanPrincipal + $cash + $totalInvestments;

        // 4. Liabilities: Member Savings & Welfare
        $savings = $getBal('savings', false);
        $welfare = $getBal('welfare', false);
        $data['liabilities_equity'][] = ['label' => 'Member Savings', 'amount' => $savings];
        $data['liabilities_equity'][] = ['label' => 'Benevolent Fund', 'amount' => $welfare];

        // 4. Equity: Share Capital
        $shares = $getBal('shares', false);
        $data['liabilities_equity'][] = ['label' => 'Share Capital', 'amount' => $shares];

        // 5. Equity: Retained Earnings (Revenue - Expenses)
        $revenue  = $getSysBal('SACCO Revenue', false);
        $expenses = $getSysBal('SACCO Expenses', true);
        $earnings = $revenue - $expenses;
        $data['liabilities_equity'][] = ['label' => 'Retained Earnings (Surplus)', 'amount' => $earnings];
        
        $data['totals']['liability'] = $savings + $welfare + $shares + $earnings;

        return $data;
    }

    /**
     * PDF Generation via ExportHelper
     */
    public function generatePDF($title, $data, $returnString = false) {
        $outputMode = $returnString ? 'S' : 'D';
        
        return ExportHelper::pdf($title, [], function($pdf) use ($data) {
            // Header
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->SetTextColor(20, 61, 48); // Forest Green
            $pdf->Cell(0, 10, 'STATEMENT OF FINANCIAL POSITION', 0, 1, 'C');
            $pdf->SetFont('Arial', '', 10);
            $pdf->SetTextColor(100);
            $pdf->Cell(0, 5, 'Generated on ' . date('d M Y H:i:s'), 0, 1, 'C');
            $pdf->Ln(5);
            
            // Draw Line
            $pdf->SetDrawColor(20, 61, 48);
            $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
            $pdf->Ln(10);

            // Assets Table
            $pdf->SetFillColor(20, 61, 48);
            $pdf->SetTextColor(255);
            $pdf->SetFont('Arial', 'B', 11);
            
            // Widths: Label = 140, Amount = 50
            $w = [140, 50];
            $a = ['L', 'R'];
            
            $pdf->Cell($w[0], 10, ' ASSETS', 1, 0, 'L', true);
            $pdf->Cell($w[1], 10, 'AMOUNT (KES) ', 1, 1, 'R', true);

            $pdf->SetTextColor(0);
            $pdf->SetFont('Arial', '', 11);
            
            foreach ($data['assets'] as $asset) {
                // Use new Row function for multi-line support
                $pdf->Row(
                    [' ' . $asset['label'], number_format((float)($asset['amount'] ?? 0), 2) . ' '],
                    $w,
                    $a,
                    6 // Line height
                );
            }
            
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell($w[0], 10, ' TOTAL ASSETS', 1, 0, 'L', true);
            $pdf->Cell($w[1], 10, number_format((float)($data['totals']['assets'] ?? 0), 2) . ' ', 1, 1, 'R', true);
            $pdf->Ln(10);

            // Liabilities & Equity Table
            $pdf->SetFillColor(20, 61, 48);
            $pdf->SetTextColor(255);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell($w[0], 10, ' LIABILITIES & EQUITY', 1, 0, 'L', true);
            $pdf->Cell($w[1], 10, 'AMOUNT (KES) ', 1, 1, 'R', true);

            $pdf->SetTextColor(0);
            $pdf->SetFont('Arial', '', 11);
            
            foreach ($data['liabilities_equity'] as $item) {
                $pdf->Row(
                    [' ' . $item['label'], number_format((float)($item['amount'] ?? 0), 2) . ' '],
                    $w,
                    $a,
                    6
                );
            }
            
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell($w[0], 10, ' TOTAL EQUITIES & LIABILITIES', 1, 0, 'L', true);
            $pdf->Cell($w[1], 10, number_format((float)($data['totals']['liability'] ?? 0), 2) . ' ', 1, 1, 'R', true);

            // Verification stamp text
            $pdf->Ln(20);
            $pdf->SetFont('Courier', 'I', 8);
            $pdf->SetTextColor(150);
            $pdf->Cell(190, 5, 'This is a system-generated report and remains a true representation of the Sacco\'s financial position.', 0, 1, 'C');
            $pdf->Cell(190, 5, 'Certification Hash: ' . md5(time() . rand()), 0, 1, 'C');
        }, 'report.pdf', $outputMode);
    }

    /**
     * Excel Export via ExportHelper
     */
    public function generateExcel($data) {
        $exportData = [];
        
        // ASSETS SECTION
        $exportData[] = ['ASSETS', '']; 
        foreach ($data['assets'] as $asset) {
            $exportData[] = [$asset['label'], $asset['amount']];
        }
        $exportData[] = ['TOTAL ASSETS', $data['totals']['assets']];
        $exportData[] = ['', '']; // Spacer
        
        // LIABILITIES SECTION
        $exportData[] = ['LIABILITIES & EQUITY', ''];
        foreach ($data['liabilities_equity'] as $item) {
            $exportData[] = [$item['label'], $item['amount']];
        }
        $exportData[] = ['TOTAL EQUITIES & LIABILITIES', $data['totals']['liability']];

        ExportHelper::csv('Statement_of_Financial_Position.csv', ['Description', 'Amount (KES)'], $exportData);
    }

    /**
     * Certified Member Statement Export
     */
    public function generateMemberStatement($memberData, $transactions) {
        ExportHelper::pdf('Member Statement', [], function($pdf) use ($memberData, $transactions) {
            $pdf->Ln(5);

            // Member Info
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(0);
            $pdf->Cell(100, 7, 'Member Name: ' . strtoupper($memberData['full_name']), 0, 0);
            $pdf->Cell(90, 7, 'Date: ' . date('d M Y'), 0, 1, 'R');
            $pdf->Cell(100, 7, 'Member ID: ' . $memberData['member_id'], 0, 0);
            $pdf->Cell(90, 7, 'National ID: ' . $memberData['national_id'], 0, 1, 'R');
            $pdf->Ln(10);

            // Summary Box
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(190, 10, ' FINANCIAL SUMMARY', 1, 1, 'L', true);
            $pdf->SetFont('Arial', '', 10);
            
            $netWorth = $memberData['total_savings'] + $memberData['total_shares'] - $memberData['loan_debt'];

            $pdf->Cell(95, 8, ' Total Savings:', 1);
            $pdf->Cell(95, 8, ' KES ' . number_format((float)$memberData['total_savings'], 2), 1, 1, 'R');
            $pdf->Cell(95, 8, ' Share Capital:', 1);
            $pdf->Cell(95, 8, ' KES ' . number_format((float)$memberData['total_shares'], 2), 1, 1, 'R');
            $pdf->Cell(95, 8, ' Outstanding Loans:', 1);
            $pdf->Cell(95, 8, ' KES ' . number_format((float)$memberData['loan_debt'], 2), 1, 1, 'R');
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(95, 8, ' ESTIMATED NET WORTH:', 1);
            $pdf->Cell(95, 8, ' KES ' . number_format((float)$netWorth, 2), 1, 1, 'R');
            $pdf->Ln(10);

            // Transaction History
            $pdf->SetFillColor(20, 61, 48);
            $pdf->SetTextColor(255);
            $pdf->SetFont('Arial', 'B', 10);
            $w = [35, 45, 60, 50];
            $a = ['L', 'L', 'L', 'R'];

            $pdf->Cell($w[0], 8, ' DATE', 1, 0, 'C', true);
            $pdf->Cell($w[1], 8, ' TYPE', 1, 0, 'C', true);
            $pdf->Cell($w[2], 8, ' REFERENCE', 1, 0, 'C', true);
            $pdf->Cell($w[3], 8, ' AMOUNT (KES)', 1, 1, 'C', true);

            $pdf->SetTextColor(0);
            $pdf->SetFont('Arial', '', 9);
            
            foreach ($transactions as $t) {
                $pdf->Row(
                    [
                        ' ' . date('d/m/Y', strtotime($t['transaction_date'])),
                        ' ' . strtoupper($t['transaction_type']),
                        ' ' . $t['reference_no'],
                        number_format((float)$t['amount'], 2) . ' '
                    ],
                    $w,
                    $a,
                    6
                );
            }

            // Footer & Stamp
            $pdf->Ln(15);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(190, 10, 'OFFICIAL CERTIFICATION', 0, 1, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->MultiCell(190, 5, "This statement is a certified record of the member's transactions with Umoja Drivers SACCO as of " . date('d M Y H:i:s') . ". Any discrepancies should be reported to the SACCO management within 7 days.");
            
            $pdf->Ln(10);
            $pdf->Cell(60, 10, '________________________', 0, 0);
            $pdf->Cell(70, 10, '', 0, 0);
            $pdf->Cell(60, 10, '________________________', 0, 1);
            $pdf->Cell(60, 5, 'Chief Accountant', 0, 0, 'C');
            $pdf->Cell(70, 5, '', 0, 0);
            $pdf->Cell(60, 5, 'Date of Issue', 0, 1, 'C');

        });
    }
}
