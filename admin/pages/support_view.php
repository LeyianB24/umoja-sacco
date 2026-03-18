<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
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

if (!function_exists('getInitials')) {
    function getInitials($name) {
        $parts = explode(' ', trim($name ?? 'U'));
        $initials = strtoupper(substr($parts[0], 0, 1));
        if (count($parts) > 1) $initials .= strtoupper(substr(end($parts), 0, 1));
        return $initials;
    }
}

$statusConfig = [
    'Pending' => ['color' => '#F59E0B', 'bg' => '#FEF3C7', 'icon' => 'bi-clock-history'],
    'Open'    => ['color' => '#3B82F6', 'bg' => '#DBEAFE', 'icon' => 'bi-lightning-charge-fill'],
    'Closed'  => ['color' => '#10B981', 'bg' => '#D1FAE5', 'icon' => 'bi-check-circle-fill'],
];
$currentStatus = $ticket['status'];
$statusCfg = $statusConfig[$currentStatus] ?? $statusConfig['Pending'];

$pageTitle = "Ticket #$support_id";
?>
<?php $layout->header($pageTitle); ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

<style>
/* ─── Base Override ─── */
*, body, .main-content-wrapper, .container-fluid {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ─── Hero Banner ─── */
.sv-hero {
    background: linear-gradient(135deg, #0F392B 0%, #1a5c43 50%, #0d2e22 100%);
    border-radius: 20px;
    padding: 36px 40px;
    position: relative;
    overflow: hidden;
    margin-bottom: 28px;
}
.sv-hero::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 280px; height: 280px;
    background: radial-gradient(circle, rgba(57,181,74,0.18) 0%, transparent 70%);
    border-radius: 50%;
}
.sv-hero::after {
    content: '';
    position: absolute;
    bottom: -40px; left: 30%;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(57,181,74,0.1) 0%, transparent 70%);
    border-radius: 50%;
}
.sv-hero-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: rgba(255,255,255,0.65);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 1.2px;
    text-decoration: none;
    text-transform: uppercase;
    margin-bottom: 16px;
    transition: color 0.2s;
}
.sv-hero-back:hover { color: #A3E635; }
.sv-hero h1 {
    font-size: 2.4rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 6px;
    line-height: 1.1;
    letter-spacing: -0.5px;
}
.sv-hero-sub {
    color: rgba(255,255,255,0.6);
    font-size: 0.9rem;
    font-weight: 500;
    margin: 0;
}
.sv-hero-sub strong { color: #A3E635; font-weight: 700; }
.sv-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 20px;
    border-radius: 100px;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    border: 1.5px solid rgba(255,255,255,0.2);
    backdrop-filter: blur(8px);
    background: rgba(255,255,255,0.08);
    color: #fff;
}
.sv-status-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #A3E635;
    box-shadow: 0 0 0 3px rgba(163,230,53,0.3);
    animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot {
    0%, 100% { box-shadow: 0 0 0 3px rgba(163,230,53,0.3); }
    50% { box-shadow: 0 0 0 6px rgba(163,230,53,0.12); }
}

/* ─── Cards ─── */
.sv-card {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #E8F0ED;
    box-shadow: 0 2px 20px rgba(15,57,43,0.06);
    overflow: hidden;
}
.sv-card-header {
    padding: 22px 28px 18px;
    border-bottom: 1px solid #F0F7F4;
    display: flex;
    align-items: flex-start;
    gap: 14px;
}
.sv-card-body { padding: 24px 28px; }
.sv-ticket-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #0F392B, #2d7a56);
    display: flex; align-items: center; justify-content: center;
    color: #A3E635;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.sv-ticket-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: #0F392B;
    margin: 0 0 3px;
    line-height: 1.3;
}
.sv-ticket-meta {
    font-size: 0.78rem;
    color: #7a9e8e;
    font-weight: 500;
    margin: 0;
}
.sv-ticket-meta strong { color: #0F392B; font-weight: 700; }

/* ─── Chat Area ─── */
.sv-chat {
    background: #F7FBF9;
    border-radius: 14px;
    padding: 20px;
    max-height: 500px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 16px;
    scroll-behavior: smooth;
}
.sv-chat::-webkit-scrollbar { width: 4px; }
.sv-chat::-webkit-scrollbar-track { background: transparent; }
.sv-chat::-webkit-scrollbar-thumb { background: #c8ddd6; border-radius: 4px; }

/* ─── Chat Bubbles ─── */
.sv-bubble-wrap {
    display: flex;
    gap: 10px;
    align-items: flex-end;
    animation: bubbleIn 0.35s ease both;
}
.sv-bubble-wrap.is-admin { flex-direction: row-reverse; }
@keyframes bubbleIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

.sv-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem;
    font-weight: 800;
    flex-shrink: 0;
    letter-spacing: 0.5px;
}
.sv-avatar-member {
    background: linear-gradient(135deg, #0F392B, #2d7a56);
    color: #A3E635;
}
.sv-avatar-admin {
    background: linear-gradient(135deg, #A3E635, #6fba1b);
    color: #0F392B;
}

.sv-bubble {
    max-width: 72%;
    padding: 13px 16px;
    border-radius: 16px;
    font-size: 0.875rem;
    line-height: 1.65;
    color: #1e3a2f;
    position: relative;
}
.sv-bubble-member {
    background: #fff;
    border: 1px solid #E0EDE7;
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 6px rgba(15,57,43,0.07);
}
.sv-bubble-admin {
    background: linear-gradient(135deg, #0F392B, #1a5c43);
    color: #fff;
    border-bottom-right-radius: 4px;
    box-shadow: 0 4px 16px rgba(15,57,43,0.25);
}
.sv-bubble-admin .sv-bubble-sender { color: rgba(255,255,255,0.5); }
.sv-bubble-admin .sv-bubble-time { color: rgba(255,255,255,0.45); }

.sv-bubble-sender {
    font-size: 0.7rem;
    font-weight: 800;
    color: #7a9e8e;
    letter-spacing: 0.3px;
    margin-bottom: 5px;
    text-transform: uppercase;
}
.sv-bubble-time {
    font-size: 0.65rem;
    color: #a0b8b0;
    font-weight: 500;
    margin-top: 7px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.sv-inbox-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.62rem;
    font-weight: 700;
    color: rgba(255,255,255,0.5);
    background: rgba(255,255,255,0.08);
    border-radius: 6px;
    padding: 2px 7px;
    margin-bottom: 6px;
    letter-spacing: 0.4px;
    text-transform: uppercase;
}
.sv-inbox-badge-member {
    color: #7a9e8e;
    background: rgba(15,57,43,0.06);
}

/* Attachment */
.sv-attachment {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #E0EDE7;
}
.sv-attachment a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #0F392B;
    background: #F0F7F4;
    padding: 5px 12px;
    border-radius: 20px;
    text-decoration: none;
    border: 1px solid #C8DDD6;
    transition: all 0.2s;
}
.sv-attachment a:hover { background: #0F392B; color: #A3E635; }

/* ─── Reply Box ─── */
.sv-reply-box {
    background: #F7FBF9;
    border-radius: 16px;
    border: 1.5px solid #E0EDE7;
    padding: 20px 24px;
    margin-top: 20px;
    transition: border-color 0.2s;
}
.sv-reply-box:focus-within {
    border-color: #39B54A;
    box-shadow: 0 0 0 4px rgba(57,181,74,0.08);
}
.sv-reply-label {
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #7a9e8e;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.sv-reply-textarea {
    width: 100%;
    background: transparent;
    border: none;
    outline: none;
    resize: none;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.9rem;
    color: #0F392B;
    line-height: 1.7;
    font-weight: 500;
}
.sv-reply-textarea::placeholder { color: #a8c5bb; }
.sv-reply-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid #E0EDE7;
    gap: 12px;
    flex-wrap: wrap;
}
.sv-status-select-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
}
.sv-status-label {
    font-size: 0.7rem;
    font-weight: 800;
    color: #7a9e8e;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    white-space: nowrap;
}
.sv-status-select {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: 0.82rem;
    font-weight: 700;
    color: #0F392B;
    background: #fff;
    border: 1.5px solid #C8DDD6;
    border-radius: 10px;
    padding: 6px 14px;
    outline: none;
    cursor: pointer;
    transition: border-color 0.2s;
    -webkit-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%230F392B' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 28px;
}
.sv-status-select:focus { border-color: #39B54A; }

.sv-send-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #39B54A, #2d9a3c);
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 10px 24px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.85rem;
    font-weight: 800;
    letter-spacing: 0.2px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 14px rgba(57,181,74,0.35);
}
.sv-send-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(57,181,74,0.45);
}
.sv-send-btn:active { transform: translateY(0); }
.sv-send-btn i { font-size: 0.8rem; }

/* ─── Alerts ─── */
.sv-alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 0.82rem;
    font-weight: 600;
    margin-bottom: 16px;
}
.sv-alert-success { background: #D1FAE5; color: #065f46; border: 1px solid #A7F3D0; }
.sv-alert-error { background: #FEE2E2; color: #991b1b; border: 1px solid #FECACA; }
.sv-alert i { font-size: 1rem; flex-shrink: 0; }

/* ─── Sidebar Info Card ─── */
.sv-info-card {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #E8F0ED;
    box-shadow: 0 2px 20px rgba(15,57,43,0.06);
    overflow: hidden;
}
.sv-info-header {
    background: linear-gradient(135deg, #0F392B, #1a5c43);
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.sv-info-header-icon {
    width: 36px; height: 36px;
    background: rgba(163,230,53,0.15);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #A3E635;
    font-size: 0.95rem;
}
.sv-info-header h6 {
    color: rgba(255,255,255,0.55);
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    margin: 0 0 1px;
}
.sv-info-header p {
    color: #fff;
    font-size: 0.82rem;
    font-weight: 700;
    margin: 0;
}

.sv-info-body { padding: 8px 0; }
.sv-info-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 13px 24px;
    border-bottom: 1px solid #F0F7F4;
    gap: 12px;
}
.sv-info-row:last-child { border-bottom: none; }
.sv-info-row-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.75rem;
    color: #7a9e8e;
    font-weight: 600;
}
.sv-info-row-label i {
    width: 28px; height: 28px;
    background: #F0F7F4;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem;
    color: #0F392B;
}
.sv-info-row-val {
    font-size: 0.82rem;
    font-weight: 800;
    color: #0F392B;
    text-align: right;
}
.sv-cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #E8F5E9;
    color: #1a6b35;
    border-radius: 8px;
    padding: 4px 10px;
    font-size: 0.73rem;
    font-weight: 700;
}
.sv-ref-badge {
    font-family: 'Courier New', monospace;
    background: #F7FBF9;
    color: #0F392B;
    border: 1px solid #C8DDD6;
    border-radius: 8px;
    padding: 4px 10px;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.5px;
}

/* ─── Status Quick-Change Card ─── */
.sv-quick-status {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #E8F0ED;
    box-shadow: 0 2px 20px rgba(15,57,43,0.06);
    margin-top: 18px;
    overflow: hidden;
}
.sv-quick-status-header {
    padding: 16px 24px;
    border-bottom: 1px solid #F0F7F4;
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: #7a9e8e;
    display: flex;
    align-items: center;
    gap: 7px;
}
.sv-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 10px;
    font-size: 0.78rem;
    font-weight: 800;
}
.sv-conv-count {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 20px 24px;
    text-align: center;
}
.sv-conv-count .big-num {
    font-size: 2.4rem;
    font-weight: 800;
    color: #0F392B;
    line-height: 1;
}
.sv-conv-count .big-label {
    font-size: 0.73rem;
    color: #7a9e8e;
    font-weight: 600;
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

/* ─── Divider ─── */
.sv-day-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #a0b8b0;
    font-size: 0.67rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}
.sv-day-divider::before,
.sv-day-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #E0EDE7;
}

