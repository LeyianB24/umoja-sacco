<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
// Set member 8's loan to overdue
$conn->query("UPDATE loans SET next_repayment_date = '2026-03-20' WHERE member_id = 8 AND status IN ('active', 'disbursed')");
echo "Updated member 8 loan to overdue.\n";

require_once 'c:/xampp/htdocs/usms/core/Services/CronService.php';
$cron = new \USMS\Services\CronService();
$count = $cron->sendBulkLateReminders();
echo "Bulk reminders queued. Count: $count\n";

// Run worker to send
require_once 'c:/xampp/htdocs/usms/core/Services/EmailQueueService.php';
$emailService = new \USMS\Services\EmailQueueService();
$res = $emailService->processPendingEmails();
echo "Emails processed: Sent: {$res['sent']}, Failed: {$res['failed']}\n";

$check = $conn->query("SELECT status, last_error FROM email_queue WHERE recipient_email = 'bezaleltomaka@gmail.com' ORDER BY queue_id DESC LIMIT 1");
$r = $check->fetch_assoc();
echo "Status for bezaleltomaka@gmail.com: " . $r['status'] . "\n";
echo "Error: " . ($r['last_error'] ?? 'None') . "\n";
