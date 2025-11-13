<?php
// inc/email.php
// Unified email + notification helper for Umoja Sacco System

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

// Include DB connection
require_once __DIR__ . '/../config/db_connect.php';

/**
 * Send an email and log a notification in the database.
 *
 * @param string $to_email  Recipient email address
 * @param string $subject   Email subject
 * @param string $body      Email body (HTML supported)
 * @param int|null $member_id  Member ID for notification
 * @param int|null $admin_id   Admin ID for notification
 * @return bool|string True if sent successfully, error message otherwise
 */
function sendEmailWithNotification($to_email, $subject, $body, $member_id = null, $admin_id = null)
{
    global $conn;
    $mail = new PHPMailer(true);

    try {
        // ======================
        //  SMTP CONFIGURATION
        // ======================
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        //  Change these credentials accordingly
        $mail->Username   = 'leyianbeza24@gmail.com';
        $mail->Password   = 'duzb mbqt fnsz ipkg'; // App password (not Gmail login)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // ======================
        //  EMAIL DETAILS
        // ======================
        $mail->setFrom('leyianbeza24@gmail.com', 'Umoja Drivers Sacco');
        $mail->addAddress($to_email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = nl2br($body);
        $mail->AltBody = strip_tags($body);

        // ======================
        //  SEND EMAIL
        // ======================
        $sent = $mail->send();

        // ======================
        //  SAVE NOTIFICATION
        // ======================
        if ($conn && ($member_id || $admin_id)) {
            $sql = "INSERT INTO notifications (member_id, admin_id, title, message, status, created_at)
                    VALUES (?, ?, ?, ?, 'unread', NOW())";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("iiss", $member_id, $admin_id, $subject, $body);
                $stmt->execute();
                $stmt->close();
            }
        }

        return $sent ? true : "Email not sent (unknown reason)";

    } catch (Exception $e) {
        return "Mailer Error: " . $mail->ErrorInfo;
    }
}
// Simple alias for backwards compatibility
function sendEmail($to_email, $subject, $body, $member_id = null, $admin_id = null)
{
    return sendEmailWithNotification($to_email, $subject, $body, $member_id, $admin_id);
}