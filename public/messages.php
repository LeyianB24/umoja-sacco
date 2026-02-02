<?php
// public/messages.php
// Enhanced Chat System: CSRF Security, Date Separators, Broadcast Modal, and Advanced Theming

if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();

require_once __DIR__ . '/../config/db_connect.php';
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

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Dashboard Return Link
$dashboard_url = match($my_role) {
    'superadmin' => '../superadmin/dashboard.php',
    'manager'    => '../manager/dashboard.php',
    'accountant' => '../accountant/dashboard.php',
    'admin'      => '../admin/pages/dashboard.php',
    'member'     => '../member/pages/dashboard.php',
    default      => 'login.php'
};

// ============================================================================
//  2. HELPER FUNCTIONS
// ============================================================================

function saveAttachment($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
    
    $allowed = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 
        'png' => 'image/png',  'pdf' => 'application/pdf',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($ext, $allowed) || $allowed[$ext] !== $mime) return null;

    $dir = __DIR__ . '/uploads/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    $newName = uniqid('msg_', true) . '.' . $ext;
    $path = $dir . $newName;
    
    return move_uploaded_file($file['tmp_name'], $path) ? 'uploads/' . $newName : null;
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

// ============================================================================
//  3. HANDLE POST REQUESTS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security violation: Invalid CSRF token.");
    }

    $recipient_role = $_POST['recipient_role'] ?? 'member';
    $target_id      = intval($_POST['target_id'] ?? 0);
    $body           = trim($_POST['body'] ?? '');
    $attachment     = saveAttachment($_FILES['attachment'] ?? null);
    $subject        = ($recipient_role === 'broadcast') ? 'Broadcast' : 'Chat';

    if ($body || $attachment) {
        
        // A. BROADCAST LOGIC
        if ($recipient_role === 'broadcast' && $my_role !== 'member') {
            $target_grp = $_POST['broadcast_group'] ?? 'members';
            
            $conn->begin_transaction();
            try {
                if ($target_grp === 'members') {
                    $recipients = $conn->query("SELECT member_id FROM members WHERE status='active'");
                    $stmt = $conn->prepare("INSERT INTO messages (from_admin_id, to_member_id, subject, body, attachment, sent_at, is_read) VALUES (?, ?, 'Broadcast', ?, ?, NOW(), 0)");
                    while ($m = $recipients->fetch_assoc()) {
                        $stmt->bind_param("iiss", $my_id, $m['member_id'], $body, $attachment);
                        $stmt->execute();
                    }
                } elseif (in_array($target_grp, ['admin', 'manager', 'accountant', 'superadmin', 'all_admins'])) {
                    $where = ($target_grp === 'all_admins') ? "" : "WHERE role='$target_grp'";
                    $recipients = $conn->query("SELECT admin_id FROM admins $where");
                    $stmt = $conn->prepare("INSERT INTO messages (from_admin_id, to_admin_id, subject, body, attachment, sent_at, is_read) VALUES (?, ?, 'Admin Broadcast', ?, ?, NOW(), 0)");
                    while ($a = $recipients->fetch_assoc()) {
                        if ($a['admin_id'] == $my_id) continue; // Don't send to self
                        $stmt->bind_param("iiss", $my_id, $a['admin_id'], $body, $attachment);
                        $stmt->execute();
                    }
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
            }
            header("Location: messages.php");
            exit;
        }

        // B. DIRECT MESSAGE LOGIC
        $sql = match ($my_role) {
            'member' => ($recipient_role === 'admin')
                ? "INSERT INTO messages (from_member_id, to_admin_id, subject, body, attachment, sent_at) VALUES (?, ?, ?, ?, ?, NOW())"
                : "INSERT INTO messages (from_member_id, to_member_id, subject, body, attachment, sent_at) VALUES (?, ?, ?, ?, ?, NOW())",
            default => ($recipient_role === 'admin')
                ? "INSERT INTO messages (from_admin_id, to_admin_id, subject, body, attachment, sent_at) VALUES (?, ?, ?, ?, ?, NOW())"
                : "INSERT INTO messages (from_admin_id, to_member_id, subject, body, attachment, sent_at) VALUES (?, ?, ?, ?, ?, NOW())",
        };
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisss", $my_id, $target_id, $subject, $body, $attachment);
        $stmt->execute();
        
        header("Location: messages.php?chat_with=$target_id&role=$recipient_role");
        exit;
    }
}

