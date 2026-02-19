<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/email.php';

// Auth Check
require_admin();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');
require_permission();

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['full_name'] ?? 'IT Support'; 
$db = $conn;

// Fetch Ticket
$support_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($support_id < 1) { header("Location: support.php"); exit; }

$sql = "SELECT s.*, m.full_name AS member_name, m.email AS member_email, 
               a.full_name AS admin_name, a.email AS admin_email
        FROM support_tickets s
        LEFT JOIN members m ON s.member_id = m.member_id
        LEFT JOIN admins a  ON s.member_id = 0 AND s.admin_id = a.admin_id
        WHERE s.support_id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $support_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) { header("Location: support.php?msg=NotFound"); exit; }

// GATEKEEPER: Strict Role Access Control
$my_role_id = (int)($_SESSION['role_id'] ?? 0);
if ($my_role_id !== 1 && (int)$ticket['assigned_role_id'] !== $my_role_id) {
    header("Location: support.php?error=unauthorized_access");
    exit;
}

$creator_is_member = ($ticket['member_id'] > 0);
$creator_name      = $creator_is_member ? $ticket['member_name'] : $ticket['admin_name'];
$creator_email     = $creator_is_member ? $ticket['member_email'] : $ticket['admin_email'];
$member_id_target  = $ticket['member_id']; 

