<?php
// inc/notification_bell.php

$user_type = $_SESSION['role'] ?? 'member';
$user_id = $_SESSION['admin_id'] ?? $_SESSION['member_id'];

$stmt = $conn->prepare("
    SELECT COUNT(*) AS c 
    FROM notifications 
    WHERE user_type = ? AND user_id = ? AND status='unread'
");
$stmt->bind_param("si", $user_type, $user_id);
$stmt->execute();
$unread = $stmt->get_result()->fetch_assoc()['c'];
?>

<!-- Notification Bell -->
<div class="dropdown">
    <button class="btn btn-light position-relative" data-bs-toggle="dropdown">
        <i class="bi bi-bell"></i>
        <?php if ($unread > 0): ?>
            <span class="badge bg-danger position-absolute top-0 start-100 translate-middle">
                <?= $unread ?>
            </span>
        <?php endif; ?>
    </button>

    <div class="dropdown-menu dropdown-menu-end p-2" style="min-width:300px;">
        <strong class="d-block mb-2">Notifications</strong>

        <?php
        $n = $conn->query("
            SELECT * FROM notifications 
            WHERE user_type='$user_type' AND user_id=$user_id 
            ORDER BY created_at DESC LIMIT 6
        ");

        if ($n->num_rows === 0) {
            echo "<div class='text-muted'>No notifications</div>";
        } else {
            while($row = $n->fetch_assoc()):
        ?>
            <div class="border-bottom pb-2 mb-2">
                <div><?= htmlspecialchars($row['message']) ?></div>
                <small class="text-muted"><?= $row['created_at'] ?></small>
            </div>
        <?php endwhile; } ?>

        <a href="<?= BASE_URL ?>/public/notifications.php" class="btn btn-sm btn-outline-primary w-100">View All</a>
    </div>
</div>