// ===========================================================================
//  4. FETCH THREADS (SIDEBAR)
// ===========================================================================
$threads = [];
$filterSQL = ($my_role === 'member')
    ? "SELECT m.*, 
            CASE WHEN (m.from_admin_id IS NOT NULL OR m.to_admin_id IS NOT NULL) THEN 'admin' ELSE 'member' END AS partner_role,
            CASE WHEN (m.from_admin_id IS NOT NULL OR m.to_admin_id IS NOT NULL) THEN COALESCE(m.from_admin_id, m.to_admin_id)
                 WHEN m.from_member_id = ? THEN m.to_member_id ELSE m.from_member_id END AS partner_id
       FROM messages m WHERE (m.from_member_id = ? OR m.to_member_id = ?) ORDER BY sent_at DESC"
    : "SELECT m.*, 
            CASE WHEN (m.from_member_id IS NOT NULL OR m.to_member_id IS NOT NULL) THEN 'member' ELSE 'admin' END AS partner_role,
            CASE WHEN (m.from_member_id IS NOT NULL OR m.to_member_id IS NOT NULL) THEN COALESCE(m.from_member_id, m.to_member_id)
                 WHEN m.from_admin_id = ? THEN m.to_admin_id ELSE m.from_admin_id END AS partner_id
       FROM messages m WHERE (m.from_admin_id = ? OR m.to_admin_id = ?) ORDER BY sent_at DESC";

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
            $name = $info['full_name'] ?? 'Admin User';
            $pic = null;
        } else {
            $info = $conn->query("SELECT full_name, profile_pic FROM members WHERE member_id=$pid")->fetch_assoc();
            $name = $info['full_name'] ?? 'Member User';
            $pic = $info['profile_pic'] ?? null;
        }
        
        $threads[$key] = [
            'id' => $pid, 'role' => $prole, 'name' => $name, 'pic' => $pic,
            'last_msg' => $row['body'] ?: '📎 Attachment', 
            'time' => $row['sent_at'], 
            'unread' => 0
        ];
    }
    
    $isReceiver = ($my_role === 'member') 
        ? ($row['to_member_id'] == $my_id && $row['from_member_id'] != $my_id && $row['from_admin_id'] != $my_id) 
        : ($row['to_admin_id'] == $my_id && $row['from_admin_id'] != $my_id);

    if ($isReceiver && $row['is_read'] == 0) $threads[$key]['unread']++;
}

// ============================================================================
//  5. FETCH ACTIVE CHAT
// ============================================================================
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
            $info = $conn->query("SELECT full_name FROM admins WHERE admin_id=$active_id")->fetch_assoc() ?? [];
            $partner = ['name' => $info['full_name'] ?? 'Unknown Admin', 'pic' => null];
        } else {
            $info = $conn->query("SELECT full_name, profile_pic FROM members WHERE member_id=$active_id")->fetch_assoc() ?? [];
            $partner = ['name' => $info['full_name'] ?? 'Unknown Member', 'pic' => $info['profile_pic'] ?? null];
        }
    }
    $partner = $partner ?? ['name' => 'Unknown', 'pic' => null];
    $partner['name'] = $partner['name'] ?? 'Unknown';

    $markSQL = match($my_role) {
        'member' => ($active_role === 'admin') 
            ? "UPDATE messages SET is_read=1 WHERE from_admin_id=$active_id AND to_member_id=$my_id" 
            : "UPDATE messages SET is_read=1 WHERE from_member_id=$active_id AND to_member_id=$my_id",
        default => ($active_role === 'admin') 
            ? "UPDATE messages SET is_read=1 WHERE from_admin_id=$active_id AND to_admin_id=$my_id" 
            : "UPDATE messages SET is_read=1 WHERE from_member_id=$active_id AND to_admin_id=$my_id"
    };
    $conn->query($markSQL);

    $filter = match ($my_role) {
        'member' => ($active_role === 'admin')
            ? "(from_member_id=$my_id AND to_admin_id=$active_id) OR (from_admin_id=$active_id AND to_member_id=$my_id)"
            : "(from_member_id=$my_id AND to_member_id=$active_id) OR (from_member_id=$active_id AND to_member_id=$my_id)",
        default => ($active_role === 'admin')
            ? "(from_admin_id=$my_id AND to_admin_id=$active_id) OR (from_admin_id=$active_id AND to_admin_id=$my_id)"
            : "(from_admin_id=$my_id AND to_member_id=$active_id) OR (from_member_id=$active_id AND to_admin_id=$my_id)",
    };
    
    $res = $conn->query("SELECT * FROM messages WHERE $filter ORDER BY sent_at ASC");
    while ($m = $res->fetch_assoc()) {
        $messages[] = $m;
        if ($m['subject'] === 'Broadcast') $is_broadcast = true;
    }
}

