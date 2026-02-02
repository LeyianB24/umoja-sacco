<?php
// inc/email.php
// Unified email + notification handler for Umoja Sacco System

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// PHPMailer includes
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

// DB connection
require_once __DIR__ . '/../config/db_connect.php';

/**
 * Send an email and also create an in-app notification.
 *
 * @param string $to_email   Recipient email
 * @param string $subject    Email subject
 * @param string $body_html  HTML email body (long format)
 * @param int|null $member_id
 * @param int|null $admin_id
 * @return bool
 */
function sendEmailWithNotification($to_email, $subject, $body_html, $member_id = null, $admin_id = null)
{
    global $conn;
    $mail = new PHPMailer(true);

    try {
        // ======================
        // SMTP CONFIGURATION
        // ======================
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'leyianbeza24@gmail.com'; // sender Gmail
        $mail->Password   = 'duzb mbqt fnsz ipkg';    // Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // ======================
        // EMAIL SETUP
        // ======================
        $mail->setFrom('leyianbeza24@gmail.com', 'Umoja Drivers Sacco');
        $mail->addAddress($to_email);
        $mail->isHTML(true);
        $mail->Subject = $subject;

        // Wrap the email body in a cleaner HTML template
        $mail->Body = "
            <div style='font-family:Arial, sans-serif; line-height:1.6; color:#333; max-width:600px; margin:0 auto; border:1px solid #eee; border-radius:10px; overflow:hidden;'>
                <div style='background:#f8f9fa; padding:20px; text-align:center; border-bottom:3px solid #D0F35D;'>
                    <img src='" . SITE_URL . "/public/assets/images/people_logo.png' alt='Logo' style='width:60px; height:60px; margin-bottom:10px;'>
                    <h2 style='color:#0F392B; margin:0;'>Umoja Drivers Sacco</h2>
                </div>
                <div style='padding:30px;'>
                    <p>$body_html</p>
                </div>
                <div style='background:#f8f9fa; padding:15px; text-align:center; font-size:12px; color:#666;'>
                    &copy; " . date('Y') . " Umoja Drivers Sacco Ltd. This is an automated message.
                </div>
            </div>";
        $mail->AltBody = strip_tags($body_html);

        // ======================
        // SEND EMAIL
        // ======================
        $mail->send();

        // ======================
        // STORE NOTIFICATION
        // ======================
        if ($conn && ($member_id || $admin_id)) {

            $to_role = $member_id ? 'member' : 'admin';
            $user_id = $member_id ?: $admin_id;
            $user_type = $member_id ? 'member' : 'admin';

            // Clean plain-text version for notifications
            $plain_message = trim(strip_tags($body_html));
            if (strlen($plain_message) > 180) {
                $plain_message = substr($plain_message, 0, 180) . '...';
            }

            $sql = "INSERT INTO notifications 
                    (member_id, admin_id, user_id, user_type, to_role, title, message, status, is_read, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'unread', 0, NOW())";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param(
                    "iiissss",
                    $member_id,
                    $admin_id,
                    $user_id,
                    $user_type,
                    $to_role,
                    $subject,
                    $plain_message
                );
                $stmt->execute();
                $stmt->close();
            }
        }

        return true;

    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Backward-compatible alias
function sendEmail($to_email, $subject, $body_html, $member_id = null, $admin_id = null)
{
    return sendEmailWithNotification($to_email, $subject, $body_html, $member_id, $admin_id);
}