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

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
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

        $totalUnits  = (float)($this->db->query("SELECT COALESCE(SUM(share_units),0) FROM shares")->fetchColumn());
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
}
