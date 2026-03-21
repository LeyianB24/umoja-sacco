<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';

// 1. Find or create a test member/loan
require_once 'c:/xampp/htdocs/usms/core/Database/Database.php';
use USMS\Database\Database;
$pdo = Database::getInstance()->getPdo();

$loan_id = 26;
$member_id = 2; // Bezalel Leyian
echo "Testing with Loan ID: $loan_id\n";

// 2. Test Daily Fines
echo "Setting next_repayment_date to yesterday...\n";
$yesterday = date('Y-m-d', strtotime('-1 day'));
$conn->query("UPDATE loans SET next_repayment_date = '$yesterday' WHERE loan_id = $loan_id");

// Clear any existing fine today for this loan to allow re-testing
$today = date('Y-m-d');
$conn->query("DELETE FROM fines WHERE loan_id = $loan_id AND date_applied = '$today'");

echo "Running daily_fines job...\n";
passthru("php c:/xampp/htdocs/usms/cron/run.php daily_fines");

// Check if fine applied
$res = $conn->query("SELECT * FROM fines WHERE loan_id = $loan_id AND date_applied = '$today'");
if ($res->num_rows > 0) {
    echo "SUCCESS: Fine applied correctly.\n";
} else {
    echo "FAILURE: Fine NOT applied.\n";
}

// Check email queue
$res = $conn->query("SELECT * FROM email_queue WHERE subject LIKE '%Late Payment Penalty Applied%' ORDER BY created_at DESC LIMIT 1");
if ($res->num_rows > 0) {
    echo "SUCCESS: Email queued for fine.\n";
} else {
    echo "FAILURE: Email NOT queued for fine.\n";
}

// --- TEST 2: REPAYMENT REMINDERS ---
echo "\n--- TEST 2: REPAYMENT REMINDERS ---\n";
$threeDaysFromNow = date('Y-m-d', strtotime('+3 days'));
$pdo->prepare("UPDATE loans SET next_repayment_date = ?, status = 'disbursed' WHERE loan_id = 26")
    ->execute([$threeDaysFromNow]);

echo "Running repayment_reminders job...\n";
passthru("php c:/xampp/htdocs/usms/cron/run.php repayment_reminders");

$res = $conn->query("SELECT * FROM email_queue WHERE subject LIKE '%Repayment Reminder%' ORDER BY created_at DESC LIMIT 1");
if ($res->num_rows > 0) {
    echo "SUCCESS: Email queued for reminder.\n";
} else {
    echo "FAILURE: Email NOT queued for reminder.\n";
}
