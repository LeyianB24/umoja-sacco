<?php
declare(strict_types=1);
/**
 * Financial Integrity Checker
 * Validates consistency between contributions, ledger entries, and accounts
 */

class FinancialIntegrityChecker {
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    /**
     * Check if contributions match ledger entries
     */
    public function checkContributionLedgerSync($member_id = null) {
        $where = $member_id ? "WHERE c.member_id = " . (int)$member_id : "";
        
        $sql = "
            SELECT c.contribution_id, c.amount, c.reference_no, 
                   (SELECT SUM(le.credit - le.debit) 
                    FROM ledger_entries le 
                    JOIN ledger_transactions lt ON le.transaction_id = lt.transaction_id
                    WHERE lt.reference_no = c.reference_no) as ledger_amount
            FROM contributions c
            $where
            HAVING amount != IFNULL(ledger_amount, 0)
        ";
        
        $result = $this->conn->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Check if ledger accounts match entry sums
     */
    public function checkAccountBalanceConsistency($member_id = null) {
        $where = $member_id ? "WHERE la.member_id = " . (int)$member_id : "";
        
        $sql = "
            SELECT la.account_id, la.account_name, la.current_balance, 
                   (SELECT SUM(credit - debit) FROM ledger_entries WHERE account_id = la.account_id) as entry_sum
            FROM ledger_accounts la
            $where
            HAVING current_balance != IFNULL(entry_sum, 0)
        ";
        
        $result = $this->conn->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Detect double postings (same ref, same amount, same account)
     */
    public function detectDoublePostings() {
        $sql = "
            SELECT lt.reference_no, le.account_id, le.credit, le.debit, COUNT(*) as count
            FROM ledger_entries le
            JOIN ledger_transactions lt ON le.transaction_id = lt.transaction_id
            WHERE lt.reference_no IS NOT NULL AND lt.reference_no != ''
            GROUP BY lt.reference_no, le.account_id, le.credit, le.debit
            HAVING count > 1
        ";
        
        $result = $this->conn->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Record a health check result
     */
    public function logCheck($type, $status, $details) {
        $stmt = $this->conn->prepare("
            INSERT INTO integrity_checks (check_type, status, details)
            VALUES (?, ?, ?)
        ");
        $details_json = json_encode($details);
        $stmt->bind_param("sss", $type, $status, $details_json);
        return $stmt->execute();
    }
    
    /**
     * Run all checks and log them
     */
    public function runFullAudit() {
        $results = [];
        
        // 1. Sync check
        $sync_issues = $this->checkContributionLedgerSync();
        $results['sync'] = [
            'status' => empty($sync_issues) ? 'passed' : 'failed',
            'data' => $sync_issues
        ];
        $this->logCheck('contribution_ledger_sync', $results['sync']['status'], $sync_issues);
        
        // 2. Balance check
        $balance_issues = $this->checkAccountBalanceConsistency();
        $results['balance'] = [
            'status' => empty($balance_issues) ? 'passed' : 'failed',
            'data' => $balance_issues
        ];
        $this->logCheck('account_balance_consistency', $results['balance']['status'], $balance_issues);
        
        // 3. Double posting check
        $double_postings = $this->detectDoublePostings();
        $results['double_posting'] = [
            'status' => empty($double_postings) ? 'passed' : 'failed',
            'data' => $double_postings
        ];
        $this->logCheck('double_posting_detection', $results['double_posting']['status'], $double_postings);
        
        return $results;
    }
}
