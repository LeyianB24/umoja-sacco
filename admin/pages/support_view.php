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

// Mark related messages as read
$ticket_subject_search = "Ticket #$support_id";
$conn->query("UPDATE messages SET is_read = 1 WHERE to_admin_id = $admin_id AND subject LIKE '%$ticket_subject_search%'");

// Fetch Conversation (Unified: support_replies + messages)
$conversation = [];

// 1. Get Support Replies
$sql_chat = "SELECT r.message, r.created_at, r.sender_type,
             CASE WHEN r.sender_type='admin' THEN a.full_name ELSE m.full_name END as sender_name,
             'support' as origin
             FROM support_replies r
             LEFT JOIN admins a ON r.sender_type='admin' AND r.sender_id = a.admin_id
             LEFT JOIN members m ON r.sender_type='member' AND r.sender_id = m.member_id
             WHERE r.support_id = ?";
$stmt_chat = $db->prepare($sql_chat);
$stmt_chat->bind_param("i", $support_id);
$stmt_chat->execute();
$res_chat = $stmt_chat->get_result();
while($row = $res_chat->fetch_assoc()) { $conversation[] = $row; }

// 2. Get Related General Messages
$sql_msg = "SELECT body as message, sent_at as created_at, 
            CASE WHEN from_admin_id IS NOT NULL THEN 'admin' ELSE 'member' END as sender_type,
            CASE WHEN from_admin_id IS NOT NULL THEN a.full_name ELSE m.full_name END as sender_name,
            'inbox' as origin
            FROM messages msg
            LEFT JOIN admins a ON msg.from_admin_id = a.admin_id
            LEFT JOIN members m ON msg.from_member_id = m.member_id
            WHERE (msg.to_admin_id = ? OR msg.from_admin_id = ?) 
            AND msg.subject LIKE ? 
            AND (msg.to_member_id = ? OR msg.from_member_id = ?)";

$search_term = "%Ticket #$support_id%";
$member_id_ref = (int)$ticket['member_id'];

$stmt_msg = $db->prepare($sql_msg);
$stmt_msg->bind_param("iisii", $admin_id, $admin_id, $search_term, $member_id_ref, $member_id_ref);
$stmt_msg->execute();
$res_msg = $stmt_msg->get_result();
while($row = $res_msg->fetch_assoc()) { $conversation[] = $row; }

// Sort conversation by creation time
usort($conversation, function($a, $b) {
    return strtotime($a['created_at']) <=> strtotime($b['created_at']);
});

function getInitials($name) { return strtoupper(substr($name ?? 'U', 0, 1)); }

$pageTitle = "Ticket View";
?>
<?php $layout->header($pageTitle); ?>

