<?php
declare(strict_types=1);
/**
 * inc/EmailQueueManager.php
 * Production-grade email delivery with queue, retry, and tracking.
 * SMTP credentials read from app_config.php constants (SMTP_HOST, SMTP_PORT, etc.)
 */

require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailQueueManager
{
    private \mysqli $conn;
    private string $smtp_host;
    private string $smtp_username;
    private string $smtp_password;
    private int    $smtp_port;
    private string $from_email;
    private string $from_name;

    public function __construct(\mysqli $db_connection)
    {
        $this->conn          = $db_connection;
        // Credentials sourced from app_config.php constants; hardcoded values act as fallback only
        $this->smtp_host     = defined('SMTP_HOST')      ? SMTP_HOST      : 'smtp.gmail.com';
        $this->smtp_username = defined('SMTP_USERNAME')  ? SMTP_USERNAME  : 'leyianbeza24@gmail.com';
        $this->smtp_password = defined('SMTP_PASSWORD')  ? SMTP_PASSWORD  : '';
        $this->smtp_port     = defined('SMTP_PORT')      ? (int) SMTP_PORT : 587;
        $this->from_email    = defined('SMTP_USERNAME')  ? SMTP_USERNAME  : 'leyianbeza24@gmail.com';
        $this->from_name     = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Umoja Drivers Sacco';
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
        $stmt = $this->conn->prepare("
            INSERT INTO email_queue (recipient_email, recipient_name, subject, body, priority, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param('ssssi', $recipient_email, $recipient_name, $subject, $body, $priority);
        $stmt->execute();
        $queue_id = (int) $this->conn->insert_id;
        $stmt->close();
        return $queue_id;
    }

    /**
     * Process a batch of pending emails from the queue.
     * @return array{sent: int, failed: int}
     */
    public function processPendingEmails(int $batch_size = 10): array
    {
        $stmt = $this->conn->prepare("
            SELECT queue_id, recipient_email, recipient_name, subject, body, attempts, max_attempts
            FROM email_queue
            WHERE status = 'pending'
              AND attempts < max_attempts
              AND (scheduled_for IS NULL OR scheduled_for <= NOW())
            ORDER BY priority ASC, created_at ASC
            LIMIT ?
        ");
        $stmt->bind_param('i', $batch_size);
        $stmt->execute();
        $result = $stmt->get_result();

        $sent   = 0;
        $failed = 0;

        while ($email = $result->fetch_assoc()) {
            $queue_id    = (int) $email['queue_id'];
            $send_result = $this->sendEmail(
                $email['recipient_email'],
                $email['recipient_name'],
                $email['subject'],
                $email['body']
            );

            if ($send_result['success']) {
                $this->conn->query(
                    "UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE queue_id = {$queue_id}"
                );
                $sent++;
            } else {
                $error        = $this->conn->real_escape_string((string) $send_result['error']);
                $new_attempts = (int) $email['attempts'] + 1;
                $max_attempts = (int) $email['max_attempts'];

                if ($new_attempts >= $max_attempts) {
                    $this->conn->query(
                        "UPDATE email_queue SET status = 'failed', attempts = {$new_attempts}, last_error = '{$error}' WHERE queue_id = {$queue_id}"
                    );
                } else {
                    $this->conn->query(
                        "UPDATE email_queue SET attempts = {$new_attempts}, last_error = '{$error}' WHERE queue_id = {$queue_id}"
                    );
                }
                $failed++;
            }
        }

        $stmt->close();
        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Send a single email via SMTP.
     * @return array{success: bool, error: string|null}
     */
    private function sendEmail(
        string $to_email,
        string $to_name,
        string $subject,
        string $body_html
    ): array {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtp_username;
            $mail->Password   = $this->smtp_password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $this->smtp_port;

            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to_email, $to_name);
            $mail->isHTML(true);
            $mail->Subject  = $subject;
            $mail->Body     = $this->wrapEmailTemplate($body_html);
            $mail->AltBody  = strip_tags($body_html);

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
     * Wrap content in the Umoja Sacco branded email template.
     */
    private function wrapEmailTemplate(string $content): string
    {
        $site_url = defined('SITE_URL') ? SITE_URL : 'http://localhost/usms';
        $year     = date('Y');

        return "
        <div style='font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;border:1px solid #eee;border-radius:10px;overflow:hidden;'>
            <div style='background:#0F392B;padding:20px;text-align:center;'>
                <h2 style='color:#D0F35D;margin:0;'>Umoja Drivers Sacco</h2>
                <p style='color:#fff;margin:5px 0 0 0;font-size:14px;'>Your Financial Partner</p>
            </div>
            <div style='padding:30px;'>
                {$content}
            </div>
            <div style='background:#f8f9fa;padding:15px;text-align:center;font-size:12px;color:#666;'>
                <p style='margin:0;'>&copy; {$year} Umoja Drivers Sacco Ltd.</p>
                <p style='margin:5px 0 0 0;'>This is an automated message. Please do not reply.</p>
            </div>
        </div>";
    }

    /**
     * Get queue statistics grouped by status.
     * @return array<string, int>
     */
    public function getQueueStats(): array
    {
        $stats  = [];
        $result = $this->conn->query(
            "SELECT status, COUNT(*) AS count FROM email_queue GROUP BY status"
        );
        while ($row = $result->fetch_assoc()) {
            $stats[$row['status']] = (int) $row['count'];
        }
        return $stats;
    }
}
