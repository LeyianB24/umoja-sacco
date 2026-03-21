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
    private EmailQueueService $emailService;

    public function __construct() {
        echo "LOG: CronService constructor called\n";
        $this->db = Database::getInstance()->getPdo();
        $this->txService = new TransactionService();
        $this->settingsService = new SettingsService();
        $this->emailService = new EmailQueueService();
    }

    /**
     * Identifies overdue loans and applies daily fines
     */
    public function applyDailyFines(): int {
        file_put_contents('c:/xampp/htdocs/usms/debug_cron.log', "[" . date('Y-m-d H:i:s') . "] LOG: Called applyDailyFines\n", FILE_APPEND);
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
        
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        file_put_contents('c:/xampp/htdocs/usms/debug_cron.log', "[" . date('Y-m-d H:i:s') . "] LOG: Found " . count($loans) . " loans to process\n", FILE_APPEND);
        
        $processedCount = 0;
        foreach ($loans as $loan) {
            file_put_contents('c:/xampp/htdocs/usms/debug_cron.log', "[" . date('Y-m-d H:i:s') . "] LOG: Processing Loan #{$loan['loan_id']}\n", FILE_APPEND);
            
            $transactionStarted = false;
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $transactionStarted = true;
            }

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

                if ($transactionStarted && $this->db->inTransaction()) {
                    $this->db->commit();
                }
                $processedCount++;

                // 4. Send Email Notification
                $stmtMember = $this->db->prepare("SELECT first_name, email FROM members WHERE member_id = ?");
                $stmtMember->execute([$loan['member_id']]);
                $member = $stmtMember->fetch(PDO::FETCH_ASSOC);

                if ($member && $member['email']) {
                    $subject = "Late Payment Penalty Applied - Loan #{$loan['loan_id']}";
                    $body = "<p>Dear {$member['first_name']},</p>
                             <p>A daily late payment penalty of <b>KES " . number_format($fineAmount, 2) . "</b> has been applied to your loan account (#{$loan['loan_id']}) because your repayment was due on {$loan['next_repayment_date']}.</p>
                             <p>Your current outstanding balance is <b>KES " . number_format((float)$loan['current_balance'] + $fineAmount, 2) . "</b>.</p>
                             <p>Please make your payment promptly to avoid further penalties.</p>
                             <p>Thank you for choosing Umoja Drivers Sacco.</p>";
                    
                    $this->emailService->queueEmail($member['email'], $member['first_name'], $subject, $body);
                }
            } catch (Exception $e) {
                if ($transactionStarted && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                error_log("Failed to apply fine to loan #{$loan['loan_id']}: " . $e->getMessage());
                file_put_contents('c:/xampp/htdocs/usms/debug_cron.log', "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
        return $processedCount;
    }

    /**
     * Sends reminders for repayments due in exactly 3 days
     */
    public function sendRepaymentReminders(): int {
        $targetDate = date('Y-m-d', strtotime('+3 days'));
        
        $sql = "SELECT l.loan_id, l.member_id, l.next_repayment_date, l.current_balance, m.first_name, m.email 
                FROM loans l
                JOIN members m ON l.member_id = m.member_id
                WHERE l.status IN ('active', 'disbursed') 
                AND DATE(l.next_repayment_date) = ?
                AND m.email IS NOT NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$targetDate]);
        
        $sentCount = 0;
        while ($loan = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $subject = "Repayment Reminder - Loan #{$loan['loan_id']}";
            $body = "<p>Dear {$loan['first_name']},</p>
                     <p>This is a friendly reminder that your loan repayment (#{$loan['loan_id']}) is due on <b>{$loan['next_repayment_date']}</b>.</p>
                     <p>Current outstanding balance: <b>KES " . number_format((float)$loan['current_balance'], 2) . "</b>.</p>
                     <p>Please ensure you have sufficient funds to avoid late payment penalties.</p>
                     <p>Thank you for being a valued member of Umoja Drivers Sacco.</p>";
            
            $this->emailService->queueEmail($loan['email'], $loan['first_name'], $subject, $body);
            $sentCount++;
        }

        return $sentCount;
    }
}