// Handle Reply Logic
$success = ""; $error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reply_msg  = trim($_POST['reply_message'] ?? '');
    $new_status = $_POST['status'] ?? "Pending";

    if ($reply_msg === "") {
        $error = "Reply cannot be empty.";
    } else {
        $db->begin_transaction();
        try {
            // 1. Record the reply
            $stmt_rep = $db->prepare("INSERT INTO support_replies (support_id, sender_type, sender_id, message, user_role) VALUES (?, 'admin', ?, ?, 'admin')");
            $stmt_rep->bind_param("iis", $support_id, $admin_id, $reply_msg);
            $stmt_rep->execute();

            // 2. Update Ticket Status
            $stmt_stat = $db->prepare("UPDATE support_tickets SET status = ?, is_resolved = ? WHERE support_id = ?");
            $is_res_flag = ($new_status === 'Closed') ? 1 : 0;
            $stmt_stat->bind_param("sii", $new_status, $is_res_flag, $support_id);
            $stmt_stat->execute();

            // 3. Notify Member (In-App + Message + Email)
            if ($creator_is_member) {
                // a) In-App Notification
                $notif_title = "Update: Ticket #$support_id";
                $notif_msg = "Admin has responded to your ticket. Status: $new_status";
                $stmt_notif = $db->prepare("INSERT INTO notifications (member_id, title, message, status, user_type, user_id, created_at) VALUES (?, ?, ?, 'unread', 'member', ?, NOW())");
                $stmt_notif->bind_param("issi", $member_id_target, $notif_title, $notif_msg, $member_id_target);
                $stmt_notif->execute();

                // b) Insert into Messages (Member Inbox)
                $msg_subject = "Re: Ticket #$support_id - " . substr($ticket['subject'], 0, 100);
                $msg_body = $reply_msg;
                $stmt_msg = $db->prepare("INSERT INTO messages (from_admin_id, to_member_id, subject, body, sent_at, is_read) VALUES (?, ?, ?, ?, NOW(), 0)");
                $stmt_msg->bind_param("iiss", $admin_id, $member_id_target, $msg_subject, $msg_body);
                $stmt_msg->execute();

                // c) Send Email Notification
                if ($creator_email) {
                    $email_body = "
                        <h3 style='color:#0F392B;'>Support Ticket Update</h3>
                        <p>Dear <strong>" . htmlspecialchars($creator_name) . "</strong>,</p>
                        <p>Our team has responded to your support ticket:</p>
                        <div style='background:#f1f5f9; padding:20px; border-radius:8px; border-left:4px solid #39B54A; margin:15px 0;'>
                            <p style='margin:0 0 8px;'><strong>Ticket #$support_id</strong> — " . htmlspecialchars($ticket['subject']) . "</p>
                            <p style='margin:0 0 8px; color:#475569;'>Status: <strong>$new_status</strong></p>
                            <hr style='border:none; border-top:1px solid #e2e8f0; margin:12px 0;'>
                            <p style='margin:0; color:#334155;'>" . nl2br(htmlspecialchars($reply_msg)) . "</p>
                        </div>
                        <p>You can view the full conversation in your <a href='" . SITE_URL . "/member/pages/support.php' style='color:#39B54A; font-weight:600;'>Support Center</a> or check your <a href='" . SITE_URL . "/member/pages/messages.php' style='color:#39B54A; font-weight:600;'>Messages</a>.</p>
                    ";
                    sendEmailWithNotification(
                        $creator_email,
                        "Ticket #$support_id Update — " . SITE_NAME,
                        $email_body,
                        $member_id_target
                    );
                }
            }

            $db->commit();
            header("Location: support_view.php?id=$support_id&msg=success");
            exit;
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'success') $success = "Reply transmitted successfully.";

// Fetch Conversation
$conversation = [];
$sql_chat = "SELECT r.message, r.created_at, r.sender_type,
             CASE WHEN r.sender_type='admin' THEN a.full_name ELSE m.full_name END as sender_name
             FROM support_replies r
             LEFT JOIN admins a ON r.sender_type='admin' AND r.sender_id = a.admin_id
             LEFT JOIN members m ON r.sender_type='member' AND r.sender_id = m.member_id
             WHERE r.support_id = ? ORDER BY r.created_at ASC";
$stmt_chat = $db->prepare($sql_chat);
$stmt_chat->bind_param("i", $support_id);
$stmt_chat->execute();
$res_chat = $stmt_chat->get_result();
while($row = $res_chat->fetch_assoc()) { $conversation[] = $row; }

function getInitials($name) { return strtoupper(substr($name ?? 'U', 0, 1)); }

$pageTitle = "Ticket View";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket #<?= $support_id ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (() => {
            const saved = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', saved);
        })();
    </script>
    
    <style>
        :root {
            --hope-bg: #f3f4f6;
            --hope-green-dark: #102a1e;
            --hope-lime: #bef264;
            --hope-border: #e5e7eb;
        }
        
        body { background: var(--hope-bg); color: #111827; font-family: 'Plus Jakarta Sans', sans-serif; }
        .hope-card { background: #fff; border-radius: 28px; border: 1px solid var(--hope-border); box-shadow: 0 4px 20px rgba(0,0,0,0.02); overflow: hidden; }
        .chat-area { background: #fafafa; border-radius: 20px; padding: 25px; max-height: 500px; overflow-y: auto; }
        .msg-bubble { max-width: 80%; padding: 14px 20px; border-radius: 20px; margin-bottom: 20px; font-size: 0.92rem; }
        .msg-member { background: #fff; color: #374151; border: 1px solid var(--hope-border); border-bottom-left-radius: 4px; margin-right: auto; }
        .msg-admin { background: var(--hope-green-dark); color: #fff; border-bottom-right-radius: 4px; margin-left: auto; }
        .avatar-circle { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; }
        .btn-hope-lime { background: var(--hope-lime); color: var(--hope-green-dark); border-radius: 50px; font-weight: 700; padding: 12px 30px; border: none; }
        .main-content { margin-left: 280px; transition: margin-left 0.3s ease; }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content">
        <?php $layout->topbar($pageTitle ?? 'Support Ticket'); ?>
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <a href="support.php" class="text-decoration-none text-muted small fw-bold"><i class="bi bi-chevron-left"></i> BACK TO QUEUE</a>
                    <h2 class="fw-bold mt-2">Ticket #<?= $support_id ?></h2>
                </div>
                <div class="text-end">
                    <span class="badge rounded-pill px-3 py-2 bg-white  border fw-bold"><?= strtoupper($ticket['status']) ?></span>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="hope-card p-4">
                        <div class="mb-4 pb-3 border-bottom">
                            <h4 class="fw-bold "><?= htmlspecialchars($ticket['subject']) ?></h4>
                            <p class="text-muted small mb-0">Original request filed by <?= htmlspecialchars($creator_name) ?></p>
                        </div>

                        <div class="chat-area mb-4" id="chatBox">
                            <div class="d-flex gap-3 mb-4">
                                <div class="avatar-circle bg-light border "><?= getInitials($creator_name) ?></div>
                                <div class="msg-bubble msg-member shadow-sm">
                                    <?= nl2br(htmlspecialchars($ticket['message'])) ?>
                                    <?php if($ticket['attachment']): ?>
                                        <div class="mt-2 pt-2 border-top small">
                                            <a href="<?= BASE_URL . '/' . htmlspecialchars($ticket['attachment']) ?>" target="_blank" class="text-primary fw-bold">
                                                <i class="bi bi-file-earmark-arrow-down"></i> Download Attachment
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php foreach ($conversation as $msg): 
                                $is_admin = ($msg['sender_type'] === 'admin');
                            ?>
                                <div class="d-flex gap-3 <?= $is_admin ? 'flex-row-reverse' : '' ?> mb-3">
                                    <div class="avatar-circle <?= $is_admin ? 'bg-success text-white' : 'bg-light border' ?>">
                                        <?= getInitials($msg['sender_name']) ?>
                                    </div>
                                    <div class="msg-bubble <?= $is_admin ? 'msg-admin' : 'msg-member shadow-sm' ?>">
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="bg-light p-4 rounded-4">
                            <?php if($success): ?>
                                <div class="alert alert-success border-0 rounded-3 small"><?= $success ?></div>
                            <?php endif; ?>
                            <?php if($error): ?>
                                <div class="alert alert-danger border-0 rounded-3 small"><?= $error ?></div>
                            <?php endif; ?>
                            
                            <form method="post">
                                <textarea name="reply_message" class="form-control border-0 rounded-4 shadow-sm mb-3 p-3" rows="4" placeholder="Type your response..." required></textarea>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="small fw-bold text-muted">Status:</span>
                                        <select name="status" class="form-select form-select-sm border-0 shadow-sm rounded-pill px-3">
                                            <option value="Pending" <?= $ticket['status']=='Pending'?'selected':'' ?>>Pending</option>
                                            <option value="Open" <?= $ticket['status']=='Open'?'selected':'' ?>>Open</option>
                                            <option value="Closed" <?= $ticket['status']=='Closed'?'selected':'' ?>>Closed</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-hope-lime">Send Reply <i class="bi bi-send ms-2"></i></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="hope-card p-4">
                        <h6 class="fw-bold text-uppercase small text-muted mb-4">Ticket Details</h6>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted small">Category</span>
                            <span class="fw-bold small text-primary"><?= ucfirst($ticket['category']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted small">Submitted</span>
                            <span class="fw-bold small"><?= date('d M, Y', strtotime($ticket['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php $layout->footer(); ?>
        </div>
    
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const chatBox = document.getElementById('chatBox');
        if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    });
</script>
</body>
</html>
