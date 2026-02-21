<?php
declare(strict_types=1);
// usms/inc/LoanHelper.php
// Enterprise Loan Logic Engine - V4

class LoanHelper {
    
    private $db;

    public function __construct($conn) {
        $this->db = $conn;
    }

    /**
     * Calculates the "Free Shares" available for a member to use as a guarantor.
     * Formula: Total Shares - Sum of active guarantees
     */
    public function getFreeShares($member_id) {
        // 1. Get Total Share Capital (Via Golden Ledger)
        $sqlShares = "SELECT SUM(current_balance) as total FROM ledger_accounts WHERE member_id = ? AND category = 'shares'";
        $stmt = $this->db->prepare($sqlShares);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $total_shares = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();

        // 2. Get Currently Committed Guarantees
        // Filter by loans that are NOT yet fully paid or defaulted
        $sqlCommitted = "SELECT SUM(guaranteed_amount) as committed 
                         FROM loan_guarantors lg
                         JOIN loans l ON lg.loan_id = l.loan_id
                         WHERE lg.member_id = ? 
                         AND lg.status IN ('accepted', 'pending')
                         AND l.status IN ('pending', 'approved', 'disbursed')";
        $stmt = $this->db->prepare($sqlCommitted);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $committed = $stmt->get_result()->fetch_assoc()['committed'] ?? 0;
        $stmt->close();

        return max(0, $total_shares - $committed);
    }

    /**
     * Validates if a list of guarantors is sufficient for a loan amount.
     * $guarantors format: [['member_id' => 1, 'amount' => 5000], ...]
     */
    public function validateGuarantors($loan_amount, $guarantors, $min_percentage = 100) {
        $total_guaranteed = 0;
        $errors = [];

        foreach ($guarantors as $g) {
            $free = $this->getFreeShares($g['member_id']);
            if ($g['amount'] > $free) {
                $errors[] = "Guarantor ID #{$g['member_id']} exceeds their free shares limit (Available: KES " . number_format($free) . ")";
            }
            $total_guaranteed += $g['amount'];
        }

        $required = ($min_percentage / 100) * $loan_amount;
        if ($total_guaranteed < $required) {
            $errors[] = "Insufficient guarantees. Required: KES " . number_format($required) . ", Provided: KES " . number_format($total_guaranteed);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'total_guaranteed' => $total_guaranteed,
            'coverage_percent' => ($total_guaranteed / $loan_amount) * 100
        ];
    }

    /**
     * Records guarantor requests for a new loan.
     */
    public function attachGuarantors($loan_id, $guarantors) {
        $stmt = $this->db->prepare("INSERT INTO loan_guarantors (loan_id, member_id, guaranteed_amount) VALUES (?, ?, ?)");
        foreach ($guarantors as $g) {
            $stmt->bind_param("iid", $loan_id, $g['member_id'], $g['amount']);
            $stmt->execute();
        }
        $stmt->close();
        return true;
    }
}
