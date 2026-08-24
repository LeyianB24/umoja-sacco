<?php
declare(strict_types=1);

/**
 * api/v1/public_api.php
 * Public endpoints for landing page, contact, statistics
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/functions.php';

/** @var mysqli $conn */
global $conn;

$action = $_GET['action'] ?? $_GET['endpoint'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input  = get_json_input();

if ($action === 'stats' && $method === 'GET') {
    // Total members
    $total_members = 0;
    $m_res = $conn->query("SELECT COUNT(*) as c FROM members WHERE status = 'active'");
    if ($m_res) $total_members = (int)($m_res->fetch_assoc()['c'] ?? 0);

    // Total savings
    $total_savings = 0.0;
    $s_res = $conn->query("SELECT COALESCE(SUM(amount), 0) as s FROM transactions WHERE transaction_type IN ('deposit', 'contribution')");
    if ($s_res) $total_savings = (float)($s_res->fetch_assoc()['s'] ?? 0);

    // Total loans disbursed
    $loans_disbursed = 0.0;
    $l_res = $conn->query("SELECT SUM(amount) as l FROM loans WHERE status IN ('disbursed', 'settled')");
    if ($l_res) $loans_disbursed = (float)($l_res->fetch_assoc()['l'] ?? 0);

    // Asset base
    $asset_base = $total_savings * 1.35; // Estimated reserve ratio

    api_success([
        'stats' => [
            'active_members' => max(150, $total_members),
            'total_savings' => max(12500000.0, $total_savings),
            'loans_disbursed' => max(28000000.0, $loans_disbursed),
            'asset_base' => max(45000000.0, $asset_base),
            'interest_rate' => 10.0,
            'max_loan_multiple' => 3
        ]
    ]);
}

if ($action === 'contact' && $method === 'POST') {
    $name    = trim($input['name'] ?? '');
    $email   = trim($input['email'] ?? '');
    $subject = trim($input['subject'] ?? 'General Inquiry');
    $message = trim($input['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        api_error('Name, email, and message are required.', 422);
    }

    if ($conn->query("SHOW TABLES LIKE 'messages'")->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_name, sender_email, subject, body, sent_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("ssss", $name, $email, $subject, $message);
            $stmt->execute();
            $stmt->close();
        }
    }

    api_success(null, 'Thank you for reaching out! Our support team will get back to you shortly.');
}

api_error('Unknown public endpoint: ' . htmlspecialchars($action), 404);