<style>
    .main-content { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
    @media (max-width: 991px) { .main-content { margin-left: 0; padding: 1.5rem; } }
    
    .chat-area { 
        border-radius: 24px; padding: 30px; max-height: 600px; 
        overflow-y: auto; background: rgba(15, 46, 37, 0.02); 
        border: 1px solid rgba(0,0,0,0.05);
        scrollbar-width: thin;
        scrollbar-color: var(--forest-light) transparent;
    }
    .msg-bubble { max-width: 85%; padding: 18px 24px; border-radius: 28px; margin-bottom: 20px; font-size: 0.95rem; line-height: 1.6; position: relative; }
    .msg-member { background: white; border: 1px solid var(--glass-border); border-bottom-left-radius: 4px; color: var(--text-primary); box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
    .msg-admin { background: var(--forest); color: white; border-bottom-right-radius: 4px; box-shadow: 0 12px 24px rgba(15, 46, 37, 0.15); }
    
    .chat-avatar { 
        width: 44px; height: 44px; border-radius: 14px; 
        display: flex; align-items: center; justify-content: center; 
        font-weight: 800; font-size: 0.9rem; flex-shrink: 0;
    }
    .reply-area {
        background: white; border-radius: 28px; padding: 25px;
        border: 1px solid var(--glass-border);
        box-shadow: 0 15px 35px rgba(0,0,0,0.05);
    }
    
    /* Animation for chat bubbles */
    .chat-bubble-anim { animation: fadeInUp 0.4s ease-out forwards; opacity: 0; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content">
        <?php $layout->topbar($pageTitle ?? 'Support Ticket'); ?>
        
        <div class="hp-hero mb-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <a href="support.php" class="text-white opacity-75 small fw-bold text-decoration-none mb-3 d-inline-block">
                        <i class="bi bi-arrow-left me-1"></i> BACK TO QUEUE
                    </a>
                    <h1 class="display-4 fw-800 mb-2">Ticket #<?= $support_id ?>.</h1>
                    <p class="opacity-75 fs-5">Managing member inquiry with <span class="text-lime fw-bold">secure communication</span>.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <span class="badge rounded-pill px-4 py-2 bg-white bg-opacity-10 text-white border border-white border-opacity-25 fw-bold">
                        <?= strtoupper($ticket['status']) ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="container-fluid py-4">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="glass-card p-4">
                        <div class="mb-4 pb-3 border-bottom">
                            <h4 class="fw-800 text-forest mb-1"><?= htmlspecialchars($ticket['subject']) ?></h4>
                            <p class="text-muted small mb-0">Original request filed by <span class="fw-bold text-forest"><?= htmlspecialchars($creator_name) ?></span></p>
                        </div>

                        <div class="chat-area mb-4 shadow-sm" id="chatBox">
                            <div class="d-flex gap-3 mb-4 chat-bubble-anim">
                                <div class="chat-avatar bg-forest-light text-lime"><?= getInitials($creator_name) ?></div>
                                <div class="msg-bubble msg-member">
                                    <div class="fw-bold mb-1 small text-forest"><?= htmlspecialchars($creator_name) ?></div>
                                    <?= nl2br(htmlspecialchars($ticket['message'])) ?>
                                    <?php if($ticket['attachment']): ?>
                                        <div class="mt-3 pt-3 border-top small">
                                            <a href="<?= BASE_URL . '/' . htmlspecialchars($ticket['attachment']) ?>" target="_blank" class="btn btn-light btn-sm rounded-pill px-3 fw-bold border">
                                                <i class="bi bi-file-earmark-arrow-down me-1 text-forest"></i> Download Attachment
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mt-2 text-muted" style="font-size: 0.65rem;">
                                        <?= date('H:i • d M', strtotime($ticket['created_at'])) ?>
                                    </div>
                                </div>
                            </div>

                            <?php foreach ($conversation as $index => $msg): 
                                $is_admin = ($msg['sender_type'] === 'admin');
                            ?>
                                <div class="d-flex gap-3 <?= $is_admin ? 'flex-row-reverse' : '' ?> mb-3 chat-bubble-anim" style="animation-delay: <?= $index * 0.1 ?>s">
                                    <div class="chat-avatar <?= $is_admin ? 'bg-lime text-forest' : 'bg-forest-light text-lime' ?>">
                                        <?= getInitials($msg['sender_name']) ?>
                                    </div>
                                    <div class="msg-bubble <?= $is_admin ? 'msg-admin' : 'msg-member' ?>">
                                        <?php if(!$is_admin): ?>
                                            <div class="fw-bold mb-1 small text-forest"><?= htmlspecialchars($msg['sender_name']) ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if(isset($msg['origin']) && $msg['origin'] === 'inbox'): ?>
                                            <div class="small fw-800 opacity-50 mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                                <i class="bi bi-mailbox me-1"></i> VIA GENERAL INBOX
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                        
                                        <div class="mt-2" style="font-size: 0.65rem; opacity: 0.7;">
                                            <?= date('H:i • d M', strtotime($msg['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="bg-forest bg-opacity-5 p-4 rounded-4 border">
                            <?php if($success): ?>
                                <div class="alert alert-success border-0 rounded-4 fw-bold small"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div>
                            <?php endif; ?>
                            <?php if($error): ?>
                                <div class="alert alert-danger border-0 rounded-4 fw-bold small"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?></div>
                            <?php endif; ?>
                            
                            <form method="post">
                                <textarea name="reply_message" class="form-control border-0 rounded-4 shadow-sm mb-4 p-3" rows="4" placeholder="Type your response here..." required></textarea>
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                    <div class="d-flex align-items-center gap-3 bg-white p-2 rounded-pill shadow-sm border px-3">
                                        <span class="small fw-800 text-muted text-uppercase ls-1">Update Status:</span>
                                        <select name="status" class="form-select form-select-sm border-0 bg-transparent fw-bold text-forest" style="width: auto; min-width: 120px;">
                                            <option value="Pending" <?= $ticket['status']=='Pending'?'selected':'' ?>>Pending</option>
                                            <option value="Open" <?= $ticket['status']=='Open'?'selected':'' ?>>Open (Active)</option>
                                            <option value="Closed" <?= $ticket['status']=='Closed'?'selected':'' ?>>Closed (Resolved)</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-lime rounded-pill px-5 py-2 fw-bold shadow-lg">
                                        Send Reply <i class="bi bi-send-fill ms-2"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="glass-card">
                        <h6 class="fw-800 text-uppercase small text-muted mb-4 ls-1">Meta-Information</h6>
                        <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
                            <span class="text-muted small">Category</span>
                            <span class="badge bg-forest bg-opacity-10 text-forest rounded-pill px-3"><?= ucfirst($ticket['category']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
                            <span class="text-muted small">Submitted On</span>
                            <span class="fw-800 text-forest small"><?= date('d M, Y', strtotime($ticket['created_at'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Ticket Reference</span>
                            <span class="fw-800 text-forest small">#US-<?= $support_id ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php $layout->footer(); ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const chatBox = document.getElementById('chatBox');
        if(chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
            // Add a slight delay to trigger animations sequentially if needed
        }
    });
</script>
</body>
</html>
