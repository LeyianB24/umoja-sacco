<?php
// public/support.php
// Unified support ticket page for all roles (members/admins/accounts)

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/email.php';

if (empty($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

$role      = $_SESSION['role'];
$admin_id  = $_SESSION['admin_id'] ?? null;
$member_id = $_SESSION['member_id'] ?? null;

// Dashboard redirect
$dashboard = match ($role) {
    'superadmin' => BASE_URL . '/superadmin/dashboard.php',
    'manager'    => BASE_URL . '/manager/dashboard.php',
    'accountant' => BASE_URL . '/accountant/dashboard.php',
    'member'     => BASE_URL . '/member/dashboard.php',
    default      => BASE_URL . '/public/login.php'
};

$success = "";
$error = "";


// -------------------------------------------------------
// PROCESS TICKET SUBMISSION
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($subject === '' || $message === '') {
        $error = "Please fill in all required fields.";
    } else {

        // ---------------- Attachment ----------------
        $attachmentPath = null;

        if (!empty($_FILES['attachment']['name'])) {

            $fileName = time() . '_' . basename($_FILES['attachment']['name']);
            $dir = __DIR__ . "/uploads/support/";

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $target = $dir . $fileName;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
                $attachmentPath = "public/uploads/support/" . $fileName;
            }
        }

        // ---------------- Assign to Superadmin ----------------
        $super = $conn->query("SELECT admin_id, email FROM admins WHERE role='superadmin' LIMIT 1")
                      ->fetch_assoc();

        $assigned_admin = $super['admin_id'] ?? 1;
        $super_email    = $super['email'] ?? 'admin@example.com';


        // ---------------- Insert into support_tickets ----------------
        // support_tickets:
        // admin_id | member_id | subject | message | status | attachment
        $sql = "
            INSERT INTO support_tickets (admin_id, member_id, subject, message, status, attachment)
            VALUES (?, ?, ?, ?, 'Pending', ?)
        ";

        $stmt = $conn->prepare($sql);

        // For non-members: member_id must be 0
        $ticket_member_id = $member_id ?: 0;

        $stmt->bind_param("iisss",
            $assigned_admin,
            $ticket_member_id,
            $subject,
            $message,
            $attachmentPath
        );
        $stmt->execute();

        $ticket_id = $stmt->insert_id;


        // ---------------- Insert into notifications ----------------
        // notifications table requires:
        // to_role, user_type, user_id, status, title, message

        $notif_sql = "
            INSERT INTO notifications (to_role, user_type, user_id, status, title, message)
            VALUES ('superadmin', 'admin', ?, 'unread', ?, ?)
        ";

        $stmt2 = $conn->prepare($notif_sql);
        $stmt2->bind_param("iss",
            $assigned_admin,
            $subject,
            $message
        );
        $stmt2->execute();


        // ---------------- Send email to superadmin ----------------
        sendEmail(
            $super_email,
            "New Support Ticket #$ticket_id",
            "
                A new support ticket has been submitted.<br><br>
                <b>Subject:</b> $subject <br>
                <b>Message:</b><br>" . nl2br($message)
        );

        $success = "Your support ticket has been submitted successfully.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Support – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSET_BASE ?>/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">

<a href="<?= $dashboard ?>" class="btn btn-secondary mb-3">← Back to Dashboard</a>

<div class="card p-4" style="max-width:650px;margin:auto;">
    <h4 class="mb-3">Submit Support Ticket</h4>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <label class="form-label mt-3">Select Support Category</label>
        <select name="subject" class="form-select" required>
            <option value="">-- Select Issue --</option>
            <option>Account Access</option>
            <option>Loan Issue</option>
            <option>Repayment Error</option>
            <option>Contribution Issue</option>
            <option>Withdrawal Issue</option>
            <option>MPESA Payment Issue</option>
            <option>Profile Update Issue</option>
            <option>System Bug</option>
            <option>Manager/Accountant Assistance</option>
            <option>General Inquiry</option>
            <option>Other</option>
        </select>

        <label class="form-label mt-3">Message</label>
        <textarea name="message" class="form-control" rows="5" required></textarea>

        <label class="form-label mt-3">Attachment (optional)</label>
        <input type="file" name="attachment" class="form-control">

        <button class="btn btn-success mt-3 w-100">Submit Ticket</button>
    </form>
</div>

</body>
</html>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>