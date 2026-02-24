<?php
declare(strict_types=1);

namespace USMS\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * USMS\Services\EmailService
 * Core Email Dispatcher using PHPMailer
 */
class EmailService {
    
    /**
     * Send an email with optional attachments.
     */
    public function send(string $to, string $subject, string $body, array $attachments = [], ?string $altBody = null): bool {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
            $mail->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;

            // Recipients
            $mail->setFrom(defined('SMTP_USERNAME') ? SMTP_USERNAME : 'info@umojadrivers.co.ke', defined('SITE_NAME') ? SITE_NAME : 'Umoja Drivers Sacco');
            $mail->addAddress($to);

            // Attachments
            if (!empty($attachments)) {
                foreach ($attachments as $att) {
                    if (isset($att['path'])) {
                        $mail->addAttachment($att['path'], $att['name'] ?? ''); 
                    } elseif (isset($att['content'])) {
                        $mail->addStringAttachment($att['content'], $att['name']);
                    }
                }
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $altBody ?: strip_tags($body);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error ({$to}): " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Static gateway for convenience
     */
    public static function quickSend(string $to, string $subject, string $body): bool {
        return (new self())->send($to, $subject, $body);
    }
}
