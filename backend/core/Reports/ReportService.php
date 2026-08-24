<?php
declare(strict_types=1);

namespace USMS\Reports;

use USMS\Database\Database;
use PDO;

/**
 * USMS\Reports\ReportService
 * Enterprise Reporting Engine - Generates financial reports and member statements.
 */
class ReportService {
    private PDO $db;

    public function __construct($db = null) {
        try {
            $this->db = ($db instanceof PDO) ? $db : \USMS\Database\Database::getInstance()->getPdo();
        } catch (\Exception $e) {
            // Fallback for environments where the Database singleton might not be fully ready
            // though in USMS it usually is. 
            global $pdo;
            if (isset($pdo)) $this->db = $pdo;
            else throw $e;
        }
    }

    /**
     * Generates a PDF Report using the Enterprise Export Engine
     */
    public function generatePDF(string $title, array $data, bool $returnContent = false) {
        $config = [
            'title' => $title,
            'module' => 'Reporting Service',
            'output_mode' => $returnContent ? 'S' : 'D'
        ];

        // We use a closure to handle the specific balance sheet layout
        $renderTask = function($pdf) use ($data) {
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetTextColor(27, 94, 32);
            $pdf->Cell(0, 10, 'STATEMENT OF FINANCIAL POSITION', 0, 1, 'L');
            $pdf->Ln(2);

            // Assets Header
            $pdf->SetFillColor(235, 245, 235);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(130, 8, ' ASSETS DESCRIPTION', 1, 0, 'L', true);
            $pdf->Cell(50, 8, 'AMOUNT (KES) ', 1, 1, 'R', true);

            // Assets Rows
            $pdf->SetFont('Arial', '', 9);
            foreach ($data['assets'] as $row) {
                $pdf->Cell(130, 8, ' ' . $row['label'], 1, 0, 'L');
                $pdf->Cell(50, 8, number_format((float)$row['amount'], 2) . ' ', 1, 1, 'R');
            }

            // Assets Total
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Cell(130, 8, ' TOTAL ASSETS', 1, 0, 'L', true);
            $pdf->Cell(50, 8, number_format((float)$data['totals']['assets'], 2) . ' ', 1, 1, 'R', true);
            $pdf->Ln(10);

            // Liabilities Header
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetTextColor(27, 94, 32);
            $pdf->Cell(0, 10, 'LIABILITIES & EQUITY', 0, 1, 'L');
            $pdf->Ln(2);

            $pdf->SetFillColor(235, 245, 235);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(130, 8, ' LIABILITY / EQUITY ITEM', 1, 0, 'L', true);
            $pdf->Cell(50, 8, 'AMOUNT (KES) ', 1, 1, 'R', true);

            // Liabilities Rows
            $pdf->SetFont('Arial', '', 9);
            foreach ($data['liabilities_equity'] as $row) {
                $pdf->Cell(130, 8, ' ' . $row['label'], 1, 0, 'L');
                $pdf->Cell(50, 8, number_format((float)$row['amount'], 2) . ' ', 1, 1, 'R');
            }

            // Liabilities Total
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Cell(130, 8, ' TOTAL LIABILITIES & EQUITY', 1, 0, 'L', true);
            $pdf->Cell(50, 8, number_format((float)$data['totals']['liability'], 2) . ' ', 1, 1, 'R', true);
            
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'I', 8);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 5, "This is a computer-generated document and does not require a signature.", 0, 1, 'C');
        };

        return \USMS\Services\FinancialExportEngine::export('pdf', $renderTask, $config);
    }

    /**
     * Generates an Excel Export of the report data
     */
    public function generateExcel(array $data) {
        $rows = [];
        // Flatten the complex balance sheet structure for Excel
        foreach ($data['assets'] as $a) {
            $rows[] = ['Classification' => 'Asset', 'Label' => $a['label'], 'Amount' => $a['amount']];
        }
        $rows[] = ['Classification' => 'Asset', 'Label' => 'TOTAL ASSETS', 'Amount' => $data['totals']['assets']];
        
        foreach ($data['liabilities_equity'] as $le) {
            $rows[] = ['Classification' => 'Liability/Equity', 'Label' => $le['label'], 'Amount' => $le['amount']];
        }
        $rows[] = ['Classification' => 'Liability/Equity', 'Label' => 'TOTAL LIABILITIES & EQUITY', 'Amount' => $data['totals']['liability']];

        return \USMS\Services\FinancialExportEngine::export('excel', $rows, [
            'title' => 'Financial Report',
            'module' => 'Balance Sheet',
            'headers' => ['Classification', 'Label', 'Amount']
        ]);
    }

    // ─── Internal Query Helpers ───────────────────────────────────────────────

    private function getLedgerBalByCategory(string $category, string $endDate): float {
        $sql = "SELECT SUM(le.debit - le.credit) as bal 
                FROM ledger_entries le 
                JOIN ledger_accounts la ON le.account_id = la.account_id 
                WHERE la.category = ? AND DATE(le.created_at) <= ?";
        $st = $this->db->prepare($sql);
        $st->execute([$category, $endDate]);
        return (float)($st->fetchColumn() ?? 0);
    }

    private function getLedgerBalByName(string $name, string $endDate): float {
        $sql = "SELECT SUM(le.debit - le.credit) as bal 
                FROM ledger_entries le 
                JOIN ledger_accounts la ON le.account_id = la.account_id 
                WHERE la.account_name = ? AND DATE(le.created_at) <= ?";
        $st = $this->db->prepare($sql);
        $st->execute([$name, $endDate]);
        return (float)($st->fetchColumn() ?? 0);
    }

    // ─── Core Reports ─────────────────────────────────────────────────────────

    /**
     * Calculates the dynamic Net Asset Value (NAV) per share
     */
    public function getShareValuation(): array {
        $endDate = date('Y-m-d');

        $loans       = $this->getLedgerBalByCategory('loans', $endDate);
        $cash        = $this->getLedgerBalByName('Cash at Hand', $endDate)
                     + $this->getLedgerBalByName('M-Pesa Float', $endDate)
                     + $this->getLedgerBalByName('Bank Account', $endDate)
                     + $this->getLedgerBalByName('Paystack Clearing Account', $endDate);
        $investments = (float)($this->db->query("SELECT COALESCE(SUM(current_value),0) FROM investments WHERE status = 'active'")->fetchColumn());
        $totalAssets = $loans + $cash + $investments;

        $savings     = -$this->getLedgerBalByCategory('savings', $endDate);
        $welfare     = -$this->getLedgerBalByCategory('welfare', $endDate);
        $liabilities = $savings + $welfare;
        $netEquity   = $totalAssets - $liabilities;

        $totalUnits  = (float)($this->db->query("SELECT COALESCE(SUM(units_owned),0) FROM member_shareholdings")->fetchColumn());
        $price       = ($totalUnits > 0) ? ($netEquity / $totalUnits) : 100.00;
        if ($price < 1.0) $price = 100.00;

        return [
            'price'        => round($price, 2),
            'equity'       => $netEquity,
            'total_units'  => $totalUnits,
            'total_assets' => $totalAssets,
            'liabilities'  => $liabilities
        ];
    }

    /**
     * Generates a Statement of Financial Position dataset
     */
    public function getBalanceSheetData(string $startDate, string $endDate): array {
        $data = [
            'assets'             => [],
            'liabilities_equity' => [],
            'totals'             => ['assets' => 0.0, 'liability' => 0.0]
        ];

        // Assets
        $loanPrincipal  = $this->getLedgerBalByCategory('loans', $endDate);
        $data['assets'][] = ['label' => 'Loans Receivable (Principal)', 'amount' => $loanPrincipal];

        $cash = $this->getLedgerBalByName('Cash at Hand', $endDate)
              + $this->getLedgerBalByName('M-Pesa Float', $endDate)
              + $this->getLedgerBalByName('Bank Account', $endDate)
              + $this->getLedgerBalByName('Paystack Clearing Account', $endDate);
        $data['assets'][] = ['label' => 'Cash & Bank Balances', 'amount' => $cash];

        $investments = (float)($this->db->query("SELECT COALESCE(SUM(current_value),0) FROM investments WHERE status = 'active'")->fetchColumn());
        $data['assets'][] = ['label' => 'Strategic Investment Portfolio', 'amount' => $investments];

        $data['totals']['assets'] = $loanPrincipal + $cash + $investments;

        // Liabilities & Equity
        $savings = -$this->getLedgerBalByCategory('savings', $endDate);
        $welfare = -$this->getLedgerBalByCategory('welfare', $endDate);
        $shares  = -$this->getLedgerBalByCategory('shares', $endDate);

        $data['liabilities_equity'][] = ['label' => 'Member Savings',    'amount' => $savings];
        $data['liabilities_equity'][] = ['label' => 'Benevolent Fund',   'amount' => $welfare];
        $data['liabilities_equity'][] = ['label' => 'Share Capital',     'amount' => $shares];

        $revenue  = -$this->getLedgerBalByName('SACCO Revenue', $endDate);
        $expenses =  $this->getLedgerBalByName('SACCO Expenses', $endDate);
        $earnings = $revenue - $expenses;
        $data['liabilities_equity'][] = ['label' => 'Retained Earnings (Surplus)', 'amount' => $earnings];

        $data['totals']['liability'] = $savings + $welfare + $shares + $earnings;

        return $data;
    }

    /**
     * Generates member statement dataset for a given period
     */
    public function getMemberStatementData(int $member_id, ?string $start_date = null, ?string $end_date = null): array {
        $query  = "SELECT * FROM transactions WHERE member_id = ?";
        $params = [$member_id];

        if ($start_date) { $query .= " AND created_at >= ?"; $params[] = $start_date; }
        if ($end_date)   { $query .= " AND created_at <= ?"; $params[] = $end_date;   }

        $query .= " ORDER BY created_at ASC, transaction_id ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        $ledger      = [];
        $running_bal = 0.0;
        while ($t = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $is_inflow   = in_array($t['transaction_type'], ['deposit', 'income', 'repayment', 'refund']);
            $amt         = (float)$t['amount'];
            $running_bal += $is_inflow ? $amt : -$amt;
            $t['running_balance'] = $running_bal;
            $t['direction']       = $is_inflow ? 'IN' : 'OUT';
            $ledger[]             = $t;
        }

        return $ledger;
    }

    /**
     * Generates a member statement PDF (Backward compatibility bridge)
     */
    public function generateMemberStatement(array $memberData, array $transactions): \USMS\Reports\PdfTemplate {
        $pdf = new \USMS\Reports\PdfTemplate('P');
        $pdf->setMetadata('Certified Member Statement', 'Reporting', [
            'Member' => $memberData['full_name'],
            'Member No' => $memberData['member_no'] ?? 'N/A'
        ]);
        $pdf->AddPage();
        
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Umoja Sacco Certified Statement', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, 'Generated on: ' . date('d M, Y H:i'), 0, 1, 'C');
        $pdf->Ln(5);

        // Summary
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 8, 'ACCOUNT SUMMARY', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(50, 7, 'Savings Balance:', 0, 0); $pdf->Cell(0, 7, 'KES ' . number_format($memberData['total_savings'], 2), 0, 1);
        $pdf->Cell(50, 7, 'Share Capital:', 0, 0);   $pdf->Cell(0, 7, 'KES ' . number_format($memberData['total_shares'], 2), 0, 1);
        $pdf->Cell(50, 7, 'Outstanding Loans:', 0, 0); $pdf->Cell(0, 7, 'KES ' . number_format($memberData['loan_debt'], 2), 0, 1);
        $pdf->Ln(5);

        // Transaction Table
        $headers = ['Date', 'Type', 'Amount', 'Reference'];
        $pdf->UniversalTable($headers, array_map(fn($t) => [
            $t['transaction_date'],
            ucfirst($t['transaction_type']),
            number_format($t['amount'], 2),
            $t['reference_no']
        ], $transactions));

        return $pdf;
    }
}
