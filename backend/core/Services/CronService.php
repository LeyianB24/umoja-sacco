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
        $this->db = Database::getInstance()->getPdo();
        $this->txService = new TransactionService();
        $this->settingsService = new SettingsService();
        $this->emailService = new EmailQueueService();
    }

    /**
     * Identifies overdue loans and applies daily fines
     */
    public function applyDailyFines(): int {
        $today = date('Y-m-d');

        // Logic: Active/Disbursed loans where next_repayment_date is in the past
        // and a fine hasn't already been applied today for this loan.
        $sql = "SELECT l.loan_id, l.member_id, l.next_repayment_date, l.current_balance, l.amount 
                FROM loans l
                WHERE l.status IN ('active', 'disbursed') 
                AND DATE(l.next_repayment_date) < ? 
                AND NOT EXISTS (
                    SELECT 1 FROM fines f 
                    WHERE f.loan_id = l.loan_id AND f.date_applied = ?
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$today, $today]);
        
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processedCount = 0;
        foreach ($loans as $loan) {
            // Calculate 0.05% fine based on original loan amount
            $fineAmount = round((float)$loan['amount'] * 0.0005, 2);
            if ($fineAmount < 1.0) $fineAmount = 1.0; // Minimum fine floor

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
                    'method'        => 'cash'
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
                $stmtMember = $this->db->prepare("SELECT email, full_name FROM members WHERE member_id = ?");
                $stmtMember->execute([$loan['member_id']]);
                $member = $stmtMember->fetch(PDO::FETCH_ASSOC);

                if ($member && $member['email']) {
                    $subject = "Late Payment Penalty Applied - Loan #{$loan['loan_id']}";
                    $body = "<p>Dear {$member['full_name']},</p>
                             <p>A daily late payment penalty of <b>KES " . number_format($fineAmount, 2) . "</b> has been applied to your loan account (#{$loan['loan_id']}) because your repayment was due on {$loan['next_repayment_date']}.</p>
                             <p>Your current outstanding balance is <b>KES " . number_format((float)$loan['current_balance'] + $fineAmount, 2) . "</b>.</p>
                             <p>Please make your payment promptly to avoid further penalties.</p>
                             <p>Thank you for choosing Umoja Drivers Sacco.</p>";
                    
                    $this->emailService->queueEmail($member['email'], $member['full_name'], $subject, $body);
                }
            } catch (Exception $e) {
                if ($transactionStarted && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                error_log("Failed to apply fine to loan #{$loan['loan_id']}: " . $e->getMessage());
            }
        }
        
        // Process email queue if fines were applied
        if ($processedCount > 0) {
            $this->emailService->processPendingEmails(50);
        }
        
        return $processedCount;
    }

    /**
     * Sends reminders for repayments due in exactly 3 days
     */
    public function sendRepaymentReminders(): int {
        $targetDate = date('Y-m-d', strtotime('+3 days'));
        
        $sql = "SELECT l.loan_id, l.member_id, l.next_repayment_date, l.current_balance, m.full_name, m.email 
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
            $body = "<p>Dear {$loan['full_name']},</p>
                     <p>This is a friendly reminder that your loan repayment (#{$loan['loan_id']}) is due on <b>{$loan['next_repayment_date']}</b>.</p>
                     <p>Current outstanding balance: <b>KES " . number_format((float)$loan['current_balance'], 2) . "</b>.</p>
                     <p>Please ensure you have sufficient funds to avoid late payment penalties.</p>
                     <p>Thank you for being a valued member of Umoja Drivers Sacco.</p>";
            
            $this->emailService->queueEmail($loan['email'], $loan['full_name'], $subject, $body);
            $sentCount++;
        }

        // Process the queue immediately since XAMPP often lacks background cron
        if ($sentCount > 0) {
            $this->emailService->processPendingEmails(50);
        }

        return $sentCount;
    }

    /**
     * Sends manual late payment reminders to ALL overdue members at once.
     */
    public function sendBulkLateReminders(): int {
        $sql = "SELECT l.loan_id, l.member_id, l.next_repayment_date, l.current_balance, m.full_name, m.email 
                FROM loans l
                JOIN members m ON l.member_id = m.member_id
                WHERE l.status IN ('active', 'disbursed') 
                AND DATE(l.next_repayment_date) < CURDATE()
                AND m.email IS NOT NULL";
        
        $stmt = $this->db->query($sql);
        $overdueLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sentCount = 0;
        foreach ($overdueLoans as $loan) {
            $subject = "Urgent: Late Repayment Reminder - Loan #{$loan['loan_id']}";
            $body = "<p>Dear <b>{$loan['full_name']}</b>,</p>
                     <p>This is a formal reminder regarding your outstanding loan <b>#{$loan['loan_id']}</b>.</p>
                     <p>Our records show your repayment was due on <b>" . date('d M Y', strtotime($loan['next_repayment_date'])) . "</b> and is currently overdue.</p>
                     <p><b>Outstanding Balance:</b> KES " . number_format((float)$loan['current_balance'], 2) . "</p>
                     <p>Please settle the outstanding amount immediately to stop further daily late fines from accumulating.</p>
                     <p>You can pay via the Member Portal or M-Pesa Paybill.</p>
                     <p>Best regards,<br>Umoja Drivers Sacco Management</p>";
            
            if ($this->emailService->queueEmail($loan['email'], $loan['full_name'], $subject, $body) > 0) {
                $sentCount++;
            }
        }
        
        // Process the queue immediately since XAMPP often lacks background cron
        if ($sentCount > 0) {
            $this->emailService->processPendingEmails(50);
        }

        return $sentCount;
    }

    /**
     * Sends a manual late payment reminder to a specific member
     */
    public function sendManualLateReminder(int $loan_id): bool {
        $sql = "SELECT l.loan_id, l.member_id, l.next_repayment_date, l.current_balance, m.full_name, m.email 
                FROM loans l
                JOIN members m ON l.member_id = m.member_id
                WHERE l.loan_id = ? AND m.email IS NOT NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$loan_id]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$loan) return false;

        $subject = "Urgent: Late Repayment Reminder - Loan #{$loan['loan_id']}";
        $body = "<p>Dear <b>{$loan['full_name']}</b>,</p>
                 <p>This is a formal reminder regarding your outstanding loan <b>#{$loan['loan_id']}</b>.</p>
                 <p>Our records show your repayment was due on <b>" . date('d M Y', strtotime($loan['next_repayment_date'])) . "</b> and is currently overdue.</p>
                 <p><b>Outstanding Balance:</b> KES " . number_format((float)$loan['current_balance'], 2) . "</p>
                 <p>Please settle the outstanding amount immediately to stop further daily late fines from accumulating.</p>
                 <p>You can pay via the Member Portal or M-Pesa Paybill.</p>
                 <p>If you have already made the payment, please ignore this email.</p>
                 <p>Best regards,<br>Umoja Drivers Sacco Management</p>";
        $queued = $this->emailService->queueEmail($loan['email'], $loan['full_name'], $subject, $body) > 0;
        if ($queued) {
            $this->emailService->processPendingEmails(10);
        }
        return $queued;
    }
}
