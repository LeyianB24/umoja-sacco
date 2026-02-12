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

function sendEmailWithNotification($to_email, $subject, $body_content, $member_id = null, $admin_id = null, $metadata = [])
{
    global $conn;
    $email_success = false;
    $delivery_error = null;

    // 1. Prepare Branded HTML Body
    $site_name = defined('SITE_NAME') ? SITE_NAME : 'Umoja Drivers Sacco';
    $site_url  = defined('SITE_URL') ? SITE_URL : 'http://localhost/usms';
    $logo_url  = defined('SITE_LOGO') ? SITE_LOGO : $site_url . '/public/assets/images/people_logo.png';
    $date_now  = date('jS M, Y H:i:s');
    
    // Transaction details if available in metadata
    $trx_id = $metadata['trx_id'] ?? 'N/A';
    $reg_no = $metadata['reg_no'] ?? 'N/A';
    $balance = isset($metadata['balance']) ? 'KES ' . number_format($metadata['balance'], 2) : 'N/A';

    $body_html = "
    <div style='font-family:\"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; line-height:1.6; color:#1e293b; max-width:600px; margin:20px auto; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);'>
        <div style='background:#f8fafc; padding:30px; text-align:center; border-bottom:4px solid #39B54A;'>
            <img src='{$logo_url}' alt='Logo' style='height:60px; margin-bottom:10px;'>
            <h2 style='color:#0F392B; margin:0; font-size:22px;'>{$site_name}</h2>
            <p style='color:#64748b; font-size:12px; margin:5px 0 0;'>Official Communication</p>
        </div>
        <div style='padding:40px; background:#ffffff;'>
            <div style='margin-bottom:25px;'>
                {$body_content}
            </div>
            
            <div style='background:#f1f5f9; border-radius:8px; padding:20px; font-size:13px;'>
                <table style='width:100%;'>
                    <tr><td style='color:#64748b; padding:4px 0;'>Transaction ID:</td><td style='font-weight:600; text-align:right;'>{$trx_id}</td></tr>
                    <tr><td style='color:#64748b; padding:4px 0;'>Date & Time:</td><td style='font-weight:600; text-align:right;'>{$date_now}</td></tr>
                    <tr><td style='color:#64748b; padding:4px 0;'>Member RegNo:</td><td style='font-weight:600; text-align:right;'>{$reg_no}</td></tr>
                    <tr><td style='color:#64748b; padding:4px 0;'>Current Balance:</td><td style='font-weight:600; text-align:right; color:#1d7c2a;'>{$balance}</td></tr>
                </table>
            </div>
        </div>
        <div style='background:#f8fafc; padding:25px; text-align:center; font-size:11px; color:#94a3b8; border-top:1px solid #f1f5f9;'>
            <p style='margin:0;'>&copy; " . date('Y') . " {$site_name}. All Rights Reserved.</p>
            <p style='margin:5px 0;'>This is an automated message. Do not reply.</p>
            <div style='margin-top:10px;'>
                <a href='{$site_url}' style='color:#39B54A; text-decoration:none;'>Home</a> | 
                <a href='{$site_url}/member/pages/profile.php' style='color:#39B54A; text-decoration:none;'>My Account</a>
            </div>
        </div>
    </div>";

    // 2. SEND EMAIL
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'leyianbeza24@gmail.com'; 
        $mail->Password   = 'duzb mbqt fnsz ipkg';    
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        $mail->setFrom('leyianbeza24@gmail.com', $site_name);
        $mail->addAddress($to_email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body_html;
        $mail->AltBody = strip_tags($body_content);

        $mail->send();
        $email_success = true;
    } catch (Exception $e) {
        $delivery_error = $mail->ErrorInfo;
        error_log("Mailer Error: " . $delivery_error);
    }

    // 3. LOG TO NOTIFICATIONS (Live-Simulation Requirement)
    if ($conn && ($member_id || $admin_id)) {
        try {
            $to_role = $member_id ? 'member' : 'admin';
            $user_id = $member_id ?: $admin_id;
            $user_type = $member_id ? 'member' : 'admin';
            $delivery_status = $email_success ? 'sent' : 'failed';
            $json_meta = json_encode($metadata);
            
            $plain_msg = trim(strip_tags($body_content));
            if (strlen($plain_msg) > 180) $plain_msg = substr($plain_msg, 0, 180) . '...';

            $sql = "INSERT INTO notifications 
                    (member_id, admin_id, user_id, user_type, to_role, comms_type, delivery_status, recipient, title, message, delivery_error, metadata, is_read, created_at)
                    VALUES (?, ?, ?, ?, ?, 'email', ?, ?, ?, ?, ?, ?, 0, NOW())";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("iiissssssss", $member_id, $admin_id, $user_id, $user_type, $to_role, $delivery_status, $to_email, $subject, $plain_msg, $delivery_error, $json_meta);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log("Notification Log Failed: " . $e->getMessage());
        }
    }

    return $email_success;
}

// Backward-compatible alias
function sendEmail($to_email, $subject, $body_html, $member_id = null, $admin_id = null)
{
    return sendEmailWithNotification($to_email, $subject, $body_html, $member_id, $admin_id);
}