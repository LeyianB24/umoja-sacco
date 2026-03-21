<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
require_once 'c:/xampp/htdocs/usms/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->SMTPDebug  = 2; // Enable verbose debug output

    $mail->setFrom(SMTP_USERNAME, SITE_NAME);
    $mail->addAddress('bezaleltomaka@gmail.com', 'Bezalel Tomaka');

    $mail->isHTML(true);
    $mail->Subject = 'SMTP Test - ' . date('Y-m-d H:i:s');
    $mail->Body    = 'This is a test email to verify the SMTP configuration.';

    echo "Sending email...\n";
    $mail->send();
    echo "Message has been sent successfully!\n";
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}\n";
}
