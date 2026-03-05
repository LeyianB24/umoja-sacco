<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../inc/functions.php';

// 1. AUTHENTICATION & SECURITY
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

$my_role = $_SESSION['role'];
$my_id = $my_role === 'member'
    ? ($_SESSION['member_id'] ?? 0)
    : ($_SESSION['admin_id'] ?? ($_SESSION['user_id'] ?? 0));

if ($my_role === 'member') {
    if (empty($_SESSION['member_pic'])) {
        $uPic = $conn->query("SELECT profile_pic FROM members WHERE member_id = $my_id")->fetch_assoc();
        $_SESSION['member_pic'] = $uPic['profile_pic'] ?? null;
    }
} else {
    $_SESSION['admin_pic'] = null;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$dashboard_url = match($my_role) {
    'superadmin' => '../superadmin/dashboard.php',
    'manager'    => '../manager/dashboard.php',
    'accountant' => '../accountant/dashboard.php',
    'admin'      => '../admin/pages/dashboard.php',
    'member'     => '../member/pages/dashboard.php',
    default      => 'login.php'
};
if (!empty($_GET['return_to'])) $dashboard_url = urldecode($_GET['return_to']);

// ── Helper Functions ──
function saveAttachment($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!array_key_exists($ext, $allowed) || $allowed[$ext] !== $mime) return null;
    $dir = __DIR__ . '/uploads/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $newName = uniqid('msg_', true) . '.' . $ext;
    return move_uploaded_file($file['tmp_name'], $dir . $newName) ? 'uploads/' . $newName : null;
}

function formatChatDate($datetime) {
    $time = strtotime($datetime);
    $date = date('Y-m-d', $time);
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    if ($date === $today) return 'Today';
    if ($date === $yesterday) return 'Yesterday';
    return date('M d, Y', $time);
}

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        \USMS\Http\ErrorHandler::abort(403, "Security violation: Invalid CSRF token.");
    }
    $recipient_role = $_POST['recipient_role'] ?? 'member';
    $target_id      = intval($_POST['target_id'] ?? 0);
    $body           = trim($_POST['body'] ?? '');
    $attachment     = saveAttachment($_FILES['attachment'] ?? null);
    $subject        = ($recipient_role === 'broadcast') ? 'Broadcast' : 'Chat';

    if ($recipient_role !== 'broadcast' && $target_id > 0) {
        $history_sql = match ($my_role) {
            'member' => ($recipient_role === 'admin')
                ? "SELECT subject FROM messages WHERE (from_member_id=$my_id AND to_admin_id=$target_id) OR (from_admin_id=$target_id AND to_member_id=$my_id) ORDER BY sent_at DESC LIMIT 1"
                : "SELECT subject FROM messages WHERE (from_member_id=$my_id AND to_member_id=$target_id) OR (from_member_id=$target_id AND to_member_id=$my_id) ORDER BY sent_at DESC LIMIT 1",
            default => ($recipient_role === 'admin')
                ? "SELECT subject FROM messages WHERE (from_admin_id=$my_id AND to_admin_id=$target_id) OR (from_admin_id=$target_id AND to_admin_id=$my_id) ORDER BY sent_at DESC LIMIT 1"
                : "SELECT subject FROM messages WHERE (from_admin_id=$my_id AND to_member_id=$target_id) OR (from_member_id=$target_id AND to_admin_id=$my_id) ORDER BY sent_at DESC LIMIT 1",
        };
        $history_res = $conn->query($history_sql);
        if ($history_res && $history_row = $history_res->fetch_assoc()) {
            $subject = $history_row['subject'] ?: 'Chat';
        }
    }

    if ($body || $attachment) {
        if ($recipient_role === 'broadcast' && $my_role !== 'member') {
            $target_grp = $_POST['broadcast_group'] ?? 'members';
            $conn->begin_transaction();
            try {
                if ($target_grp === 'members') {
                    $recipients = $conn->query("SELECT member_id FROM members WHERE status='active'");
                    $stmt = $conn->prepare("INSERT INTO messages (from_admin_id, to_member_id, subject, body, attachment, sent_at, is_read) VALUES (?, ?, 'Broadcast', ?, ?, NOW(), 0)");
                    while ($m = $recipients->fetch_assoc()) { $stmt->bind_param("iiss", $my_id, $m['member_id'], $body, $attachment); $stmt->execute(); }
                } elseif (in_array($target_grp, ['admin','manager','accountant','superadmin','all_admins'])) {
                    $where = ($target_grp === 'all_admins') ? "" : "WHERE role='$target_grp'";
                    $recipients = $conn->query("SELECT admin_id FROM admins $where");
                    $stmt = $conn->prepare("INSERT INTO messages (from_admin_id, to_admin_id, subject, body, attachment, sent_at, is_read) VALUES (?, ?, 'Admin Broadcast', ?, ?, NOW(), 0)");
                    while ($a = $recipients->fetch_assoc()) { if ($a['admin_id'] == $my_id) continue; $stmt->bind_param("iiss", $my_id, $a['admin_id'], $body, $attachment); $stmt->execute(); }
                }
                $conn->commit();
            } catch (Exception $e) { $conn->rollback(); }
            header("Location: messages.php"); exit;
        }
        $sql = match ($my_role) {
            'member' => ($recipient_role === 'admin') ? "INSERT INTO messages (from_member_id, to_admin_id, subject, body, attachment, sent_at) VALUES (?, ?, ?, ?, ?, NOW())" : "INSERT INTO messages (from_member_id, to_member_id, subject, body, attachment, sent_at) VALUES (?, ?, ?, ?, ?, NOW())",
            default  => ($recipient_role === 'admin') ? "INSERT INTO messages (from_admin_id, to_admin_id, subject, body, attachment, sent_at) VALUES (?, ?, ?, ?, ?, NOW())" : "INSERT INTO messages (from_admin_id, to_member_id, subject, body, attachment, sent_at) VALUES (?, ?, ?, ?, ?, NOW())",
        };
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisss", $my_id, $target_id, $subject, $body, $attachment);
        $stmt->execute();
        $return_param = !empty($_POST['return_to']) ? '&return_to=' . urlencode($_POST['return_to']) : '';
        header("Location: messages.php?chat_with=$target_id&role=$recipient_role" . $return_param); exit;
    }
}

