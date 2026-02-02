<?php
// inc/notification_helpers.php
// Unified Notification System

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/sms.php';

/**
 * Send notification (both email and SMS)
 * @param int $member_id - Member ID
 * @param string $type - Notification type
 * @param array $data - Additional data for the notification
 */
function send_notification($conn, $member_id, $type, $data = []) {
    // Fetch member details
    $stmt = $conn->prepare("SELECT full_name, email, phone FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$member) return false;
    
    $name = $member['full_name'];
    $email = $member['email'];
    $phone = $member['phone'];
    
    // Generate notification content based on type
    $notification = get_notification_content($type, $name, $data);
    
    $success = true;
    
    // Send Email
    if (EMAIL_ENABLED && !empty($email)) {
        try {
            sendEmail($email, $notification['email_subject'], $notification['email_body'], $member_id);
        } catch (Exception $e) {
            error_log("Email notification failed: " . $e->getMessage());
            $success = false;
        }
    }
    
    // Send SMS
    if (SMS_ENABLED && !empty($phone)) {
        try {
            send_sms($phone, $notification['sms_message']);
        } catch (Exception $e) {
            error_log("SMS notification failed: " . $e->getMessage());
            $success = false;
        }
    }
    
    // Log notification in database
    $msg = $notification['sms_message'];
    $conn->query("INSERT INTO notifications (user_type, user_id, message, is_read, created_at) 
                  VALUES ('member', $member_id, '" . $conn->real_escape_string($msg) . "', 0, NOW())");
    
    return $success;
}

/**
 * Get notification content templates
 */
function get_notification_content($type, $name, $data) {
    $amount = isset($data['amount']) ? number_format($data['amount'], 2) : '0.00';
    $ref = $data['reference'] ?? '';
    
    $templates = [
        'loan_approved' => [
            'email_subject' => 'Loan Application Approved',
            'email_body' => "<p>Dear <strong>$name</strong>,</p>
                            <p>Your loan application for <strong>KES $amount</strong> has been approved!</p>
                            <p>The funds have been credited to your account. You can withdraw them at any time.</p>
                            <p>Reference: <strong>$ref</strong></p>
                            <p>Thank you for banking with us.</p>",
            'sms_message' => "Dear $name, your loan of KES $amount has been approved. Ref: $ref. Visit your dashboard to withdraw."
        ],
        'loan_disbursed' => [
            'email_subject' => 'Loan Disbursed',
            'email_body' => "<p>Dear <strong>$name</strong>,</p>
                            <p>Your loan of <strong>KES $amount</strong> has been disbursed to your wallet.</p>
                            <p>You can now withdraw the funds via M-Pesa.</p>
                            <p>Reference: <strong>$ref</strong></p>",
            'sms_message' => "Dear $name, KES $amount has been added to your wallet. Withdraw now. Ref: $ref"
        ],
        'withdrawal_success' => [
            'email_subject' => 'Withdrawal Successful',
            'email_body' => "<p>Dear <strong>$name</strong>,</p>
                            <p>Your withdrawal of <strong>KES $amount</strong> was successful.</p>
                            <p>M-Pesa Reference: <strong>$ref</strong></p>
                            <p>Thank you for using our services.</p>",
            'sms_message' => "Dear $name, withdrawal of KES $amount successful. M-Pesa Ref: $ref"
        ],
        'deposit_success' => [
            'email_subject' => 'Deposit Confirmed',
            'email_body' => "<p>Dear <strong>$name</strong>,</p>
                            <p>Your deposit of <strong>KES $amount</strong> has been confirmed.</p>
                            <p>Receipt: <strong>$ref</strong></p>
                            <p>Your new balance is available in your account.</p>",
            'sms_message' => "Dear $name, deposit of KES $amount confirmed. Receipt: $ref. Thank you!"
        ],
        'welfare_granted' => [
            'email_subject' => 'Welfare Support Granted',
            'email_body' => "<p>Dear <strong>$name</strong>,</p>
                            <p>You have been granted welfare support of <strong>KES $amount</strong>.</p>
                            <p>Reason: " . ($data['reason'] ?? 'Welfare Support') . "</p>
                            <p>The funds are now in your wallet. You can withdraw them anytime.</p>",
            'sms_message' => "Dear $name, you have received welfare support of KES $amount. Funds available for withdrawal."
        ]
    ];
    
    return $templates[$type] ?? [
        'email_subject' => 'Notification from ' . SITE_NAME,
        'email_body' => "<p>Dear <strong>$name</strong>,</p><p>You have a new notification.</p>",
        'sms_message' => "Dear $name, you have a new notification from " . SITE_SHORT_NAME
    ];
}

function mark_notification_read($conn, $notif_id, $user_id, $user_role = 'member') {
    if ($notif_id === 'all') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, status = 'read' WHERE user_id = ? AND user_type = ?");
        $stmt->bind_param("is", $user_id, $user_role);
    } else {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, status = 'read' WHERE notification_id = ? AND user_id = ? AND user_type = ?");
        $stmt->bind_param("iis", $notif_id, $user_id, $user_role);
    }
    return $stmt->execute();
}
