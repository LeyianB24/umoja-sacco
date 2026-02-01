<?php
// inc/message_icon.php

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Identify User
$user_role = $_SESSION['role'] ?? 'guest';
$user_id   = $_SESSION['admin_id'] ?? $_SESSION['member_id'] ?? 0;

$unread_msgs = 0;
$msg_result = false;

// 2. Query Logic based on Role
if ($user_id > 0) {
    if ($user_role === 'member') {
        // Member sees messages sent TO them (from Admin or other Members)
        $sql = "SELECT m.*, 
                    COALESCE(a.full_name, mem.full_name, 'System') as sender_name
                FROM messages m
                LEFT JOIN admins a ON m.from_admin_id = a.admin_id
                LEFT JOIN members mem ON m.from_member_id = mem.member_id
                WHERE m.to_member_id = ? 
                ORDER BY m.sent_at DESC";
    } else {
        // Admin sees messages sent TO them (from Members)
        $sql = "SELECT m.*, mem.full_name as sender_name
                FROM messages m
                JOIN members mem ON m.from_member_id = mem.member_id
                WHERE m.to_admin_id = ?
                ORDER BY m.sent_at DESC";
    }

    // Execute
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $msg_result = $stmt->get_result();
    
    // Count unread (PHP side to avoid running query twice)
    $all_messages = [];
    while ($row = $msg_result->fetch_assoc()) {
        $all_messages[] = $row;
        if ($row['is_read'] == 0) {
            $unread_msgs++;
        }
    }
}
?>

<div class="dropdown me-3">
    <button class="btn btn-light border-0 position-relative rounded-circle" type="button" data-bs-toggle="dropdown" style="width: 40px; height: 40px;">
        <i class="bi bi-envelope fs-5 text-secondary"></i>
        <?php if ($unread_msgs > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary border border-light">
                <?= $unread_msgs > 9 ? '9+' : $unread_msgs ?>
            </span>
        <?php endif; ?>
    </button>

    <div class="dropdown-menu dropdown-menu-end shadow border-0" style="width: 320px;">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <h6 class="mb-0 fw-bold">Messages</h6>
            <a href="../public/messages.php?action=compose" class="btn btn-sm btn-primary rounded-pill" style="font-size: 0.7rem;">
                <i class="bi bi-pencil-fill me-1"></i> Compose
            </a>
        </div>

        <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
            <?php if (!empty($all_messages)): ?>
                <?php foreach (array_slice($all_messages, 0, 5) as $msg): ?>
                    <a href="../public/messages.php?id=<?= $msg['message_id'] ?>" class="list-group-item list-group-item-action <?= $msg['is_read'] == 0 ? 'bg-light' : '' ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <small class="fw-bold text-truncate" style="max-width: 150px;"><?= htmlspecialchars($msg['sender_name']) ?></small>
                            <small class="text-muted" style="font-size: 0.7rem;"><?= date('M d', strtotime($msg['sent_at'])) ?></small>
                        </div>
                        <div class="text-truncate small text-secondary mb-1"><?= htmlspecialchars($msg['subject']) ?></div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-chat-square-dots fs-3 mb-2 d-block opacity-25"></i>
                    <small>No messages yet</small>
                </div>
            <?php endif; ?>
        </div>

        <div class="p-2 border-top text-center bg-light">
            <a href="../public/messages.php" class="text-decoration-none small fw-bold">View All Messages</a>
        </div>
    </div>
</div>