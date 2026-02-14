<?php
// inc/Mailer.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class Mailer {
    
    /**
     * Send an email with optional attachments.
     * 
     * @param string $to Recipient email
     * @param string $subject Subject line
     * @param string $body HTML body content
     * @param array $attachments Array of ['content' => binaryString, 'name' => 'filename.pdf']
     * @param string|null $altBody Plain text alternative
     * @return bool True on success, False on failure
     */
    public static function send($to, $subject, $body, $attachments = [], $altBody = null) {
        // Load Config
        $config = require __DIR__ . '/../config/environment.php';
        $mailConf = $config['email'];

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $mailConf['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $mailConf['smtp_username'];
            $mail->Password   = $mailConf['smtp_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $mailConf['smtp_port'];

            // Recipients
            $mail->setFrom($mailConf['from_email'], $mailConf['from_name']);
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
            error_log("Mailer Error ({$to}): {$mail->ErrorInfo}");
            return false;
        }
    }
}
?>
