<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use PDO;

/**
 * USMS\Services\LoanService
 * Enterprise Loan Logic Engine - V4
 * Handles guarantor validation, loan limits, and status transitions.
 */
class LoanService {
    
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }

    /**
     * Calculates the "Free Shares" available for a member to use as a guarantor.
     * Formula: Total Shares - Sum of active guarantees
     */
    public function getFreeShares(int $member_id): float {
        // 1. Get Total Share Capital (Via Golden Ledger)
        $stmt = $this->db->prepare("SELECT SUM(current_balance) as total FROM ledger_accounts WHERE member_id = ? AND category = 'shares'");
        $stmt->execute([$member_id]);
        $total_shares = (float)($stmt->fetch()['total'] ?? 0);

        // 2. Get Currently Committed Guarantees
        $sqlCommitted = "SELECT SUM(guaranteed_amount) as committed 
                         FROM loan_guarantors lg
                         JOIN loans l ON lg.loan_id = l.loan_id
                         WHERE lg.member_id = ? 
                         AND lg.status IN ('accepted', 'pending')
                         AND l.status IN ('pending', 'approved', 'disbursed')";
        $stmt = $this->db->prepare($sqlCommitted);
        $stmt->execute([$member_id]);
        $committed = (float)($stmt->fetch()['committed'] ?? 0);

        return max(0, $total_shares - $committed);
    }

    /**
     * Validates if a list of guarantors is sufficient for a loan amount.
     * $guarantors format: [['member_id' => 1, 'amount' => 5000], ...]
     */
    public function validateGuarantors(float $loan_amount, array $guarantors, float $min_percentage = 100): array {
        $total_guaranteed = 0.0;
        $errors = [];

        foreach ($guarantors as $g) {
            $free = $this->getFreeShares((int)$g['member_id']);
            if ((float)$g['amount'] > $free) {
                $errors[] = "Guarantor ID #{$g['member_id']} exceeds their free shares limit (Available: KES " . number_format($free) . ")";
            }
            $total_guaranteed += (float)$g['amount'];
        }

        $required = ($min_percentage / 100) * $loan_amount;
        if ($total_guaranteed < $required) {
            $errors[] = "Insufficient guarantees. Required: KES " . number_format($required) . ", Provided: KES " . number_format($total_guaranteed);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'total_guaranteed' => $total_guaranteed,
            'coverage_percent' => $loan_amount > 0 ? ($total_guaranteed / $loan_amount) * 100 : 100
        ];
    }

    /**
     * Records guarantor requests for a new loan.
     */
    public function attachGuarantors(int $loan_id, array $guarantors): bool {
        $stmt = $this->db->prepare("INSERT INTO loan_guarantors (loan_id, member_id, guaranteed_amount) VALUES (?, ?, ?)");
        foreach ($guarantors as $g) {
            $stmt->execute([$loan_id, (int)$g['member_id'], (float)$g['amount']]);
        }
        return true;
    }
}
