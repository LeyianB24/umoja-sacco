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
    'admin'      => '../admin/dashboard.php',
    'member'     => '../member/dashboard.php',
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
            $members = $conn->query("SELECT member_id FROM members WHERE status='active'");
            $stmt = $conn->prepare("INSERT INTO messages (from_admin_id, to_member_id, subject, body, attachment, sent_at, is_read) VALUES (?, ?, 'Broadcast', ?, ?, NOW(), 0)");
            
            $conn->begin_transaction();
            try {
                while ($m = $members->fetch_assoc()) {
                    $stmt->bind_param("iiss", $my_id, $m['member_id'], $body, $attachment);
                    $stmt->execute();
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
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
    /* ============================ */
    /* THEME CONFIGURATION        */
    /* ============================ */
    :root[data-bs-theme="light"] {
        /* Palette: Teal/Emerald & Gold */
        --primary-color: #0f766e;  /* Teal-700 */
        --primary-hover: #115e59;  /* Teal-800 */
        --accent-color: #f59e0b;   /* Amber-500 */
        
        --app-bg: #f0fdfa;         /* Very Light Teal/Gray */
        --sidebar-bg: #ffffff;
        --chat-bg: #ffffff;
        --border-color: #e2e8f0;
        
        /* Message Bubbles */
        --bubble-in: #f1f5f9;      /* Slate-100 */
        --text-bubble-in: #1e293b;
        --bubble-out: #0f766e;     /* Primary Color */
        --text-bubble-out: #ffffff;
        
        --active-thread: #ccfbf1;  /* Teal-100 */
        --text-secondary: #64748b;
    }
    
    :root[data-bs-theme="dark"] {
        /* Palette: Dark Slate & Emerald */
        --primary-color: #2dd4bf;  /* Teal-400 */
        --primary-hover: #14b8a6;  /* Teal-500 */
        --accent-color: #fbbf24;   /* Amber-400 */
        
        --app-bg: #0f172a;         /* Slate-900 */
        --sidebar-bg: #1e293b;     /* Slate-800 */
        --chat-bg: #0f172a;        /* Slate-900 */
        --border-color: #334155;   /* Slate-700 */
        
        /* Message Bubbles */
        --bubble-in: #334155;      /* Slate-700 */
        --text-bubble-in: #e2e8f0;
        --bubble-out: #115e59;     /* Teal-800 */
        --text-bubble-out: #f0fdfa;
        
        --active-thread: #1e293b;  
        --text-secondary: #94a3b8;
    }

    /* GLOBAL OVERRIDES */
    body { background-color: var(--app-bg); height: 100vh; overflow: hidden; font-family: 'Segoe UI', system-ui, sans-serif; transition: background 0.3s; color: var(--text-bubble-in); }
    
    /* Bootstrap Override for Primary Buttons */
    .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
    .btn-primary:hover, .btn-primary:active, .btn-primary:focus { background-color: var(--primary-hover); border-color: var(--primary-hover); }
    .text-primary { color: var(--primary-color) !important; }
    .bg-primary { background-color: var(--primary-color) !important; }

    .app-layout { display: flex; height: 100vh; max-width: 1600px; margin: 0 auto; }
    
    /* SIDEBAR */
    .sidebar { width: 350px; background: var(--sidebar-bg); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; z-index: 100; transition: transform 0.3s; }
    .sidebar-header { padding: 1.25rem; border-bottom: 1px solid var(--border-color); }
    .thread-list { overflow-y: auto; flex: 1; }
    .thread-item { padding: 1rem; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background 0.2s; text-decoration: none; color: inherit; display: block; }
    .thread-item:hover { background-color: var(--app-bg); }
    .thread-item.active { background-color: var(--active-thread); border-left: 4px solid var(--primary-color); }
    
    /* CHAT AREA */
    .chat-area { flex: 1; display: flex; flex-direction: column; background: var(--chat-bg); position: relative; }
    .chat-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; background: var(--sidebar-bg); }
    .chat-messages { flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 0.5rem; }
    .chat-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--sidebar-bg); }
    
    /* BUBBLES */
    .msg-group { margin-bottom: 1rem; }
    .date-divider { text-align: center; margin: 1.5rem 0; position: relative; }
    .date-divider span { background: var(--app-bg); padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; color: var(--text-secondary); border: 1px solid var(--border-color); }

    .msg-row { display: flex; margin-bottom: 4px; }
    .msg-row.sent { justify-content: flex-end; }
    .msg-row.received { justify-content: flex-start; }
    
    .bubble { max-width: 70%; padding: 0.75rem 1rem; border-radius: 18px; position: relative; font-size: 0.95rem; line-height: 1.5; word-wrap: break-word; }
    .msg-row.received .bubble { background: var(--bubble-in); color: var(--text-bubble-in); border-top-left-radius: 4px; }
    .msg-row.sent .bubble { background: var(--bubble-out); color: var(--text-bubble-out); border-top-right-radius: 4px; }
    
    .msg-meta { font-size: 0.7rem; margin-top: 4px; opacity: 0.7; display: flex; align-items: center; gap: 4px; justify-content: flex-end; }
    
    /* COMPONENTS */
    .chat-input { background: var(--app-bg); border: 1px solid var(--border-color); border-radius: 24px; padding: 10px 20px; color: inherit; width: 100%; }
    .chat-input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px var(--active-thread); }
    .btn-circle { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: transform 0.2s; }
    .btn-circle:hover { transform: scale(1.05); }

    /* MOBILE */
    @media (max-width: 768px) {
        .sidebar { position: absolute; top: 0; left: 0; bottom: 0; width: 100%; transform: translateX(0); }
        .sidebar.hidden { transform: translateX(-100%); }
        .chat-area { width: 100%; }
        .chat-area.hidden { display: none; }
    }
    
    /* Theme Toggle Animation */
    #themeToggle i { transition: transform 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55); }
    .spin-icon { transform: rotate(360deg); }
    </style>