// ── Fetch Threads ──
$threads = [];
$filterSQL = ($my_role === 'member')
    ? "SELECT m.*, CASE WHEN (m.from_admin_id IS NOT NULL OR m.to_admin_id IS NOT NULL) THEN 'admin' ELSE 'member' END AS partner_role, CASE WHEN (m.from_admin_id IS NOT NULL OR m.to_admin_id IS NOT NULL) THEN COALESCE(m.from_admin_id, m.to_admin_id) WHEN m.from_member_id = ? THEN m.to_member_id ELSE m.from_member_id END AS partner_id FROM messages m WHERE (m.from_member_id = ? OR m.to_member_id = ?) ORDER BY sent_at DESC"
    : "SELECT m.*, CASE WHEN (m.from_member_id IS NOT NULL OR m.to_member_id IS NOT NULL) THEN 'member' ELSE 'admin' END AS partner_role, CASE WHEN (m.from_member_id IS NOT NULL OR m.to_member_id IS NOT NULL) THEN COALESCE(m.from_member_id, m.to_member_id) WHEN m.from_admin_id = ? THEN m.to_admin_id ELSE m.from_admin_id END AS partner_id FROM messages m WHERE (m.from_admin_id = ? OR m.to_admin_id = ?) ORDER BY sent_at DESC";

$stmt = $conn->prepare($filterSQL);
$stmt->bind_param("iii", $my_id, $my_id, $my_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $pid = $row['partner_id'];
    $prole = $row['partner_role'];
    if (!$pid) continue;
    $key = $prole . '_' . $pid;
    if (!isset($threads[$key])) {
        if ($prole === 'admin') {
            $info = $conn->query("SELECT full_name FROM admins WHERE admin_id=$pid")->fetch_assoc();
            $name = $info['full_name'] ?? 'Admin User'; $pic = null;
        } else {
            $info = $conn->query("SELECT full_name, profile_pic FROM members WHERE member_id=$pid")->fetch_assoc();
            $name = $info['full_name'] ?? 'Member User'; $pic = $info['profile_pic'] ?? null;
        }
        $threads[$key] = ['id'=>$pid,'role'=>$prole,'name'=>$name,'pic'=>$pic,'last_msg'=>$row['body']?:'📎 Attachment','time'=>$row['sent_at'],'unread'=>0];
    }
    $isReceiver = ($my_role === 'member')
        ? ($row['to_member_id'] == $my_id && $row['from_member_id'] != $my_id && $row['from_admin_id'] != $my_id)
        : ($row['to_admin_id'] == $my_id && $row['from_admin_id'] != $my_id);
    if ($isReceiver && $row['is_read'] == 0) $threads[$key]['unread']++;
}

// ── Fetch Active Chat ──
$active_id   = intval($_GET['chat_with'] ?? 0);
$active_role = $_GET['role'] ?? null;
$messages    = [];
$is_broadcast = false;
$partner = null;

if ($active_id && $active_role) {
    $key = $active_role . '_' . $active_id;
    $partner = $threads[$key] ?? null;
    if (!$partner) {
        if ($active_role === 'admin') {
            $info = $conn->query("SELECT full_name FROM admins WHERE admin_id=$active_id")->fetch_assoc();
            $partner = ['name' => $info['full_name'] ?? 'Unknown Admin', 'pic' => null];
        } else {
            $info = $conn->query("SELECT full_name, profile_pic FROM members WHERE member_id=$active_id")->fetch_assoc();
            $partner = ['name' => $info['full_name'] ?? 'Unknown Member', 'pic' => $info['profile_pic'] ?? null];
        }
    }
    if (!is_array($partner)) $partner = ['name' => 'Unknown User', 'pic' => null];
    $partner['name'] = $partner['name'] ?? 'Unknown User';
    $partner['pic']  = $partner['pic'] ?? null;

    $markSQL = match($my_role) {
        'member' => ($active_role === 'admin') ? "UPDATE messages SET is_read=1 WHERE from_admin_id=$active_id AND to_member_id=$my_id" : "UPDATE messages SET is_read=1 WHERE from_member_id=$active_id AND to_member_id=$my_id",
        default  => ($active_role === 'admin') ? "UPDATE messages SET is_read=1 WHERE from_admin_id=$active_id AND to_admin_id=$my_id" : "UPDATE messages SET is_read=1 WHERE from_member_id=$active_id AND to_admin_id=$my_id"
    };
    $conn->query($markSQL);

    $filter = match ($my_role) {
        'member' => ($active_role === 'admin') ? "(from_member_id=$my_id AND to_admin_id=$active_id) OR (from_admin_id=$active_id AND to_member_id=$my_id)" : "(from_member_id=$my_id AND to_member_id=$active_id) OR (from_member_id=$active_id AND to_member_id=$my_id)",
        default  => ($active_role === 'admin') ? "(from_admin_id=$my_id AND to_admin_id=$active_id) OR (from_admin_id=$active_id AND to_admin_id=$my_id)" : "(from_admin_id=$my_id AND to_member_id=$active_id) OR (from_member_id=$active_id AND to_admin_id=$my_id)",
    };
    $res = $conn->query("SELECT * FROM messages WHERE $filter ORDER BY sent_at ASC");
    while ($m = $res->fetch_assoc()) {
        $messages[] = $m;
        if ($m['subject'] === 'Broadcast') $is_broadcast = true;
    }
}

