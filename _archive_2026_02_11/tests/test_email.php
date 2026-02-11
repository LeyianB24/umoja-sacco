<?php
/**
 * Test Email Configuration
 * Verifies SMTP settings and email delivery
 */

require_once __DIR__ . '/../inc/email.php';
require_once __DIR__ . '/../inc/EmailQueueManager.php';
require_once __DIR__ . '/../config/db_connect.php';

if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost/usms');

echo "═══════════════════════════════════════════════════════════\n";
echo "           EMAIL CONFIGURATION TEST\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Test email address (change this to your email)
$test_email = "leyianbeza24@gmail.com";
$test_subject = "USMS System Test - Email Functionality";
$test_body = "
    <h2>Email Test Successful!</h2>
    <p>This is a test email from the Umoja Drivers Sacco Management System.</p>
    <p><strong>Test Time:</strong> " . date('Y-m-d H:i:s') . "</p>
    <p>If you received this email, your email configuration is working correctly.</p>
    <hr>
    <p><strong>Next Steps:</strong></p>
    <ul>
        <li>✅ SMTP configuration verified</li>
        <li>✅ Email delivery confirmed</li>
        <li>🔄 Implementing email queue system</li>
    </ul>
";

echo "Sending test email to: $test_email\n";
echo "Subject: $test_subject\n\n";

try {
    $result = sendEmailWithNotification($test_email, $test_subject, $test_body);
    
    if ($result) {
        echo "✅ SUCCESS! Email sent successfully.\n";
        echo "\nCheck your inbox at: $test_email\n";
        echo "Note: Check spam folder if not in inbox.\n";
    } else {
        echo "❌ FAILED! Email could not be sent.\n";
        echo "\nPossible issues:\n";
        echo "1. SMTP credentials incorrect\n";
        echo "2. Gmail App Password expired\n";
        echo "3. Network connectivity issues\n";
        echo "4. Gmail blocking the connection\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n";
    echo "\nStack trace:\n{$e->getTraceAsString()}\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
