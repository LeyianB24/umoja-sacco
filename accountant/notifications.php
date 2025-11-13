<?php
// accountant/notifications.php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -----------------------------------------------------------
// AUTH CHECK
// -----------------------------------------------------------
if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] !== 'accountant')) {
    header("Location: ../public/login.php");
    exit;
}

$accountant_id = $_SESSION['admin_id'];

// -----------------------------------------------------------
// FETCH NOTIFICATIONS FOR THIS ACCOUNTANT ONLY
// -----------------------------------------------------------
// Notifications table expected fields:
//  - to_role ('accountant')
//  - user_id (the specific accountant receiving it)
//  - title, message, status, is_read, created_at
//-----------------------------------------------------------
$sql = "
    SELECT notification_id, title, message, status, is_read, created_at
    FROM notifications
    WHERE to_role = 'accountant'
      AND user_id = ?
    ORDER BY created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $accountant_id);
$stmt->execute();
$result = $stmt->get_result();

// -----------------------------------------------------------
// MARK ALL AS READ FOR THIS ACCOUNTANT
// -----------------------------------------------------------
$markSql = "
    UPDATE notifications
    SET status='read', is_read=1
    WHERE to_role='accountant'
      AND user_id=?
";
$markStmt = $conn->prepare($markSql);
$markStmt->bind_param("i", $accountant_id);
$markStmt->execute();

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
                            $title     = htmlspecialchars($row['title'] ?? 'Notification');
                            $message   = htmlspecialchars($row['message'] ?? '');
                            $createdAt = $row['created_at']
                                ? date('d M Y, h:i A', strtotime($row['created_at']))
                                : '';
                            $isRead    = (int)$row['is_read'] === 1;
                        ?>

                        <div class="list-group-item border-0 border-bottom py-3 d-flex justify-content-between align-items-start <?= $isRead ? '' : 'bg-light' ?>">
                            <div class="ms-2 me-auto">
                                <div class="fw-semibold text-success">
                                    <i class="fas <?= $isRead ? 'fa-envelope-open-text' : 'fa-envelope' ?> me-2"></i>
                                    <?= $title ?>
                                </div>
                                <small class="text-muted"><?= nl2br($message) ?></small>
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