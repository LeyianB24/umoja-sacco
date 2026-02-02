<?php
// usms/inc/StatementHelper.php
// Enterprise Reporting: Running Balance Ledger Logic - V4

class StatementHelper {
    
    private $db;

    public function __construct($conn) {
        $this->db = $conn;
    }

    /**
     * Fetches all transactions for a member and calculates a running balance.
     */
    public function getRunningBalanceLedger($member_id, $start_date = null, $end_date = null) {
        $query = "SELECT * FROM transactions WHERE member_id = ? ";
        $params = [$member_id];
        $types = "i";

        if ($start_date) {
            $query .= "AND created_at >= ? ";
            $params[] = $start_date;
            $types .= "s";
        }
        if ($end_date) {
            $query .= "AND created_at <= ? ";
            $params[] = $end_date;
            $types .= "s";
        }

        $query .= "ORDER BY created_at ASC, transaction_id ASC";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $ledger = [];
        $running_bal = 0;

        while ($t = $res->fetch_assoc()) {
            // Determine if inflow (+) or outflow (-)
            // Types mapping (Context dependent)
            $is_inflow = in_array($t['transaction_type'], ['deposit', 'income', 'repayment', 'refund']);
            
            $amt = $t['amount'];
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
    public function formatStatementTable($ledger) {
        $html = '<table class="table table-bordered table-striped">';
        $html .= '<thead><tr><th>Date</th><th>Description</th><th>Ref</th><th>In</th><th>Out</th><th>Balance</th></tr></thead><tbody>';
        
        foreach ($ledger as $t) {
            $in  = $t['direction'] === 'IN' ? number_format($t['amount'], 2) : '-';
            $out = $t['direction'] === 'OUT' ? number_format($t['amount'], 2) : '-';
            $bal = number_format($t['running_balance'], 2);
            $date = date('d-M-Y', strtotime($t['created_at']));
            $desc = htmlspecialchars($t['notes'] ?? strtoupper($t['related_table']));

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
