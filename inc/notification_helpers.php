<?php
declare(strict_types=1);
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
    
    // Send Email & App Notification (Unified)
    if (EMAIL_ENABLED && !empty($email)) {
        try {
            // This now handles both SMTP and Database Notification insertion
            sendEmail($email, $notification['email_subject'], $notification['email_body'], $member_id);
        } catch (Throwable $e) {
            error_log("Notification system failed: " . $e->getMessage());
            $success = false;
        }
    }
    
    // Send SMS
    if (SMS_ENABLED && !empty($phone)) {
        try {
            send_sms($phone, $notification['sms_message']);
        } catch (Throwable $e) {
            error_log("SMS notification failed: " . $e->getMessage());
            $success = false;
        }
    }

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
        ],
        'registration_success' => [
            'email_subject' => 'Welcome to ' . SITE_NAME,
            'email_body' => "<p>Dear <strong>$name</strong>,</p>
                            <p>Welcome to <strong>" . SITE_NAME . "</strong>! Your account has been successfully created.</p>
                            <p>Member No: <strong>" . ($data['member_no'] ?? 'N/A') . "</strong></p>
                            <p>Please log in to your portal to complete your KYC verification if you haven't already.</p>
                            <p>Thank you for joining us.</p>",
            'sms_message' => "Welcome $name to " . SITE_SHORT_NAME . "! Your Member No is " . ($data['member_no'] ?? 'N/A') . ". Complete your KYC to activate your account."
        ],
        'profile_updated' => [
            'email_subject' => 'Profile Updated Successfully',
            'email_body' => "<p>Dear <strong>$name</strong>,</p>
                            <p>This is to confirm that your profile details on " . SITE_NAME . " have been updated successfully.</p>
                            <p>If you did not make these changes, please contact support immediately.</p>",
            'sms_message' => "Dear $name, your profile on " . SITE_SHORT_NAME . " has been updated successfully."
        ],
        'withdrawal_request' => [
            'email_subject' => 'Withdrawal Request Initiated',
            'email_body' => "<p>Dear <strong>$name</strong>,</p>
                            <p>We have received a request to withdraw <strong>KES $amount</strong> from your account.</p>
                            <p>Status: <strong>Pending Processing</strong></p>
                            <p>Reference: <strong>$ref</strong></p>
                            <p>You will be notified once the transaction is complete.</p>",
            'sms_message' => "Dear $name, your withdrawal of KES $amount has been received and is being processed. Ref: $ref"
        ],
        'payment_request' => [
            'email_subject' => 'Payment Action Required',
            'email_body' => "<p>Dear <strong>$name</strong>,</p>
                            <p>A payment request of <strong>KES $amount</strong> has been initiated via M-Pesa.</p>
                            <p>Please check your phone for an STK push and enter your M-Pesa PIN to authorize.</p>
                            <p>Reference: <strong>$ref</strong></p>",
            'sms_message' => "Dear $name, please check your phone for an M-Pesa STK push of KES $amount. Ref: $ref"
        ],
        'loan_applied' => [
            'email_subject' => 'Loan Application Received',
            'email_body' => "<p>Dear <strong>$name</strong>,</p>
                            <p>Your loan application for <strong>KES $amount</strong> has been received and is currently under review.</p>
                            <p>Reference: <strong>$ref</strong></p>
                            <p>We will notify you once a decision is made.</p>",
            'sms_message' => "Dear $name, your loan application of KES $amount has been received and is being reviewed. Ref: $ref"
        ],
        'loan_approved' => [
            'email_subject' => 'Loan Application Approved',
            'email_body' => "<p>Dear <strong>$name</strong>,</p>
                            <p>We are pleased to inform you that your loan application for <strong>KES $amount</strong> has been <strong>Approved</strong>.</p>
                            <p>Reference: <strong>$ref</strong></p>
                            <p>The funds are being queued for disbursement to your wallet.</p>",
            'sms_message' => "Congratulations $name! Your loan for KES $amount has been APPROVED. Ref: $ref"
        ],
        'loan_rejected' => [
            'email_subject' => 'Loan Application Update',
            'email_body' => "<p>Dear <strong>$name</strong>,</p>
                            <p>We regret to inform you that your loan application for <strong>KES $amount</strong> was not approved at this time.</p>
                            <p>Reason: <strong>" . ($data['rejection_reason'] ?? 'Not specified') . "</strong></p>
                            <p>Please contact the office for more details.</p>",
            'sms_message' => "Dear $name, your loan application for KES $amount was not approved. Reason: " . ($data['rejection_reason'] ?? 'Contact Office')
        ],
        'deposit_success' => [
            'email_subject' => 'Deposit Confirmed',
            'email_body' => "<p>Dear <strong>$name</strong>,</p>
                            <p>Your deposit of <strong>KES $amount</strong> has been received and credited to your account.</p>
                            <p>Reference: <strong>$ref</strong></p>
                            <p>Thank you for your contribution.</p>",
            'sms_message' => "Dear $name, your deposit of KES $amount has been confirmed. Ref: $ref"
        ],
        'loan_disbursed' => [
            'email_subject' => 'Loan Funds Disbursed',
            'email_body' => "<p>Dear <strong>$name</strong>,</p>
                            <p>Funds for your loan <strong>$ref</strong> amounting to <strong>KES $amount</strong> have been disbursed to your wallet.</p>
                            <p>You can now withdraw the funds at your convenience.</p>",
            'sms_message' => "Dear $name, KES $amount for loan $ref has been disbursed to your wallet. You can withdraw now."
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
/**
 * Add a persistence notification to the database (and optionally send email)
 */
function add_notification($member_id, $title, $message, $type = 'info', $link = null) {
    global $conn;
    
    // Use the existing sendEmailWithNotification to handle both DB insertion and email
    // This ensures consistency across the platform.
    require_once __DIR__ . '/email.php';
    
    // Build body for email if needed
    $body = "<strong>$title</strong><br><br>$message";
    if ($link) {
        $full_link = BASE_URL . '/' . $link;
        $body .= "<br><br><a href='$full_link' style='background:#D0F35D; padding:10px 20px; border-radius:10px; color:#0F392B; text-decoration:none; font-weight:bold;'>View Details</a>";
    }

    // Fetch member email
    $stmt = $conn->prepare("SELECT email FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $email = $stmt->get_result()->fetch_assoc()['email'] ?? null;
    $stmt->close();

    if ($email) {
        return sendEmail($email, $title, $body, $member_id);
    }
    
    // Fallback if no email: JUST insert into DB notifications
    $to_role = 'member';
    $user_type = 'member';
    $sql = "INSERT INTO notifications 
            (member_id, user_id, user_type, to_role, title, message, status, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'unread', 0, NOW())";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iiisss", $member_id, $member_id, $user_type, $to_role, $title, $message);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    return false;
}


/**
 * Add a persistence notification to the database for Admins
 */
function add_admin_notification($title, $message, $to_role = 'all', $link = null, $user_id = null) {
    global $conn;
    
    $user_type = 'admin';
    
    // Add link to message if provided
    if ($link) {
        $full_link = BASE_URL . '/' . ltrim($link, '/');
        $message .= "<br><br><a href='$full_link' style='background:#D0F35D; padding:5px 10px; border-radius:10px; color:#0F392B; text-decoration:none; font-weight:bold; font-size:0.8rem; display:inline-block;'>View Action</a>";
    }

    $sql = "INSERT INTO notifications 
            (user_id, user_type, to_role, title, message, status, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, 'unread', 0, NOW())";

    if ($stmt = $conn->prepare($sql)) {
        $uid = $user_id ? (int)$user_id : 0; // 0 or null represents all admins if to_role is not specific to an ID
        $stmt->bind_param("issss", $uid, $user_type, $to_role, $title, $message);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    return false;
}
