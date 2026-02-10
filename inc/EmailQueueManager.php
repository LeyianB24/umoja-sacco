<?php
/**
 * Email Queue Manager
 * Production-grade email delivery with queue, retry, and tracking
 */

require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailQueueManager {
    private $conn;
    private $smtp_host = 'smtp.gmail.com';
    private $smtp_username = 'leyianbeza24@gmail.com';
    private $smtp_password = 'duzb mbqt fnsz ipkg';
    private $smtp_port = 587;
    private $from_email = 'leyianbeza24@gmail.com';
    private $from_name = 'Umoja Drivers Sacco';
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    /**
     * Queue an email for sending
     */
    public function queueEmail($recipient_email, $recipient_name, $subject, $body, $priority = 5) {
        $stmt = $this->conn->prepare("
            INSERT INTO email_queue (recipient_email, recipient_name, subject, body, priority, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("ssssi", $recipient_email, $recipient_name, $subject, $body, $priority);
        $result = $stmt->execute();
        $queue_id = $this->conn->insert_id;
        $stmt->close();
        
        return $queue_id;
    }
    
    /**
     * Process pending emails in queue
     */
    public function processPendingEmails($batch_size = 10) {
        // Get pending emails, prioritized
        $stmt = $this->conn->prepare("
            SELECT queue_id, recipient_email, recipient_name, subject, body, attempts, max_attempts
            FROM email_queue
            WHERE status = 'pending' AND attempts < max_attempts
            AND (scheduled_for IS NULL OR scheduled_for <= NOW())
            ORDER BY priority ASC, created_at ASC
            LIMIT ?
        ");
        $stmt->bind_param("i", $batch_size);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sent = 0;
        $failed = 0;
        
        while ($email = $result->fetch_assoc()) {
            $queue_id = $email['queue_id'];
            
            // Attempt to send
            $send_result = $this->sendEmail(
                $email['recipient_email'],
                $email['recipient_name'],
                $email['subject'],
                $email['body']
            );
            
            if ($send_result['success']) {
                // Mark as sent
                $this->conn->query("
                    UPDATE email_queue 
                    SET status = 'sent', sent_at = NOW()
                    WHERE queue_id = $queue_id
                ");
                $sent++;
            } else {
                // Increment attempts and log error
                $error = $this->conn->real_escape_string($send_result['error']);
                $new_attempts = $email['attempts'] + 1;
                
                if ($new_attempts >= $email['max_attempts']) {
                    // Max attempts reached, mark as failed
                    $this->conn->query("
                        UPDATE email_queue 
                        SET status = 'failed', attempts = $new_attempts, last_error = '$error'
                        WHERE queue_id = $queue_id
                    ");
                } else {
                    // Retry later
                    $this->conn->query("
                        UPDATE email_queue 
                        SET attempts = $new_attempts, last_error = '$error'
                        WHERE queue_id = $queue_id
                    ");
                }
                $failed++;
            }
        }
        
        $stmt->close();
        
        return [
            'sent' => $sent,
            'failed' => $failed
        ];
    }
    
    /**
     * Send a single email via SMTP
     */
    private function sendEmail($to_email, $to_name, $subject, $body_html) {
        $mail = new PHPMailer(true);
        
        try {
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_port;
            
            // Email Setup
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to_email, $to_name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            
            // Professional email template
            $mail->Body = $this->wrapEmailTemplate($body_html);
            $mail->AltBody = strip_tags($body_html);
            
            // Send
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            $mail->send();
            
            return ['success' => true, 'error' => null];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
    }
    
    /**
     * Wrap email content in professional template
     */
    private function wrapEmailTemplate($content) {
        $site_url = defined('SITE_URL') ? SITE_URL : 'http://localhost/usms';
        
        return "
        <div style='font-family:Arial, sans-serif; line-height:1.6; color:#333; max-width:600px; margin:0 auto; border:1px solid #eee; border-radius:10px; overflow:hidden;'>
            <div style='background:#0F392B; padding:20px; text-align:center;'>
                <h2 style='color:#D0F35D; margin:0;'>Umoja Drivers Sacco</h2>
                <p style='color:#fff; margin:5px 0 0 0; font-size:14px;'>Your Financial Partner</p>
            </div>
            <div style='padding:30px;'>
                $content
            </div>
            <div style='background:#f8f9fa; padding:15px; text-align:center; font-size:12px; color:#666;'>
                <p style='margin:0;'>&copy; " . date('Y') . " Umoja Drivers Sacco Ltd.</p>
                <p style='margin:5px 0 0 0;'>This is an automated message. Please do not reply.</p>
            </div>
        </div>";
    }
    
    /**
     * Get queue statistics
     */
    public function getQueueStats() {
        $stats = [];
        
        $result = $this->conn->query("
            SELECT status, COUNT(*) as count
            FROM email_queue
            GROUP BY status
        ");
        
        while ($row = $result->fetch_assoc()) {
            $stats[$row['status']] = $row['count'];
        }
        
        return $stats;
    }
}
