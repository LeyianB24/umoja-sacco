<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use USMS\Services\TransactionService;
use Exception;
use PDO;

/**
 * USMS\Services\RegistrationService
 * ACID-Compliant Registration Fee Handler
 */
class RegistrationService {
    
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }

    /**
     * Marks a member as paid and records the transaction in the General Ledger.
     */
    public function markAsPaid(int $member_id, float $amount, string $ref_no): bool {
        $this->db->beginTransaction();
        
        try {
            // 1. Update Member Table
            $sqlMem = "UPDATE members SET registration_fee_status = 'paid', status = 'active' WHERE member_id = ?";
            $stmtMem = $this->db->prepare($sqlMem);
            if (!$stmtMem->execute([$member_id])) throw new Exception("Failed to update member status.");

            // 2. Record in Ledger via TransactionService
            $txService = new TransactionService();
            $recordSuccess = $txService->record([
                'type' => 'income',
                'amount' => $amount,
                'member_id' => $member_id,
                'related_table' => 'registration_fee',
                'ref_no' => $ref_no,
                'notes' => 'One-time Registration Fee Payment',
                'update_member_balance' => false,
                'method' => 'cash'
            ]);

            if (!$recordSuccess) {
                throw new Exception("TransactionService failed to record entry.");
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Registration Payment Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Static gateway for convenience
     */
    public static function quickMarkPaid(int $member_id, float $amount, string $ref_no): bool {
        return (new self())->markAsPaid($member_id, $amount, $ref_no);
    }
}
