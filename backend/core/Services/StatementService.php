<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use PDO;

/**
 * USMS\Services\StatementService
 * Enterprise Reporting: Running Balance Ledger Logic - V4
 */
class StatementService {
    
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }

    /**
     * Fetches all transactions for a member and calculates a running balance.
     */
    public function getRunningBalanceLedger(int $member_id, ?string $start_date = null, ?string $end_date = null): array {
        $query = "SELECT * FROM transactions WHERE member_id = ? ";
        $params = [$member_id];

        if ($start_date) {
            $query .= "AND created_at >= ? ";
            $params[] = $start_date;
        }
        if ($end_date) {
            $query .= "AND created_at <= ? ";
            $params[] = $end_date;
        }

        $query .= "ORDER BY created_at ASC, transaction_id ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        $ledger = [];
        $running_bal = 0.0;

        while ($t = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Determine if inflow (+) or outflow (-)
            $is_inflow = in_array($t['transaction_type'], ['deposit', 'income', 'repayment', 'refund']);
            
            $amt = (float)$t['amount'];
            if ($is_inflow) {
                $running_bal += $amt;
            } else {
                $running_bal -= $amt;
            }

            $t['running_balance'] = $running_bal;
            $t['direction'] = $is_inflow ? 'IN' : 'OUT';
            $ledger[] = $t;
        }

        return $ledger;
    }

    /**
     * Simplistic HTML Formatter for testing results
     */
    public function formatStatementTable(array $ledger): string {
        $html = '<table class="table table-bordered table-striped">';
        $html .= '<thead><tr><th>Date</th><th>Description</th><th>Ref</th><th>In</th><th>Out</th><th>Balance</th></tr></thead><tbody>';
        
        foreach ($ledger as $t) {
            $in  = $t['direction'] === 'IN' ? number_format((float)$t['amount'], 2) : '-';
            $out = $t['direction'] === 'OUT' ? number_format((float)$t['amount'], 2) : '-';
            $bal = number_format((float)$t['running_balance'], 2);
            $date = date('d-M-Y', strtotime($t['created_at']));
            $desc = htmlspecialchars($t['notes'] ?? strtoupper((string)$t['related_table']));

            $html .= "<tr>
                        <td>$date</td>
                        <td>$desc</td>
                        <td><small>{$t['reference_no']}</small></td>
                        <td class='text-success'>$in</td>
                        <td class='text-danger'>$out</td>
                        <td class='fw-bold'>$bal</td>
                      </tr>";
        }

        $html .= '</tbody></table>';
        return $html;
    }
}
