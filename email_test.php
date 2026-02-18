<?php
// email_test.php
require_once __DIR__ . '/inc/email.php';

$test_email = 'your-email@example.com'; // Change this to a real email to test
$subject = "Test Email From Umoja Sacco System";
$body = "<h2>Test Successful</h2><p>If you see this, email sending is working correctly.</p>";

echo "Attempting to send test email to $test_email...\n";

$success = sendEmailWithNotification($test_email, $subject, $body, null, null, ['trx_id' => 'TEST-123']);

if ($success) {
    echo "SUCCESS: Email sent successfully!\n";
} else {
    echo "FAILURE: Email failed to send. Check the error log (inc/email.php logs to error_log()).\n";
    // Since we don't have easy access to php_error.log, let's try to get more info
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'leyianbeza24@gmail.com'; 
        $mail->Password   = 'duzb mbqt fnsz ipkg';    
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587;
        $mail->setFrom('leyianbeza24@gmail.com', 'Test System');
        $mail->addAddress($test_email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->SMTPDebug = 2; // Enable verbose debug output
        $mail->Debugoutput = 'echo';
        $mail->send();
    } catch (Exception $e) {
        echo "\nDetailed Error Info:\n" . $e->getMessage();
        echo "\nMailer ErrorInfo: " . $mail->ErrorInfo;
    }
}