</head>
<body>

<div class="app-layout">
    
    <div class="sidebar <?= $active_id ? 'hidden' : '' ?>" id="sidebar">
        <div class="sidebar-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <a href="<?= $dashboard_url ?>" class="btn btn-sm btn-outline-secondary rounded-circle" style="width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center"><i class="bi bi-arrow-left"></i></a>
                <h5 class="mb-0 fw-bold">Messages</h5>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-link text-secondary p-0 me-2" id="themeToggle"><i class="bi bi-moon-stars-fill fs-5"></i></button>
                <button class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#newChatModal">
                    <i class="bi bi-plus-lg me-1"></i> New
                </button>
            </div>
        </div>

        <div class="thread-list">
            <?php if(empty($threads)): ?>
                <div class="text-center p-5 text-muted">
                    <i class="bi bi-chat-square-dots fs-1 mb-2 d-block opacity-25"></i>
                    <small>No conversations yet.</small>
                </div>
            <?php endif; ?>

            <?php foreach($threads as $key => $t): ?>
            <a href="?chat_with=<?= $t['id'] ?>&role=<?= $t['role'] ?>" class="thread-item <?= ($active_id == $t['id'] && $active_role == $t['role']) ? 'active' : '' ?>">
                <div class="d-flex gap-3">
                    <div class="position-relative">
                        <?= renderImg($t['pic'], $t['name']) ?>
                        <?php if($t['role'] == 'admin'): ?>
                            <span class="position-absolute bottom-0 end-0 bg-primary border border-white rounded-circle" style="width:12px;height:12px"></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-truncate" style="font-size:0.95rem"><?= htmlspecialchars($t['name']) ?></span>
                            <small class="text-muted" style="font-size:0.75rem"><?= date('H:i', strtotime($t['time'])) ?></small>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted text-truncate d-block" style="max-width: 180px; font-size:0.85rem">
                                <?= $t['unread'] > 0 ? '<strong class="text-primary">' . htmlspecialchars($t['last_msg']) . '</strong>' : htmlspecialchars($t['last_msg']) ?>
                            </small>
                            <?php if($t['unread'] > 0): ?>
                                <span class="badge bg-danger rounded-circle p-1" style="width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:0.65rem"><?= $t['unread'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

   <div class="chat-area <?= !$active_id ? 'hidden' : '' ?>">
    <?php if($active_id): ?>
        <div class="chat-header">
            <div class="d-flex align-items-center gap-3">
                <a href="messages.php" class="d-md-none btn btn-sm btn-light border rounded-circle">
                    <i class="bi bi-chevron-left"></i>
                </a>

                <?= renderImg($partner['pic'] ?? null, $partner['name'] ?? 'Unknown') ?>

                <div>
                    <div class="fw-bold lh-1">
                        <?= htmlspecialchars($partner['name'] ?? 'Unknown') ?>
                    </div>

                    <small class="text-muted" style="font-size:0.75rem">
                        <?= ucfirst($active_role ?? 'User') ?>
                    </small>
                </div>
            </div>

            <div class="dropdown">
                <button class="btn btn-link text-secondary" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="messages.php">Close Chat</a></li>
                </ul>
            </div>
        </div>

        <div class="chat-messages" id="messageContainer"> 
            <?php 
            $lastDate = '';
            foreach($messages as $msg): 
                $currentDate = formatChatDate($msg['sent_at']);
                if($currentDate !== $lastDate): 
            ?>
                <div class="date-divider"><span><?= $currentDate ?></span></div>
            <?php 
                $lastDate = $currentDate;
                endif;
                
                $isMe = ($my_role === 'member' 
                    ? $msg['from_member_id'] == $my_id 
                    : $msg['from_admin_id'] == $my_id);
            ?>

            <div class="msg-row <?= $isMe ? 'sent' : 'received' ?>">
                <div class="bubble shadow-sm">

                    <?php if($msg['attachment']): ?>
                        <a href="<?= $msg['attachment'] ?>" 
                           target="_blank" 
                           class="d-flex align-items-center gap-2 p-2 mb-2 rounded bg-white bg-opacity-25 
                           text-decoration-none text-reset border border-white border-opacity-25">
                            <i class="bi bi-file-earmark-arrow-down-fill fs-4"></i>
                            <div class="lh-1 text-truncate" style="max-width: 150px;">
                                <small class="d-block fw-bold">Attachment</small>
                                <small class="opacity-75" style="font-size:0.7rem">Click to view</small>
                            </div>
                        </a>
                    <?php endif; ?>

                    <?= nl2br(htmlspecialchars($msg['body'])) ?>

                    <div class="msg-meta">
                        <?= date('H:i', strtotime($msg['sent_at'])) ?>
                        <?php if($isMe): ?>
                            <i class="bi bi-check2-all <?= $msg['is_read'] ? 'text-info' : '' ?>"></i>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <?php endforeach; ?>
        </div>

        <?php if($is_broadcast): ?>
            <div class="chat-footer text-center text-muted small bg-light">
                <i class="bi bi-megaphone-fill me-2"></i> This is a broadcast message. Replies are disabled.
            </div>
        <?php else: ?>
            <div class="chat-footer">
                <form method="POST" enctype="multipart/form-data" class="d-flex align-items-end gap-2" id="msgForm">

                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="send_message" value="1">
                    <input type="hidden" name="recipient_role" value="<?= htmlspecialchars($active_role) ?>">
                    <input type="hidden" name="target_id" value="<?= $active_id ?>">

                    <label class="btn btn-light text-secondary border rounded-circle btn-circle mb-1" title="Attach File">
                        <i class="bi bi-paperclip"></i>
                        <input type="file" name="attachment" class="d-none" onchange="this.parentElement.classList.add('text-primary', 'border-primary')">
                    </label>

                    <div class="flex-grow-1">
                        <textarea name="body" 
                                  class="chat-input" 
                                  rows="1" 
                                  placeholder="Type a message..."
                                  required 
                                  style="resize:none; min-height:46px; max-height:120px; padding-top:10px"
                                  oninput="this.style.height=''; this.style.height=this.scrollHeight + 'px'"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary rounded-circle btn-circle mb-1 shadow-sm">
                        <i class="bi bi-send-fill"></i>
                    </button>

                </form>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted opacity-50 pb-5">
            <div class="bg-secondary bg-opacity-10 p-4 rounded-circle mb-3">
                <i class="bi bi-chat-square-heart-fill display-1" style="color: var(--primary-color)"></i>
            </div>
            <h4>Select a conversation</h4>
            <p>Choose a contact from the left to start chatting.</p>
        </div>
    <?php endif; ?>

    </div>
</div>

<div class="modal fade" id="newChatModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold">New Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-light border-bottom sticky-top">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" id="userSearch" class="form-control border-start-0" placeholder="Search name...">
                    </div>
                </div>
                
                <div class="list-group list-group-flush">
                    <?php if ($my_role !== 'member'): ?>
                        <button class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 bg-warning bg-opacity-10" data-bs-toggle="modal" data-bs-target="#broadcastModal">
                            <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center" style="width:40px;height:40px"><i class="bi bi-megaphone-fill"></i></div>
                            <div>
                                <div class="fw-bold">Broadcast Message</div>
                                <small class="text-muted">Send to all members at once</small>
                            </div>
                        </button>
                    <?php endif; ?>

                    <?php $admins = $conn->query("SELECT admin_id, full_name FROM admins WHERE admin_id != $my_id"); ?>
                    <?php while ($a = $admins->fetch_assoc()): ?>
                        <a href="?chat_with=<?= $a['admin_id'] ?>&role=admin" class="contact-item list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width:40px;height:40px"><i class="bi bi-shield-lock-fill"></i></div>
                            <span class="fw-bold contact-name"><?= htmlspecialchars($a['full_name']) ?></span>
                        </a>
                    <?php endwhile; ?>

                    <?php 
                    $exclude = ($my_role === 'member') ? "AND member_id != $my_id" : "";
                    $members = $conn->query("SELECT member_id, full_name, profile_pic FROM members WHERE status='active' $exclude LIMIT 100");
                    while ($m = $members->fetch_assoc()): 
                    ?>
                        <a href="?chat_with=<?= $m['member_id'] ?>&role=member" class="contact-item list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                            <?= renderImg($m['profile_pic'], $m['full_name']) ?>
                            <span class="fw-bold contact-name"><?= htmlspecialchars($m['full_name']) ?></span>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="broadcastModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-bottom-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-megaphone-fill me-2"></i>System Broadcast</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="send_message" value="1">
                    <input type="hidden" name="recipient_role" value="broadcast">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Message Content</label>
                        <textarea name="body" class="form-control" rows="5" required placeholder="Write your announcement here..."></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">Attachment (Optional)</label>
                        <input type="file" name="attachment" class="form-control">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-dark fw-bold py-2">Send to All Members</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById('messageContainer');
    if (container) container.scrollTo(0, container.scrollHeight);

    const search = document.getElementById('userSearch');
    if (search) {
        search.addEventListener('keyup', function() {
            const val = this.value.toLowerCase();
            document.querySelectorAll('.contact-item').forEach(el => {
                const name = el.querySelector('.contact-name').textContent.toLowerCase();
                el.style.display = name.includes(val) ? 'flex' : 'none';
            });
        });
    }

    const toggleBtn = document.getElementById('themeToggle');
    const icon = toggleBtn.querySelector('i');
    const html = document.documentElement;

    const saved = localStorage.getItem('theme') || 'light';
    setTheme(saved);

    toggleBtn.addEventListener('click', () => {
        icon.classList.add('spin-icon');
        setTimeout(() => icon.classList.remove('spin-icon'), 500);
        const current = html.getAttribute('data-bs-theme');
        const next = current === 'light' ? 'dark' : 'light';
        setTheme(next);
    });

    function setTheme(theme) {
        html.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
        icon.className = theme === 'dark' ? 'bi bi-sun-fill fs-5' : 'bi bi-moon-stars-fill fs-5';
    }
});
</script>
</body>
</html>