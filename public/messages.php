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

// Load Current User Picture
if ($my_role === 'member') {
    if (empty($_SESSION['member_pic'])) {
        $uPic = $conn->query("SELECT profile_pic FROM members WHERE member_id = $my_id")->fetch_assoc();
        $_SESSION['member_pic'] = $uPic['profile_pic'] ?? null;
    }
} else {
    $_SESSION['admin_pic'] = null; // Admins currently do not have photos in schema
}

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

function renderImg($blob, $name, $size = '56px', $fontSize = '1.3rem') {
    if ($blob) {
        return "<div class='avatar-circle' style='width:$size;height:$size; overflow:hidden; padding:0; background:none;'>
                  <img src='data:image/jpeg;base64," . base64_encode($blob) . "' style='width:100%;height:100%;object-fit:cover'>
                </div>";
    }
    return "<div class='avatar-circle' style='width:$size;height:$size; font-size:$fontSize;'>" . strtoupper(substr((string)($name ?? 'U'), 0, 1)) . "</div>";
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
            --forest-deep: #0B241C;
            --forest-green: #0F392B;
            --forest-mid: #164e3b;
            --forest-light: #237a5d;
            --lime: #D0F35D;
            --lime-glow: rgba(208, 243, 93, 0.4);
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.3);
            --sidebar-width: 380px;
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at top left, #123329, #0B1D18);
            height: 100vh;
            overflow: hidden;
            color: #f8f9fa;
        }

        .app-container {
            display: flex;
            height: 100vh;
            backdrop-filter: blur(15px);
            background: rgba(255,255,255,0.02);
        }

        /* Sidebar Styling: Deep Emerald Glass */
        .msg-sidebar {
            width: var(--sidebar-width);
            background: rgba(11, 36, 28, 0.7);
            backdrop-filter: blur(40px);
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255,255,255,0.08);
            box-shadow: 20px 0 50px rgba(0,0,0,0.3);
            z-index: 100;
        }

        .sidebar-header {
            padding: 40px 30px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .sidebar-header h3 {
            font-weight: 800;
            letter-spacing: -1px;
            background: linear-gradient(to right, #fff, var(--lime));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .thread-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.1) transparent;
        }

        .thread-item {
            display: flex;
            padding: 20px;
            border-radius: 24px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: rgba(255,255,255,0.6);
            border: 1px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .thread-item:hover {
            background: rgba(255,255,255,0.04);
            transform: translateX(8px);
            color: #fff;
        }

        .thread-item.active {
            background: rgba(208, 243, 93, 0.08);
            border-color: rgba(208, 243, 93, 0.2);
            color: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .thread-item.active::before {
            content: '';
            position: absolute;
            left: 0; top: 25%; height: 50%;
            width: 4px;
            background: var(--lime);
            border-radius: 0 4px 4px 0;
            box-shadow: 0 0 15px var(--lime);
        }

        .avatar-circle {
            width: 56px; height: 56px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--lime), #b2d54a);
            color: var(--forest-green);
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            transition: var(--transition);
        }

        .thread-item:hover .avatar-circle {
            transform: scale(1.1) rotate(-5deg);
        }

        /* Chat Area: Modern Satin */
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: radial-gradient(circle at 50% 50%, #fcfdfe, #f0f4f8);
            position: relative;
        }

        .chat-header {
            padding: 25px 40px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex; align-items: center; justify-content: space-between;
            z-index: 50;
        }

        .messages-viewport {
            flex: 1;
            overflow-y: auto;
            padding: 40px;
            display: flex; flex-direction: column; gap: 20px;
            background: url("https://www.transparenttextures.com/patterns/pinstriped-suit.png");
            scroll-behavior: smooth;
        }

        .chat-input-area {
            padding: 30px 45px 40px;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(0,0,0,0.05);
        }

        /* Bubbles: High Contrast & Depth */
        .bubble-wrapper {
            display: flex; width: 100%;
            margin-bottom: 6px;
            perspective: 1000px;
        }

        .bubble-wrapper.sent { justify-content: flex-end; }
        .bubble-wrapper.received { justify-content: flex-start; }

        .chat-bubble {
            max-width: 65%;
            padding: 16px 24px;
            border-radius: 28px;
            position: relative;
            font-size: 1rem;
            line-height: 1.6;
            transition: var(--transition);
            animation: bubblePop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        @keyframes bubblePop {
            from { opacity: 0; transform: scale(0.9) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .sent .chat-bubble {
            background: linear-gradient(135deg, var(--forest-green), var(--forest-mid));
            color: #fff;
            border-bottom-right-radius: 6px;
            box-shadow: 0 10px 25px rgba(15, 57, 43, 0.2);
        }

        .received .chat-bubble {
            background: #fff;
            color: var(--forest-green);
            border-bottom-left-radius: 6px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            border: 1px solid #edf2f7;
        }

        /* Attachment: Premium UI Chips */
        .attachment-card {
            background: rgba(255,255,255,0.12);
            border-radius: 18px;
            padding: 12px 18px;
            margin-bottom: 12px;
            display: flex; align-items: center; gap: 14px;
            text-decoration: none; color: #fff;
            border: 1px solid rgba(255,255,255,0.15);
            transition: var(--transition);
        }

        .attachment-card:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }

        .received .attachment-card {
            background: #f1f5f9;
            color: var(--forest-green);
            border-color: #e2e8f0;
        }

        /* Input Area: Floating Glass bar */
        .input-wrapper {
            background: #fff;
            border: 1px solid #edf2f7;
            padding: 8px 8px 8px 24px;
            border-radius: 24px;
            display: flex; align-items: center; gap: 15px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.05);
            transition: var(--transition);
        }

        .input-wrapper:focus-within {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.1);
            border-color: var(--forest-light);
        }

        .input-glass {
            background: transparent !important;
            border: none !important;
            padding: 15px 0 !important;
            font-size: 1rem !important;
            font-weight: 500 !important;
            color: var(--forest-green) !important;
        }

        .input-glass::placeholder { color: #94a3b8; }

        .btn-send {
            width: 56px; height: 56px;
            border-radius: 18px;
            background: var(--lime);
            border: none;
            color: var(--forest-green);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            transition: var(--transition);
            box-shadow: 0 8px 20px var(--lime-glow);
        }

        .btn-send:hover {
            transform: scale(1.1) rotate(10deg);
            background: #d9f77d;
        }

        /* Custom Badges & Dates */
        .badge-unread {
            background: var(--lime);
            color: var(--forest-green);
            font-weight: 800; padding: 5px 10px;
            border-radius: 10px; font-size: 0.75rem;
            box-shadow: 0 5px 15px var(--lime-glow);
        }

        .date-chip {
            text-align: center; margin: 30px 0;
            position: relative;
        }

        .date-chip span {
            background: rgba(255, 255, 255, 0.9);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--forest-mid);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            text-transform: uppercase; letter-spacing: 1.5px;
        }

        @media (max-width: 1200px) {
            :root { --sidebar-width: 320px; }
        }
        
        @media (max-width: 991px) {
            .msg-sidebar { position: fixed; left: -100%; height: 100%; z-index: 1000; transition: 0.5s; }
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
                    <i class="bi bi-chevron-left me-1"></i> Dashboard
                </a>
                <button class="btn btn-sm btn-light rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#newChatModal">
                    <i class="bi bi-plus-lg me-1"></i> NEW
                </button>
            </div>
            <h3 class="fw-800 mb-1">Messages</h3>
            <p class="small opacity-50 mb-0 text-uppercase" style="letter-spacing:1px; font-size: 0.65rem">Secure Encryption Enabled</p>
        </div>

        <div class="thread-container">
            <?php if(empty($threads)): ?>
                <div class="text-center p-5 opacity-25">
                    <i class="bi bi-chat-dots display-1 mb-3"></i>
                    <p>No conversations yet</p>
                </div>
            <?php endif; ?>

            <?php foreach($threads as $key => $t): ?>
            <a href="?chat_with=<?= $t['id'] ?>&role=<?= $t['role'] ?>" class="thread-item <?= ($active_id == $t['id'] && $active_role == $t['role']) ? 'active' : '' ?>">
                <div class="me-3">
                    <?= renderImg($t['pic'], $t['name']) ?>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold text-truncate" style="font-size:1rem"><?= htmlspecialchars($t['name']) ?></span>
                        <small class="opacity-50" style="font-size:0.7rem"><?= date('H:i', strtotime($t['time'])) ?></small>
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

        <div class="p-4" style="background: rgba(0,0,0,0.1)">
            <div class="d-flex align-items-center gap-3">
                <?= renderImg($_SESSION['admin_pic'] ?? $_SESSION['member_pic'] ?? null, $_SESSION['admin_name'] ?? $_SESSION['member_name'] ?? 'U', '44px', '1rem') ?>
                <div class="overflow-hidden">
                    <div class="fw-bold text-truncate text-white"><?= htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['member_name'] ?? 'User') ?></div>
                    <div class="small opacity-50 text-uppercase" style="font-size:0.6rem; letter-spacing:1px"><?= $my_role ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Chat Area -->
    <main class="chat-container">
        <?php if($active_id): ?>
            <div class="chat-header shadow-sm">
                <div class="d-flex align-items-center">
                    <button class="btn d-lg-none me-3 p-0" onclick="document.getElementById('msgSidebar').classList.toggle('show')">
                        <i class="bi bi-list fs-3"></i>
                    </button>
                    <div class="me-3 shadow-sm">
                        <?= renderImg($partner['pic'], $partner['name'], '50px', '1.2rem') ?>
                    </div>
                    <div>
                        <h5 class="fw-800 mb-0"><?= htmlspecialchars((string)($partner['name'] ?? 'Unknown')) ?></h5>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="badge bg-success bg-opacity-10 text-success py-1 px-3 rounded-pill" style="font-size:0.65rem; font-weight: 700">
                                <i class="bi bi-circle-fill me-1" style="font-size:0.4rem"></i> ONLINE
                            </span>
                            <small class="text-muted small">ID: #<?= $active_id ?></small>
                        </div>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-light rounded-circle p-0" style="width:40px;height:40px" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-2">
                        <li><a class="dropdown-item rounded-3 py-2" href="messages.php"><i class="bi bi-x-circle me-2 text-danger"></i> Close Chat</a></li>
                        <li><a class="dropdown-item rounded-3 py-2" href="#"><i class="bi bi-exclamation-triangle me-2 text-warning"></i> Report Thread</a></li>
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
                    <div class="chat-bubble shadow-sm">
                        <?php if($msg['attachment']): ?>
                            <a href="<?= $msg['attachment'] ?>" target="_blank" class="attachment-card shadow-sm">
                                <i class="bi bi-file-earmark-bar-graph-fill fs-4 text-lime"></i>
                                <div class="overflow-hidden">
                                    <div class="fw-bold small">Documents Shared</div>
                                    <div class="small opacity-50" style="font-size:0.65rem">Tap to download</div>
                                </div>
                            </a>
                        <?php endif; ?>
                        
                        <div class="body-text"><?= nl2br(htmlspecialchars($msg['body'])) ?></div>

                        <div class="d-flex justify-content-end align-items-center gap-2 mt-2 opacity-50" style="font-size: 0.65rem">
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
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="send_message" value="1">
                    <input type="hidden" name="recipient_role" value="<?= htmlspecialchars($active_role) ?>">
                    <input type="hidden" name="target_id" value="<?= $active_id ?>">

                    <div class="input-wrapper shadow-lg">
                        <label class="btn btn-light rounded-circle shadow-sm me-1" style="width:44px;height:44px; display: flex; align-items:center; justify-content:center" title="Attach File">
                            <i class="bi bi-plus-lg text-forest"></i>
                            <input type="file" name="attachment" class="d-none">
                        </label>
                        
                        <textarea name="body" class="form-control input-glass flex-grow-1" rows="1" placeholder="Craft a message..." required style="resize:none"></textarea>

                        <button type="submit" class="btn-send ms-2">
                            <i class="bi bi-arrow-up-short"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
                <div class="p-4 bg-light text-center text-muted small border-top fw-500">
                    <i class="bi bi-shield-lock-fill me-2 text-warning"></i> System Broadcast: Replies are strictly restricted to outgoing only.
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="d-flex flex-column align-items-center justify-content-center h-100 p-5 text-center fade-in">
                <div class="mb-5" style="position: relative">
                    <div class="avatar-circle" style="width: 140px; height: 140px; font-size: 4rem; border-radius: 40px">
                        <i class="bi bi-chat-heart-fill"></i>
                    </div>
                </div>
                <h2 class="fw-800 text-forest">Messages Hub</h2>
                <p class="text-secondary mb-4" style="max-width:420px; font-size: 1.1rem">Unified communication gateway for Umoja Drivers Sacco. Start a secure thread with staff or members.</p>
                <div class="d-flex gap-3">
                    <button class="btn btn-forest rounded-pill px-5 py-3 fw-bold shadow-lg" data-bs-toggle="modal" data-bs-target="#newChatModal">
                        START CONVERSATION
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- New Chat Modal -->
<div class="modal fade" id="newChatModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-2xl rounded-5 overflow-hidden">
            <div class="modal-header border-0 bg-forest text-white p-5">
                <div>
                    <h4 class="modal-title fw-800">Secure Direct</h4>
                    <p class="small opacity-50 mb-0">Search registered users within the ecosystem</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-light">
                <div class="list-group list-group-flush">
                    <?php if ($my_role !== 'member'): ?>
                        <div class="list-group-item list-group-item-action p-4 border-0 mb-2 mt-2 mx-3 rounded-4 shadow-sm" style="background: var(--lime); cursor:pointer" data-bs-toggle="modal" data-bs-target="#broadcastModal">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-circle bg-white text-forest shadow-none" style="border-radius:12px"><i class="bi bi-megaphone"></i></div>
                                <div><div class="fw-800 text-forest">Pulse Broadcast</div><small class="text-forest opacity-75">Update all active members instantly</small></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="px-4 py-3 bg-white sticky-top border-bottom">
                         <input type="text" class="form-control rounded-pill border-light bg-light px-4" placeholder="Search by name or ID...">
                    </div>

                    <?php 
                    $admins = $conn->query("SELECT admin_id, full_name, r.name as role 
                                         FROM admins a 
                                         JOIN roles r ON a.role_id = r.id 
                                         WHERE a.admin_id != $my_id 
                                         ORDER BY full_name ASC");
                    while ($a = $admins->fetch_assoc()): ?>
                        <a href="?chat_with=<?= $a['admin_id'] ?>&role=admin" class="list-group-item list-group-item-action d-flex align-items-center gap-3 p-4 border-0 bg-transparent">
                            <?= renderImg(null, $a['full_name'], '48px', '1.1rem') ?>
                            <div class="flex-grow-1">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($a['full_name']) ?></div>
                                <div class="badge bg-forest bg-opacity-10 text-forest rounded-pill py-1 px-2 border-0 mt-1" style="font-size:0.6rem"><?= $a['role'] ?></div>
                            </div>
                            <i class="bi bi-chevron-right opacity-25"></i>
                        </a>
                    <?php endwhile; ?>

                    <?php 
                    $members = $conn->query("SELECT member_id, full_name, national_id, profile_pic FROM members WHERE status='active' AND member_id != $my_id LIMIT 50");
                    while ($m = $members->fetch_assoc()): ?>
                        <a href="?chat_with=<?= $m['member_id'] ?>&role=member" class="list-group-item list-group-item-action d-flex align-items-center gap-3 p-4 border-0 bg-transparent">
                            <?= renderImg($m['profile_pic'], $m['full_name'], '48px', '1.1rem') ?>
                            <div class="flex-grow-1">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($m['full_name']) ?></div>
                                <div class="small opacity-50" style="font-size:0.75rem">Member #<?= $m['member_id'] ?> • ID <?= $m['national_id'] ?></div>
                            </div>
                            <i class="bi bi-chevron-right opacity-25"></i>
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

    // Simple Auto-expand Textarea
    const tx = document.getElementsByTagName('textarea');
    for (let i = 0; i < tx.length; i++) {
        tx[i].setAttribute('style', 'height:' + (tx[i].scrollHeight) + 'px;overflow-y:hidden;');
        tx[i].addEventListener("input", OnInput, false);
    }

    function OnInput() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    }
</script>
</body>
</html>
