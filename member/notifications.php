<?php
// member/notifications.php

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -----------------------------------------------------------
// AUTH CHECK
// -----------------------------------------------------------
if (empty($_SESSION['member_id'])) {
    header("Location: ../public/login.php");
    exit;
}

$member_id = (int) $_SESSION['member_id'];

// -----------------------------------------------------------
// FETCH MEMBER NOTIFICATIONS
// -----------------------------------------------------------
$query = "
    SELECT notification_id, title, message, status, is_read, created_at
    FROM notifications
    WHERE to_role = 'member'
      AND user_id = ?
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

// -----------------------------------------------------------
// MARK AS READ (AFTER FETCH TO AVOID MISSING NEW ONES)
// -----------------------------------------------------------
$update = $conn->prepare("
    UPDATE notifications
    SET status = 'read', is_read = 1
    WHERE to_role = 'member'
      AND user_id = ?
      AND is_read = 0
");
$update->bind_param("i", $member_id);
$update->execute();

?>
<div class="container-fluid px-4 mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold text-success">
            <i class="fas fa-bell me-2"></i> Notifications
        </h4>
        <a href="dashboard.php" class="btn btn-secondary rounded-pill">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body">

            <?php if ($result->num_rows > 0): ?>
                <div class="list-group list-group-flush">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                            $title     = htmlspecialchars($row['title'] ?: 'Notification');
                            $message   = htmlspecialchars($row['message'] ?: '');
                            $createdAt = $row['created_at']
                                ? date('d M Y, h:i A', strtotime($row['created_at']))
                                : '';
                            $isRead    = (int) $row['is_read'] === 1;
                        ?>
                        <div class="list-group-item border-0 border-bottom py-3 d-flex justify-content-between align-items-start <?= $isRead ? '' : 'bg-light' ?>">
                            <div class="ms-2 me-auto">
                                <div class="fw-semibold text-success mb-1">
                                    <i class="fas <?= $isRead ? 'fa-envelope-open-text' : 'fa-envelope' ?> me-2"></i>
                                    <?= $title ?>
                                </div>
                                <small class="text-muted d-block"><?= nl2br($message) ?></small>
                            </div>
                            <span class="text-muted small"><?= $createdAt ?></span>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-bell-slash fa-3x mb-3"></i>
                    <p>No notifications yet.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>