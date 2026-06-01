<?php
// bin/send_raw_email.php
// Usage: php send_raw_email.php /path/to/payload.json

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line." . PHP_EOL;
    exit(1);
}

$payloadPath = $argv[1] ?? null;
if (!$payloadPath || !file_exists($payloadPath)) {
    fwrite(STDERR, "Payload file missing or not found: " . ($payloadPath ?? '(none)') . PHP_EOL);
    exit(2);
}

$payload = json_decode(file_get_contents($payloadPath), true);
if (!is_array($payload)) {
    fwrite(STDERR, "Invalid payload file: $payloadPath\n");
    @unlink($payloadPath);
    exit(3);
}

$to = $payload['to'] ?? null;
$subject = $payload['subject'] ?? '(no subject)';
$body = $payload['body'] ?? '';

// Load PHPMailer and config
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../config/environment.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email_config = $GLOBALS['env_config']['email'] ?? ($env_config['email'] ?? []);
$site_name = defined('SITE_NAME') ? SITE_NAME : 'Umoja Drivers Sacco';

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $email_config['smtp_host'] ?? (defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com');
    $mail->SMTPAuth   = true;
    $mail->Username   = $email_config['smtp_username'] ?? (defined('SMTP_USERNAME') ? SMTP_USERNAME : '');
    $mail->Password   = $email_config['smtp_password'] ?? (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '');
    $mail->SMTPSecure = ($email_config['smtp_port'] ?? 587) == 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $email_config['smtp_port'] ?? 587;
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

    $from_email = $email_config['from_email'] ?? (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'info@umojadrivers.co.ke');
    $from_name = $email_config['from_name'] ?? $site_name;

    $mail->setFrom($from_email, $from_name);
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->AltBody = strip_tags($body);

    $mail->send();
    // Clean up payload
    @unlink($payloadPath);
    exit(0);
} catch (Exception $e) {
    // Log to file as a non-DB fallback
    $err = "[" . date('Y-m-d H:i:s') . "] send_raw_email.php error: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/../storage/email_errors.log', $err, FILE_APPEND);
    @unlink($payloadPath);
    exit(4);
}