function renderAvatar($blob, $name, $size = '44px', $fontSize = '1rem') {
    $initials = strtoupper(substr(trim((string)($name ?? 'U')), 0, 1));
    $parts = explode(' ', trim((string)($name ?? 'U')));
    if (count($parts) > 1) $initials = strtoupper(substr($parts[0],0,1) . substr(end($parts),0,1));
    if ($blob) {
        return "<div class='msg-avatar' style='width:$size;height:$size;'>
                  <img src='data:image/jpeg;base64," . base64_encode($blob) . "' style='width:100%;height:100%;object-fit:cover;border-radius:50%;'>
                </div>";
    }
    return "<div class='msg-avatar' style='width:$size;height:$size;font-size:$fontSize;'>$initials</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <style>
    /* ─── Reset & Base ─── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
        height: 100%;
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #0B1E17;
        overflow: hidden;
        color: #fff;
    }

    /* ─── App Shell ─── */
    .msg-app {
        display: flex;
        height: 100vh;
        width: 100%;
        overflow: hidden;
    }

    /* ═══════════════════════════════
       SIDEBAR
    ═══════════════════════════════ */
    .msg-sidebar {
        width: 340px;
        min-width: 340px;
        background: linear-gradient(180deg, #0d2218 0%, #081812 100%);
        display: flex;
        flex-direction: column;
        border-right: 1px solid rgba(255,255,255,0.07);
        position: relative;
        z-index: 10;
    }

    /* Sidebar Top */
    .sb-top {
        padding: 28px 24px 20px;
        flex-shrink: 0;
    }
    .sb-nav-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 22px;
    }
    .sb-back-link {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: rgba(255,255,255,0.45);
        font-size: 0.73rem;
        font-weight: 700;
        text-decoration: none;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        transition: color 0.2s;
    }
    .sb-back-link:hover { color: #A3E635; }
    .sb-new-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(163,230,53,0.12);
        border: 1px solid rgba(163,230,53,0.25);
        border-radius: 10px;
        padding: 6px 13px;
        font-size: 0.72rem;
        font-weight: 800;
        color: #A3E635;
        cursor: pointer;
        transition: all 0.2s;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .sb-new-btn:hover { background: rgba(163,230,53,0.2); }
    .sb-title {
        font-size: 1.55rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.5px;
        margin-bottom: 4px;
        line-height: 1.15;
    }
    .sb-subtitle {
        font-size: 0.63rem;
        font-weight: 700;
        color: rgba(255,255,255,0.28);
        text-transform: uppercase;
        letter-spacing: 1.2px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .sb-subtitle::before {
        content: '';
        width: 6px; height: 6px;
        border-radius: 50%;
        background: #39B54A;
        box-shadow: 0 0 0 3px rgba(57,181,74,0.2);
        animation: sbDotPulse 2s ease-in-out infinite;
    }
    @keyframes sbDotPulse {
        0%,100% { box-shadow: 0 0 0 3px rgba(57,181,74,0.2); }
        50%      { box-shadow: 0 0 0 6px rgba(57,181,74,0.07); }
    }

    /* Search */
    .sb-search {
        padding: 0 24px 16px;
        flex-shrink: 0;
    }
    .sb-search-wrap {
        display: flex;
        align-items: center;
        gap: 9px;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.07);
        border-radius: 12px;
        padding: 9px 14px;
        transition: all 0.2s;
    }
    .sb-search-wrap:focus-within {
        border-color: rgba(163,230,53,0.3);
        background: rgba(163,230,53,0.05);
    }
    .sb-search-icon { color: rgba(255,255,255,0.3); font-size: 0.85rem; flex-shrink: 0; }
    .sb-search-input {
        background: transparent;
        border: none;
        outline: none;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.82rem;
        font-weight: 500;
        color: #fff;
        width: 100%;
    }
    .sb-search-input::placeholder { color: rgba(255,255,255,0.25); }

    /* Thread List */
    .sb-threads {
        flex: 1;
        overflow-y: auto;
        padding: 4px 14px 12px;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,0.07) transparent;
    }
    .sb-threads::-webkit-scrollbar { width: 3px; }
    .sb-threads::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 3px; }

    .sb-section-label {
        font-size: 0.6rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: rgba(255,255,255,0.22);
        padding: 12px 8px 6px;
    }

    .thread-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 12px;
        border-radius: 14px;
        text-decoration: none;
        color: rgba(255,255,255,0.65);
        border: 1px solid transparent;
        position: relative;
        overflow: hidden;
        transition: all 0.22s cubic-bezier(0.4,0,0.2,1);
        margin-bottom: 3px;
    }
    .thread-item:hover {
        background: rgba(255,255,255,0.04);
        color: rgba(255,255,255,0.9);
        transform: translateX(3px);
    }
    .thread-item.active {
        background: rgba(163,230,53,0.08);
        border-color: rgba(163,230,53,0.18);
        color: #fff;
    }
    .thread-item.active::after {
        content: '';
        position: absolute;
        left: 0; top: 20%; height: 60%;
        width: 3px;
        background: linear-gradient(to bottom, #A3E635, #39B54A);
        border-radius: 0 3px 3px 0;
    }
    .thread-meta { flex: 1; min-width: 0; }
    .thread-name {
        font-size: 0.875rem;
        font-weight: 700;
        color: inherit;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 3px;
    }
    .thread-preview {
        font-size: 0.76rem;
        color: rgba(255,255,255,0.38);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: 500;
    }
    .thread-item.active .thread-preview { color: rgba(255,255,255,0.55); }
    .thread-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;
        flex-shrink: 0;
    }
    .thread-time {
        font-size: 0.63rem;
        color: rgba(255,255,255,0.28);
        font-weight: 600;
    }
    .thread-unread {
        background: linear-gradient(135deg, #A3E635, #6fba1b);
        color: #0F392B;
        border-radius: 8px;
        padding: 2px 7px;
        font-size: 0.62rem;
        font-weight: 800;
        min-width: 20px;
        text-align: center;
    }

    /* Sidebar Footer */
    .sb-footer {
        padding: 14px 20px 18px;
        border-top: 1px solid rgba(255,255,255,0.06);
        display: flex;
        align-items: center;
        gap: 11px;
        flex-shrink: 0;
    }
    .sb-footer-name {
        font-size: 0.82rem;
        font-weight: 700;
        color: rgba(255,255,255,0.85);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sb-footer-role {
        font-size: 0.6rem;
        font-weight: 700;
        color: rgba(255,255,255,0.3);
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }

    /* Avatar */
    .msg-avatar {
        border-radius: 50%;
        background: linear-gradient(135deg, #A3E635, #6fba1b);
        color: #0F392B;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        flex-shrink: 0;
        overflow: hidden;
    }
    .msg-avatar-admin {
        background: linear-gradient(135deg, #0F392B, #2d7a56);
        color: #A3E635;
    }

    /* Empty Sidebar */
    .sb-empty {
        text-align: center;
        padding: 40px 20px;
        color: rgba(255,255,255,0.2);
    }
    .sb-empty i { font-size: 2.5rem; display: block; margin-bottom: 10px; }
    .sb-empty p { font-size: 0.82rem; font-weight: 500; }

    /* ═══════════════════════════════
       MAIN CHAT AREA
    ═══════════════════════════════ */
    .msg-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #F7FBF9;
        overflow: hidden;
        position: relative;
    }

    /* Chat Header */
    .chat-head {
        background: #fff;
        border-bottom: 1px solid #E8F0ED;
        padding: 16px 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-shrink: 0;
        box-shadow: 0 1px 12px rgba(15,57,43,0.07);
        z-index: 5;
    }
    .chat-head-left { display: flex; align-items: center; gap: 13px; }
    .chat-head-name {
        font-size: 0.975rem;
        font-weight: 800;
        color: #0F392B;
        margin: 0 0 3px;
    }
    .chat-head-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.67rem;
        font-weight: 700;
        color: #39B54A;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .chat-head-status::before {
        content: '';
        width: 6px; height: 6px;
        border-radius: 50%;
        background: #39B54A;
        animation: sbDotPulse 2s ease-in-out infinite;
    }
    .chat-head-id {
        font-size: 0.7rem;
        color: #a0b8b0;
        font-weight: 600;
        margin-left: 8px;
    }
    .chat-menu-btn {
        width: 36px; height: 36px;
        border-radius: 10px;
        background: #F0F7F4;
        border: 1px solid #E0EDE7;
        display: flex; align-items: center; justify-content: center;
        color: #5a7a6e;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.9rem;
    }
    .chat-menu-btn:hover { background: #E0EDE7; color: #0F392B; }

    /* Chat Viewport */
    .chat-viewport {
        flex: 1;
        overflow-y: auto;
        padding: 28px 36px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        scroll-behavior: smooth;
    }
    .chat-viewport::-webkit-scrollbar { width: 4px; }
    .chat-viewport::-webkit-scrollbar-thumb { background: #c8ddd6; border-radius: 4px; }

    /* Date Separator */
    .chat-date-sep {
        display: flex;
        align-items: center;
        gap: 14px;
        color: #a0b8b0;
        font-size: 0.67rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin: 14px 0 10px;
    }
    .chat-date-sep::before, .chat-date-sep::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #E0EDE7;
    }

    /* Bubbles */
    .bubble-row {
        display: flex;
        align-items: flex-end;
        gap: 9px;
        margin-bottom: 3px;
        animation: bblIn 0.3s cubic-bezier(0.16,1,0.3,1) both;
    }
    .bubble-row.sent { flex-direction: row-reverse; }
    @keyframes bblIn {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .chat-bubble {
        max-width: 64%;
        padding: 12px 18px;
        border-radius: 18px;
        font-size: 0.875rem;
        line-height: 1.65;
        position: relative;
    }
    .bubble-sent {
        background: linear-gradient(135deg, #0F392B, #1a5c43);
        color: #fff;
        border-bottom-right-radius: 5px;
        box-shadow: 0 6px 20px rgba(15,57,43,0.22);
    }
    .bubble-received {
        background: #fff;
        color: #0F392B;
        border: 1px solid #E0EDE7;
        border-bottom-left-radius: 5px;
        box-shadow: 0 2px 10px rgba(15,57,43,0.06);
    }
    .bubble-time {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
        font-size: 0.62rem;
        margin-top: 6px;
        opacity: 0.55;
    }
    .bubble-sent .bubble-time { color: rgba(255,255,255,0.7); }
    .bubble-received .bubble-time { color: #7a9e8e; }

    /* Attachment */
    .attach-chip {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 13px;
        border-radius: 12px;
        margin-bottom: 8px;
        text-decoration: none;
        transition: all 0.2s;
        font-size: 0.8rem;
        font-weight: 700;
    }
    .bubble-sent .attach-chip {
        background: rgba(255,255,255,0.12);
        color: rgba(255,255,255,0.9);
        border: 1px solid rgba(255,255,255,0.15);
    }
    .bubble-sent .attach-chip:hover { background: rgba(255,255,255,0.2); }
    .bubble-received .attach-chip {
        background: #F0F7F4;
        color: #0F392B;
        border: 1px solid #E0EDE7;
    }
    .bubble-received .attach-chip:hover { background: #E0EDE7; }
    .attach-chip-icon {
        width: 32px; height: 32px;
        border-radius: 8px;
        background: rgba(255,255,255,0.15);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        font-size: 0.9rem;
    }
    .bubble-received .attach-chip-icon { background: #0F392B; color: #A3E635; }
    .attach-chip-sub { font-size: 0.62rem; font-weight: 500; opacity: 0.6; margin-top: 1px; }

    /* ─── Input Area ─── */
    .chat-input-area {
        background: #fff;
        border-top: 1px solid #E8F0ED;
        padding: 16px 28px 20px;
        flex-shrink: 0;
    }
    .chat-input-wrap {
        display: flex;
        align-items: flex-end;
        gap: 10px;
        background: #F7FBF9;
        border: 1.5px solid #E0EDE7;
        border-radius: 18px;
        padding: 10px 10px 10px 18px;
        transition: all 0.2s;
    }
    .chat-input-wrap:focus-within {
        border-color: #39B54A;
        background: #fff;
        box-shadow: 0 0 0 4px rgba(57,181,74,0.08);
    }
    .chat-attach-btn {
        width: 38px; height: 38px;
        border-radius: 10px;
        background: #F0F7F4;
        border: 1px solid #E0EDE7;
        display: flex; align-items: center; justify-content: center;
        color: #5a7a6e;
        cursor: pointer;
        flex-shrink: 0;
        transition: all 0.2s;
        font-size: 0.9rem;
    }
    .chat-attach-btn:hover { background: #0F392B; color: #A3E635; border-color: #0F392B; }
    .chat-textarea {
        flex: 1;
        background: transparent;
        border: none;
        outline: none;
        resize: none;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.875rem;
        font-weight: 500;
        color: #0F392B;
        line-height: 1.6;
        min-height: 24px;
        max-height: 120px;
        overflow-y: auto;
        padding: 4px 0;
    }
    .chat-textarea::placeholder { color: #a8c5bb; }
    .chat-send-btn {
        width: 44px; height: 44px;
        border-radius: 13px;
        background: linear-gradient(135deg, #39B54A, #2d9a3c);
        border: none;
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem;
        cursor: pointer;
        flex-shrink: 0;
        transition: all 0.2s;
        box-shadow: 0 4px 14px rgba(57,181,74,0.35);
    }
    .chat-send-btn:hover { transform: scale(1.07) rotate(10deg); box-shadow: 0 6px 20px rgba(57,181,74,0.45); }

    /* Broadcast Lock */
    .broadcast-lock {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 20px;
        background: #FFFBEB;
        border-top: 1px solid #FDE68A;
        font-size: 0.8rem;
        font-weight: 600;
        color: #92400e;
    }

    /* ─── Empty State ─── */
    .chat-empty {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px;
        text-align: center;
        background: #F7FBF9;
    }
    .chat-empty-icon {
        width: 100px; height: 100px;
        border-radius: 28px;
        background: linear-gradient(135deg, #0F392B, #1a5c43);
        display: flex; align-items: center; justify-content: center;
        color: #A3E635;
        font-size: 2.4rem;
        margin-bottom: 24px;
        box-shadow: 0 12px 36px rgba(15,57,43,0.22);
    }
    .chat-empty h2 {
        font-size: 1.4rem;
        font-weight: 800;
        color: #0F392B;
        margin-bottom: 8px;
        letter-spacing: -0.3px;
    }
    .chat-empty p {
        font-size: 0.9rem;
        color: #7a9e8e;
        font-weight: 500;
        max-width: 380px;
        line-height: 1.6;
        margin-bottom: 24px;
    }
    .chat-empty-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #0F392B;
        color: #fff;
        border: none;
        border-radius: 14px;
        padding: 12px 28px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.855rem;
        font-weight: 800;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 6px 20px rgba(15,57,43,0.25);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .chat-empty-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(15,57,43,0.32); }

    /* ─── Modal ─── */
    .modal-content {
        border: none;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 24px 64px rgba(0,0,0,0.18);
    }
    .modal-head-dark {
        background: linear-gradient(135deg, #0F392B, #1a5c43);
        padding: 24px 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .modal-head-dark h4 {
        font-size: 1rem;
        font-weight: 800;
        color: #fff;
        margin: 0 0 2px;
    }
    .modal-head-dark p {
        font-size: 0.73rem;
        color: rgba(255,255,255,0.5);
        margin: 0;
    }
    .modal-head-dark .btn-close-white {
        filter: invert(1) brightness(2);
        opacity: 0.6;
    }
    .modal-search-bar {
        padding: 14px 20px;
        border-bottom: 1px solid #E8F0ED;
        background: #fff;
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .modal-search-input {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.84rem;
        font-weight: 500;
        background: #F7FBF9;
        border: 1.5px solid #E0EDE7;
        border-radius: 12px;
        padding: 9px 14px 9px 36px;
        width: 100%;
        outline: none;
        transition: all 0.2s;
    }
    .modal-search-input:focus { border-color: #39B54A; box-shadow: 0 0 0 3px rgba(57,181,74,0.08); }
    .modal-search-wrap { position: relative; }
    .modal-search-icon {
        position: absolute;
        left: 12px; top: 50%;
        transform: translateY(-50%);
        color: #a0b8b0;
        font-size: 0.82rem;
    }
    .modal-user-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 20px;
        text-decoration: none;
        border-bottom: 1px solid #F7FBF9;
        transition: background 0.18s;
    }
    .modal-user-item:hover { background: #F7FBF9; }
    .modal-user-name {
        font-size: 0.875rem;
        font-weight: 700;
        color: #0F392B;
        margin-bottom: 3px;
    }
    .modal-user-sub {
        font-size: 0.7rem;
        color: #7a9e8e;
        font-weight: 500;
    }
    .modal-user-badge {
        display: inline-flex;
        align-items: center;
        background: #E8F5E9;
        color: #1a6b35;
        border-radius: 6px;
        padding: 2px 8px;
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .modal-user-chevron { color: #c8ddd6; font-size: 0.8rem; margin-left: auto; }
    .modal-broadcast-card {
        margin: 12px 16px;
        background: linear-gradient(135deg, #0F392B, #1a5c43);
        border-radius: 14px;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        width: calc(100% - 32px);
        text-align: left;
    }
    .modal-broadcast-card:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(15,57,43,0.25); }
    .modal-broadcast-icon {
        width: 38px; height: 38px;
        border-radius: 11px;
        background: rgba(163,230,53,0.15);
        display: flex; align-items: center; justify-content: center;
        color: #A3E635;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .modal-broadcast-title { font-size: 0.875rem; font-weight: 800; color: #fff; margin-bottom: 2px; }
    .modal-broadcast-sub   { font-size: 0.72rem; color: rgba(255,255,255,0.5); }
    .modal-section-label {
        font-size: 0.6rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: #a0b8b0;
        padding: 12px 20px 6px;
    }

    @media (max-width: 991px) {
        .msg-sidebar { position: fixed; left: -100%; height: 100%; z-index: 999; transition: left 0.3s cubic-bezier(0.4,0,0.2,1); box-shadow: 20px 0 60px rgba(0,0,0,0.4); }
        .msg-sidebar.show { left: 0; }
    }
    </style>
</head>
<body>
<div class="msg-app">

    <!-- ── SIDEBAR ── -->
    <aside class="msg-sidebar" id="msgSidebar">
        <div class="sb-top">
            <div class="sb-nav-row">
                <a href="<?= $dashboard_url ?>" class="sb-back-link">
                    <i class="bi bi-chevron-left"></i> Dashboard
                </a>
                <button class="sb-new-btn" data-bs-toggle="modal" data-bs-target="#newChatModal">
                    <i class="bi bi-plus-lg"></i> New
                </button>
            </div>
            <div class="sb-title">Messages</div>
            <div class="sb-subtitle">End-to-end secured</div>
        </div>

        <div class="sb-search">
            <div class="sb-search-wrap">
                <i class="bi bi-search sb-search-icon"></i>
                <input type="text" class="sb-search-input" placeholder="Search conversations..." id="threadSearch">
            </div>
        </div>

        <div class="sb-threads" id="threadList">
            <?php if (empty($threads)): ?>
                <div class="sb-empty">
                    <i class="bi bi-chat-dots"></i>
                    <p>No conversations yet</p>
                </div>
            <?php else: ?>
                <div class="sb-section-label">Recent</div>
                <?php foreach($threads as $key => $t):
                    $thread_link = "?chat_with=" . $t['id'] . "&role=" . $t['role'];
                    if (!empty($_GET['return_to'])) $thread_link .= "&return_to=" . urlencode($_GET['return_to']);
                    $isActive = ($active_id == $t['id'] && $active_role == $t['role']);
                ?>
                <a href="<?= $thread_link ?>" class="thread-item <?= $isActive ? 'active' : '' ?>" data-name="<?= strtolower(htmlspecialchars($t['name'])) ?>">
                    <?= renderAvatar($t['pic'], $t['name'], '42px', '0.9rem') ?>
                    <div class="thread-meta">
                        <div class="thread-name"><?= htmlspecialchars($t['name']) ?></div>
                        <div class="thread-preview">
                            <?= $t['unread'] > 0 ? '<strong style="color:rgba(255,255,255,0.7);">' . htmlspecialchars($t['last_msg']) . '</strong>' : htmlspecialchars($t['last_msg']) ?>
                        </div>
                    </div>
                    <div class="thread-right">
                        <span class="thread-time"><?= date('H:i', strtotime($t['time'])) ?></span>
                        <?php if($t['unread'] > 0): ?>
                            <span class="thread-unread"><?= $t['unread'] ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="sb-footer">
            <?= renderAvatar($_SESSION['admin_pic'] ?? $_SESSION['member_pic'] ?? null, $_SESSION['admin_name'] ?? $_SESSION['member_name'] ?? 'User', '38px', '0.85rem') ?>
            <div style="flex:1; min-width:0;">
                <div class="sb-footer-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['member_name'] ?? 'User') ?></div>
                <div class="sb-footer-role"><?= $my_role ?></div>
            </div>
        </div>
    </aside>

    <!-- ── MAIN CHAT ── -->
    <main class="msg-main">
        <?php if ($active_id && $partner): ?>

            <!-- Header -->
            <div class="chat-head">
                <div class="chat-head-left">
                    <button class="chat-menu-btn d-lg-none" onclick="document.getElementById('msgSidebar').classList.toggle('show')">
                        <i class="bi bi-list"></i>
                    </button>
                    <?= renderAvatar($partner['pic'], $partner['name'], '42px', '0.95rem') ?>
                    <div>
                        <div class="chat-head-name"><?= htmlspecialchars((string)($partner['name'] ?? 'Unknown')) ?></div>
                        <div>
                            <span class="chat-head-status">Online</span>
                            <span class="chat-head-id">· ID #<?= $active_id ?></span>
                        </div>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="chat-menu-btn" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2" style="font-family:'Plus Jakarta Sans',sans-serif;">
                        <li><a class="dropdown-item rounded-3 py-2 small fw-600" href="messages.php" style="font-weight:600;"><i class="bi bi-x-circle me-2 text-danger"></i> Close Chat</a></li>
                        <li><a class="dropdown-item rounded-3 py-2 small fw-600" href="#" style="font-weight:600;"><i class="bi bi-flag me-2 text-warning"></i> Report Thread</a></li>
                    </ul>
                </div>
            </div>

            <!-- Messages Viewport -->
            <div class="chat-viewport" id="msgViewport">
                <?php
                $lastDate = '';
                foreach ($messages as $msg):
                    $currDate = formatChatDate($msg['sent_at']);
                    if ($currDate !== $lastDate):
                ?>
                    <div class="chat-date-sep"><?= $currDate ?></div>
                <?php $lastDate = $currDate; endif;
                $isMe = ($my_role === 'member'
                    ? $msg['from_member_id'] == $my_id
                    : $msg['from_admin_id'] == $my_id);
                ?>
                <div class="bubble-row <?= $isMe ? 'sent' : 'received' ?>">
                    <?php if (!$isMe): ?>
                        <?= renderAvatar($partner['pic'], $partner['name'], '30px', '0.7rem') ?>
                    <?php endif; ?>
                    <div class="chat-bubble <?= $isMe ? 'bubble-sent' : 'bubble-received' ?>">
                        <?php if ($msg['attachment']): ?>
                            <a href="<?= $msg['attachment'] ?>" target="_blank" class="attach-chip">
                                <div class="attach-chip-icon"><i class="bi bi-file-earmark-fill"></i></div>
                                <div>
                                    <div>Document Attached</div>
                                    <div class="attach-chip-sub">Tap to download</div>
                                </div>
                            </a>
                        <?php endif; ?>
                        <?php if ($msg['body']): ?>
                            <div><?= nl2br(htmlspecialchars($msg['body'])) ?></div>
                        <?php endif; ?>
                        <div class="bubble-time">
                            <?= date('H:i', strtotime($msg['sent_at'])) ?>
                            <?php if ($isMe): ?>
                                <i class="bi bi-check2-all <?= $msg['is_read'] ? 'text-success' : '' ?>"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Input Area -->
            <?php if (!$is_broadcast): ?>
            <div class="chat-input-area">
                <form method="POST" enctype="multipart/form-data" id="msgForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="send_message" value="1">
                    <input type="hidden" name="recipient_role" value="<?= htmlspecialchars($active_role) ?>">
                    <input type="hidden" name="target_id" value="<?= $active_id ?>">
                    <?php if (!empty($_GET['return_to'])): ?>
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($_GET['return_to']) ?>">
                    <?php endif; ?>
                    <div class="chat-input-wrap">
                        <label class="chat-attach-btn" title="Attach file">
                            <i class="bi bi-paperclip"></i>
                            <input type="file" name="attachment" class="d-none">
                        </label>
                        <textarea name="body" class="chat-textarea" placeholder="Write a message..." rows="1" required id="msgTextarea"></textarea>
                        <button type="submit" class="chat-send-btn">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="broadcast-lock">
                <i class="bi bi-shield-lock-fill text-warning"></i>
                System Broadcast — replies are restricted to outgoing only.
            </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Empty State -->
            <div class="chat-empty">
                <div class="chat-empty-icon">
                    <i class="bi bi-chat-heart-fill"></i>
                </div>
                <h2>Messages Hub</h2>
                <p>Unified communication gateway. Start a secure thread with staff or members instantly.</p>
                <button class="chat-empty-btn" data-bs-toggle="modal" data-bs-target="#newChatModal">
                    <i class="bi bi-plus-lg"></i> Start Conversation
                </button>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- ── New Chat Modal ── -->
<div class="modal fade" id="newChatModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:480px;">
        <div class="modal-content">
            <div class="modal-head-dark">
                <div>
                    <h4>New Conversation</h4>
                    <p>Search registered users within the ecosystem</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="background:#fff; max-height:520px; overflow-y:auto;">

                <?php if ($my_role !== 'member'): ?>
                <div style="padding:12px 16px 4px;">
                    <button class="modal-broadcast-card" data-bs-toggle="modal" data-bs-target="#broadcastModal">
                        <div class="modal-broadcast-icon"><i class="bi bi-megaphone-fill"></i></div>
                        <div>
                            <div class="modal-broadcast-title">Pulse Broadcast</div>
                            <div class="modal-broadcast-sub">Send a message to all active members instantly</div>
                        </div>
                        <i class="bi bi-chevron-right ms-auto" style="color:rgba(255,255,255,0.3);"></i>
                    </button>
                </div>
                <?php endif; ?>

                <div class="modal-search-bar">
                    <div class="modal-search-wrap">
                        <i class="bi bi-search modal-search-icon"></i>
                        <input type="text" class="modal-search-input" placeholder="Search by name or ID..." id="modalSearch">
                    </div>
                </div>

                <div id="modalUserList">
                    <?php
                    $admins = $conn->query("SELECT admin_id, full_name, r.name as role FROM admins a JOIN roles r ON a.role_id = r.id WHERE a.admin_id != $my_id ORDER BY full_name ASC");
                    if ($admins && $admins->num_rows > 0):
                    ?>
                    <div class="modal-section-label">Staff</div>
                    <?php while ($a = $admins->fetch_assoc()): ?>
                    <a href="?chat_with=<?= $a['admin_id'] ?>&role=admin" class="modal-user-item" data-name="<?= strtolower(htmlspecialchars($a['full_name'])) ?>">
                        <div class="msg-avatar msg-avatar-admin" style="width:40px;height:40px;font-size:0.85rem;"><?= strtoupper(substr($a['full_name'],0,1)) ?></div>
                        <div>
                            <div class="modal-user-name"><?= htmlspecialchars($a['full_name']) ?></div>
                            <span class="modal-user-badge"><?= htmlspecialchars($a['role']) ?></span>
                        </div>
                        <i class="bi bi-chevron-right modal-user-chevron"></i>
                    </a>
                    <?php endwhile; endif; ?>

                    <?php
                    $members = $conn->query("SELECT member_id, full_name, national_id, profile_pic FROM members WHERE status='active' AND member_id != $my_id LIMIT 50");
                    if ($members && $members->num_rows > 0):
                    ?>
                    <div class="modal-section-label">Members</div>
                    <?php while ($m = $members->fetch_assoc()): ?>
                    <a href="?chat_with=<?= $m['member_id'] ?>&role=member" class="modal-user-item" data-name="<?= strtolower(htmlspecialchars($m['full_name'])) ?>">
                        <?= renderAvatar($m['profile_pic'], $m['full_name'], '40px', '0.85rem') ?>
                        <div>
                            <div class="modal-user-name"><?= htmlspecialchars($m['full_name']) ?></div>
                            <div class="modal-user-sub">Member #<?= $m['member_id'] ?> · ID <?= $m['national_id'] ?></div>
                        </div>
                        <i class="bi bi-chevron-right modal-user-chevron"></i>
                    </a>
                    <?php endwhile; endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Scroll to bottom
const vp = document.getElementById('msgViewport');
if (vp) vp.scrollTop = vp.scrollHeight;

// Auto-expand textarea
const ta = document.getElementById('msgTextarea');
if (ta) {
    ta.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    ta.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('msgForm')?.submit();
        }
    });
}

// Thread search
document.getElementById('threadSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#threadList .thread-item').forEach(el => {
        el.style.display = el.dataset.name?.includes(q) ? '' : 'none';
    });
});

// Modal user search
document.getElementById('modalSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#modalUserList .modal-user-item').forEach(el => {
        el.style.display = el.dataset.name?.includes(q) ? '' : 'none';
    });
});
</script>
</body>
</html>