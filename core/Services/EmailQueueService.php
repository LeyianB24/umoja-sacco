<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PDO;

/**
 * USMS\Services\EmailQueueService
 * Production-grade email delivery with queue, retry, and tracking.
 */
class EmailQueueService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }

    /**
     * Queue an email for later delivery.
     */
    public function queueEmail(
        string $recipient_email,
        string $recipient_name,
        string $subject,
        string $body,
        int    $priority = 5
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO email_queue (recipient_email, recipient_name, subject, body, priority, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$recipient_email, $recipient_name, $subject, $body, $priority]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Process a batch of pending emails from the queue.
     */
    public function processPendingEmails(int $batch_size = 10): array {
        $stmt = $this->db->prepare("
            SELECT queue_id, recipient_email, recipient_name, subject, body, attempts, max_attempts
            FROM email_queue
            WHERE status = 'pending'
              AND attempts < max_attempts
              AND (scheduled_for IS NULL OR scheduled_for <= NOW())
            ORDER BY priority ASC, created_at ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $batch_size, PDO::PARAM_INT);
        $stmt->execute();

        $sentCount = 0;
        $failedCount = 0;

        while ($email = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $queue_id = (int)$email['queue_id'];
            $result = $this->sendSingle($email);

            if ($result['success']) {
                $this->db->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE queue_id = ?")->execute([$queue_id]);
                $sentCount++;
            } else {
                $new_attempts = (int)$email['attempts'] + 1;
                $status = ($new_attempts >= (int)$email['max_attempts']) ? 'failed' : 'pending';
                $this->db->prepare("UPDATE email_queue SET status = ?, attempts = ?, last_error = ? WHERE queue_id = ?")
                         ->execute([$status, $new_attempts, $result['error'], $queue_id]);
                $failedCount++;
            }
        }

        return ['sent' => $sentCount, 'failed' => $failedCount];
    }

    /**
     * Send a single email via SMTP.
     */
    private function sendSingle(array $email): array {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
            $mail->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;

            $mail->setFrom(defined('SMTP_USERNAME') ? SMTP_USERNAME : 'info@umojadrivers.co.ke', defined('SITE_NAME') ? SITE_NAME : 'Umoja Drivers Sacco');
            $mail->addAddress($email['recipient_email'], $email['recipient_name']);
            $mail->isHTML(true);
            $mail->Subject  = $email['subject'];
            $mail->Body     = $this->wrapTemplate($email['body']);
            $mail->AltBody  = strip_tags($email['body']);

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];

            $mail->send();
            return ['success' => true, 'error' => null];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
    }

    /**
     * Wrap content in the Sacco branded email template.
     */
    private function wrapTemplate(string $content): string {
        $year     = date('Y');
        $site_name = defined('SITE_NAME') ? SITE_NAME : 'Umoja Drivers Sacco';

        return "
        <div style='font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;border:1px solid #eee;border-radius:10px;overflow:hidden;'>
            <div style='background:#0F392B;padding:20px;text-align:center;'>
                <h2 style='color:#D0F35D;margin:0;'>{$site_name}</h2>
                <p style='color:#fff;margin:5px 0 0 0;font-size:14px;'>Your Financial Partner</p>
            </div>
            <div style='padding:30px;'>
                {$content}
            </div>
            <div style='background:#f8f9fa;padding:15px;text-align:center;font-size:12px;color:#666;'>
                <p style='margin:0;'>&copy; {$year} {$site_name} Ltd.</p>
                <p style='margin:5px 0 0 0;'>This is an automated message. Please do not reply.</p>
            </div>
        </div>";
    }

    /**
     * Get queue statistics grouped by status.
     */
    public function getQueueStats(): array {
        $stmt = $this->db->query("SELECT status, COUNT(*) AS count FROM email_queue GROUP BY status");
        $stats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = (int)$row['count'];
        }
        return $stats;
    }
}
