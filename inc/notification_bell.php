<?php
// inc/notification_bell.php

// 1. Safe Session Handling
$user_type = $_SESSION['role'] ?? 'member';
// Handle potential admin vs member ID conflict
if (isset($_SESSION['admin_id'])) {
    $user_id = $_SESSION['admin_id'];
} elseif (isset($_SESSION['member_id'])) {
    $user_id = $_SESSION['member_id'];
} else {
    $user_id = 0; // Guest or error state
}

// 2. Get Unread Count (Prepared Statement)
$stmt_count = $conn->prepare("
    SELECT COUNT(*) AS c 
    FROM notifications 
    WHERE user_type = ? AND user_id = ? AND status = 'unread'
");
$stmt_count->bind_param("si", $user_type, $user_id);
$stmt_count->execute();
$unread_count = $stmt_count->get_result()->fetch_assoc()['c'] ?? 0;
$stmt_count->close();

// 3. Helper function for "Time Ago"
/**
 * Helper function for "Time Ago"
 * Fixed to handle weeks correctly using timestamps
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // If less than 1 second, say "just now"
    if ($diff->y == 0 && $diff->m == 0 && $diff->d == 0 && $diff->h == 0 && $diff->i == 0 && $diff->s == 0) {
        return 'just now';
    }

    // Weeks logic: PHP DateInterval doesn't do weeks natively, so we calculate it manually
    // if it's less than a month but more than 7 days.
    $weeks = 0;
    if ($diff->y == 0 && $diff->m == 0 && $diff->d >= 7) {
        $weeks = floor($diff->d / 7);
        $diff->d -= $weeks * 7;
    }

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week', // We will handle this manually below
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );

    $result = [];

    // 1. Handle Years, Months
    if ($diff->y) $result[] = $diff->y . ' ' . $string['y'] . ($diff->y > 1 ? 's' : '');
    if ($diff->m) $result[] = $diff->m . ' ' . $string['m'] . ($diff->m > 1 ? 's' : '');

    // 2. Handle Weeks (Manual variable)
    if ($weeks) $result[] = $weeks . ' ' . $string['w'] . ($weeks > 1 ? 's' : '');

    // 3. Handle Days, Hours, Minutes, Seconds
    if ($diff->d) $result[] = $diff->d . ' ' . $string['d'] . ($diff->d > 1 ? 's' : '');
    if ($diff->h) $result[] = $diff->h . ' ' . $string['h'] . ($diff->h > 1 ? 's' : '');
    if ($diff->i) $result[] = $diff->i . ' ' . $string['i'] . ($diff->i > 1 ? 's' : '');
    if ($diff->s) $result[] = $diff->s . ' ' . $string['s'] . ($diff->s > 1 ? 's' : '');

    // Slice to just the first unit (e.g., "2 hours ago" instead of "2 hours, 5 minutes ago")
    if (!$full) $result = array_slice($result, 0, 1);

    return $result ? implode(', ', $result) . ' ago' : 'just now';
}
?>

<style>
    .notification-dropdown { width: 320px; padding: 0; border-radius: 0.5rem; border: none; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
    .notification-header { padding: 1rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .notification-body { max-height: 300px; overflow-y: auto; }
    .notification-item { padding: 0.75rem 1rem; border-bottom: 1px solid #f0f0f0; transition: background 0.2s; display: block; text-decoration: none; color: inherit; }
    .notification-item:hover { background-color: #f8f9fa; }
    .notification-item.unread { background-color: #e6f3eb; /* Umoja Light Green */ }
    .notification-item:last-child { border-bottom: none; }
    .notification-footer { padding: 0.75rem; text-align: center; border-top: 1px solid #eee; background: #f9f9f9; border-radius: 0 0 0.5rem 0.5rem; }
    
    /* Scrollbar styling */
    .notification-body::-webkit-scrollbar { width: 6px; }
    .notification-body::-webkit-scrollbar-thumb { background-color: #ccc; border-radius: 3px; }
</style>

<div class="dropdown">
    <button class="btn btn-light border-0 position-relative rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 40px; height: 40px;">
        <i class="bi bi-bell fs-5 text-secondary"></i>
        <?php if ($unread_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light">
                <?= $unread_count > 9 ? '9+' : $unread_count ?>
                <span class="visually-hidden">unread messages</span>
            </span>
        <?php endif; ?>
    </button>

    <div class="dropdown-menu dropdown-menu-end notification-dropdown">
        <div class="notification-header">
            <h6 class="mb-0 fw-bold">Notifications</h6>
            <?php if ($unread_count > 0): ?>
                <small class="text-success fw-bold"><?= $unread_count ?> New</small>
            <?php endif; ?>
        </div>

        <div class="notification-body">
            <?php
            // Fetch last 6 notifications (Using Prepared Statement for Security)
            $stmt_list = $conn->prepare("
                SELECT * FROM notifications 
                WHERE user_type = ? AND user_id = ? 
                ORDER BY created_at DESC LIMIT 6
            ");
            $stmt_list->bind_param("si", $user_type, $user_id);
            $stmt_list->execute();
            $result = $stmt_list->get_result();

            if ($result->num_rows > 0):
                while ($row = $result->fetch_assoc()): 
                    // Determine icon based on message content (Simple keyword matching)
                    $icon = 'bi-info-circle text-primary';
                    if (stripos($row['message'], 'approved') !== false) $icon = 'bi-check-circle text-success';
                    if (stripos($row['message'], 'rejected') !== false) $icon = 'bi-x-circle text-danger';
                    if (stripos($row['message'], 'alert') !== false) $icon = 'bi-exclamation-circle text-warning';
                    
                    $is_unread = ($row['status'] === 'unread');
            ?>
                <a href="#" class="notification-item <?= $is_unread ? 'unread' : '' ?>" data-note-id="<?= $row['id'] ?>">
                    <div class="d-flex align-items-start">
                        <div class="me-3 mt-1">
                            <i class="bi <?= $icon ?> fs-5"></i>
                        </div>
                        <div>
                            <p class="mb-1 small " style="line-height: 1.4;"><?= htmlspecialchars($row['message']) ?></p>
                            <small class="text-muted" style="font-size: 0.75rem;">
                                <i class="bi bi-clock me-1"></i><?= time_elapsed_string($row['created_at']) ?>
                            </small>
                        </div>
                    </div>
                </a>
            <?php 
                endwhile; 
            else: 
            ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-bell-slash fs-3 mb-2 d-block opacity-50"></i>
                    <small>No notifications yet</small>
                </div>
            <?php endif; 
            $stmt_list->close();
            ?>
        </div>

        <div class="notification-footer">
            <a href="<?= ASSET_BASE ?>/../member/pages/notifications.php" class="small text-decoration-none fw-bold text-success">
                View All Notifications <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>