function renderImg($blob, $name) {
    if ($blob) {
        return "<img src='data:image/jpeg;base64," . base64_encode($blob) . "' class='rounded-circle shadow-sm' style='width:40px;height:40px;object-fit:cover'>";
    }
    // Using custom primary color for fallback avatar
    return "<div class='rounded-circle d-flex align-items-center justify-content-center shadow-sm text-white' style='width:40px;height:40px;font-weight:bold;font-size:0.9rem; background: var(--primary-color)'>" . strtoupper(substr($name ?? 'U', 0, 1)) . "</div>";
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --forest-green: #0F392B;
            --forest-mid: #134e3b;
            --forest-light: #1a634b;
            --lime: #D0F35D;
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.2);
            --sidebar-width: 360px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            height: 100vh;
            overflow: hidden;
            color: var(--forest-green);
        }

        .app-container {
            display: flex;
            height: 100vh;
            backdrop-filter: blur(10px);
        }

        /* Sidebar Styling */
        .msg-sidebar {
            width: var(--sidebar-width);
            background: var(--forest-green);
            color: white;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 30px 24px;
            background: rgba(0,0,0,0.1);
        }

        .sidebar-footer {
            padding: 24px;
            background: rgba(0,0,0,0.2);
        }

        .thread-container {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }

        .thread-item {
            display: flex;
            padding: 16px;
            border-radius: 16px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: rgba(255,255,255,0.7);
            border: 1px solid transparent;
        }

        .thread-item:hover {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .thread-item.active {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .avatar-circle {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: var(--lime);
            color: var(--forest-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.2rem;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border: 2px solid rgba(255,255,255,0.2);
        }

        /* Chat Area Styling */
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8f9fa;
            position: relative;
        }

        .chat-header {
            padding: 20px 30px;
            background: white;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        .messages-viewport {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: url("https://www.transparenttextures.com/patterns/cubes.png");
        }

        .chat-input-area {
            padding: 24px 30px;
            background: white;
            border-top: 1px solid #e9ecef;
        }

        /* Bubble Styling */
        .bubble-wrapper {
            display: flex;
            width: 100%;
            margin-bottom: 4px;
        }

        .bubble-wrapper.sent { justify-content: flex-end; }
        .bubble-wrapper.received { justify-content: flex-start; }

        .chat-bubble {
            max-width: 65%;
            padding: 14px 20px;
            border-radius: 20px;
            position: relative;
            font-size: 0.95rem;
            line-height: 1.5;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .sent .chat-bubble {
            background: var(--forest-green);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .received .chat-bubble {
            background: white;
            color: var(--forest-green);
            border-bottom-left-radius: 4px;
            border: 1px solid #e9ecef;
        }

        .bubble-time {
            font-size: 0.7rem;
            margin-top: 6px;
            opacity: 0.6;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 4px;
        }

        /* Input Styling */
        .input-glass {
            background: #f1f3f5 !important;
            border: 2px solid transparent !important;
            border-radius: 16px !important;
            padding: 14px 22px !important;
            transition: all 0.3s !important;
            font-weight: 500;
        }

        .input-glass:focus {
            background: white !important;
            border-color: var(--forest-green) !important;
            box-shadow: 0 0 0 4px rgba(15, 57, 43, 0.05) !important;
        }

        .btn-send {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: var(--lime);
            border: none;
            color: var(--forest-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            transition: all 0.3s;
        }

        .btn-send:hover {
            transform: scale(1.05) translateY(-2px);
            background: #e1ff8d;
            box-shadow: 0 5px 15px rgba(208, 243, 93, 0.3);
        }

        /* Misc */
        .badge-unread {
            background: var(--lime);
            color: var(--forest-green);
            font-weight: 800;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 0.7rem;
        }

        .date-chip {
            text-align: center;
            margin: 24px 0;
            position: relative;
        }

        .date-chip span {
            background: rgba(15, 57, 43, 0.05);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--forest-mid);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .attachment-card {
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 10px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: white;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .received .attachment-card {
            background: #f8f9fa;
            color: var(--forest-green);
            border-color: #dee2e6;
        }
        
        @media (max-width: 991px) {
            .msg-sidebar { position: fixed; left: -100%; height: 100%; z-index: 1000; }
            .msg-sidebar.show { left: 0; }
        }
    </style>
</head>
<body>

<div class="app-container">
    
    <!-- Sidebar -->
    <aside class="msg-sidebar" id="msgSidebar">
        <div class="sidebar-header">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="<?= $dashboard_url ?>" class="btn btn-link p-0 text-white opacity-75 text-decoration-none">
                    <i class="bi bi-chevron-left me-2"></i> Dashboard
                </a>
                <button class="btn btn-sm btn-light rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#newChatModal">
                    <i class="bi bi-plus-lg me-1"></i> New
                </button>
            </div>
            <h3 class="fw-extrabold mb-1">Messages</h3>
            <p class="small opacity-50 mb-0">Secure Member Communication</p>
        </div>

        <div class="thread-container">
            <?php if(empty($threads)): ?>
                <div class="text-center p-5 opacity-25">
                    <i class="bi bi-chat-dots display-1 mb-3"></i>
                    <p>No conversations found</p>
                </div>
            <?php endif; ?>

            <?php foreach($threads as $key => $t): ?>
            <a href="?chat_with=<?= $t['id'] ?>&role=<?= $t['role'] ?>" class="thread-item <?= ($active_id == $t['id'] && $active_role == $t['role']) ? 'active' : '' ?>">
                <div class="avatar-circle me-3">
                    <?= strtoupper(substr((string)($t['name'] ?? 'U'), 0, 1)) ?>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold text-truncate" style="font-size:0.95rem"><?= htmlspecialchars($t['name']) ?></span>
                        <small class="opacity-50" style="font-size:0.75rem"><?= date('H:i', strtotime($t['time'])) ?></small>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-truncate opacity-75" style="font-size:0.85rem">
                            <?= $t['unread'] > 0 ? '<strong>' . htmlspecialchars($t['last_msg']) . '</strong>' : htmlspecialchars($t['last_msg']) ?>
                        </small>
                        <?php if($t['unread'] > 0): ?>
                            <span class="badge-unread"><?= $t['unread'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="sidebar-footer">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-circle" style="width:40px;height:40px;font-size:0.9rem">
                    <?= strtoupper(substr((string)($_SESSION['admin_name'] ?? $_SESSION['member_name'] ?? 'U'), 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                    <div class="fw-bold text-truncate"><?= htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['member_name'] ?? 'User') ?></div>
                    <div class="small opacity-50 text-uppercase" style="font-size:0.6rem; letter-spacing:1px"><?= $my_role ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Chat Area -->
    <main class="chat-container">
        <?php if($active_id): ?>
            <div class="chat-header">
                <div class="d-flex align-items-center">
                    <button class="btn d-lg-none me-3 p-0" onclick="document.getElementById('msgSidebar').classList.toggle('show')">
                        <i class="bi bi-list fs-3"></i>
                    </button>
                    <div class="avatar-circle me-3" style="width:46px;height:46px;border-radius:12px">
                        <?= strtoupper(substr((string)($partner['name'] ?? 'U'), 0, 1)) ?>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?= htmlspecialchars((string)($partner['name'] ?? 'Unknown')) ?></h5>
                        <span class="badge bg-success bg-opacity-10 text-success py-1 px-2 rounded-pill" style="font-size:0.65rem">
                            <i class="bi bi-circle-fill me-1" style="font-size:0.4rem"></i> ACTIVE NOW
                        </span>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-light rounded-pill p-2" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item" href="messages.php"><i class="bi bi-x-lg me-2"></i> Close Chat</a></li>
                    </ul>
                </div>
            </div>

            <div class="messages-viewport" id="msgViewport">
                <?php 
                $lastDate = '';
                foreach($messages as $msg): 
                    $currDate = formatChatDate($msg['sent_at']);
                    if($currDate !== $lastDate): 
                ?>
                    <div class="date-chip"><span><?= $currDate ?></span></div>
                <?php $lastDate = $currDate; endif; ?>

                <?php 
                $isMe = ($my_role === 'member' 
                    ? $msg['from_member_id'] == $my_id 
                    : $msg['from_admin_id'] == $my_id);
                ?>

                <div class="bubble-wrapper <?= $isMe ? 'sent' : 'received' ?>">
                    <div class="chat-bubble">
                        <?php if($msg['attachment']): ?>
                            <a href="<?= $msg['attachment'] ?>" target="_blank" class="attachment-card">
                                <i class="bi bi-file-earmark-arrow-down-fill fs-4"></i>
                                <div class="overflow-hidden">
                                    <div class="fw-bold small">Attachment</div>
                                    <div class="small opacity-50" style="font-size:0.7rem">Click to view file</div>
                                </div>
                            </a>
                        <?php endif; ?>
                        
                        <?= nl2br(htmlspecialchars($msg['body'])) ?>

                        <div class="bubble-time">
                            <?= date('H:i', strtotime($msg['sent_at'])) ?>
                            <?php if($isMe): ?>
                                <i class="bi bi-check2-all <?= $msg['is_read'] ? 'text-lime' : '' ?>"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if(!$is_broadcast): ?>
            <div class="chat-input-area">
                <form method="POST" enctype="multipart/form-data" class="d-flex gap-3 align-items-center">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="send_message" value="1">
                    <input type="hidden" name="recipient_role" value="<?= htmlspecialchars($active_role) ?>">
                    <input type="hidden" name="target_id" value="<?= $active_id ?>">

                    <div class="dropup">
                        <label class="btn btn-light rounded-pill p-3 border" title="Attach File">
                            <i class="bi bi-paperclip fs-5"></i>
                            <input type="file" name="attachment" class="d-none">
                        </label>
                    </div>

                    <div class="flex-grow-1">
                        <textarea name="body" class="form-control input-glass" rows="1" placeholder="Type your message here..." required style="resize:none"></textarea>
                    </div>

                    <button type="submit" class="btn-send">
                        <i class="bi bi-send-fill text-forest"></i>
                    </button>
                </form>
            </div>
            <?php else: ?>
                <div class="p-3 bg-light text-center text-muted small border-top">
                    <i class="bi bi-megaphone-fill me-2"></i> This is a system broadcast. Replies are disabled.
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="d-flex flex-column align-items-center justify-content-center h-100 p-5 text-center">
                <div class="mb-4 bg-white p-4 rounded-circle shadow-sm">
                    <i class="bi bi-chat-quote display-1 text-forest opacity-25"></i>
                </div>
                <h3 class="fw-bold">Welcome to SACCO Messages</h3>
                <p class="text-muted" style="max-width:400px">Select a staff member or another member from the list to start a secure conversation.</p>
                <button class="btn btn-forest rounded-pill px-5 py-2 mt-3" data-bs-toggle="modal" data-bs-target="#newChatModal">
                    Start New Chat
                </button>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Modals -->
<div class="modal fade" id="newChatModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 bg-light p-4">
                <h5 class="modal-title fw-bold">Who would you like to message?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="list-group list-group-flush">
                    <?php if ($my_role !== 'member'): ?>
                        <div class="p-4 border-bottom bg-warning bg-opacity-10 cursor-pointer" data-bs-toggle="modal" data-bs-target="#broadcastModal">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-circle bg-warning text-dark"><i class="bi bi-megaphone"></i></div>
                                <div><div class="fw-bold">System Broadcast</div><small>Message all members</small></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php $admins = $conn->query("SELECT admin_id, full_name, r.name as role 
                                                 FROM admins a 
                                                 JOIN roles r ON a.role_id = r.id 
                                                 WHERE admin_id != $my_id"); ?>
                    <?php while ($a = $admins->fetch_assoc()): ?>
                        <a href="?chat_with=<?= $a['admin_id'] ?>&role=admin" class="list-group-item list-group-item-action d-flex align-items-center gap-3 p-4">
                            <div class="avatar-circle"><?= strtoupper(substr((string)($a['full_name'] ?? 'A'), 0, 1)) ?></div>
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($a['full_name']) ?></div>
                                <small class="text-uppercase opacity-50 small"><?= $a['role'] ?></small>
                            </div>
                        </a>
                    <?php endwhile; ?>

                    <?php $members = $conn->query("SELECT member_id, full_name, national_id FROM members WHERE status='active' AND member_id != $my_id LIMIT 50"); ?>
                    <?php while ($m = $members->fetch_assoc()): ?>
                        <a href="?chat_with=<?= $m['member_id'] ?>&role=member" class="list-group-item list-group-item-action d-flex align-items-center gap-3 p-4">
                            <div class="avatar-circle bg-secondary text-white"><?= strtoupper(substr((string)($m['full_name'] ?? 'M'), 0, 1)) ?></div>
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($m['full_name']) ?></div>
                                <small class="opacity-50 small">Member: <?= $m['national_id'] ?></small>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const viewport = document.getElementById('msgViewport');
    if(viewport) viewport.scrollTop = viewport.scrollHeight;
</script>
</body>
</html>
