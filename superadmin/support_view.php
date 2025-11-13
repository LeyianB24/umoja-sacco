<?php
// superadmin/support_view.php
// View + reply to a support ticket (Superadmin)

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/email.php';
require_once __DIR__ . '/../inc/header.php';

require_superadmin();

// Provide a safe fallback if inc/email.php did not define sendEmail()
// This prevents "undefined function" errors and uses PHP mail() with simple HTML headers.
// If your project has a mailer utility, prefer defining sendEmail() there instead.
if (!function_exists('sendEmail')) {
    function sendEmail(string $to, string $subject, string $htmlBody): bool
    {
        // Basic headers for HTML email; adapt From as needed for your environment.
        $fromDomain = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $from = 'no-reply@' . preg_replace('/^www\./', '', $fromDomain);
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $from . "\r\n";

        // Suppress warnings from mail() and return boolean success/failure.
        return @mail($to, $subject, $htmlBody, $headers);
    }
}

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];


// ---------------------------------------------
// 1. Validate ticket ID
// ---------------------------------------------
$support_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($support_id < 1) die("Invalid Ticket ID");


// ---------------------------------------------
// 2. Fetch the ticket
// ---------------------------------------------
$sql = "
    SELECT 
        s.*,
        a.full_name AS admin_name,
        a.email AS admin_email,
        m.full_name AS member_name,
        m.email AS member_email
    FROM support_tickets s
    LEFT JOIN admins a  ON s.member_id = 0 AND s.admin_id = a.admin_id
    LEFT JOIN members m ON s.member_id = m.member_id
    WHERE s.support_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $support_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) die("Ticket not found.");


// Who created the ticket?
$creator_is_member = ($ticket['member_id'] > 0);

$creator_name  = $creator_is_member ? $ticket['member_name'] : $ticket['admin_name'];
$creator_email = $creator_is_member ? $ticket['member_email'] : $ticket['admin_email'];


// ---------------------------------------------
// 3. Handle Replies
// ---------------------------------------------
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reply_msg  = trim($_POST['reply_message']);
    $new_status = $_POST['status'] ?? "Pending";

    if ($reply_msg === "") {
        $error = "Reply cannot be empty.";
    } else {

        // ---------------------------------------------
        // 3A. Insert reply into support_replies
        // ---------------------------------------------
        $sql = "
            INSERT INTO support_replies 
            (support_id, sender_type, sender_id, message, user_role)
            VALUES (?, 'admin', ?, ?, 'superadmin')
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $support_id, $admin_id, $reply_msg);
        $stmt->execute();

        // ---------------------------------------------
        // 3B. Update ticket status
        // ---------------------------------------------
        $sql2 = "UPDATE support_tickets SET status=? WHERE support_id=?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("si", $new_status, $support_id);
        $stmt2->execute();

        // ---------------------------------------------
        // 3C. Insert Notification (correct structure)
        // ---------------------------------------------
        $notify_role = $creator_is_member ? "member" : "admin";
        $notify_user = $creator_is_member ? $ticket['member_id'] : $ticket['admin_id'];

        $sql3 = "
            INSERT INTO notifications 
            (to_role, status, title, message, user_type, user_id)
            VALUES (?, 'unread', ?, ?, ?, ?)
        ";

        $stmt3 = $conn->prepare($sql3);

        $title = "Support Ticket #$support_id Updated";
        $body  = "Superadmin replied: $reply_msg";

        $stmt3->bind_param("ssssi", 
            $notify_role, 
            $title, 
            $body, 
            $notify_role, 
            $notify_user
        );
        $stmt3->execute();

        // ---------------------------------------------
        // 3D. Email the creator if email exists
        // ---------------------------------------------
        if (!empty($creator_email)) {
            sendEmail(
                $creator_email,
                "Support Ticket #$support_id Updated",
                nl2br($body)
            );
        }

        $success = "Reply sent successfully.";
    }
}


// ---------------------------------------------
// 4. Fetch all replies
// ---------------------------------------------
$sql = "
    SELECT 
        r.*, 
        a.full_name AS admin_name,
        m.full_name AS member_name
    FROM support_replies r
    LEFT JOIN admins a  ON r.sender_type='admin' AND r.sender_id = a.admin_id
    LEFT JOIN members m ON r.sender_type='member' AND r.sender_id = m.member_id
    WHERE r.support_id = ?
    ORDER BY r.created_at ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $support_id);
$stmt->execute();
$replies = $stmt->get_result();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Support Ticket #<?= $support_id ?> — Superadmin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">

<a href="<?= BASE_URL ?>/superadmin/support.php" class="btn btn-secondary mb-3">
    ← Back to Support
</a>

<div class="card p-4 mb-4">
    <h4>Support Ticket #<?= $ticket['support_id'] ?></h4>
    <p class="text-muted">Submitted by: <?= htmlspecialchars($creator_name) ?></p>

    <div class="mt-3">
        <strong>Subject:</strong>
        <div><?= htmlspecialchars($ticket['subject']) ?></div>
    </div>

    <div class="mt-3">
        <strong>Message:</strong>
        <div><?= nl2br(htmlspecialchars($ticket['message'])) ?></div>
    </div>

    <?php if ($ticket['attachment']): ?>
        <div class="mt-3">
            <strong>Attachment:</strong><br>
            <a href="<?= BASE_URL . '/' . $ticket['attachment'] ?>" 
               target="_blank" 
               class="btn btn-sm btn-primary">
                View Attachment
            </a>
        </div>
    <?php endif; ?>

    <div class="mt-3">
        <strong>Status:</strong>
        <span class="badge bg-info"><?= $ticket['status'] ?></span>
    </div>
</div>

<!-- Conversation -->
<div class="card p-4 mb-4">
    <h5>Conversation</h5>
    <hr>

    <?php if ($replies->num_rows === 0): ?>
        <p class="text-muted">No replies yet.</p>

    <?php else: ?>
        <?php while ($r = $replies->fetch_assoc()): ?>
            <div class="mb-3 p-3 border rounded">
                <strong>
                    <?= htmlspecialchars($r['admin_name'] ?: $r['member_name'] ?: 'User') ?>
                </strong><br>

                <?= nl2br(htmlspecialchars($r['message'])) ?><br>

                <small class="text-muted"><?= $r['created_at'] ?></small>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<!-- Reply Box -->
<div class="card p-4">
    <h5>Reply to This Ticket</h5>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <label class="form-label">Message</label>
        <textarea name="reply_message" class="form-control" rows="4" required></textarea>

        <label class="form-label mt-3">Update Status</label>
        <select name="status" class="form-select">
            <option value="Pending" <?= $ticket['status']=='Pending'?'selected':'' ?>>Pending</option>
            <option value="Open" <?= $ticket['status']=='Open'?'selected':'' ?>>Open</option>
            <option value="Closed" <?= $ticket['status']=='Closed'?'selected':'' ?>>Closed</option>
        </select>

        <button class="btn btn-success mt-3">Send Reply</button>
    </form>
</div>

</body>
</html>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>