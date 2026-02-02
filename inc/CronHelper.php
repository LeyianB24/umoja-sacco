<?php
// usms/inc/CronHelper.php
require_once __DIR__ . '/TransactionHelper.php';
require_once __DIR__ . '/SettingsHelper.php';

class CronHelper {
    private $db;
    private $txHelper;

    public function __construct($conn) {
        $this->db = $conn;
        $this->txHelper = new TransactionHelper($conn);
    }

    /**
     * Identifies overdue loans and applies daily fines
     */
    public function applyDailyFines() {
        $fineAmount = (float)SettingsHelper::get('late_payment_fine_daily', 50.00);
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
        $stmt->bind_param("ss", $today, $today);
        $stmt->execute();
        $overdueLoans = $stmt->get_result();

        $processedCount = 0;
        while ($loan = $overdueLoans->fetch_assoc()) {
            $this->db->begin_transaction();
            try {
                // 1. Record in fines table
                $stmtFine = $this->db->prepare("INSERT INTO fines (loan_id, amount, date_applied) VALUES (?, ?, ?)");
                $stmtFine->bind_param("ids", $loan['loan_id'], $fineAmount, $today);
                $stmtFine->execute();

                // 2. Record ledger entry
                $ref = "FINE-" . $loan['loan_id'] . "-" . date('Ymd');
                $ok = $this->txHelper->recordDoubleEntry([
                    'member_id'     => $loan['member_id'],
                    'amount'        => $fineAmount,
                    'type'          => 'fine',
                    'ref_no'        => $ref,
                    'notes'         => "Daily late fine for Loan #{$loan['loan_id']}",
                    'related_id'    => $loan['loan_id'],
                    'related_table' => 'loans',
                    'update_member_balance' => false, // Fines increase loan balance, not necessarily member's wallet
                    'use_external_txn' => true
                ]);

                if (!$ok) throw new Exception("Ledger entry failed");

                // 3. Update loan balance
                $stmtUpdate = $this->db->prepare("UPDATE loans SET current_balance = current_balance + ? WHERE loan_id = ?");
                $stmtUpdate->bind_param("di", $fineAmount, $loan['loan_id']);
                $stmtUpdate->execute();

                $this->db->commit();
                $processedCount++;
            } catch (Exception $e) {
                $this->db->rollback();
                error_log("Failed to apply fine to loan #{$loan['loan_id']}: " . $e->getMessage());
            }
        }

        return $processedCount;
    }
}
