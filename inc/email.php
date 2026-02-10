<?php
// inc/email.php
// Unified email + notification handler for Umoja Sacco System

// Unified email + notification handler for Umoja Sacco System
// REFACTORED: Notification Priority + SSL Bypass

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// PHPMailer includes (ensure paths are correct)
if (file_exists(__DIR__ . '/../vendor/phpmailer/src/Exception.php')) {
    require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
}

require_once __DIR__ . '/../config/db_connect.php';

function sendEmailWithNotification($to_email, $subject, $body_html, $member_id = null, $admin_id = null)
{
    global $conn;
    $notification_success = false;
    $email_success = false;

    // 1. STORE NOTIFICATION (Prioritize this!)
    if ($conn && ($member_id || $admin_id)) {
        try {
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
                $stmt->bind_param("iiissss", $member_id, $admin_id, $user_id, $user_type, $to_role, $subject, $plain_message);
                if ($stmt->execute()) {
                    $notification_success = true;
                } else {
                     error_log("Notification Execute Failed: " . $stmt->error);
                }
                $stmt->close();
            } else {
                 error_log("Notification Prepare Failed: " . $conn->error);
            }
        } catch (Throwable $e) {
            error_log("Notification Insert Failed: " . $e->getMessage());
        }
    }

    // 2. SEND EMAIL (Development SSL Bypass)
    $mail = new PHPMailer(true);
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        // Use App Password provided
        $mail->Username   = 'leyianbeza24@gmail.com'; 
        $mail->Password   = 'duzb mbqt fnsz ipkg';    
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587;

        // SSL Bypass for Localhost/XAMPP
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Email Content
        $mail->setFrom('leyianbeza24@gmail.com', 'Umoja Drivers Sacco');
        $mail->addAddress($to_email);
        $mail->isHTML(true);
        $mail->Subject = $subject;

        // Formatted Body
        $site_url = defined('SITE_URL') ? SITE_URL : 'http://localhost/usms';
        $mail->Body = "
            <div style='font-family:Arial, sans-serif; line-height:1.6; color:#333; max-width:600px; margin:0 auto; border:1px solid #eee; border-radius:10px; overflow:hidden;'>
                <div style='background:#f8f9fa; padding:20px; text-align:center; border-bottom:3px solid #D0F35D;'>
                    <h2 style='color:#0F392B; margin:0;'>Umoja Drivers Sacco</h2>
                </div>
                <div style='padding:30px;'>
                    <p>$body_html</p>
                </div>
                <div style='background:#f8f9fa; padding:15px; text-align:center; font-size:12px; color:#666;'>
                    &copy; " . date('Y') . " Umoja Drivers Sacco Ltd. Automated message.
                </div>
            </div>";
        $mail->AltBody = strip_tags($body_html);

        $mail->send();
        $email_success = true;

    } catch (Exception $e) {
        // Log SMTP error but don't crash script
        error_log("Mailer Error: " . $mail->ErrorInfo);
    }

    // Success if notification created OR email sent
    return $notification_success || $email_success;
}

// Backward-compatible alias
function sendEmail($to_email, $subject, $body_html, $member_id = null, $admin_id = null)
{
    return sendEmailWithNotification($to_email, $subject, $body_html, $member_id, $admin_id);
}