/* ─── Responsive ─── */
@media (max-width: 768px) {
    .sv-hero { padding: 24px 20px; }
    .sv-hero h1 { font-size: 1.8rem; }
    .sv-bubble { max-width: 88%; }
    .sv-card-body { padding: 16px; }
    .sv-reply-actions { flex-direction: column; align-items: stretch; }
    .sv-send-btn { width: 100%; justify-content: center; }
}
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

        <!-- ── Hero ── -->
        <div class="sv-hero">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <a href="support.php" class="sv-hero-back">
                        <i class="bi bi-arrow-left"></i> Back to Queue
                    </a>
                    <h1>Ticket #<?= $support_id ?>.</h1>
                    <p class="sv-hero-sub">
                        Filed by <strong><?= htmlspecialchars($creator_name) ?></strong>
                        &nbsp;·&nbsp; <?= htmlspecialchars(ucfirst($ticket['category'])) ?>
                        &nbsp;·&nbsp; <?= date('d M Y', strtotime($ticket['created_at'])) ?>
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <div class="sv-status-pill d-inline-flex">
                        <?php if($currentStatus !== 'Closed'): ?>
                            <span class="sv-status-dot"></span>
                        <?php else: ?>
                            <i class="bi bi-check-circle-fill" style="color:#A3E635; font-size:0.8rem;"></i>
                        <?php endif; ?>
                        <?= strtoupper($currentStatus) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Main Grid ── -->
        <div class="row g-4">

            <!-- LEFT: Conversation -->
            <div class="col-lg-8">
                <div class="sv-card">
                    <div class="sv-card-header">
                        <div class="sv-ticket-icon">
                            <i class="bi bi-ticket-detailed-fill"></i>
                        </div>
                        <div>
                            <p class="sv-ticket-title"><?= htmlspecialchars($ticket['subject']) ?></p>
                            <p class="sv-ticket-meta">Original request from <strong><?= htmlspecialchars($creator_name) ?></strong></p>
                        </div>
                    </div>
                    <div class="sv-card-body">

                        <!-- Chat Area -->
                        <div class="sv-chat" id="chatBox">

                            <div class="sv-day-divider">Start of conversation</div>

                            <!-- Opening Message -->
                            <div class="sv-bubble-wrap">
                                <div class="sv-avatar sv-avatar-member"><?= getInitials($creator_name) ?></div>
                                <div class="sv-bubble sv-bubble-member">
                                    <div class="sv-bubble-sender"><?= htmlspecialchars($creator_name) ?></div>
                                    <?= nl2br(htmlspecialchars($ticket['message'])) ?>
                                    <?php if($ticket['attachment']): ?>
                                        <div class="sv-attachment">
                                            <a href="<?= BASE_URL . '/' . htmlspecialchars($ticket['attachment']) ?>" target="_blank">
                                                <i class="bi bi-file-earmark-arrow-down"></i> Download Attachment
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <div class="sv-bubble-time">
                                        <i class="bi bi-clock" style="font-size:0.6rem;"></i>
                                        <?= date('H:i · d M Y', strtotime($ticket['created_at'])) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Replies -->
                            <?php foreach ($conversation as $index => $msg):
                                $is_admin = ($msg['sender_type'] === 'admin');
                                $is_inbox = isset($msg['origin']) && $msg['origin'] === 'inbox';
                            ?>
                                <div class="sv-bubble-wrap <?= $is_admin ? 'is-admin' : '' ?>" style="animation-delay: <?= $index * 0.07 ?>s">
                                    <div class="sv-avatar <?= $is_admin ? 'sv-avatar-admin' : 'sv-avatar-member' ?>">
                                        <?= getInitials($msg['sender_name']) ?>
                                    </div>
                                    <div class="sv-bubble <?= $is_admin ? 'sv-bubble-admin' : 'sv-bubble-member' ?>">
                                        <?php if(!$is_admin): ?>
                                            <div class="sv-bubble-sender"><?= htmlspecialchars($msg['sender_name']) ?></div>
                                        <?php endif; ?>
                                        <?php if($is_inbox): ?>
                                            <div class="<?= $is_admin ? 'sv-inbox-badge' : 'sv-inbox-badge sv-inbox-badge-member' ?>">
                                                <i class="bi bi-mailbox"></i> Via General Inbox
                                            </div>
                                        <?php endif; ?>
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                        <div class="sv-bubble-time">
                                            <i class="bi bi-clock" style="font-size:0.6rem;"></i>
                                            <?= date('H:i · d M Y', strtotime($msg['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Reply Form -->
                        <?php if($success): ?>
                            <div class="sv-alert sv-alert-success mt-4">
                                <i class="bi bi-check-circle-fill"></i> <?= $success ?>
                            </div>
                        <?php endif; ?>
                        <?php if($error): ?>
                            <div class="sv-alert sv-alert-error mt-4">
                                <i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?>
                            </div>
                        <?php endif; ?>

                        <form method="post">
                            <div class="sv-reply-box">
                                <div class="sv-reply-label">
                                    <i class="bi bi-reply-fill" style="color:#39B54A;"></i>
                                    Your Reply
                                </div>
                                <textarea
                                    name="reply_message"
                                    class="sv-reply-textarea"
                                    rows="4"
                                    placeholder="Type your response to this ticket..."
                                    required></textarea>
                                <div class="sv-reply-actions">
                                    <div class="sv-status-select-wrap">
                                        <span class="sv-status-label">Set Status</span>
                                        <select name="status" class="sv-status-select">
                                            <option value="Pending" <?= $ticket['status']=='Pending'?'selected':'' ?>>⏳ Pending</option>
                                            <option value="Open" <?= $ticket['status']=='Open'?'selected':'' ?>>⚡ Open (Active)</option>
                                            <option value="Closed" <?= $ticket['status']=='Closed'?'selected':'' ?>>✅ Closed (Resolved)</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="sv-send-btn">
                                        Send Reply <i class="bi bi-send-fill"></i>
                                    </button>
                                </div>
                            </div>
                        </form>

                    </div>
                </div>
            </div>

            <!-- RIGHT: Sidebar -->
            <div class="col-lg-4">

                <!-- Meta Info -->
                <div class="sv-info-card">
                    <div class="sv-info-header">
                        <div class="sv-info-header-icon">
                            <i class="bi bi-info-circle-fill"></i>
                        </div>
                        <div>
                            <h6>Ticket Details</h6>
                            <p>#US-<?= $support_id ?></p>
                        </div>
                    </div>
                    <div class="sv-info-body">
                        <div class="sv-info-row">
                            <div class="sv-info-row-label">
                                <i class="bi bi-tag-fill"></i>
                                Category
                            </div>
                            <div class="sv-cat-badge">
                                <i class="bi bi-circle-fill" style="font-size:0.45rem; color:#39B54A;"></i>
                                <?= ucfirst($ticket['category']) ?>
                            </div>
                        </div>
                        <div class="sv-info-row">
                            <div class="sv-info-row-label">
                                <i class="bi bi-calendar3"></i>
                                Submitted
                            </div>
                            <div class="sv-info-row-val"><?= date('d M, Y', strtotime($ticket['created_at'])) ?></div>
                        </div>
                        <div class="sv-info-row">
                            <div class="sv-info-row-label">
                                <i class="bi bi-hash"></i>
                                Reference
                            </div>
                            <span class="sv-ref-badge">#US-<?= $support_id ?></span>
                        </div>
                        <div class="sv-info-row">
                            <div class="sv-info-row-label">
                                <i class="bi bi-person-fill"></i>
                                Requester
                            </div>
                            <div class="sv-info-row-val" style="font-size:0.78rem;"><?= htmlspecialchars($creator_name) ?></div>
                        </div>
                        <div class="sv-info-row">
                            <div class="sv-info-row-label">
                                <i class="bi bi-circle-fill" style="font-size:0.55rem;"></i>
                                Status
                            </div>
                            <span class="sv-status-badge" style="background:<?= $statusCfg['bg'] ?>; color:<?= $statusCfg['color'] ?>;">
                                <i class="bi <?= $statusCfg['icon'] ?>" style="font-size:0.7rem;"></i>
                                <?= $currentStatus ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Conversation Count -->
                <div class="sv-quick-status mt-4">
                    <div class="sv-quick-status-header">
                        <i class="bi bi-chat-dots-fill" style="color:#39B54A;"></i>
                        Conversation
                    </div>
                    <div class="sv-conv-count">
                        <div class="big-num"><?= count($conversation) + 1 ?></div>
                        <div class="big-label">Total Messages</div>
                    </div>
                </div>

            </div>
        </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script>
// Auto-scroll chat to bottom on load
(function() {
    const box = document.getElementById('chatBox');
    if (box) box.scrollTop = box.scrollHeight;
})();
</script>
</body>
</html>