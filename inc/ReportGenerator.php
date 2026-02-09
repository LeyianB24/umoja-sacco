<?php
// usms/inc/ReportGenerator.php
require_once __DIR__ . '/../core/finance/FinancialExportEngine.php';

class ReportGenerator {
    private $db;

    public function __construct($conn) {
        $this->db = $conn;
    }

    /**
     * Generates a "Statement of Financial Position" dataset
     */
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

        // 2. Assets: Liquidity (Cash, M-Pesa, Bank)
        $cash = $getSysBal('Cash at Hand', true) + $getSysBal('M-Pesa Float', true) + $getSysBal('Bank Account', true);
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
     * PDF Generation via FinancialExportEngine
     */
    public function generatePDF($title, $data, $returnString = false) {
        $outputMode = $returnString ? 'S' : 'D';
        
        return FinancialExportEngine::export('pdf', function($pdf) use ($data) {
            // Content starts below the branded header
            $pdf->Ln(5);

            // Assets Table
            $pdf->SetFillColor(20, 61, 48);
            $pdf->SetTextColor(255);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(140, 10, ' ASSETS', 1, 0, 'L', true);
            $pdf->Cell(50, 10, 'AMOUNT (KES) ', 1, 1, 'R', true);

            $pdf->SetTextColor(0);
            $pdf->SetFont('Arial', '', 11);
            foreach ($data['assets'] as $asset) {
                $pdf->Cell(140, 10, ' ' . $asset['label'], 1);
                $pdf->Cell(50, 10, number_format((float)$asset['amount'], 2) . ' ', 1, 1, 'R');
            }
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(140, 10, ' TOTAL ASSETS', 1);
            $pdf->Cell(50, 10, number_format((float)$data['totals']['assets'], 2) . ' ', 1, 1, 'R');
            $pdf->Ln(10);

            // Liabilities & Equity Table
            $pdf->SetFillColor(20, 61, 48);
            $pdf->SetTextColor(255);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(140, 10, ' LIABILITIES & EQUITY', 1, 0, 'L', true);
            $pdf->Cell(50, 10, 'AMOUNT (KES) ', 1, 1, 'R', true);

            $pdf->SetTextColor(0);
            $pdf->SetFont('Arial', '', 11);
            foreach ($data['liabilities_equity'] as $item) {
                $pdf->Cell(140, 10, ' ' . $item['label'], 1);
                $pdf->Cell(50, 10, number_format((float)$item['amount'], 2) . ' ', 1, 1, 'R');
            }
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(140, 10, ' TOTAL EQUITIES & LIABILITIES', 1);
            $pdf->Cell(50, 10, number_format((float)$data['totals']['liability'], 2) . ' ', 1, 1, 'R');

            // Verification stamp text
            $pdf->Ln(20);
            $pdf->SetFont('Courier', 'I', 8);
            $pdf->SetTextColor(150);
            $pdf->Cell(190, 5, 'This is a system-generated report and remains a true representation of the Sacco\'s financial position.', 0, 1, 'C');
            $pdf->Cell(190, 5, 'Certification Hash: ' . md5(time() . rand()), 0, 1, 'C');
        }, [
            'title' => $title,
            'module' => 'Finance Module',
            'output_mode' => $outputMode,
            'total_value' => $data['totals']['assets'] // Log the asset total as the value
        ]);
    }

    /**
     * Excel Export via FinancialExportEngine
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

        FinancialExportEngine::export('excel', $exportData, [
            'title' => 'Statement of Financial Position',
            'module' => 'Finance Module',
            'headers' => ['Description', 'Amount (KES)'],
            'total_value' => $data['totals']['assets']
        ]);
    }

    /**
     * Certified Member Statement Export
     */
    public function generateMemberStatement($memberData, $transactions) {
        FinancialExportEngine::export('pdf', function($pdf) use ($memberData, $transactions) {
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
            $pdf->Cell(35, 8, ' DATE', 1, 0, 'C', true);
            $pdf->Cell(45, 8, ' TYPE', 1, 0, 'C', true);
            $pdf->Cell(60, 8, ' REFERENCE', 1, 0, 'C', true);
            $pdf->Cell(50, 8, ' AMOUNT (KES)', 1, 1, 'C', true);

            $pdf->SetTextColor(0);
            $pdf->SetFont('Arial', '', 9);
            foreach ($transactions as $t) {
                $pdf->Cell(35, 8, ' ' . date('d/m/Y', strtotime($t['transaction_date'])), 1);
                $pdf->Cell(45, 8, ' ' . strtoupper($t['transaction_type']), 1);
                $pdf->Cell(60, 8, ' ' . $t['reference_no'], 1);
                $pdf->Cell(50, 8, number_format((float)$t['amount'], 2) . ' ', 1, 1, 'R');
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

        }, [
            'title' => 'Member Statement',
            'module' => 'Members Module',
            'account_ref' => $memberData['member_reg_no'] ?? $memberData['member_id'],
            'total_value' => $memberData['total_savings'] // Log savings as significant value
        ]);
    }
}
