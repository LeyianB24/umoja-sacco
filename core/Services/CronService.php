<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use USMS\Services\TransactionService;
use USMS\Services\SettingsService;
use Exception;
use PDO;

/**
 * USMS\Services\CronService
 * System Scheduling and Automated Maintenance Service.
 */
class CronService {
    private PDO $db;
    private TransactionService $txService;
    private SettingsService $settingsService;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
        $this->txService = new TransactionService();
        $this->settingsService = new SettingsService();
    }

    /**
     * Identifies overdue loans and applies daily fines
     */
    public function applyDailyFines(): int {
        $fineAmount = (float)$this->settingsService->get('late_payment_fine_daily', 50.00);
        $today = date('Y-m-d');

        // Logic: Active/Disbursed loans where next_repayment_date is in the past
        // and a fine hasn't already been applied today for this loan.
        $sql = "SELECT l.loan_id, l.member_id, l.next_repayment_date, l.current_balance 
                FROM loans l
                WHERE l.status IN ('active', 'disbursed') 
                AND l.next_repayment_date < ? 
                AND NOT EXISTS (
                    SELECT 1 FROM fines f 
                    WHERE f.loan_id = l.loan_id AND f.date_applied = ?
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$today, $today]);
        
        $processedCount = 0;
        while ($loan = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->db->beginTransaction();
            try {
                // 1. Record in fines table
                $stmtFine = $this->db->prepare("INSERT INTO fines (loan_id, amount, date_applied) VALUES (?, ?, ?)");
                $stmtFine->execute([$loan['loan_id'], $fineAmount, $today]);

                // 2. Record ledger entry
                $ref = "FINE-" . $loan['loan_id'] . "-" . date('Ymd');
                $ok = $this->txService->record([
                    'member_id'     => (int)$loan['member_id'],
                    'amount'        => $fineAmount,
                    'type'          => 'fine',
                    'ref_no'        => $ref,
                    'notes'         => "Daily late fine for Loan #{$loan['loan_id']}",
                    'related_id'    => (int)$loan['loan_id'],
                    'related_table' => 'loans',
                    'update_member_balance' => false,
                    'method'        => 'system'
                ]);

                if (!$ok) throw new Exception("Ledger entry failed");

                // 3. Update loan balance
                $stmtUpdate = $this->db->prepare("UPDATE loans SET current_balance = current_balance + ? WHERE loan_id = ?");
                $stmtUpdate->execute([$fineAmount, $loan['loan_id']]);

                $this->db->commit();
                $processedCount++;
            } catch (Exception $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                error_log("Failed to apply fine to loan #{$loan['loan_id']}: " . $e->getMessage());
            }
        }

        return $processedCount;
    }
}
